import { StepHandler } from '.';
import { unzipFile } from '@wp-playground/common';
import { logger } from '@php-wasm/logger';
import { resolveWordPressRelease } from '@wp-playground/wordpress';
import { Semaphore } from '@php-wasm/util';

/**
 * @inheritDoc setSiteLanguage
 * @hasRunnableExample
 * @example
 *
 * <code>
 * {
 * 		"step": "setSiteLanguage",
 * 		"language": "en_US"
 * }
 * </code>
 */
export interface SetSiteLanguageStep {
	step: 'setSiteLanguage';
	/** The language to set, e.g. 'en_US' */
	language: string;
}

/**
 * Infers the translation package URL for a given WordPress version.
 *
 * If it cannot be inferred, the latest translation package will be used instead.
 */
export const getWordPressTranslationUrl = async (
	wpVersion: string,
	language: string,
	latestBetaWordPressVersion?: string,
	latestStableWordPressVersion?: string
) => {
	/**
	 * Infer a WordPress version we can feed into the translations API based
	 * on the requested fully-qualified WordPress version.
	 *
	 * The translation API provides translations for:
	 *
	 * - all major.minor WordPress releases
	 * - all major.minor.patch WordPress releases
	 * - Latest beta/RC version – under a label like "6.6-RC". It's always "-RC".
	 *   There's no "-BETA1", "-RC1", "-RC2", etc.
	 *
	 * The API does not provide translations for "nightly", "latest", or
	 * old beta/RC versions.
	 *
	 * For example translations for WordPress 6.6-BETA1 or 6.6-RC1 are found under
	 * https://downloads.wordpress.org/translation/core/6.6-RC/en_GB.zip
	 */
	let resolvedVersion = null;
	if (wpVersion.match(/^(\d+\.\d+)(?:\.\d+)?$/)) {
		// Use the version directly if it's a major.minor or major.minor.patch.
		resolvedVersion = wpVersion;
	} else if (wpVersion.match(/^(\d.\d(.\d)?)-(beta|rc|alpha|nightly).*$/i)) {
		// Translate "6.4-alpha", "6.5-beta", "6.6-nightly", "6.6-RC" etc.
		// to "6.6-RC"
		if (latestBetaWordPressVersion) {
			resolvedVersion = latestBetaWordPressVersion;
		} else {
			let resolved = await resolveWordPressRelease('beta');
			// Beta versions are only available during the beta period –
			// let's use the latest stable release as a fallback.
			if (resolved.source !== 'api') {
				resolved = await resolveWordPressRelease('latest');
			}
			resolvedVersion = resolved!.version;
		}
		resolvedVersion = resolvedVersion
			// Remove the patch version, e.g. 6.6.1-RC1 -> 6.6-RC1
			.replace(/^(\d.\d)(.\d+)/i, '$1')
			// Replace "rc" and "beta" with "RC", e.g. 6.6-nightly -> 6.6-RC
			.replace(/(rc|beta).*$/i, 'RC');
	} else {
		/**
		 * Use the latest stable version otherwise.
		 *
		 * The requested version is neither stable, nor beta/RC, nor alpha/nightly.
		 * It must be a custom version string. We could actually fail at this point,
		 * but it seems more useful to* download translations from the last official
		 * WordPress version. If that assumption is wrong, let's reconsider this whenever
		 * someone reports a related issue.
		 */
		if (latestStableWordPressVersion) {
			resolvedVersion = latestStableWordPressVersion;
		} else {
			const resolved = await resolveWordPressRelease('latest');
			resolvedVersion = resolved!.version;
		}
	}
	if (!resolvedVersion) {
		throw new Error(
			`WordPress version ${wpVersion} is not supported by the setSiteLanguage step`
		);
	}
	return `https://downloads.wordpress.org/translation/core/${resolvedVersion}/${language}.zip`;
};

/**
 * Sets the site language and download translations.
 */
export const setSiteLanguage: StepHandler<SetSiteLanguageStep> = async (
	playground,
	{ language },
	progress
) => {
	progress?.tracker.setCaption(progress?.initialCaption || 'Translating');

	await playground.defineConstant('WPLANG', language);

	const docroot = await playground.documentRoot;

	const wpVersion = (
		await playground.run({
			code: `<?php
			require '${docroot}/wp-includes/version.php';
			echo $wp_version;
		`,
		})
	).text;

	const translations = [
		{
			url: await getWordPressTranslationUrl(wpVersion, language),
			type: 'core',
		},
	];

	const pluginListResponse = await playground.run({
		code: `<?php
		require_once('${docroot}/wp-load.php');
		require_once('${docroot}/wp-admin/includes/plugin.php');
		echo json_encode(
			array_values(
				array_map(
					function($plugin) {
						return [
							'slug'    => $plugin['TextDomain'],
							'version' => $plugin['Version']
						];
					},
					array_filter(
						get_plugins(),
						function($plugin) {
							return !empty($plugin['TextDomain']);
						}
					)
				)
			)
		);`,
	});

	const plugins = pluginListResponse.json;
	for (const { slug, version } of plugins) {
		translations.push({
			url: `https://downloads.wordpress.org/translation/plugin/${slug}/${version}/${language}.zip`,
			type: 'plugin',
		});
	}

	const themeListResponse = await playground.run({
		code: `<?php
		require_once('${docroot}/wp-load.php');
		require_once('${docroot}/wp-admin/includes/theme.php');
		echo json_encode(
			array_values(
				array_map(
					function($theme) {
						return [
							'slug'    => $theme->get('TextDomain'),
							'version' => $theme->get('Version')
						];
					},
					wp_get_themes()
				)
			)
		);`,
	});

	const themes = themeListResponse.json;
	for (const { slug, version } of themes) {
		translations.push({
			url: `https://downloads.wordpress.org/translation/theme/${slug}/${version}/${language}.zip`,
			type: 'theme',
		});
	}

	if (!(await playground.isDir(`${docroot}/wp-content/languages/plugins`))) {
		await playground.mkdir(`${docroot}/wp-content/languages/plugins`);
	}
	if (!(await playground.isDir(`${docroot}/wp-content/languages/themes`))) {
		await playground.mkdir(`${docroot}/wp-content/languages/themes`);
	}

	// Fetch translations in parallel
	const fetchQueue = new Semaphore({ concurrency: 5 });
	const translationsQueue = translations.map(({ url, type }) =>
		fetchQueue.run(async () => {
			try {
				const response = await fetch(url);
				if (!response.ok) {
					throw new Error(
						`Failed to download translations for ${type}: ${response.statusText}`
					);
				}

				let destination = `${docroot}/wp-content/languages`;
				if (type === 'plugin') {
					destination += '/plugins';
				} else if (type === 'theme') {
					destination += '/themes';
				}

				await unzipFile(
					playground,
					new File(
						[await response.blob()],
						`${language}-${type}.zip`
					),
					destination
				);
			} catch (error) {
				/**
				 * Throw an error when a core translation isn't found.
				 *
				 * The language slug used in the Blueprint is not recognized by the
				 * WordPress.org API and will always return a 404. This is likely
				 * unintentional – perhaps a typo or the API consumer guessed the
				 * slug wrong.
				 *
				 * The least we can do is communicate the problem.
				 */
				if (type === 'core') {
					throw new Error(
						`Failed to download translations for WordPress. Please check if the language code ${language} is correct. You can find all available languages and translations on https://translate.wordpress.org/.`
					);
				}
				/**
				 * WordPress core has translations for the requested language,
				 * but one of the installed plugins or themes doesn't.
				 *
				 * This is fine. Not all plugins and themes have translations for
				 * every language. Let's just log a warning and move on.
				 */
				logger.warn(
					`Error downloading translations for ${type}: ${error}`
				);
			}
		})
	);
	await Promise.all(translationsQueue);
};

/**
 * Manages site metadata, which is stored in a file called `site-info.json`
 *
 * Today, it's specific to Playground website. Tomorrow, it's meant to be
 * a standardized format for describing a Playground (or even WordPress?)
 * site's configuration, independent of the runtime, e.g. Playground website,
 * Playground CLI, Studio, WP-ENV, hosted environment etc.
 */

import {
	Blueprint,
	PHPConstants,
	compileBlueprint,
} from '@wp-playground/blueprints';
import { resolveBlueprintFromURL } from './state/url/resolve-blueprint-from-url';

/**
 * The supported site storage types.
 *
 * Is it possible to restrict this to those three values for all Playground runtimes?
 * Or should the runtime be allowed to use custom storage types?
 *
 * NOTE: We are using different storage terms than our query API in order
 * to be more explicit about storage medium in the site metadata format.
 */
export const SiteStorageTypes = ['opfs', 'local-fs', 'none'] as const;
export type SiteStorageType = (typeof SiteStorageTypes)[number];

/**
 * The site logo data.
 */
export type SiteLogo = {
	mime: string;
	data: string;
};

// TODO: Create a schema for this as the design matures
/**
 * The Site metadata that is persisted.
 */
export interface SiteMetadata {
	storage: SiteStorageType;
	id: string;
	name: string;
	logo?: SiteLogo;

	// TODO: The designs show keeping admin username and password. Why do we want that?
	whenCreated?: number;
	// TODO: Consider keeping timestamps.
	//       For a user, timestamps might be useful to disambiguate identically-named sites.
	//       For playground, we might choose to sort by most recently used.
	//whenLastLoaded: number;

	// @TODO: Accept any string as a php version?
	runtimeConfiguration: Pick<
		Required<Blueprint>,
		'features' | 'extraLibraries' | 'preferredVersions'
	> & {
		constants?: PHPConstants;
	};
	originalBlueprint: Blueprint;
}

export async function createSiteMetadata(
	initialMetadata: {
		name: string;
	} & Partial<Omit<SiteMetadata, 'runtimeConfiguration'>>
): Promise<SiteMetadata> {
	const { name, originalBlueprint, ...remainingMetadata } = initialMetadata;

	const blueprint: Blueprint =
		originalBlueprint ??
		// TODO: This is a hack because we are just abusing a URL-oriented
		// function to create a completely default Blueprint. Let's fix this by
		// making default creation first-class.
		(await resolveBlueprintFromURL(new URL('https://w.org')));

	const compiledBlueprint = compileBlueprint(blueprint);

	return {
		name,
		id: crypto.randomUUID(),
		whenCreated: Date.now(),
		storage: 'none',
		originalBlueprint: blueprint,

		...remainingMetadata,

		runtimeConfiguration: {
			preferredVersions: {
				wp: compiledBlueprint.versions.wp,
				php: compiledBlueprint.versions.php,
			},
			features: compiledBlueprint.features,
			extraLibraries: compiledBlueprint.extraLibraries,
			/*
			 * Constants don't matter so much for temporary sites so let's
			 * use an empty object here. We can't easily figure out which
			 * additional constants were applied via playground.defineConstant()
			 * at this stage anyway.
			 *
			 * This property is only relevant for stored sites to ensure they're
			 * consistently applied across page reloads.
			 */
			constants: {},
		},
	};
}

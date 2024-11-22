import { MinifiedWordPressVersions } from '@wp-playground/wordpress-builds';
import { getWordPressTranslationUrl } from './set-site-language';

describe('getTranslationUrl()', () => {
	it('should return a major.minor translation URL for a major.minor version', () => {
		expect(getWordPressTranslationUrl('6.6', 'en_US')).toBe(
			'https://downloads.wordpress.org/translation/core/6.6/en_US.zip'
		);
	});

	it('should return a major.minor.patch translation URL for a major.minor.patch version', () => {
		expect(getWordPressTranslationUrl('6.5.1', 'es_ES')).toBe(
			'https://downloads.wordpress.org/translation/core/6.5.1/es_ES.zip'
		);
	});

	[
		{
			version: '6.6-RC1',
			description:
				'should return the latest RC translation URL for a RC version',
		},
		{
			version: '6.6-beta2',
			description:
				'should return the latest RC translation URL for a beta version',
		},
		{
			version: '6.6-nightly',
			description:
				'should return the latest RC translation URL for a nightly version',
		},
		{
			version: '6.8-alpha-59408',
			description:
				'should return the latest RC translation URL for an alpha version',
		},
	].forEach(({ version, description }) => {
		it(description, () => {
			const latestBetaVersion =
				MinifiedWordPressVersions['beta'].split('-')[0];
			expect(getWordPressTranslationUrl(version, 'en_US')).toBe(
				`https://downloads.wordpress.org/translation/core/${latestBetaVersion}-RC/en_US.zip`
			);
		});
	});
});

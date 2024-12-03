import { getWordPressTranslationUrl } from './set-site-language';

describe('getTranslationUrl()', () => {
	[
		{
			versionString: '6.2',
			latestBetaVersion: '6.6-RC',
			latestMinifiedVersion: '6.5.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.2/en_US.zip`,
			description:
				'should return a major.minor translation URL when the input version string is in a major.minor format',
		},
		{
			versionString: '6.2.1',
			latestBetaVersion: '6.3.1-RC',
			latestMinifiedVersion: '6.4.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.2.1/en_US.zip`,
			description:
				'should return a major.minor.patch translation URL when the input version string is in a major.minor.patch format',
		},
		{
			versionString: '6.6-RC1',
			latestBetaVersion: '6.6-RC1',
			latestMinifiedVersion: '6.5.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.6-RC/en_US.zip`,
			description:
				'should return the latest RC translation URL for a RC version',
		},
		{
			versionString: '6.6-beta2',
			latestBetaVersion: '6.6-RC',
			latestMinifiedVersion: '6.5.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.6-RC/en_US.zip`,
			description:
				'should return the latest RC translation URL for a beta version',
		},
		{
			versionString: '6.6-nightly',
			latestBetaVersion: '6.6-RC',
			latestMinifiedVersion: '6.5.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.6-RC/en_US.zip`,
			description:
				'should return the latest RC translation URL for a nightly version',
		},
		{
			versionString: '6.8-alpha-59408',
			latestBetaVersion: '6.8-RC',
			latestMinifiedVersion: '6.7.2',
			expectedUrl: `https://downloads.wordpress.org/translation/core/6.8-RC/en_US.zip`,
			description:
				'should return the latest RC translation URL for an alpha version',
		},
	].forEach(
		({
			versionString,
			latestBetaVersion,
			latestMinifiedVersion,
			expectedUrl,
			description,
		}) => {
			it(description, () => {
				expect(
					getWordPressTranslationUrl(
						versionString,
						'en_US',
						latestBetaVersion,
						latestMinifiedVersion
					)
				).resolves.toBe(expectedUrl);
			});
		}
	);
});

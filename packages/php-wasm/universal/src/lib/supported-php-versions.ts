export const SupportedPHPVersions = [
	'8.4',
	'8.3',
	'8.2',
	'8.1',
	'8.0',
	'7.4',
	'7.3',
	'7.2',
	'7.1',
	'7.0',
] as const;
export const LatestSupportedPHPVersion = SupportedPHPVersions[0];
export const SupportedPHPVersionsList = SupportedPHPVersions as any as string[];
export type SupportedPHPVersion = (typeof SupportedPHPVersions)[number];

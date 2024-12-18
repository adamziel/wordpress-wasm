import { UrlResource, GitDirectoryResource } from './resources';

describe('UrlResource', () => {
	it('should create a new instance of UrlResource', () => {
		const resource = new UrlResource({
			resource: 'url',
			url: 'https://example.com',
			caption: 'Example',
		});
		expect(resource).toBeInstanceOf(UrlResource);
	});

	it('should translate github.com URLs into raw.githubusercontent.com URLs', () => {
		const resource = new UrlResource({
			resource: 'url',
			url: 'https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-includes/version.php',
			caption: 'Example',
		});
		expect(resource.getURL()).toBe(
			'https://raw.githubusercontent.com/WordPress/wordpress-develop/trunk/src/wp-includes/version.php'
		);
	});
});

describe('GitDirectoryResource', () => {
	describe('resolve', () => {
		it.each([
			'packages/docs/site/docs/blueprints/tutorial',
			'/packages/docs/site/docs/blueprints/tutorial',
		])(
			'should return a list of files in the directory (path: %s)',
			async (path) => {
				const resource = new GitDirectoryResource({
					resource: 'git:directory',
					url: 'https://github.com/WordPress/wordpress-playground',
					ref: '05138293dd39e25a9fa8e43a9cc775d6fb780e37',
					path,
				});
				const { files } = await resource.resolve();
				expect(Object.keys(files)).toEqual([
					'01-what-are-blueprints-what-you-can-do-with-them.md',
					'02-how-to-load-run-blueprints.md',
					'03-build-your-first-blueprint.md',
					'index.md',
				]);
			}
		);
	});
});

import fs from 'fs';
import os from 'os';
import path from 'path';
import {
	LatestSupportedPHPVersion,
	PHP,
	__private__dont__use,
	rotatePHPRuntime,
} from '@php-wasm/universal';
import { loadNodeRuntime } from '../lib';
import { createNodeFsMountHandler } from '../lib/node-fs-mount';

const recreateRuntime = async (version: any = LatestSupportedPHPVersion) =>
	await loadNodeRuntime(version);

describe('rotatePHPRuntime()', () => {
	it('Preserves the /internal directory through PHP runtime recreation', async () => {
		// Rotate the PHP runtime
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);

		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 10,
		});

		// Create a temporary directory and a file in it
		const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'temp-'));
		const tempFile = path.join(tempDir, 'file');
		fs.writeFileSync(tempFile, 'playground');

		// Mount the temporary directory
		php.mkdir('/internal/shared');
		php.writeFile('/internal/shared/test', 'playground');

		// Confirm the file is there
		expect(php.fileExists('/internal/shared/test')).toBe(true);

		// Rotate the PHP runtime
		for (let i = 0; i < 15; i++) {
			await php.run({ code: `` });
		}

		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(1);

		// Confirm the file is still there
		expect(php.fileExists('/internal/shared/test')).toBe(true);
		expect(php.readFileAsText('/internal/shared/test')).toBe('playground');
	});

	it('Preserves a single NODEFS mount through PHP runtime recreation', async () => {
		// Rotate the PHP runtime
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);

		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 10,
		});

		// Create a temporary directory and a file in it
		const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'temp-'));
		const tempFile = path.join(tempDir, 'file');
		fs.writeFileSync(tempFile, 'playground');

		// Mount the temporary directory
		php.mkdir('/test-root');
		await php.mount('/test-root', createNodeFsMountHandler(tempDir));

		// Confirm the file is still there
		expect(php.readFileAsText('/test-root/file')).toBe('playground');

		// Rotate the PHP runtime
		for (let i = 0; i < 15; i++) {
			await php.run({ code: `` });
		}

		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(1);

		// Confirm the local NODEFS mount is lost
		expect(php.readFileAsText('/test-root/file')).toBe('playground');
	});

	it('Preserves 4 WordPress plugin mounts through PHP runtime recreation', async () => {
		// Rotate the PHP runtime
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);

		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/wordpress',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 10,
		});

		// Create temporary directories and files for plugins and uploads
		const tempDirs = [
			fs.mkdtempSync(path.join(os.tmpdir(), 'data-liberation-')),
			fs.mkdtempSync(path.join(os.tmpdir(), 'data-liberation-markdown-')),
			fs.mkdtempSync(
				path.join(os.tmpdir(), 'data-liberation-static-files-editor-')
			),
			fs.mkdtempSync(path.join(os.tmpdir(), 'static-pages-')),
		];

		// Add test files to each directory
		tempDirs.forEach((dir, i) => {
			fs.writeFileSync(path.join(dir, 'test.php'), `plugin-${i}`);
		});

		// Create WordPress directory structure
		php.mkdir('/wordpress/wp-content/plugins/data-liberation');
		php.mkdir('/wordpress/wp-content/plugins/z-data-liberation-markdown');
		php.mkdir(
			'/wordpress/wp-content/plugins/z-data-liberation-static-files-editor'
		);
		php.mkdir('/wordpress/wp-content/uploads/static-pages');

		// Mount the directories using WordPress paths
		await php.mount(
			'/wordpress/wp-content/plugins/data-liberation',
			createNodeFsMountHandler(tempDirs[0])
		);
		await php.mount(
			'/wordpress/wp-content/plugins/z-data-liberation-markdown',
			createNodeFsMountHandler(tempDirs[1])
		);
		await php.mount(
			'/wordpress/wp-content/plugins/z-data-liberation-static-files-editor',
			createNodeFsMountHandler(tempDirs[2])
		);
		await php.mount(
			'/wordpress/wp-content/uploads/static-pages',
			createNodeFsMountHandler(tempDirs[3])
		);

		// Verify files exist
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/data-liberation/test.php'
			)
		).toBe('plugin-0');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/z-data-liberation-markdown/test.php'
			)
		).toBe('plugin-1');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/z-data-liberation-static-files-editor/test.php'
			)
		).toBe('plugin-2');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/uploads/static-pages/test.php'
			)
		).toBe('plugin-3');

		// Rotate the PHP runtime
		for (let i = 0; i < 15; i++) {
			await php.run({ code: `` });
		}

		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(1);

		// Verify files still exist after rotation
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/data-liberation/test.php'
			)
		).toBe('plugin-0');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/z-data-liberation-markdown/test.php'
			)
		).toBe('plugin-1');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/plugins/z-data-liberation-static-files-editor/test.php'
			)
		).toBe('plugin-2');
		expect(
			php.readFileAsText(
				'/wordpress/wp-content/uploads/static-pages/test.php'
			)
		).toBe('plugin-3');
	});

	it('Free up the available PHP memory', async () => {
		const freeMemory = (php: PHP) =>
			php[__private__dont__use].HEAPU32.reduce(
				(count: number, byte: number) =>
					byte === 0 ? count + 1 : count,
				0
			);

		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		// Rotate the PHP runtime
		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1000,
		});
		const freeInitially = freeMemory(php);
		for (let i = 0; i < 1000; i++) {
			await php.run({
				code: `<?php
			// Do some string allocations
			for($i=0;$i<10;$i++) {
				echo "abc";
			}
			file_put_contents('./test', 'test');
			`,
			});
		}
		const freeAfter1000Requests = freeMemory(php);
		expect(freeAfter1000Requests).toBeLessThan(freeInitially);

		// Rotate the PHP runtime
		await php.run({ code: `<?php echo "abc";` });
		const freeAfterRotation = freeMemory(php);
		expect(freeAfterRotation).toBeGreaterThan(freeAfter1000Requests);
	}, 30_000);

	it('Should recreate the PHP runtime after maxRequests', async () => {
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		const php = new PHP(await recreateRuntimeSpy());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1,
		});
		// Rotate the PHP runtime
		await php.run({ code: `` });
		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);
	}, 30_000);

	it('Should not rotate after the cleanup handler is called, even if max requests is reached', async () => {
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		const php = new PHP(await recreateRuntimeSpy());
		const cleanup = rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1,
		});
		// Rotate the PHP runtime
		await php.run({ code: `` });
		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);

		cleanup();

		// No further rotation should happen
		await php.run({ code: `` });
		await php.run({ code: `` });

		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);
	}, 30_000);

	it('Should recreate the PHP runtime after a PHP runtime crash', async () => {
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		const php = new PHP(await recreateRuntimeSpy());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1234,
		});
		// Cause a PHP runtime rotation due to error
		await php.dispatchEvent({
			type: 'request.error',
			error: new Error('mock error'),
			source: 'php-wasm',
		});
		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);
	}, 30_000);

	it('Should not recreate the PHP runtime after a PHP fatal', async () => {
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		const php = new PHP(await recreateRuntimeSpy());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1234,
		});
		// Trigger error with no `source`
		await php.dispatchEvent({
			type: 'request.error',
			error: new Error('mock error'),
		});
		// Trigger error with request `source`
		await php.dispatchEvent({
			type: 'request.error',
			error: new Error('mock error'),
			source: 'request',
		});
		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(1);
	}, 30_000);

	it('Should not rotate after the cleanup handler is called, even if there is a PHP runtime error', async () => {
		const recreateRuntimeSpy = vitest.fn(recreateRuntime);
		const php = new PHP(await recreateRuntimeSpy());
		const cleanup = rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1,
		});
		// Rotate the PHP runtime
		await php.run({ code: `` });
		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);

		cleanup();

		// No further rotation should happen
		php.dispatchEvent({
			type: 'request.error',
			error: new Error('mock error'),
			source: 'php-wasm',
		});

		expect(recreateRuntimeSpy).toHaveBeenCalledTimes(2);
	}, 30_000);

	it('Should hotswap the PHP runtime from 8.2 to 8.3', async () => {
		let nbCalls = 0;
		const recreateRuntimeSpy = vitest.fn(() => {
			if (nbCalls === 0) {
				++nbCalls;
				return recreateRuntime('8.2');
			}
			return recreateRuntime('8.3');
		});
		const php = new PHP(await recreateRuntimeSpy());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime: recreateRuntimeSpy,
			maxRequests: 1,
		});
		const version1 = (
			await php.run({
				code: `<?php echo PHP_VERSION;`,
			})
		).text;
		const version2 = (
			await php.run({
				code: `<?php echo PHP_VERSION;`,
			})
		).text;
		expect(version1).toMatch(/^8\.2/);
		expect(version2).toMatch(/^8\.3/);
	}, 30_000);

	it('Should preserve the custom SAPI name', async () => {
		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime,
			maxRequests: 1,
		});
		php.setSapiName('custom SAPI');

		// Rotate the PHP runtime
		await php.run({ code: `` });
		const result = await php.run({
			code: `<?php echo php_sapi_name();`,
		});
		expect(result.text).toBe('custom SAPI');
	});

	it('Should preserve the MEMFS files', async () => {
		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime,
			maxRequests: 1,
		});

		// Rotate the PHP runtime
		await php.run({ code: `` });

		php.mkdir('/test-root');
		php.writeFile('/test-root/index.php', '<?php echo "hi";');

		// Rotate the PHP runtime
		await php.run({ code: `` });

		expect(php.fileExists('/test-root/index.php')).toBe(true);
		expect(php.readFileAsText('/test-root/index.php')).toBe(
			'<?php echo "hi";'
		);
	}, 30_000);

	it('Should not overwrite the NODEFS files', async () => {
		const php = new PHP(await recreateRuntime());
		rotatePHPRuntime({
			php,
			cwd: '/test-root',
			recreateRuntime,
			maxRequests: 1,
		});

		// Rotate the PHP runtime
		await php.run({ code: `` });

		php.mkdir('/test-root');
		php.writeFile('/test-root/index.php', 'test');
		php.mkdir('/test-root/nodefs');

		const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'temp-'));
		const tempFile = path.join(tempDir, 'file');
		fs.writeFileSync(tempFile, 'playground');
		const date = new Date();
		date.setFullYear(date.getFullYear() - 1);
		fs.utimesSync(tempFile, date, date);
		try {
			php.mount('/test-root/nodefs', createNodeFsMountHandler(tempDir));

			// Rotate the PHP runtime
			await php.run({ code: `` });

			// Expect the file to still have the same utime
			const stats = fs.statSync(tempFile);
			expect(Math.round(stats.atimeMs)).toBe(Math.round(date.getTime()));

			// The MEMFS file should still be there
			expect(php.fileExists('/test-root/index.php')).toBe(true);
		} finally {
			fs.rmSync(tempFile);
			fs.rmdirSync(tempDir);
		}
	}, 30_000);
});

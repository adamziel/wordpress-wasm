import { PHP } from '@php-wasm/universal';
import { phpVars } from '@php-wasm/util';
import { runSql } from './run-sql';
import { PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';

const phpVersion = '8.0';
describe('Blueprint step runSql', () => {
	let php: PHP;
	let handler: PHPRequestHandler;
	const documentRoot = '/wordpress';

	const outputLogPath = `/tmp/sql-execution-log.json`;
	beforeEach(async () => {
		handler = new PHPRequestHandler({
			phpFactory: async () => new PHP(await loadNodeRuntime(phpVersion)),
			documentRoot,
		});
		php = await handler.getPrimaryPhp();
		php.mkdir(documentRoot);
		// Create an object that will log all function calls
		const js = phpVars({ documentRoot, outputLogPath });
		/**
		 * The run-sql step loads WordPress by including wp-load.php.
		 * We don't need the rest of WordPress for this test, so we
		 * create a minimal wp-load.php that just logs the sql queries.
		 */
		php.writeFile(
			`${documentRoot}/wp-load.php`,
			`<?php
			class MockLogger
			{
				public function __call($function, $args)
				{
					$entry = (object)[
						'type' => 'CALL',
						'function' => $function,
						'args' => $args,
					];

					file_put_contents(${js.outputLogPath}, json_encode($entry) . "\n", FILE_APPEND);
				}
			}

			global $wpdb;
			$wpdb = new MockLogger();
			file_put_contents(${js.outputLogPath}, '');
			`
		);
	});

	it('should split and "run" sql queries', async () => {
		// Test a single query
		await runSql(php, {
			sql: new File(['SELECT * FROM wp_users;'], 'single-query.sql'),
		});

		const result = php.readFileAsText(outputLogPath);
		expect(result).toBe(
			`{"type":"CALL","function":"query","args":["SELECT * FROM wp_users;"]}\n`
		);
	});

	it('should split and "run" multiple sql queries', async () => {
		await runSql(php, {
			sql: new File(
				[
					['SELECT * FROM wp_users;', 'SELECT * FROM wp_posts;'].join(
						'\n'
					),
				],
				'multiple-queries.sql'
			),
		});

		const result = php.readFileAsText(outputLogPath);
		expect(result).toBe(
			`{"type":"CALL","function":"query","args":["SELECT * FROM wp_users;\\n"]}\n{"type":"CALL","function":"query","args":["SELECT * FROM wp_posts;"]}\n`
		);
	});

	it('should support inputs with empty lines and semicolon-only lines', async () => {
		await runSql(php, {
			sql: new File(
				[
					[
						'SELECT * FROM wp_users;',
						';',
						'',
						'SELECT * FROM wp_posts;',
						'',
					].join('\n'),
				],
				'no-trailing-newline.sql'
			),
		});

		const result = php.readFileAsText(outputLogPath);
		expect(result).toBe(
			`{"type":"CALL","function":"query","args":["SELECT * FROM wp_users;\\n"]}\n{"type":"CALL","function":"query","args":["SELECT * FROM wp_posts;\\n"]}\n`
		);
	});
});

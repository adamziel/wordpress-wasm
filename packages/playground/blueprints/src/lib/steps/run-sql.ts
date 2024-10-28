import { StepHandler } from '.';
import { rm } from './rm';
import { phpVars, randomFilename } from '@php-wasm/util';

/**
 * @inheritDoc runSql
 * @hasRunnableExample
 * @example
 *
 * <code>
 * {
 *		"step": "runSql",
 *		"sql": {
 *			"resource": "literal",
 *			"name": "schema.sql",
 *			"contents": "DELETE FROM wp_posts"
 *		}
 * }
 * </code>
 */
export interface RunSqlStep<ResourceType> {
	/**
	 * The step identifier.
	 */
	step: 'runSql';
	/**
	 * The SQL to run. Each non-empty line must contain a valid SQL query.
	 */
	sql: ResourceType;
}

/**
 * Run one or more SQL queries.
 *
 * This step will treat each non-empty line in the input SQL as a query and
 * try to execute it using `$wpdb`. Queries spanning multiple lines are not
 * yet supported.
 */
export const runSql: StepHandler<RunSqlStep<File>> = async (
	playground,
	{ sql },
	progress?
) => {
	progress?.tracker.setCaption(`Executing SQL Queries`);

	const sqlFilename = `/tmp/${randomFilename()}.sql`;

	await playground.writeFile(
		sqlFilename,
		new Uint8Array(await sql.arrayBuffer())
	);

	const docroot = await playground.documentRoot;

	const js = phpVars({ docroot, sqlFilename });

	const runPhp = await playground.run({
		code: `<?php
		require_once ${js.docroot} . '/wp-load.php';

		$handle = fopen(${js.sqlFilename}, 'r');

		global $wpdb;

		while ($line = fgets($handle)) {
			if(trim($line, " \n;") === '') {
				continue;
			}

			$wpdb->query($line);
		}
	`,
	});

	await rm(playground, { path: sqlFilename });

	return runPhp;
};

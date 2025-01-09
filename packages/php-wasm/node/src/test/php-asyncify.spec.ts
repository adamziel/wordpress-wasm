import http from 'http';
import fs from 'fs';
import path from 'path';
import {
	PHP,
	PHPRequestHandler,
	SupportedPHPVersions,
	setPhpIniEntries,
} from '@php-wasm/universal';
import { phpVars } from '@php-wasm/util';
// eslint-disable-next-line @nx/enforce-module-boundaries
import InitialDockerfile from '../../../compile/php/Dockerfile?raw';
import { loadNodeRuntime } from '../lib';
import { RecommendedPHPVersion } from '@wp-playground/common';

// Start a server to test network functions
const server = http.createServer((req, res) => {
	res.writeHead(200, { 'Content-Type': 'text/plain' });
	res.end('Hello World\n');
});
const host = '127.0.0.1';
const port = await new Promise((resolve) => {
	server.listen(0, function () {
		resolve((server.address() as any).port);
	});
});
const httpUrl = `http://${host}:${port}`;
const js = phpVars({
	host,
	port,
	httpUrl,
});

const phpVersions =
	'PHP' in process.env ? [process.env['PHP']] : SupportedPHPVersions;

describe.each(phpVersions)('PHP %s – asyncify', () => {
	const topOfTheStack: Array<string> = [
		// http:// stream handler
		`file_get_contents(${js['httpUrl']});`,

		`$fp = fopen(${js['httpUrl']}, "r");
		 fread($fp, 1024);
		 fclose($fp);`,
		// `getimgsize(${js['httpUrl']});`,

		// Network functions from https://www.php.net/manual/en/book.network.php
		`$fp = fsockopen(${js['host']}, ${js['port']});
		 fwrite($fp, "GET / HTTP/1.1\\r\\n\\r\\n");
		 fread($fp, 10);
		 fclose($fp);`,
		`gethostbyname(${js['httpUrl']});`,

		// @TODO:

		// https:// stream handler
		// MySQL functions from https://www.php.net/manual/en/book.mysql.php
		// PDO functions from https://www.php.net/manual/en/book.pdo.php
		// Sockets functions from https://www.php.net/manual/en/book.sockets.php
	];

	let php: PHP;
	let requestHandler: PHPRequestHandler;
	beforeEach(async () => {
		requestHandler = new PHPRequestHandler({
			phpFactory: async () =>
				new PHP(await loadNodeRuntime(RecommendedPHPVersion)),
			documentRoot: '/',
		});
		php = await requestHandler.getPrimaryPhp();
		await setPhpIniEntries(php, { allow_url_fopen: 1 });
	});

	describe.each(topOfTheStack)('%s', (networkCall) => {
		test('Direct call', () => assertNoCrash(` ${networkCall}`));
		describe('Function calls', () => {
			test('Simple call', () =>
				assertNoCrash(`function top() { ${networkCall} } top();`));
			test('Via call_user_func', () =>
				assertNoCrash(
					`function top() { ${networkCall} } call_user_func('top'); `
				));
			test('Via call_user_func_array', () =>
				assertNoCrash(
					`function top() { ${networkCall} } call_user_func_array('top', array());`
				));
		});

		describe('Array functions', () => {
			test('array_filter', () =>
				assertNoCrash(`
					function top() { ${networkCall} }
					array_filter(array('top'), 'top');
				`));

			test('array_map', () =>
				assertNoCrash(`
					function top() { ${networkCall} }
					array_map(array('top'), 'top');
				`));

			// Network calls in sort() would be silly so let's skip those for now.
		});

		describe('Class method calls', () => {
			test('Regular method', () =>
				assertNoCrash(`
				class Top {
					function my_method() { ${networkCall} }
				}
				$x = new Top();
				$x->my_method();
			`));
			test('Via ReflectionMethod->invoke()', () =>
				assertNoCrash(`
				class Top {
					function my_method() { ${networkCall} }
				}
				$reflectionMethod = new ReflectionMethod('Top', 'my_method');
				$reflectionMethod->invoke(new Top());
			`));
			test('Via ReflectionMethod->invokeArgs()', () =>
				assertNoCrash(`
				class Top {
					function my_method() { ${networkCall} }
				}
				$reflectionMethod = new ReflectionMethod('Top', 'my_method');
				$reflectionMethod->invokeArgs(new Top(), array());
			`));
			test('Via call_user_func', () =>
				assertNoCrash(`
				class Top {
					function my_method() { ${networkCall} }
				}
				call_user_func([new Top(), 'my_method']);
				`));
			test('Via call_user_func_array', () =>
				assertNoCrash(`
				class Top {
					function my_method() { ${networkCall} }
				}
				call_user_func_array([new Top(), 'my_method'], []);
				`));
			test('Constructor', () =>
				assertNoCrash(`
				class Top {
					function __construct() { ${networkCall} }
				}
				new Top();
			`));
			test('Destructor', () =>
				assertNoCrash(`
				class Top {
					function __destruct() { ${networkCall} }
				}
				$x = new Top();
				unset($x);
			`));
			test('__call', () =>
				assertNoCrash(`
				class Top {
					function __call($method, $args) { ${networkCall} }
				}
				$x = new Top();
				$x->test();
			`));
			test('__get', () =>
				assertNoCrash(`
				class Top {
					function __get($prop) { ${networkCall} }
				}
				$x = new Top();
				$x->test;
			`));
			test('__set', () =>
				assertNoCrash(`
				class Top {
					function __set($prop, $value) { ${networkCall} }
				}
				$x = new Top();
				$x->test = 1;
			`));
			test('__isset', () =>
				assertNoCrash(`
				class Top {
					function __isset($prop) { ${networkCall} }
				}
				$x = new Top();
				isset($x->test);
			`));
			test('offsetSet', () => {
				assertNoCrash(`
					class Top implements ArrayAccess {
						function offsetExists($offset) { ${networkCall} }
						function offsetGet($offset) { ${networkCall} }
						function offsetSet($offset, $value) { ${networkCall} }
						function offsetUnset($offset) { ${networkCall} }
					}
					$x = new Top();
					isset($x['test']);
					$a = $x['test'];
					$x['test'] = 123;
					unset($x['test']);
				`);
			});
			test('Iterator', () =>
				assertNoCrash(`
				$data = new class() implements IteratorAggregate {
					public function getIterator(): Traversable {
						${networkCall};
						return new ArrayIterator( [] );
					}
				};
				echo json_encode( [
					...$data
				] );
			`));
			test('yield', () =>
				assertNoCrash(`
				function countTo2() {
					${networkCall};
					yield '1';
					${networkCall};
					yield '2';
				}
				foreach(countTo2() as $number) {
					echo $number;
				}
			`));
			test('Soap', async () => {
				await php.writeFile(
					'/soap-server.php',
					`<?php
					class TestSoapServer
					{
						public function getMessage()
						{
							return 'Hello, World!';
						}
					}
					try {
						$options = [
							'uri' => ${requestHandler.absoluteUrl},
						];
						$server = new SoapServer(null, $options);
						$server->setClass('TestSoapServer');
						$server->handle();
					} catch (SoapFault $e) {
						echo "Server Error: " . $e->getMessage();
					}`
				);

				assertNoCrash(`
					$client = new SoapClient('${requestHandler.absoluteUrl}/soap-server.php');
					echo json_encode($client->getMessage());
				`);
			});
		});
	});

	async function assertNoCrash(code: string) {
		try {
			const result = await php.run({
				code: `<?php ${code}`,
			});
			expect(result).toBeTruthy();
			expect(result.text).toBe('');
			expect(result.errors).toBeFalsy();
		} catch (e) {
			if (
				'FIX_DOCKERFILE' in process.env &&
				process.env['FIX_DOCKERFILE'] === 'true' &&
				'functionsMaybeMissingFromAsyncify' in php
			) {
				const missingCandidates = (
					php.functionsMaybeMissingFromAsyncify as string[]
				)
					.map((candidate) =>
						candidate.replace('byn$fpcast-emu$', '')
					)
					.filter(
						(candidate) => !Dockerfile.includes(`"${candidate}"`)
					);
				if (missingCandidates.length) {
					addAsyncifyFunctionsToDockerfile(missingCandidates);
					throw new Error(
						`Asyncify crash! The following missing functions were just auto-added to the ASYNCIFY_ONLY list in the Dockerfile: \n ` +
							missingCandidates.join(', ') +
							`\nYou now need to rebuild PHP and re-run this test: \n` +
							`  npm run recompile:php:node:asyncify:8.0\n` +
							`  node --stack-trace-limit=100 ./node_modules/.bin/nx test php-wasm-node --test-name-pattern='asyncify'\n`
					);
				}

				const err = new Error(
					`Asyncify crash! No C functions present in the stack trace were missing ` +
						`from the Dockerfile. This could mean the stack trace is too short – try increasing the stack trace limit ` +
						`with --stack-trace-limit=100. If you already did that, fixing this problem will likely take more digging.`
				);
				err.cause = e;
				throw err;
			}
		}
	}
});

let Dockerfile = InitialDockerfile;
const DockerfilePath = path.resolve(
	__dirname,
	'../../../compile/php/Dockerfile'
);
function addAsyncifyFunctionsToDockerfile(functions: string[]) {
	const currentDockerfile = fs.readFileSync(DockerfilePath, 'utf8') + '';
	const lookup = `export ASYNCIFY_ONLY=$'`;
	const idx = currentDockerfile.indexOf(lookup) + lookup.length;
	const updatedDockerfile =
		currentDockerfile.substring(0, idx) +
		functions.map((f) => `"${f}",\\\n`).join('') +
		currentDockerfile.substring(idx);
	fs.writeFileSync(DockerfilePath, updatedDockerfile);
	Dockerfile = updatedDockerfile;
}

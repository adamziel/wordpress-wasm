import { getPHPLoaderModule, loadNodeRuntime } from '..';
import { vi } from 'vitest';
import {
	__private__dont__use,
	getPhpIniEntries,
	loadPHPRuntime,
	PHP,
	setPhpIniEntries,
	SupportedPHPVersions,
} from '@php-wasm/universal';
import { existsSync, rmSync, readFileSync, mkdirSync, writeFileSync } from 'fs';
import { createSpawnHandler, phpVar } from '@php-wasm/util';
import { createNodeFsMountHandler } from '../lib/node-fs-mount';

const testDirPath = '/__test987654321';
const testFilePath = '/__test987654321.txt';
/**
 * Preface to Pygmalion is a longer chunk of text that
 * won't fit into a pipe buffer and will require multiple
 * read/write cycles to complete. This is perfect for testing
 * whether these chunks are appended to the output one after
 * another (as opposed to writing over the previous chunk).
 */
const pygmalion = `PREFACE TO PYGMALION.

A Professor of Phonetics.

As will be seen later on, Pygmalion needs, not a preface, but a sequel,
which I have supplied in its due place. The English have no respect for
their language, and will not teach their children to speak it. They
spell it so abominably that no man can teach himself what it sounds
like. It is impossible for an Englishman to open his mouth without
making some other Englishman hate or despise him. German and Spanish
are accessible to foreigners: English is not accessible even to
Englishmen. The reformer England needs today is an energetic phonetic
enthusiast: that is why I have made such a one the hero of a popular
play. There have been heroes of that kind crying in the wilderness for
many years past. When I became interested in the subject towards the
end of the eighteen-seventies, Melville Bell was dead; but Alexander J.
Ellis was still a living patriarch, with an impressive head always
covered by a velvet skull cap, for which he would apologize to public
meetings in a very courtly manner. He and Tito Pagliardini, another
phonetic veteran, were men whom it was impossible to dislike. Henry
Sweet, then a young man, lacked their sweetness of character: he was
about as conciliatory to conventional mortals as Ibsen or Samuel
Butler. His great ability as a phonetician (he was, I think, the best
of them all at his job) would have entitled him to high official
recognition, and perhaps enabled him to popularize his subject, but for
his Satanic contempt for all academic dignitaries and persons in
general who thought more of Greek than of phonetics. Once, in the days
when the Imperial Institute rose in South Kensington, and Joseph
Chamberlain was booming the Empire, I induced the editor of a leading
monthly review to commission an article from Sweet on the imperial
importance of his subject. When it arrived, it contained nothing but a
savagely derisive attack on a professor of language and literature
whose chair Sweet regarded as proper to a phonetic expert only. The
article, being libelous, had to be returned as impossible; and I had to
renounce my dream of dragging its author into the limelight. When I met
him afterwards, for the first time for many years, I found to my
astonishment that he, who had been a quite tolerably presentable young
man, had actually managed by sheer scorn to alter his personal
appearance until he had become a sort of walking repudiation of Oxford
and all its traditions. It must have been largely in his own despite
that he was squeezed into something called a Readership of phonetics
there. The future of phonetics rests probably with his pupils, who all
swore by him; but nothing could bring the man himself into any sort of
compliance with the university, to which he nevertheless clung by
divine right in an intensely Oxonian way. I daresay his papers, if he
has left any, include some satires that may be published without too
destructive results fifty years hence. He was, I believe, not in the
least an ill-natured man: very much the opposite, I should say; but he
would not suffer fools gladly.`;

describe.each(SupportedPHPVersions)('PHP %s', (phpVersion) => {
	let php: PHP;
	beforeEach(async () => {
		php = new PHP(await loadNodeRuntime(phpVersion as any));
		php.mkdir('/php');
		await setPhpIniEntries(php, { disable_functions: '' });
	});
	afterEach(async () => {
		// Clean up
		try {
			php.exit(0);
		} catch (e) {
			// ignore exit-related exceptions
		}
	});

	describe('ENV variables', () => {
		it('Supports setting per-request ENV variables', async () => {
			const result = await php.run({
				env: {
					OPTIONS: 'WordPress',
				},
				code: `<?php
				echo 'env.OPTIONS: ' . getenv("OPTIONS");
			`,
			});
			expect(result.text).toEqual('env.OPTIONS: WordPress');
		});

		it('Does not remember ENV variables between requests', async () => {
			await php.run({
				env: {
					OPTIONS: 'WordPress',
				},
				code: `<?php
				echo 'env.OPTIONS: ' . getenv("OPTIONS");
			`,
			});
			const result = await php.run({
				code: `<?php
				echo 'env.OPTIONS: ' . getenv("OPTIONS");
			`,
			});
			expect(result.text).toEqual('env.OPTIONS: ');
		});
	});

	describe('exec()', () => {
		it('echo', async () => {
			const result = await php.run({
				code: `<?php
				echo 'stdout: ' . exec("echo WordPress");
			`,
			});
			expect(result.text).toEqual('stdout: WordPress');
		});
	});

	describe('shell_exec()', () => {
		it('echo', async () => {
			const result = await php.run({
				code: `<?php
				echo 'stdout: ' . shell_exec("echo WordPress");
			`,
			});
			expect(result.text).toEqual('stdout: WordPress\n');
		});
	});

	/**
	 * @issue https://github.com/WordPress/wordpress-playground/issues/1042
	 */
	describe('dns_* function warnings', () => {
		it('dns_check_record should throw a warning', async () => {
			const result = await php.run({
				code: `<?php
				dns_check_record('w.org', 2);
			`,
			});
			expect(result.text).toContain(
				'dns_check_record() always returns false in PHP.wasm.'
			);
		});
	});

	describe('dns_* functions()', () => {
		beforeEach(async () => {
			await setPhpIniEntries(php, {
				...getPhpIniEntries(php),
				// Disable warnings to test the function output
				error_reporting: 'E_ALL & ~E_WARNING',
			});
		});
		it('dns_check_record should exist and be possible to run', async () => {
			const result = await php.run({
				code: `<?php
				var_dump(dns_check_record('w.org', 2));
			`,
			});
			expect(result.text).toEqual('bool(false)\n');
		});
		it('dns_get_record should exist and be possible to run', async () => {
			const result = await php.run({
				code: `<?php
				var_dump(dns_get_record('w.org'));
			`,
			});
			expect(result.text).toEqual('array(0) {\n}\n');
		});
		it('dns_get_mx should exist and be possible to run', async () => {
			const result = await php.run({
				code: `<?php
				var_dump(dns_get_mx('', $mxhosts));
			`,
			});
			expect(result.text).toEqual('bool(false)\n');
		});
		it('getmxrr should exist and be possible to run', async () => {
			const result = await php.run({
				code: `<?php
				var_dump(getmxrr('', $mxhosts));
			`,
			});
			expect(result.text).toEqual('bool(false)\n');
		});
	});

	describe('popen()', () => {
		it('popen("echo", "r")', async () => {
			const result = await php.run({
				code: `<?php
				$fp = popen("echo WordPress", "r");
				echo 'stdout: ' . fread($fp, 1024);
				pclose($fp);
			`,
			});
			expect(result.text).toEqual('stdout: WordPress\n');
		});

		it('popen("cat", "w")', async () => {
			try {
				const result = await php.run({
					code: `<?php
					$fp = popen("cat > out", "w");
					fwrite($fp, "WordPress\n");
					fclose($fp);

					sleep(1); // @TODO: call js_wait_until_process_exits() in fclose();

					$fp = popen("cat out", "r");
					echo 'stdout: ' . fread($fp, 1024);
					pclose($fp);
				`,
				});

				expect(result.text).toEqual('stdout: WordPress\n');
			} finally {
				rmSync('out', { force: true });
			}
		});
	});

	describe('proc_open()', () => {
		it('echo "WordPress"; stdin=file (empty), stdout=file, stderr=file, file_get_contents', async () => {
			const result = await php.run({
				code: `<?php
				file_put_contents('/tmp/process_in', '');
				$res = proc_open(
					"echo WordPress",
					array(
						array("file","/tmp/process_in", "r"),
						array("file","/tmp/process_out", "w"),
						array("file","/tmp/process_err", "w"),
					),
					$pipes
				);
				proc_close($res);

                sleep(1); // @TODO: call js_wait_until_process_exits() in fclose();

				$stdout = file_get_contents("/tmp/process_out");
				$stderr = file_get_contents("/tmp/process_err");

				echo 'stdout: ' . $stdout . "";
				echo 'stderr: ' . $stderr . PHP_EOL;
			`,
			});
			expect(result.text).toEqual('stdout: WordPress\nstderr: \n');
		});

		it('echo "WordPress"; stdin=file (empty), stdout=pipe, stderr=pipe, stream_get_contents', async () => {
			const result = await php.run({
				code: `<?php
				file_put_contents('/tmp/process_in', '');
				$res = proc_open(
					"echo WordPress",
					array(
						array("file","/tmp/process_in", "r"),
						array("pipe","w"),
						array("pipe","w"),
					),
					$pipes
				);

				$stdout = stream_get_contents($pipes[1]);
				$stderr = stream_get_contents($pipes[2]);
				proc_close($res);

				echo 'stdout: ' . $stdout . "";
				echo 'stderr: ' . $stderr . PHP_EOL;
			`,
			});
			expect(result.text).toEqual('stdout: WordPress\nstderr: \n');
		});

		it('echo "WordPress"; stdin=file (empty), stdout=pipe, stderr=pipe, fread', async () => {
			const result = await php.run({
				code: `<?php
				file_put_contents('/tmp/process_in', '');
				$res = proc_open(
					"echo WordPress",
					array(
						array("file","/tmp/process_in", "r"),
						array("pipe","w"),
						array("pipe","w"),
					),
					$pipes
				);

				$stdout = fread($pipes[1], 1024);
				$stderr = fread($pipes[2], 1024);
				proc_close($res);

				echo 'stdout: ' . $stdout . "";
				echo 'stderr: ' . $stderr . PHP_EOL;
			`,
			});
			expect(result.text).toEqual('stdout: WordPress\nstderr: \n');
		});

		// This test fails on older PHP versions
		if (!['7.0', '7.1', '7.2', '7.3'].includes(phpVersion)) {
			it('cat: stdin=pipe, stdout=file, stderr=file, file_get_contents', async () => {
				const result = await php.run({
					code: `<?php
                    $res = proc_open(
                        "cat",
                        array(
                            array("pipe","r"),
                            array("file","/tmp/process_out", "w"),
                            array("file","/tmp/process_err", "w"),
                        ),
                        $pipes
                    );
                    fwrite($pipes[0], 'WordPress\n');

                    proc_close($res);

                    $stdout = file_get_contents("/tmp/process_out");
                    $stderr = file_get_contents("/tmp/process_err");

                    echo 'stdout: ' . $stdout . "";
                    echo 'stderr: ' . $stderr . PHP_EOL;
                `,
				});
				expect(result.text).toEqual('stdout: WordPress\nstderr: \n');
			});
		}

		it('cat: stdin=file, stdout=file, stderr=file, file_get_contents', async () => {
			const result = await php.run({
				code: `<?php
				file_put_contents('/tmp/process_in', 'WordPress\n');
				$res = proc_open(
					"cat",
					array(
						array("file","/tmp/process_in", "r"),
						array("file","/tmp/process_out", "w"),
						array("file","/tmp/process_err", "w"),
					),
					$pipes
				);

				proc_close($res);

				$stdout = file_get_contents("/tmp/process_out");
				$stderr = file_get_contents("/tmp/process_err");

				echo 'stdout: ' . $stdout . "";
				echo 'stderr: ' . $stderr . PHP_EOL;
			`,
			});

			expect(result.text).toEqual('stdout: WordPress\nstderr: \n');
		});

		it('Passes the cwd and env arguments', async () => {
			const handler = createSpawnHandler(
				(command: string[], processApi: any, options: any) => {
					processApi.flushStdin();
					processApi.stdout(options.cwd + '\n');
					for (const key in options.env) {
						processApi.stdout(key + '=' + options.env[key] + '\n');
					}
					processApi.exit(0);
				}
			);

			php.setSpawnHandler(handler);

			const result = await php.run({
				code: `<?php
				$res = proc_open(
					"cat",
					array(
						array("pipe","r"),
						array("file","/tmp/process_out", "w"),
						array("file","/tmp/process_err", "w"),
					),
					$pipes,
					"/wordpress",
					array("FOO" => "BAR", "BAZ" => "QUX")
				);
				fwrite($pipes[0], 'WordPress\n');
				proc_close($res);
				$stdout = file_get_contents("/tmp/process_out");
				$stderr = file_get_contents("/tmp/process_err");
				echo 'stdout: ' . $stdout . "";
				echo 'stderr: ' . $stderr . PHP_EOL;
			`,
			});

			expect(result.text).toEqual(
				'stdout: /wordpress\nFOO=BAR\nBAZ=QUX\nstderr: \n'
			);
		});

		async function pygmalionToProcess(cmd = 'less') {
			return await php.run({
				code: `<?php
			$fd = fopen( "php://temp", "r+" );
			fputs( $fd, ${phpVar(pygmalion)} );
			rewind( $fd );

			$descriptorspec = array(
				0 => $fd,
				1 => fopen('php://stdout', 'wb'),
				2 => fopen('/tmp/stderr', 'wb')
			);
			$fp = proc_open( ${phpVar(cmd)}, $descriptorspec, $pipes );
			proc_close( $fp );
			`,
			});
		}

		it('Pipe pygmalion from a file to STDOUT through a synchronous JavaScript callback', async () => {
			const handler = createSpawnHandler(
				(command: string[], processApi: any) => {
					processApi.on('stdin', (data: Uint8Array) => {
						processApi.stdout(data);
					});
					processApi.flushStdin();
					processApi.exit(0);
				}
			);

			php.setSpawnHandler(handler);
			const result = await pygmalionToProcess();

			expect(result.text).toEqual(pygmalion);
		});

		it('Pipe pygmalion from a file to STDOUT through a asynchronous JavaScript callback', async () => {
			const handler = createSpawnHandler(
				async (command: string[], processApi: any) => {
					await new Promise((resolve) => {
						setTimeout(resolve, 1000);
					});
					processApi.on('stdin', (data: Uint8Array) => {
						processApi.stdout(data);
					});
					processApi.flushStdin();
					processApi.exit(0);
				}
			);

			php.setSpawnHandler(handler);
			const result = await pygmalionToProcess();

			expect(result.text).toEqual(pygmalion);
		});

		it('Pipe pygmalion from a file to STDOUT through "cat"', async () => {
			const result = await pygmalionToProcess('cat');
			expect(result.text).toEqual(pygmalion);
		});

		it('Uses the specified spawn handler', async () => {
			let spawnHandlerCalled = false;
			php.setSpawnHandler(() => {
				spawnHandlerCalled = true;
				return {
					stdout: {
						on: () => {},
					},
					stderr: {
						on: () => {},
					},
					stdin: {
						write: () => {},
					},
					on: (evt: string, callback: Function) => {
						if (evt === 'spawn') {
							callback();
						}
					},
					kill: () => {},
				} as any;
			});
			await php.run({
				code: `<?php
				$res = proc_open(
					"echo 'Hello World!'",
					array(
						array("pipe","r"),
						array("pipe","w"),
						array("pipe","w"),
					),
					$pipes
				);
				proc_close($res);
			`,
			});
			expect(spawnHandlerCalled).toEqual(true);
		});

		it('Stdout waits for asynchronous data to arrive', async () => {
			const handler = createSpawnHandler(
				(command: string[], processApi: any) => {
					processApi.flushStdin();
					processApi.stdout(new TextEncoder().encode('Hello World!'));
					setTimeout(() => {
						processApi.stdout(
							new TextEncoder().encode('Hello again!')
						);
						processApi.exit(0);
					}, 1000);
				}
			);

			php.setSpawnHandler(handler);

			const result = await php.run({
				code: `<?php
			$descriptorspec = array(
				1 => array("pipe","w")
			);
			$proc = proc_open( "fetch", $descriptorspec, $pipes );
			echo fread($pipes[1], 1024);
			echo "\\n";
			echo fread($pipes[1], 1024);;
			proc_close( $proc );
			`,
			});

			expect(result.text).toEqual('Hello World!\nHello again!');
		});

		it('feof() returns true when exhausted the synchronous data', async () => {
			const handler = createSpawnHandler(
				(command: string[], processApi: any) => {
					processApi.flushStdin();
					processApi.stdout(
						new TextEncoder().encode('Hello World!\n')
					);
					processApi.exit(0);
				}
			);

			php.setSpawnHandler(handler);

			const result = await php.run({
				code: `<?php
			$descriptorspec = array(
				1 => array("pipe","w")
			);
			$proc = proc_open( "echo 'Hello World'", $descriptorspec, $pipes );
			var_dump(fread($pipes[1], 1024)); // "Hello World"
			var_dump(fread($pipes[1], 1024)); // ""
			var_dump(fread($pipes[1], 1024)); // ""
			var_dump($pipes[1]);              // resource(1)
			var_dump(feof($pipes[1]));        // true
			pclose( $pipes[1] );
			`,
			});

			expect(result.text).toEqual(
				[
					`string(13) "Hello World!\n"`,
					`string(0) ""`,
					`string(0) ""`,
					`resource(3) of type (stream)`,
					`bool(true)`,
					'',
				].join('\n')
			);
		});

		it('feof() returns true when exhausted the asynchronous data', async () => {
			const handler = createSpawnHandler(
				(command: string[], processApi: any) => {
					processApi.flushStdin();
					processApi.stdout(
						new TextEncoder().encode('Hello World!\n')
					);
					setTimeout(() => {
						processApi.stdout(
							new TextEncoder().encode('Hello Again!\n')
						);
						setTimeout(() => {
							processApi.exit(0);
						}, 100);
					}, 500);
				}
			);

			php.setSpawnHandler(handler);

			const result = await php.run({
				code: `<?php
			$descriptorspec = array(
				1 => array("pipe","w")
			);
			$proc = proc_open( "echo 'Hello World'; sleep 1; echo 'Hello Again'", $descriptorspec, $pipes );
			var_dump(fread($pipes[1], 1024)); // "Hello World"
			var_dump(fread($pipes[1], 1024)); // "Hello Again"
			var_dump(fread($pipes[1], 1024)); // ""
			var_dump($pipes[1]);              // resource(1)
			var_dump(feof($pipes[1]));        // true
			pclose( $pipes[1] );
			`,
			});

			expect(result.text).toEqual(
				[
					`string(13) "Hello World!\n"`,
					`string(13) "Hello Again!\n"`,
					`string(0) ""`,
					`resource(3) of type (stream)`,
					`bool(true)`,
					'',
				].join('\n')
			);
		});

		// This test fails on older PHP versions
		if (!['7.0', '7.1', '7.2', '7.3'].includes(phpVersion)) {
			it('Gives access to command and arguments when array type is used in proc_open', async () => {
				let command = '';
				let args: string[] = [];
				php.setSpawnHandler((cmd, argc) => {
					command = cmd;
					args = argc;
					return {
						stdout: {
							on: () => {},
						},
						stderr: {
							on: () => {},
						},
						stdin: {
							write: () => {},
						},
						on: (evt: string, callback: Function) => {
							if (evt === 'spawn') {
								callback();
							}
						},
						kill: () => {},
					} as any;
				});
				await php.run({
					code: `<?php

                    $command = [ 'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing' ];

                    $descriptorspec = [
                        0 => [ "pipe", "r" ],
                        1 => [ "pipe", "w" ],
                        2 => [ "pipe", "w" ]
                    ];

                    proc_open( $command, $descriptorspec, $pipes );`,
				});
				expect(command).toEqual('lorem');
				expect(args.toString()).toEqual(
					'ipsum,dolor,sit,amet,consectetur,adipiscing'
				);
			});
		}

		it('Uses the three descriptor specs', async () => {
			const result = await php.run({
				code: `<?php

				$command = "echo 'Hello World!'";

				$descriptorspec = [
					0 => [ "pipe", "r" ],
					1 => [ "pipe", "w" ],
					2 => [ "pipe", "w" ]
				];

				$res = proc_open( $command, $descriptorspec, $pipes );

				$stdout = stream_get_contents($pipes[1]);

				proc_close($res);

				echo $stdout;
			`,
			});
			expect(result.text).toEqual('Hello World!\n');
		});

		it('Uses only stdin and stdout descriptor specs', async () => {
			const result = await php.run({
				code: `<?php

                $command = "echo 'Hello World!'";

                $descriptorspec = [
                    0 => [ "pipe", "r" ],
                    1 => [ "pipe", "w" ]
                ];

                $res = proc_open( $command, $descriptorspec, $pipes );

                $stdout = stream_get_contents($pipes[1]);

                proc_close($res);

                echo $stdout;
            `,
			});
			expect(result.text).toEqual('Hello World!\n');
		});

		it('Uses only stdout and stderr descriptor specs', async () => {
			const result = await php.run({
				code: `<?php

                $command = "echo 'Hello World!'";

                $descriptorspec = [
                    1 => [ "pipe", "w" ],
                    2 => [ "pipe", "w" ]
                ];

                $res = proc_open( $command, $descriptorspec, $pipes );

                $stdout = stream_get_contents($pipes[1]);

                proc_close($res);

                echo $stdout;
            `,
			});
			expect(result.text).toEqual('Hello World!\n');
		});

		it('Calls proc_open two times in a row', async () => {
			const result = await php.run({
				code: `<?php

				$command = "echo 'First hello world!'";

				$descriptorspec = [
					1 => [ "pipe", "w" ],
					2 => [ "pipe", "w" ]
				];

				$res = proc_open( $command, $descriptorspec, $pipes );

				$stdout = stream_get_contents($pipes[1]);

				proc_close($res);

				echo $stdout;

				$command = "echo 'Second hello world!'";

				$descriptorspec = [
					1 => [ "pipe", "w" ],
					2 => [ "pipe", "w" ]
				];

				$res = proc_open( $command, $descriptorspec, $pipes );

				$stdout = stream_get_contents($pipes[1]);

				proc_close($res);

				echo $stdout;`,
			});
			expect(result.text).toEqual(
				'First hello world!\nSecond hello world!\n'
			);
		});
	});

	describe('Filesystem', () => {
		// Unit tests for the filesystem methods of the
		// PHP runtime.
		it('writeFile() should create a file when it does not exist', () => {
			php.writeFile(testFilePath, 'Hello World!');
			expect(php.fileExists(testFilePath)).toEqual(true);
		});

		it('writeFile() should throw a useful error when parent directory does not exist', () => {
			expect(() => {
				php.writeFile('/a/b/c/d/e/f', 'Hello World!');
			}).toThrowError(
				'Could not write to "/a/b/c/d/e/f": There is no such file or directory OR the parent directory does not exist.'
			);
		});

		it('writeFile() should throw a useful error when the specified path is a directory', () => {
			php.mkdir('/dir');
			expect(() => {
				php.writeFile('/dir', 'Hello World!');
			}).toThrowError(
				new Error(
					`Could not write to "/dir": There is a directory under that path.`
				)
			);
		});

		it('writeFile() should overwrite a file when it exists', () => {
			php.writeFile(testFilePath, 'Hello World!');
			php.writeFile(testFilePath, 'New contents');
			expect(php.readFileAsText(testFilePath)).toEqual('New contents');
		});

		it('readFileAsText() should read a file as text', () => {
			php.writeFile(testFilePath, 'Hello World!');
			expect(php.readFileAsText(testFilePath)).toEqual('Hello World!');
		});

		it('readFileAsBuffer() should read a file as buffer', () => {
			php.writeFile(testFilePath, 'Hello World!');
			expect(php.readFileAsBuffer(testFilePath)).toEqual(
				new TextEncoder().encode('Hello World!')
			);
		});

		it('unlink() should delete a file', () => {
			php.writeFile(testFilePath, 'Hello World!');
			expect(php.fileExists(testFilePath)).toEqual(true);
			php.unlink(testFilePath);
			expect(php.fileExists(testFilePath)).toEqual(false);
		});

		it('mv() should move a file', () => {
			php.mkdir(testDirPath);
			const file1 = testDirPath + '/1.txt';
			const file2 = testDirPath + '/2.txt';
			php.writeFile(file1, '1');
			php.mv(file1, file2);
			expect(php.fileExists(file1)).toEqual(false);
			expect(php.fileExists(file2)).toEqual(true);
			expect(php.readFileAsText(file2)).toEqual('1');
		});

		it('mv() should replace target file if it exists', () => {
			php.mkdir(testDirPath);
			const file1 = testDirPath + '/1.txt';
			const file2 = testDirPath + '/2.txt';
			php.writeFile(file1, '1');
			php.writeFile(file2, '2');
			php.mv(file1, file2);
			expect(php.fileExists(file1)).toEqual(false);
			expect(php.fileExists(file2)).toEqual(true);
			expect(php.readFileAsText(file2)).toEqual('1');
		});

		it('mv() should throw a useful error when source file does not exist', () => {
			php.mkdir(testDirPath);
			const file1 = testDirPath + '/1.txt';
			const file2 = testDirPath + '/2.txt';
			expect(() => {
				php.mv(file1, file2);
			}).toThrowError(
				`Could not move ${testDirPath}/1.txt to ${testDirPath}/2.txt: There is no such file or directory OR the parent directory does not exist.`
			);
		});

		it('mv() should throw a useful error when target directory does not exist', () => {
			php.mkdir(testDirPath);
			const file1 = testDirPath + '/1.txt';
			const file2 = testDirPath + '/nowhere/2.txt';
			php.writeFile(file1, '1');
			expect(() => {
				php.mv(file1, file2);
			}).toThrowError(
				`Could not move ${testDirPath}/1.txt to ${testDirPath}/nowhere/2.txt: There is no such file or directory OR the parent directory does not exist.`
			);
		});

		it('mv() from NODEFS to MEMFS should work', () => {
			mkdirSync(__dirname + '/test-data/mount-contents/a/b', {
				recursive: true,
			});
			writeFileSync(
				__dirname + '/test-data/mount-contents/a/b/test.txt',
				'contents'
			);
			php.mkdir('/nodefs');
			php.mount(
				'/nodefs',
				createNodeFsMountHandler(
					__dirname + '/test-data/mount-contents'
				)
			);
			php.mkdir('/nodefs/tmp-dir-for-mv-test');
			expect(
				existsSync(
					__dirname + '/test-data/mount-contents/tmp-dir-for-mv-test'
				)
			).toEqual(true);

			php.writeFile('/nodefs/tmp-dir-for-mv-test/test.txt', 'contents');
			php.mv('/nodefs/tmp-dir-for-mv-test', '/tmp/tmp-dir-for-mv-test');
			expect(
				existsSync(
					__dirname + '/test-data/mount-contents/tmp-dir-for-mv-test'
				)
			).toEqual(false);
			expect(php.fileExists('/nodefs/tmp-dir-for-mv-test')).toEqual(
				false
			);
			expect(php.fileExists('/tmp/tmp-dir-for-mv-test')).toEqual(true);
			expect(
				php.readFileAsText('/tmp/tmp-dir-for-mv-test/test.txt')
			).toEqual('contents');
		});

		it('mv() from MEMFS to NODEFS should work', () => {
			php.mkdir('/nodefs');
			php.mount(
				'/nodefs',
				createNodeFsMountHandler(
					__dirname + '/test-data/mount-contents'
				)
			);

			php.writeFile('/nodefs/tmp-file-for-mv-test.txt', 'contents');
			php.mv('/nodefs/tmp-file-for-mv-test.txt', '/tmp/test.txt');
			expect(php.fileExists('/nodefs/tmp-file-for-mv-test.txt')).toEqual(
				false
			);
			expect(php.fileExists('/tmp/test.txt')).toEqual(true);
			expect(php.readFileAsText('/tmp/test.txt')).toEqual('contents');
		});

		it('mkdir() should create a directory', () => {
			php.mkdir(testDirPath);
			expect(php.fileExists(testDirPath)).toEqual(true);
		});

		it('mkdir() should create all nested directories', () => {
			php.mkdir(testDirPath + '/nested/doubly/triply');
			expect(php.isDir(testDirPath + '/nested/doubly/triply')).toEqual(
				true
			);
		});

		it('unlink() should throw a useful error when parent directory does not exist', () => {
			expect(() => {
				php.unlink('/a/b/c/d/e/f');
			}).toThrowError(
				'Could not unlink "/a/b/c/d/e/f": There is no such file or directory OR the parent directory does not exist.'
			);
		});

		it('isDir() should correctly distinguish between a file and a directory', () => {
			php.mkdir(testDirPath);
			expect(php.fileExists(testDirPath)).toEqual(true);
			expect(php.isDir(testDirPath)).toEqual(true);

			php.writeFile(testFilePath, 'Hello World!');
			expect(php.fileExists(testFilePath)).toEqual(true);
			expect(php.isDir(testFilePath)).toEqual(false);
		});

		it('isDir() should correctly distinguish between symlinks to a file and a directory', () => {
			php.writeFile(testFilePath, 'Hello World!');
			php.symlink(testFilePath, '/test-file-link');
			php.mkdir(testDirPath);
			php.symlink(testDirPath, '/test-dir-link');

			expect(php.isDir('/test-file-link')).toEqual(false);
			expect(php.isDir('/test-dir-link')).toEqual(true);
		});

		it('isFile() should correctly distinguish between a file and a directory', () => {
			php.writeFile(testFilePath, 'Hello World!');
			expect(php.fileExists(testFilePath)).toEqual(true);
			expect(php.isFile(testFilePath)).toEqual(true);

			php.mkdir(testDirPath);
			expect(php.fileExists(testDirPath)).toEqual(true);
			expect(php.isFile(testDirPath)).toEqual(false);
		});

		it('isFile() should correctly distinguish between symlinks to a file and a directory', () => {
			php.writeFile(testFilePath, 'Hello World!');
			php.symlink(testFilePath, '/test-file-link');
			php.mkdir(testDirPath);
			php.symlink(testDirPath, '/test-dir-link');

			expect(php.isFile('/test-file-link')).toEqual(true);
			expect(php.isFile('/test-dir-link')).toEqual(false);
		});

		it('symlink() should create a symlink to a file', () => {
			const filePath = `${testDirPath}/test.txt`;
			const fileContent = 'link to me';
			const linkPath = `${testDirPath}/test-link`;

			php.mkdir(testDirPath);
			php.writeFile(filePath, fileContent);
			php.symlink(filePath, linkPath);

			expect(php.readFileAsText(linkPath)).toEqual(fileContent);
		});

		it('symlink() should create a symlink to a directory', () => {
			const testSubdirPath = `${testDirPath}/subdir`;
			const filePath = `${testSubdirPath}/test.txt`;
			const fileContent = 'Hello, World!';
			const linkPath = `${testDirPath}/test-link`;

			php.mkdir(testSubdirPath);
			php.writeFile(filePath, fileContent);
			php.symlink(testSubdirPath, linkPath);

			expect(php.readFileAsText(`${linkPath}/test.txt`)).toEqual(
				fileContent
			);
		});

		it('symlink() should create a symlink to a symlink', () => {
			const filePath = `${testDirPath}/test.txt`;
			const fileContent = 'link to me';
			const sourceLinkPath = `${testDirPath}/test-link`;
			const linkToLinkPath = `${testDirPath}/test-link-to-link`;

			php.mkdir(testDirPath);
			php.writeFile(filePath, fileContent);
			php.symlink(filePath, sourceLinkPath);
			php.symlink(sourceLinkPath, linkToLinkPath);

			expect(php.readFileAsText(linkToLinkPath)).toEqual(fileContent);
		});

		it('isSymlink() should return true for a path to a symlink', () => {
			const filePath = `${testDirPath}/test.txt`;
			const linkPath = `${testDirPath}/test-link`;

			php.mkdir(testDirPath);
			php.writeFile(filePath, '');
			php.symlink(filePath, linkPath);

			expect(php.isSymlink(linkPath)).toEqual(true);
		});

		it('isSymlink() should correctly distinguish between a symlink and a file', () => {
			const filePath = `${testDirPath}/test.txt`;

			php.mkdir(testDirPath);
			php.writeFile(filePath, '');

			expect(php.isSymlink(filePath)).toEqual(false);
		});

		it('isSymlink() should correctly distinguish between a symlink and a directory', () => {
			php.mkdir(testDirPath);
			expect(php.isSymlink(testDirPath)).toEqual(false);
		});

		it('readlink() should read symlink target', () => {
			php.mkdir(testDirPath);
			php.symlink(testDirPath, '/test-link');
			expect(php.readlink('/test-link')).toEqual(testDirPath);
		});

		it('readlink() should throw when reading non-existent paths, files, or directories', () => {
			expect(() => php.readlink('/non-existent')).toThrowError();

			php.writeFile(testFilePath, 'Hello World!');
			// confirm assumption that file exists
			expect(php.isFile(testFilePath)).toEqual(true);
			expect(() => php.readlink(testFilePath)).toThrowError();

			php.mkdir(testDirPath);
			// confirm assumption that dir exists
			expect(php.isDir(testDirPath)).toEqual(true);
			expect(() => php.readlink(testDirPath)).toThrowError();
		});

		it('realpath() should resolve symlink target', () => {
			php.mkdir(testDirPath);
			php.symlink(testDirPath, '/test-link');
			expect(php.realpath('/test-link')).toEqual(testDirPath);
		});

		it('realpath() should resolve a path containing a symlinked directory', () => {
			const testSubdirPath = `${testDirPath}/subdir`;
			const targetFilePath = `${testSubdirPath}/test.txt`;
			php.mkdir(testSubdirPath);
			php.writeFile(targetFilePath, 'Hello World!');

			const symlinkSubdirPath = `${testDirPath}/subdir-link`;
			php.symlink(testSubdirPath, symlinkSubdirPath);

			expect(php.realpath(`${symlinkSubdirPath}/test.txt`)).toEqual(
				targetFilePath
			);
		});

		it('realpath() should resolve a path containing a symlinked directory and a symlinked target', () => {
			php.mkdir(testDirPath);

			const testSubdirPath = `${testDirPath}/subdir`;
			const targetFilePath = `${testSubdirPath}/test.txt`;
			php.mkdir(testSubdirPath);
			php.writeFile(targetFilePath, 'Hello World!');

			const symlinkSubdirPath = `${testDirPath}/subdir-link`;
			php.symlink(testSubdirPath, symlinkSubdirPath);
			const symlinkTargetPath = `${symlinkSubdirPath}/test-link.txt`;
			php.symlink(targetFilePath, symlinkTargetPath);

			expect(php.realpath(symlinkTargetPath)).toEqual(targetFilePath);
		});

		it('realpath() should return the path itself if it does not contain a symlink', () => {
			php.mkdir(testDirPath);

			const filePath = `${testDirPath}/test.txt`;
			php.writeFile(filePath, 'Hello World!');

			expect(php.realpath(filePath)).toEqual(filePath);
		});

		it('realpath() should throw when unable to resolve path', () => {
			expect(() => php.realpath('/non-existent')).toThrowError();
		});

		it('listFiles() should return a list of files in a directory', () => {
			php.mkdir(testDirPath);
			php.writeFile(testDirPath + '/test.txt', 'Hello World!');
			php.writeFile(testDirPath + '/test2.txt', 'Hello World!');
			expect(php.listFiles(testDirPath)).toEqual([
				'test.txt',
				'test2.txt',
			]);
		});

		it('listFiles() option prependPath should prepend given path to all files returned', () => {
			php.mkdir(testDirPath);
			php.writeFile(testDirPath + '/test.txt', 'Hello World!');
			php.writeFile(testDirPath + '/test2.txt', 'Hello World!');
			expect(php.listFiles(testDirPath, { prependPath: true })).toEqual([
				testDirPath + '/test.txt',
				testDirPath + '/test2.txt',
			]);
		});
	});

	describe('Exit codes', () => {
		describe('Returns exit code 0', () => {
			const testsSnippets = {
				'on empty code': '',
				'on successful run': '<?php echo "Hello world!";',
				'on notice':
					'<?php trigger_error("This is a custom notice!", E_USER_NOTICE);',
				'on warning':
					'<?php trigger_error("This is a custom warning!", E_USER_WARNING);',
				'on deprecated error':
					'<?php trigger_error("This is a custom deprecation info!", E_USER_DEPRECATED);',
				'on a warning issued by an incorrect usage of PHP':
					'<?php echo $test; ',
				'on die()': '<?php die();',
				'on die("test")': '<?php die("Test");',
				'on exit()': '<?php exit();',
				'on exit(0)': '<?php exit(0);',
			};
			for (const [testName, testSnippet] of Object.entries(
				testsSnippets
			)) {
				// Run via `code`
				it(testName, async () => {
					const result = await php.run({
						code: testSnippet,
					});
					expect(result.exitCode).toEqual(0);
				});

				// Run via request handler
				it(testName, async () => {
					php.writeFile('/test.php', testSnippet);
					const result = await php.run({
						scriptPath: '/test.php',
					});
					expect(result.exitCode).toEqual(0);
				});
			}
		});
		describe('Returns exit code > 0', () => {
			const testsSnippets = {
				'syntax error': '<?php @$!;',
				'undefined function call': '<?php no_such_function();',
				'on fatal error':
					'<?php trigger_error("This is a custom fatal error!", E_USER_ERROR);',
				'on exit(1)': '<?php exit(1);',
				'on uncaught exception': '<?php throw new Exception();',
			};
			for (const [testName, testSnippet] of Object.entries(
				testsSnippets
			)) {
				// Run via `code`
				it(testName, async () => {
					const promise = php.run({
						code: testSnippet,
					});
					await expect(promise).rejects.toThrow();
				});

				// Run via the request handler
				it(testName, async () => {
					php.writeFile('/test.php', testSnippet);
					const promise = php.run({
						scriptPath: '/test.php',
					});
					await expect(promise).rejects.toThrow();
				});
			}
		});
		it('Returns the correct exit code on subsequent runs', async () => {
			const promise1 = php.run({
				code: '<?php throw new Exception();',
			});
			// expect(result1.exitCode).toBe(255);
			await expect(promise1).rejects.toThrow(
				'PHP.run() failed with exit code 255'
			);

			const result2 = await php.run({
				code: '<?php exit(0);',
			});
			expect(result2.exitCode).toBe(0);

			const promise3 = php.run({
				code: '<?php exit(1);',
			});
			await expect(promise3).rejects.toThrow(
				'PHP.run() failed with exit code 1'
			);
		});
		it('After failure, returns the correct exit code on subsequent runs', async () => {
			const promise1 = php.run({
				code: '<?php throw new Exception();',
			});
			// expect(result1.exitCode).toBe(255);
			await expect(promise1).rejects.toThrow(
				'PHP.run() failed with exit code 255'
			);

			const result2 = await php.run({
				code: '<?php ',
			});
			expect(result2.exitCode).toBe(0);

			const promise3 = php.run({
				code: '<?php exit(1);',
			});
			await expect(promise3).rejects.toThrow(
				'PHP.run() failed with exit code 1'
			);
		});
	});

	describe('Stdio', () => {
		it('should output strings (1)', async () => {
			expect(
				await php.run({ code: '<?php echo "Hello world!";' })
			).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new TextEncoder().encode('Hello world!'),
				errors: '',
				exitCode: 0,
			});
		});
		it('should output strings (2) ', async () => {
			expect(
				await php.run({ code: '<?php echo "Hello world!\nI am PHP";' })
			).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new TextEncoder().encode('Hello world!\nI am PHP'),
				errors: '',
				exitCode: 0,
			});
		});
		it('should output bytes ', async () => {
			const results = await php.run({
				code: '<?php echo chr(1).chr(0).chr(1).chr(0).chr(2); ',
			});
			expect(results).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new Uint8Array([1, 0, 1, 0, 2]),
				errors: '',
				exitCode: 0,
			});
		});
		it('should output strings when .run() is called twice', async () => {
			expect(
				await php.run({ code: '<?php echo "Hello world!";' })
			).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new TextEncoder().encode('Hello world!'),
				errors: '',
				exitCode: 0,
			});

			expect(
				await php.run({ code: '<?php echo "Ehlo world!";' })
			).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new TextEncoder().encode('Ehlo world!'),
				errors: '',
				exitCode: 0,
			});
		});
		it('should capture error data from stderr', async () => {
			const code = `<?php
			$stdErr = fopen('php://stderr', 'w');
			fwrite($stdErr, "Hello from stderr!");
			`;
			expect(await php.run({ code })).toEqual({
				headers: expect.any(Object),
				httpStatusCode: 200,
				bytes: new TextEncoder().encode(''),
				errors: 'Hello from stderr!',
				exitCode: 0,
			});
		});
		it('should provide response text through .text', async () => {
			const code = `<?php
			echo "Hello world!";
			`;
			const response = await php.run({ code });
			expect(response.text).toEqual('Hello world!');
		});
		it('should provide response JSON through .json', async () => {
			const code = `<?php
			echo json_encode(["hello" => "world"]);
			`;
			const response = await php.run({ code });
			expect(response.json).toEqual({ hello: 'world' });
		});
	});

	describe('Interface', () => {
		it('run() should throw an error when neither `code` nor `scriptFile` is provided', async () => {
			expect(() => php.run({})).rejects.toThrowError(TypeError);
		});
	});

	describe('Startup sequence – basics', () => {
		/**
		 * This test ensures that the PHP runtime can be loaded twice.
		 *
		 * It protects from a regression that happened in the past
		 * after making the Emscripten module's main function the
		 * default export. Turns out, the generated Emscripten code
		 * replaces the default export with an instantiated module upon
		 * the first call.
		 */
		it('Should spawn two PHP runtimes', async () => {
			const phpLoaderModule1 = await getPHPLoaderModule(
				phpVersion as any
			);
			const runtimeId1 = await loadPHPRuntime(phpLoaderModule1);

			const phpLoaderModule2 = await getPHPLoaderModule(
				phpVersion as any
			);
			const runtimeId2 = await loadPHPRuntime(phpLoaderModule2);

			expect(runtimeId1).not.toEqual(runtimeId2);
		});
	});

	describe('Startup sequence', () => {
		const testScriptPath = '/test.php';
		afterEach(() => {
			if (existsSync(testScriptPath)) {
				rmSync(testScriptPath);
			}
		});

		/**
		 * Issue https://github.com/WordPress/wordpress-playground/issues/169
		 */
		it('Should work with long POST body', async () => {
			php.writeFile(testScriptPath, '<?php echo "Hello world!"; ?>');
			const body = new Uint8Array(
				readFileSync(
					new URL('./test-data/long-post-body.txt', import.meta.url)
						.pathname
				)
			);
			// 0x4000 is SAPI_POST_BLOCK_SIZE
			expect(body.length).toBeGreaterThan(0x4000);
			await expect(
				php.run({
					code: 'echo "A";',
					relativeUri: '/test.php?a=b',
					body,
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
				})
			).resolves.not.toThrow();
		});

		it('Should run a script when no code snippet is provided', async () => {
			php.writeFile(testScriptPath, `<?php echo "Hello world!"; ?>\n`);
			const response = await php.run({
				scriptPath: testScriptPath,
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual('Hello world!');
		});

		it('Should run a code snippet when provided, even if scriptPath is set', async () => {
			php.writeFile(testScriptPath, '<?php echo "Hello world!"; ?>');
			const response = await php.run({
				scriptPath: testScriptPath,
				code: '<?php echo "Hello from a code snippet!";',
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual('Hello from a code snippet!');
		});

		it('Should have access to raw request data via the php://input stream', async () => {
			const response = await php.run({
				method: 'POST',
				body: new TextEncoder().encode('{"foo": "bar"}'),
				code: `<?php echo file_get_contents('php://input');`,
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual('{"foo": "bar"}');
		});

		it('Can accept a request body with a size of 1MB without crashing', async () => {
			php.writeFile('/php/index.php', `<?php echo 'Hello World';`);
			const response = await php.run({
				scriptPath: '/php/index.php',
				body: new TextEncoder().encode('#'.repeat(1024 * 1024)),
			});
			expect(response.httpStatusCode).toEqual(200);
			expect(response.text).toEqual('Hello World');
			expect(response.errors).toEqual('');
			expect(response.exitCode).toEqual(0);
		});

		it('Can accept a request body with a size of ~512MB without crashing', async () => {
			php.writeFile('/php/index.php', `<?php echo 'Hello World';`);
			const response = await php.run({
				scriptPath: '/php/index.php',
				body: new TextEncoder().encode(
					'#'.repeat(1024 * 1024 * 512 + -24)
				),
			});
			expect(response.httpStatusCode).toEqual(200);
			expect(response.text).toEqual('Hello World');
			expect(response.errors).toEqual('');
			expect(response.exitCode).toEqual(0);
		});

		it('Frees up the heap memory after handling a request body with a size of ~512MB', async () => {
			const estimateFreeMemory = () =>
				php[__private__dont__use].HEAPU32.reduce(
					(count: number, byte: number) =>
						byte === 0 ? count + 1 : count,
					0
				) / 4;

			// The initial request will allocate a lot of memory so let's get that
			// out of the way before we start measuring.
			php.writeFile('/php/index.php', `<?php echo 'Hello World';`);
			await php.run({ scriptPath: '/php/index.php' });

			// Overwrite the memory-related functions to:
			// * Capture the body HEAP pointer
			// * Capture the encoded body length
			// * Overwrite the HEAP memory with zeros after freeing the
			//   body pointer.
			// This will allow us to estimate the amount of the memory that
			// was not freed after the request.
			const body = '#'.repeat(1024 * 1024 * 512 - 24);

			let contentLength = 0;
			const _lengthBytesUTF8 = php[__private__dont__use].lengthBytesUTF8;
			php[__private__dont__use].lengthBytesUTF8 = function (
				data: string
			) {
				const retval = _lengthBytesUTF8.call(this, data);
				if (data === body) {
					contentLength = retval;
				}
				return retval;
			};

			let bodyPtr = 0;
			const malloc = php[__private__dont__use].malloc;
			php[__private__dont__use].malloc = function newMalloc(
				size: number,
				...args: any
			) {
				const retval = malloc.call(this, size, ...args);
				if (size === contentLength + 1) {
					bodyPtr = retval;
				}
				return retval;
			};

			const free = php[__private__dont__use].free;
			php[__private__dont__use].free = function (
				addr: number,
				...args: any
			) {
				const retval = free.call(this, name, ...args);
				if (addr === bodyPtr) {
					php[__private__dont__use].HEAPU8.fill(
						0,
						addr,
						addr + contentLength
					);
				}
				return retval;
			};

			const getFreeMemoryBefore = estimateFreeMemory();

			php.writeFile('/php/index.php', `<?php echo 'Hello World';`);
			await php.run({
				scriptPath: '/php/index.php',
				body,
			});

			const getFreeMemoryAfter = estimateFreeMemory();

			// PHP has a memory leak, so we can't expect the memory to be exactly
			// the same as before the request, but it should be close to the original
			// value. Let's abitrarily pick 100KB as the "close enough" threshold.
			//
			// @see https://github.com/WordPress/wordpress-playground/pull/990 for more
			//      details on the memory leak in PHP.
			const memoryDifference = getFreeMemoryBefore - getFreeMemoryAfter;
			expect(memoryDifference).toBeLessThan(100 * 1024);
		});

		it('Should set $_SERVER entries for provided headers', async () => {
			const response = await php.run({
				code: `<?php echo json_encode($_SERVER);`,
				method: 'POST',
				body: new TextEncoder().encode('foo=bar'),
				headers: {
					'Content-Type': 'text/plain',
					'Content-Length': '15',
					'User-agent': 'my-user-agent',
					'custom-header': 'custom value',
					'x-test': 'x custom value',
				},
			});
			const json = response.json;
			expect(json).toHaveProperty('HTTP_USER_AGENT', 'my-user-agent');
			expect(json).toHaveProperty('HTTP_CUSTOM_HEADER', 'custom value');
			expect(json).toHaveProperty('HTTP_X_TEST', 'x custom value');
			/*
			 * The following headers should be set without the HTTP_ prefix,
			 * as PHP follows the following convention:
			 * https://www.ietf.org/rfc/rfc3875
			 */
			expect(json).toHaveProperty('CONTENT_TYPE', 'text/plain');
			expect(json).toHaveProperty('CONTENT_LENGTH', '15');
		});

		it('Should have appropriate SCRIPT_NAME, SCRIPT_FILENAME and PHP_SELF entries in $_SERVER when serving request', async () => {
			php.writeFile(
				'/php/index.php',
				`<?php echo json_encode($_SERVER);`
			);

			const response = await php.run({
				relativeUri: '/',
				scriptPath: '/php/index.php',
				method: 'GET',
				$_SERVER: {
					DOCUMENT_ROOT: '/php',
				},
			});

			const json = response.json;

			expect(json).toHaveProperty('REQUEST_URI', '/');
			expect(json).toHaveProperty('SCRIPT_NAME', '/index.php');
			expect(json).toHaveProperty('SCRIPT_FILENAME', '/php/index.php');
			expect(json).toHaveProperty('PHP_SELF', '/index.php');
		});

		it('Should have appropriate SCRIPT_NAME, SCRIPT_FILENAME and PHP_SELF entries in $_SERVER when running PHP code', async () => {
			const response = await php.run({
				code: '<?php echo json_encode($_SERVER);',
				method: 'GET',
			});

			const json = response.json;

			expect(json).toHaveProperty('REQUEST_URI', '');
			expect(json).toHaveProperty('SCRIPT_NAME', '');
			expect(json).toHaveProperty('SCRIPT_FILENAME', '');
			expect(json).toHaveProperty('PHP_SELF', '');
		});

		it('Should have appropriate SCRIPT_NAME, SCRIPT_FILENAME and PHP_SELF entries in $_SERVER when serving request from a test file in a subdirectory', async () => {
			php.mkdir('/php/subdirectory');

			php.writeFile(
				`/php/subdirectory/test.php`,
				`<?php echo json_encode($_SERVER);`
			);

			const response = await php.run({
				scriptPath: '/php/subdirectory/test.php',
				relativeUri: '/subdirectory/test.php',
				method: 'GET',
				$_SERVER: {
					DOCUMENT_ROOT: '/php',
				},
			});

			const json = response.json;

			expect(json).toHaveProperty(
				'REQUEST_URI',
				'/subdirectory/test.php'
			);
			expect(json).toHaveProperty(
				'SCRIPT_NAME',
				'/subdirectory/test.php'
			);
			expect(json).toHaveProperty(
				'SCRIPT_FILENAME',
				'/php/subdirectory/test.php'
			);
			expect(json).toHaveProperty('PHP_SELF', '/subdirectory/test.php');
		});

		it('Should expose urlencoded POST data in $_POST', async () => {
			const response = await php.run({
				code: `<?php echo json_encode($_POST);`,
				method: 'POST',
				body: new TextEncoder().encode('foo=bar'),
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual('{"foo":"bar"}');
		});

		it('Should expose urlencoded POST arrays in $_POST', async () => {
			const response = await php.run({
				code: `<?php echo json_encode($_POST);`,
				method: 'POST',
				body: new TextEncoder().encode(
					'foo[]=bar1&foo[]=bar2&indexed[key]=value'
				),
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual(
				'{"foo":["bar1","bar2"],"indexed":{"key":"value"}}'
			);
		});

		it('Should expose multipart POST data in $_POST', async () => {
			const response = await php.run({
				code: `<?php echo json_encode($_POST);`,
				method: 'POST',
				body: new TextEncoder().encode(`--boundary
Content-Disposition: form-data; name="foo"

bar`),
				headers: {
					'Content-Type': 'multipart/form-data; boundary=boundary',
				},
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(bodyText).toEqual('{"foo":"bar"}');
		});

		it('Should expose multipart POST files in $_FILES', async () => {
			const response = await php.run({
				code: `<?php echo json_encode(array(
						"files" => $_FILES,
						"is_uploaded" => is_uploaded_file($_FILES["myFile"]["tmp_name"])
					));`,
				method: 'POST',
				body: new TextEncoder().encode(`--boundary
Content-Disposition: form-data; name="myFile"; filename="text.txt"
Content-Type: text/plain

bar
--boundary--`),
				headers: {
					'Content-Type': 'multipart/form-data; boundary=boundary',
				},
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			const expectedResult = {
				files: {
					myFile: {
						name: 'text.txt',
						type: 'text/plain',
						tmp_name: expect.any(String),
						error: 0,
						size: 3,
					},
				},
				is_uploaded: true,
			};
			if (Number(phpVersion) > 8) {
				(expectedResult.files.myFile as any).full_path = 'text.txt';
			}
			expect(JSON.parse(bodyText)).toEqual(expectedResult);
		});

		it('Should provide the correct $_SERVER information', async () => {
			php.writeFile(
				testScriptPath,
				'<?php echo json_encode($_SERVER); ?>'
			);
			const response = await php.run({
				scriptPath: testScriptPath,
				relativeUri: '/test.php?a=b',
				method: 'POST',
				body: new TextEncoder().encode(`--boundary
Content-Disposition: form-data; name="myFile1"; filename="from_body.txt"
Content-Type: text/plain

bar1
--boundary--`),
				headers: {
					'Content-Type': 'multipart/form-data; boundary=boundary',
					Host: 'https://example.com:1235',
					'X-is-ajax': 'true',
				},
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			const $_SERVER = JSON.parse(bodyText);
			expect($_SERVER).toHaveProperty('REQUEST_URI', '/test.php?a=b');
			expect($_SERVER).toHaveProperty('REQUEST_METHOD', 'POST');
			expect($_SERVER).toHaveProperty(
				'CONTENT_TYPE',
				'multipart/form-data; boundary=boundary'
			);
			expect($_SERVER).toHaveProperty(
				'HTTP_HOST',
				'https://example.com:1235'
			);
			expect($_SERVER).toHaveProperty(
				'SERVER_NAME',
				'https://example.com:1235'
			);
			expect($_SERVER).toHaveProperty('HTTP_X_IS_AJAX', 'true');
			expect($_SERVER).toHaveProperty('SERVER_PORT', '1235');
		});
	});

	/**
	 * libsqlite3 path needs to be explicitly provided in Dockerfile
	 * for PHP < 7.4 – let's make sure it works
	 */
	describe('PDO SQLite support', () => {
		it('Should be able to create a database', async () => {
			const response = await php.run({
				code: `<?php
					$db = new PDO('sqlite::memory:');
					$db->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
					$db->exec('INSERT INTO test (name) VALUES ("This is a test")');
					$result = $db->query('SELECT name FROM test');
					$rows = $result->fetchAll(PDO::FETCH_COLUMN);
					echo json_encode($rows);
				?>`,
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(JSON.parse(bodyText)).toEqual(['This is a test']);
		});

		it('Should support modern libsqlite (ON CONFLICT)', async () => {
			const response = await php.run({
				code: `<?php
					$db = new PDO('sqlite::memory:');
					$db->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
					$db->exec('CREATE UNIQUE INDEX test_name ON test (name)');
					$db->exec('INSERT INTO test (name) VALUES ("This is a test")');
					$db->exec('INSERT INTO test (name) VALUES ("This is a test") ON CONFLICT DO NOTHING');
					$result = $db->query('SELECT name FROM test');
					$rows = $result->fetchAll(PDO::FETCH_COLUMN);
					echo json_encode($rows);
				?>`,
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(JSON.parse(bodyText)).toEqual(['This is a test']);
		});
	});

	/**
	 * hash extension needs to be explicitly enabled in Dockerfile
	 * for PHP < 7.3 – let's make sure it works
	 */
	describe('Hash extension support', () => {
		it('Should be able to hash a string', async () => {
			const response = await php.run({
				code: `<?php
					echo json_encode([
						'md5' => md5('test'),
						'sha1' => sha1('test'),
						'hash' => hash('sha256', 'test'),
					]);
				?>`,
			});
			const bodyText = new TextDecoder().decode(response.bytes);
			expect(JSON.parse(bodyText)).toEqual({
				md5: '098f6bcd4621d373cade4e832627b4f6',
				sha1: 'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3',
				hash: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
			});
		});
	});

	/**
	 * mbregex support
	 */
	describe('mbregex extension support', () => {
		it('Should be able to use mb_regex_encoding functions', async () => {
			const promise = php.run({
				code: `<?php
					mb_regex_encoding('UTF-8');
				?>`,
			});
			// We don't support mbregex in PHP 7.0
			if (phpVersion === '7.0') {
				await expect(promise).rejects.toThrow(
					'Call to undefined function mb_regex_encoding'
				);
			} else {
				const response = await promise;
				expect(response.errors).toBe('');
			}
		});
	});

	/**
	 * fileinfo support
	 */
	describe('fileinfo extension support', () => {
		it('Should be able to use finfo_file', async () => {
			await php.writeFile('/test.php', '<?php echo "Hello world!";');
			const response = await php.run({
				code: `<?php
					$finfo = new finfo(FILEINFO_MIME_TYPE);
					echo $finfo->file('/test.php');
				?>`,
			});
			expect(response.text).toEqual('text/x-php');
		});
	});

	describe('onMessage', () => {
		it('should pass messages to JS', async () => {
			let messageReceived = '';
			php.onMessage((message) => {
				messageReceived = message;
			});
			const out = await php.run({
				code: `<?php
				post_message_to_js('world');
				`,
			});
			expect(out.errors).toBe('');
			expect(messageReceived).toBe('world');
		});

		it('should return sync messages from JS', async () => {
			php.onMessage(async (message) => message + '!');
			const out = await php.run({
				code: `<?php echo post_message_to_js('a');`,
			});
			expect(out.errors).toBe('');
			expect(out.text).toBe('a!');
		});

		it('should return async messages from JS', async () => {
			php.onMessage(async (message) => {
				// Simulate getting data asynchronously.
				return await new Promise<string>((resolve) =>
					setTimeout(() => resolve(message + '!'), 100)
				);
			});
			const out = await php.run({
				code: `<?php echo post_message_to_js('a');`,
			});
			expect(out.errors).toBe('');
			expect(out.text).toBe('a!');
		});

		it('should return null when JS message handler throws an error', async () => {
			php.onMessage(async () => {
				// Simulate getting data asynchronously.
				return await new Promise<string>((resolve, reject) =>
					setTimeout(() => reject('Failure!'), 100)
				);
			});
			const out = await php.run({
				code: `<?php var_dump(post_message_to_js('a'));`,
			});
			expect(out.errors).toBe('');
			expect(out.text).toBe('NULL\n');
		});
	});

	describe('CLI', () => {
		let consoleLogMock: any;
		let consoleErrorMock: any;
		beforeEach(() => {
			consoleLogMock = vi
				.spyOn(console, 'log')
				.mockImplementation(() => {});
			consoleErrorMock = vi
				.spyOn(console, 'error')
				.mockImplementation(() => {});
		});

		afterAll(() => {
			consoleLogMock.mockReset();
			consoleErrorMock.mockReset();
		});
		it('should not log an error message on exit status 0', async () => {
			await php.cli(['php', '-r', '$tmp = "Hello";']);
			expect(consoleLogMock).not.toHaveBeenCalled();
			expect(consoleErrorMock).not.toHaveBeenCalled();
		});
	});

	describe('Response parsing', () => {
		it('should encode response headers', async () => {
			const out = await php.run({
				code: `<?php header('Location: /(?P<id>[\\d]+)');`,
			});
			expect(out.headers['location'][0]).toEqual('/(?P<id>[\\d]+)');
		});
	});
});

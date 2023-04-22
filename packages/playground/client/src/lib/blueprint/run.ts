import { ProgressTracker } from '@php-wasm/progress';
import { Semaphore } from '@php-wasm/util';
import { UniversalPHP } from '@php-wasm/web';
import {
	activatePlugin,
	Blueprint,
	installPlugin,
	installTheme,
	login,
	PlaygroundClient,
	replaceSite,
	setSiteOptions,
	submitImporterForm,
	updateUserMeta,
} from '../..';
import { zipNameToHumanName } from '../common';
import { unzip } from '../import-export';
import { compileBlueprint, CompiledStep } from './compile';
import { Resource } from './resources';

export async function runBlueprint(
	playground: PlaygroundClient,
	blueprint: Blueprint,
	progress: ProgressTracker
) {
	const parsed = compileBlueprint(playground, blueprint, {
		progress,
		semaphore: new Semaphore({ concurrency: 3 }),
	});

	progress.setCaption('Preparing WordPress');

	// Start fetching resources early
	for (const { resource } of parsed.resources) {
		resource.resolve();
	}

	// Run all parsed steps
	for (const step of parsed.steps) {
		await runBlueprintStep(playground, step);
	}

	await playground.goTo(parsed.landingPage);
}

async function runBlueprintStep(
	playground: UniversalPHP,
	step: CompiledStep
) {
	step.progress.fillSlowly();

	const args = step.args;
	switch (args.step) {
		case 'installPlugin': {
			const name = zipNameToHumanName(args.pluginZipFile.name);
			step.progress.setCaption(`Installing the ${name} plugin`);
			try {
				await installPlugin(
					playground,
					await args.pluginZipFile.resolve(),
					args.options
				);
			} catch (error) {
				console.error(
					`Proceeding without the ${name} plugin. Could not install it in wp-admin. ` +
						`The original error was: ${error}`
				);
				console.error(error);
			}
			break;
		}
		case 'installTheme': {
			const name = zipNameToHumanName(args.themeZipFile.name);
			step.progress.setCaption(`Installing the ${name} theme`);
			try {
				await installTheme(
					playground,
					await args.themeZipFile.resolve(),
					args.options
				);
			} catch (error) {
				console.error(
					`Proceeding without the ${name} theme. Could not install it in wp-admin. ` +
						`The original error was: ${error}`
				);
				console.error(error);
			}
			break;
		}
		case 'login':
			step.progress.setCaption(`Logging in as ${args.username}`);
			await login(playground, args.username, args.password);
			break;
		case 'activatePlugin':
			step.progress.setCaption(`Activating ${args.plugin}`);
			await activatePlugin(playground, args.plugin);
			break;
		case 'replaceSite':
			await replaceSite(playground, await args.fullSiteZip.resolve());
			break;
		case 'rm':
			await playground.unlink(args.path);
			break;
		case 'rmdir':
			await playground.rmdir(args.path);
			break;
		case 'cp':
			await playground.writeFile(
				args.toPath,
				await playground.readFileAsBuffer(args.fromPath)
			);
			break;
		case 'mv':
			await playground.mv(args.fromPath, args.toPath);
			break;
		case 'mkdir':
			await playground.mkdirTree(args.path);
			break;
		case 'importFile':
			await submitImporterForm(playground, await args.file.resolve());
			break;
		case 'setPhpIniEntry':
			await playground.setPhpIniEntry(args.key, args.value);
			break;
		case 'setSiteOptions':
			await setSiteOptions(playground, args.options);
			break;
		case 'updateUserMeta':
			await updateUserMeta(playground, args.meta, args.userId);
			break;
		case 'request':
			await playground.request(args.request);
			break;
		case 'runPHP': {
			await playground.run({
				code: args.code,
			});
			break;
		}
		case 'runPHPWithOptions':
			await playground.run(args.options);
			break;
		case 'writeFile':
			await playground.writeFile(
				args.path,
				args.data instanceof Resource
					? await fileToUint8Array(await args.data.resolve())
					: args.data
			);
			break;
		case 'unzip':
			await unzip(playground, args.zipPath, args.extractToPath);
			break;
		default:
			throw new Error(`Invalid step: ${step.args.step}`);
	}
	step.progress.finish();
}

function fileToUint8Array(file: File) {
	return new Promise<Uint8Array>((resolve, reject) => {
		const reader = new FileReader();
		reader.onload = () => {
			resolve(new Uint8Array(reader.result as ArrayBuffer));
		};
		reader.onerror = reject;
		reader.readAsArrayBuffer(file);
	});
}

// @ts-nocheck -- This is a minimal conversion from JS to TS for build purposes.
// @TODO: Make this TS checked
import { startPlaygroundWeb } from '@wp-playground/client';
// eslint-disable-next-line @nx/enforce-module-boundaries
import schema from '../../blueprints/public/blueprint-schema.json';
// @ts-ignore
import { corsProxyUrl } from 'virtual:cors-proxy-url';

const deref = (obj, root) => {
	if (!obj || typeof obj !== 'object' || !('$ref' in obj)) {
		return obj;
	}

	const path = obj['$ref'].substr(2).split('/');
	let node = root;

	for (const p of path) {
		if (!(p in node)) {
			throw new Error(`Invalid reference: "${obj['$ref']}"`);
		}
		node = node[p];
	}

	return { ...obj, ...node };
};

const reader = Symbol('reader');

const getSchemaReader = (schema, root = null) => {
	if (schema[reader]) {
		return schema[reader];
	}

	if (!root) {
		root = schema;
	}

	const proxy = new Proxy(schema, {
		get: (target, key, receiver) => {
			const val = Reflect.get(target, key, receiver);
			if (val && typeof val === 'object') {
				return getSchemaReader(deref(val, root), root);
			}
			return val;
		},
	});

	schema[reader] = proxy;

	return proxy;
};

const getPrevKeys = (editor, { column, row }) => {
	const content = editor.getValue();
	const lines = content.split('\n');
	const line = String(lines[row]);
	const colon = line.indexOf(':');

	const path = [];

	if (colon > -1 && column > colon) {
		const openQuote = line.indexOf('"');
		const closeQuote = line.indexOf('"', 1 + openQuote);
		path.push(line.substring(1 + openQuote, closeQuote));
	}

	let indent = 0;

	while (line[indent] === ' ' || line[indent] === '\t') {
		indent++;
	}

	let checkRow = -1 + row;

	while (checkRow >= 0) {
		const openQuote = lines[checkRow].indexOf('"');
		const closeQuote = lines[checkRow].indexOf('"', 1 + openQuote);
		if (openQuote > -1 && openQuote < indent) {
			path.push(lines[checkRow].substring(1 + openQuote, closeQuote));
			indent = openQuote;
		}
		checkRow--;
	}

	return path;
};

const getLastOfType = (editor, type, { column, row }, skip = 0) => {
	const content = editor.getValue();
	const lines = content.split('\n');

	let checkRow = -1 + row;

	while (checkRow >= 0) {
		const openBracket = lines[checkRow].indexOf('{');

		if (openBracket > -1) {
			if (--skip < 0) {
				return null;
			}
			checkRow--;
			continue;
		}

		let indent = 0;

		while (
			lines[checkRow][indent] === ' ' ||
			lines[checkRow][indent] === '\t'
		) {
			indent++;
		}

		const openQuote = lines[checkRow].indexOf('"');
		const closeQuote = lines[checkRow].indexOf('"', 1 + openQuote);
		const openVQuote = lines[checkRow].indexOf('"', 1 + closeQuote);
		const closeVQuote = lines[checkRow].indexOf('"', 1 + openVQuote);

		if (openQuote > -1 && openQuote === indent) {
			const checkType = lines[checkRow].substring(
				1 + openQuote,
				closeQuote
			);
			if (type === checkType) {
				return lines[checkRow].substring(1 + openVQuote, closeVQuote);
			}
		}

		checkRow--;
	}

	return null;
};

const getPrevSiblings = (editor, { column, row }) => {
	const content = editor.getValue();
	const lines = content.split('\n');

	let checkRow = -1 + row;
	let indent = 0;

	while (lines[row][indent] === ' ' || lines[row][indent] === '\t') {
		indent++;
	}

	const siblings = [];

	while (checkRow >= 0) {
		const openBracket = lines[checkRow].indexOf('{');

		if (openBracket > -1 && openBracket < indent) {
			break;
		}

		const openQuote = lines[checkRow].indexOf('"');
		const closeQuote = lines[checkRow].indexOf('"', 1 + openQuote);

		if (openQuote > -1 && openQuote === indent) {
			siblings.push(lines[checkRow].substring(1 + openQuote, closeQuote));
		}

		checkRow--;
	}

	return siblings;
};

const getStepProperties = async (stepType) => {
	const reader = getSchemaReader(schema);
	return reader.definitions.StepDefinition.oneOf
		.filter((s) => s.properties.step['const'] === stepType)
		.map((s) => s.properties)
		.flat()
		.pop();
};

const completeStepProperty = async (stepType, prefix) => {
	return schema.definitions.StepDefinition.oneOf
		.filter((s) => s.properties.step['const'] === stepType)
		.map((s) => Object.keys(s.properties))
		.flat()
		.filter((s) => s.substr(0, prefix.length) === prefix)
		.filter((s) => !['step', 'progress'].includes(s));
};

const getStepSubProperties = async (stepType, resType, property) => {
	const reader = getSchemaReader(schema);
	return reader.definitions.StepDefinition.oneOf
		.filter((s) => s.properties.step['const'] === stepType)
		.map((s) => {
			return s.properties[property].anyOf;
		})
		.flat()
		.filter((s) => !resType || s.properties.resource.const === resType)
		.map((s) => s.properties)
		.flat()
		.pop();
};

const completeStepSubProperty = async (
	stepType,
	resType,
	property,
	subKey,
	prefix
) => {
	if (!resType && !subKey) {
		return ['resource'];
	}
	const reader = getSchemaReader(schema);
	return reader.definitions.StepDefinition.oneOf
		.filter((s) => s.properties.step['const'] === stepType)
		.map((s) => {
			return s.properties[property].anyOf;
		})
		.flat()
		.filter((s) => !resType || s.properties.resource.const === resType)
		.map((s) => {
			if (subKey === null) {
				return Object.keys(s.properties);
			}
			return s.properties.resource.const;
		})
		.flat()
		.filter((s) => !['resource'].includes(s));
};

const completeStep = async (prefix) => {
	const reader = getSchemaReader(schema);
	return reader.definitions.StepDefinition.oneOf
		.map((s) => s.properties.step['const'])
		.filter((s) => s.substr(0, prefix.length) === prefix);
};

const completePhpVersion = async (prefix) => {
	const reader = getSchemaReader(schema);
	return reader.definitions.SupportedPHPVersion.enum.filter(
		(s) => s.substr(0, prefix.length) === prefix
	);
};

const completeRootKey = async (prefix) => {
	const reader = getSchemaReader(schema);
	return Object.keys(reader.definitions.Blueprint.properties).filter(
		(s) => s[0] !== '$' && s.substr(0, prefix.length) === prefix
	);
};

const completeFeature = async (prefix) => {
	const reader = getSchemaReader(schema);
	return Object.keys(
		reader.definitions.Blueprint.properties.features.properties
	).filter((s) => s[0] !== '$' && s.substr(0, prefix.length) === prefix);
};

let debounce = null;

const getCompletions = async (editor, session, pos, prefix, callback) => {
	const list = [];
	const prevKey = getPrevKeys(editor, pos);

	const content = editor.getValue();
	const lines = content.split('\n');
	const line = String(lines[pos.row]);
	const colon = line.indexOf(':');

	const { row, column } = pos;

	const qA =
		!lines[row][-1 + column] || lines[row][-1 + column] === ' ' ? '"' : '';
	const qB = !lines[row][column] || lines[row][column] === ' ' ? '"' : '';

	if (
		prevKey.length === 3 &&
		prefix.length >= 3 &&
		(prevKey.join('<') === 'slug<pluginZipFile<steps' ||
			prevKey.join('<') === 'slug<pluginData<steps')
	) {
		const wpParams = new URLSearchParams();
		wpParams.set('action', 'query_plugins');
		wpParams.set('request[page]', '1');
		wpParams.set('request[per_page]', '200');
		wpParams.set('request[locale]', 'en_US');
		wpParams.set('request[search]', prefix);
		wpParams.set('request[wp_version]', '6.4');
		const proxyParams = new URLSearchParams();

		proxyParams.set(
			'url',
			`http://api.wordpress.org/plugins/info/1.2/?${wpParams}`
		);

		if (debounce) {
			clearTimeout(debounce);
			debounce = null;
		}

		document.body.setAttribute('data-loading', true);

		debounce = setTimeout(async () => {
			try {
				const res = await fetch(
					`https://playground.wordpress.net/plugin-proxy.php?${proxyParams}`
				);
				const json = await res.json();
				json?.plugins.forEach((p) => {
					const doc = new DOMParser().parseFromString(
						p.name,
						'text/html'
					);
					const meta = doc.documentElement.textContent;
					callback(null, [
						{
							name: p.slug,
							value: qA + p.slug + qB,
							score: 1,
							meta,
						},
					]);
				});
			} finally {
				document.body.setAttribute('data-loading', false);
			}
		}, 250);
	}

	if (
		prevKey.length === 3 &&
		prefix.length >= 3 &&
		(prevKey.join('<') === 'slug<themeData<steps' ||
			prevKey.join('<') === 'slug<themeZipFile<steps')
	) {
		const wpParams = new URLSearchParams();
		wpParams.set('action', 'query_themes');
		wpParams.set('request[page]', '1');
		wpParams.set('request[per_page]', '200');
		wpParams.set('request[locale]', 'en_US');
		wpParams.set('request[search]', prefix);
		wpParams.set('request[wp_version]', '6.4');
		const proxyParams = new URLSearchParams();
		proxyParams.set(
			'url',
			`http://api.wordpress.org/themes/info/1.2/?${wpParams}`
		);

		if (debounce) {
			clearTimeout(debounce);
			debounce = null;
		}

		document.body.setAttribute('data-loading', true);

		debounce = setTimeout(async () => {
			try {
				const res = await fetch(
					`https://playground.wordpress.net/plugin-proxy.php?${proxyParams}`
				);
				const json = await res.json();
				json?.themes.forEach((p) => {
					const doc = new DOMParser().parseFromString(
						p.name,
						'text/html'
					);
					const meta = doc.documentElement.textContent;
					callback(null, [
						{
							name: p.slug,
							value: qA + p.slug + qB,
							score: 1,
							meta,
						},
					]);
				});
			} finally {
				document.body.setAttribute('data-loading', false);
			}
		}, 250);
	}

	switch (prevKey[0]) {
		case 'preferredVersions': {
			const used = await getPrevSiblings(editor, pos);
			list.push(...['wp', 'php'].filter((s) => !used.includes(s)));
			break;
		}

		case 'wp':
			list.push('latest');
			break;

		case 'php':
			list.push(...(await completePhpVersion(prefix)));
			break;

		case 'steps':
			{
				const used = await getPrevSiblings(editor, pos);
				const stepType = getLastOfType(editor, 'step', pos);
				if (stepType) {
					const suggestions = await completeStepProperty(
						stepType,
						prefix
					);
					list.push(...suggestions.filter((s) => !used.includes(s)));
				} else {
					list.push('step');
				}
			}
			break;

		case 'step':
			list.push(...(await completeStep(prefix)));
			break;

		case 'features':
			list.push(...(await completeFeature(prefix)));
			break;

		case undefined:
			list.push(...(await completeRootKey(prefix)));
			break;

		default:
			switch (prevKey[-1 + prevKey.length]) {
				case 'steps':
					{
						const stepType = getLastOfType(editor, 'step', pos, 1);
						const resType = getLastOfType(
							editor,
							'resource',
							pos,
							1
						);
						if (prevKey.length === 2) {
							if (colon === -1) {
								const used = await getPrevSiblings(editor, pos);
								const suggestions =
									await completeStepSubProperty(
										stepType,
										resType,
										prevKey[-2 + prevKey.length],
										null,
										prefix
									);
								list.push(
									...suggestions.filter(
										(s) => !used.includes(s)
									)
								);
							}
						} else if (
							prevKey.length === 3 &&
							prevKey[0] === 'resource'
						) {
							list.push(
								...(await completeStepSubProperty(
									stepType,
									resType,
									prevKey[-2 + prevKey.length],
									prevKey[0],
									prefix
								))
							);
						}
					}
					break;
			}

			break;
	}

	for (const fill of list) {
		callback(null, [
			{
				name: fill,
				value: qA + fill + qB,
				score: 1,
				meta: 'Blueprint Schema',
			},
		]);
	}
};

let errorTag;
const showError = (error) => {
	console.error(error);
	if (!errorTag) {
		errorTag = document.getElementById('error-output');
	}
	const errDoc = `<head><style>body{ color: red; font-family: monospace; } pre{ white-space: pre-wrap; } p{ margin: 0.25rem; }</style></head><body>${error}</body>`;
	errorTag.setAttribute('srcdoc', errDoc);
};
const clearError = (error) => {
	if (!errorTag) {
		errorTag = document.getElementById('error-output');
	}
	errorTag.setAttribute('srcdoc', '');
};

const formatJson = (editor, jsonObject = {}) => {
	const existing = editor.getSession().getValue();
	const formatted = JSON.stringify(jsonObject, null, 2) + '\n';
	if (formatted !== existing) {
		editor.getSession().setValue(formatted);
		if (formatted !== existing) {
			editor.getSession().setValue(formatted);
		}
	}
};

function getCurrentBlueprint(editor) {
	const blueprint = JSON.parse(editor.getValue());
	if (blueprint.features && blueprint.features.networking === false) {
		blueprint.features.networking = true;
	}
	return blueprint;
}

let lastRun = 0;
const runBlueprint = async (editor) => {
	const currentRun = ++lastRun;
	// Trash the old iframe and create a new one
	// to avoid subsequent reloads conflicting with
	// ones potentially already in progress.
	const newIframe = document.createElement('iframe');
	let playgroundIframe = document.getElementById('wp-playground');
	playgroundIframe.parentNode.insertBefore(newIframe, playgroundIframe);
	playgroundIframe.remove();
	playgroundIframe = newIframe;
	newIframe.id = 'wp-playground';

	try {
		document.body.setAttribute('data-starting', true);
		clearError();

		const blueprintJsonObject = getCurrentBlueprint(editor);
		formatJson(editor, blueprintJsonObject);

		const blueprintString = JSON.stringify(blueprintJsonObject);
		window.location.hash = blueprintString;

		const blueprintCopy = JSON.parse(blueprintString);
		await startPlaygroundWeb({
			iframe: playgroundIframe,
			remoteUrl: `https://playground.wordpress.net/remote.html`,
			blueprint: blueprintCopy,
			corsProxy: corsProxyUrl,
		});
	} catch (error) {
		if (currentRun === lastRun) {
			showError(error);
		}
	} finally {
		if (currentRun === lastRun) {
			document.body.setAttribute('data-starting', false);
		}
	}
};

const loadFromHash = (editor) => {
	const hash = decodeURI(window.location.hash.substr(1));
	try {
		let json = '';
		try {
			json = JSON.parse(atob(hash));
		} catch (e) {
			json = JSON.parse(hash);
		}
		return json;
	} catch (error) {
		console.error(error);
	}
};

function onLoaded() {
	const button = document.querySelector('button#run');
	const newTab = document.querySelector('button#new-tab');

	// eslint-disable-next-line no-undef
	const editor = ace.edit('jsontext');
	editor.setTheme('ace/theme/github_dark');
	editor.session.setMode('ace/mode/json');

	// eslint-disable-next-line no-undef
	const langTools = ace.require('ace/ext/language_tools');

	langTools.setCompleters([]);

	langTools.addCompleter({ triggerCharacters: ['"'], getCompletions });

	editor.setOptions({
		enableBasicAutocompletion: true,
		enableLiveAutocompletion: true,
		enableSnippets: true,
		useSoftTabs: true,
		tabSize: 2,
	});

	editor.getSession().on('change', async (event) => {
		if (event.action !== 'insert') {
			return;
		}

		const content = editor.getValue();
		const lines = content.split('\n');

		if (
			event.start.row === event.end.row &&
			1 < Math.abs(event.start.column - event.end.column)
		) {
			if (lines[event.end.row][event.end.column] === '"') {
				editor.moveCursorTo(event.end.row, event.end.column + 1);
				return;
			}
		}

		if (lines[event.end.row][event.end.column]) {
			return;
		}

		const inserted = event.lines.join('\n');
		const prevKey = getPrevKeys(editor, event.end);

		if (inserted.length > 1) {
			return;
		}

		if (inserted === ':') {
			const colon = lines[event.start.row].indexOf(':');
			if (colon > -1 && colon < event.start.column) {
				return;
			}

			if (prevKey.length === 1 && prevKey[0] === 'landingPage') {
				editor
					.getSession()
					.insert(
						{ row: event.end.row, column: event.end.column },
						' ""'
					);
				editor.moveCursorTo(event.end.row, 1 + event.end.column);
			}

			if (prevKey.length === 1 && prevKey[0] === 'preferredVersions') {
				editor
					.getSession()
					.insert(
						{ row: event.end.row, column: event.end.column },
						' {}'
					);
				editor.moveCursorTo(event.end.row, 1 + event.end.column);
			}

			if (prevKey.length === 2 && prevKey[1] === 'preferredVersions') {
				editor
					.getSession()
					.insert(
						{ row: event.end.row, column: event.end.column },
						' ""'
					);
				editor.moveCursorTo(event.end.row, 1 + event.end.column);
				setTimeout(() => editor.execCommand('startAutocomplete'), 0);
			}

			if (
				prevKey.length === 1 &&
				(prevKey[0] === 'steps' || prevKey[0] === 'features')
			) {
				editor
					.getSession()
					.insert(
						{ row: event.end.row, column: event.end.column },
						' {}'
					);
				editor.moveCursorTo(event.end.row, 1 + event.end.column);
			}

			if (prevKey.length === 3 && prevKey[2] === 'steps') {
				const stepType = getLastOfType(editor, 'step', event.end, 1);
				const resType = getLastOfType(editor, 'resource', event.end);
				const subProps = await getStepSubProperties(
					stepType,
					resType,
					prevKey[1]
				);
				const subProp = subProps[prevKey[0]];

				if (subProp?.type === 'string') {
					editor
						.getSession()
						.insert(
							{ row: event.end.row, column: event.end.column },
							' ""'
						);
					editor.moveCursorTo(event.end.row, 2 + event.end.column);
					editor.execCommand('startAutocomplete');
				} else if (subProp?.type === 'object') {
					editor
						.getSession()
						.insert(
							{ row: event.end.row, column: event.end.column },
							' {}'
						);
					editor.moveCursorTo(event.end.row, 2 + event.end.column);
				}
			}

			if (prevKey.length === 2 && prevKey[1] === 'steps') {
				const stepType = await getLastOfType(editor, 'step', event.end);
				const properties = await getStepProperties(stepType);
				const property = properties[prevKey[0]] ?? null;
				const propType = property.type ?? null;
				const propRef = property['$ref'];

				if (propType === 'string') {
					editor
						.getSession()
						.insert(
							{ row: event.end.row, column: event.end.column },
							' ""'
						);
					editor.moveCursorTo(event.end.row, 2 + event.end.column);
				} else if (propRef === '#/definitions/FileReference') {
					editor
						.getSession()
						.insert(
							{ row: event.end.row, column: event.end.column },
							' {}'
						);
					editor.moveCursorTo(event.end.row, 2 + event.end.column);
				}
			}
		} else if (inserted === '[') {
			editor
				.getSession()
				.insert(
					{ row: event.end.row, column: event.start.column + 1 },
					']'
				);
			return;
		} else if (inserted === '{') {
			editor
				.getSession()
				.insert(
					{ row: event.end.row, column: event.start.column + 1 },
					'}'
				);
			return;
		}
	});

	button.addEventListener('click', () => {
		try {
			clearError();
			runBlueprint(editor);
		} catch (error) {
			showError(error);
		}
	});

	let prevWin;

	newTab.addEventListener('click', () => {
		runBlueprint(editor);
		const query = new URLSearchParams();

		query.set('mode', 'seamless');
		const url =
			`https://playground.wordpress.net/?${query}#` +
			JSON.stringify(getCurrentBlueprint(editor));
		if (prevWin) {
			prevWin.close();
		}
		prevWin = window.open(url, '_blank');
	});

	initializeBlueprint().then((blueprint) => {
		formatJson(editor, blueprint);
		runBlueprint(editor);
	});

	editor.commands.addCommand({
		name: 'Run Blueprint',
		bindKey: {
			win: 'Ctrl-Enter|Ctrl-S',
			mac: 'Command-Enter|Command-S',
		},
		exec: (editor) => runBlueprint(editor),
		readOnly: false,
	});

	const save = document.querySelector('button#save');
	const open = document.querySelector('button#open');

	const saveMethod = () => {
		const dataUri = `data:application/json;base64,${btoa(
			JSON.stringify(JSON.parse(editor.getValue()), null, 2)
		)}`;
		const link = document.createElement('a');
		link.setAttribute('href', dataUri);
		link.setAttribute(
			'download',
			`blueprint-${new Date().toISOString()}.json`
		);
		link.click();
	};

	const openMethod = () => {
		const input = document.createElement('input');
		input.setAttribute('type', 'file');
		input.addEventListener('change', (event) => {
			[...input.files].forEach((f) => {
				const reader = new FileReader();
				reader.addEventListener('load', () => {
					editor.setValue(
						JSON.stringify(JSON.parse(reader.result), null, 2)
					);
					editor.moveCursorTo(0, 0);
					runBlueprint(editor);
				});
				reader.readAsText(f);
			});
		});
		input.click();
	};

	save.addEventListener('click', saveMethod);
	open.addEventListener('click', openMethod);

	editor.commands.addCommand({
		name: 'Run Blueprint',
		bindKey: {
			win: 'Ctrl-S',
			mac: 'Command-S',
		},
		exec: (editor) => runBlueprint(editor),
		readOnly: false,
	});
}

const defaultBlueprint = {
	landingPage: '/wp-admin/',
	preferredVersions: {
		php: '7.4',
		wp: '5.9',
	},
	steps: [
		{
			step: 'login',
			username: 'admin',
		},
	],
};

async function initializeBlueprint() {
	if (window.location.hash) {
		return loadFromHash();
	}

	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('blueprint-url')) {
		try {
			const blueprintUrl = new URL(urlParams.get('blueprint-url'));
			if (blueprintUrl.hostname === 'github.com') {
				blueprintUrl.pathname = blueprintUrl.pathname.replace(
					/^\/([^/]+)\/([^/]+)\/blob/,
					'/$1/$2/'
				);
				blueprintUrl.hostname = 'raw.githubusercontent.com';
			}
			console.log('blueprintUrl.toString()', blueprintUrl.toString());
			const response = await fetch(blueprintUrl.toString());
			return await response.json();
		} catch (error) {
			showError(error);
			return defaultBlueprint;
		}
	}

	return defaultBlueprint;
}

if (document.readyState !== 'loading') {
	onLoaded();
} else {
	document.addEventListener('DOMContentLoaded', onLoaded);
}

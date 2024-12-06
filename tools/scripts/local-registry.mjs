#!/usr/bin/env node

const [, , command] = process.argv;

switch (command) {
	case 'enable':
		enable();
		break;
	case 'disable':
		disable();
		break;
	default:
		console.error(`unknown command: ${command}`);
}

function enable() {
	console.log(`enable`);
}

function disable() {
	console.log(`disable`);
}

onmessage = async function (event: MessageEvent) {
	const filePath: string = event.data.path;
	const content: string = event.data.content;

	const pathParts = filePath.split('/').filter((p) => p.length > 0);

	const fileName = pathParts.pop();
	if (fileName === undefined) {
		throw new Error(`Invalid path: '${filePath}'`);
	}

	let parentDirHandle = await navigator.storage.getDirectory();
	for (const part of pathParts) {
		parentDirHandle = await parentDirHandle.getDirectoryHandle(part);
	}

	const fileHandle = await parentDirHandle.getFileHandle(fileName, {
		create: true,
	});

	const syncAccessHandle = await fileHandle.createSyncAccessHandle();
	try {
		const encodedContent = new TextEncoder().encode(content);
		syncAccessHandle.write(encodedContent);
		postMessage('done');
	} finally {
		syncAccessHandle.close();
	}
};
postMessage('ready');

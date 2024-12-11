import type { MountDevice } from '@php-wasm/web';
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import type * as pleaseLoadTypes from 'wicg-file-system-access';

export async function directoryHandleFromMountDevice(
	device: MountDevice
): Promise<FileSystemDirectoryHandle> {
	if (device.type === 'local-fs') {
		return device.handle;
	}

	return opfsPathToDirectoryHandle(device.path);
}

export async function opfsPathToDirectoryHandle(
	opfsPath: string
): Promise<FileSystemDirectoryHandle> {
	const parts = opfsPath.split('/').filter((p) => p.length > 0);
	let handle = await navigator.storage.getDirectory();
	for (const part of parts) {
		handle = await handle.getDirectoryHandle(part, { create: true });
	}
	return handle;
}

export async function fileExistsUnderDirectoryHandle(
	directoryHandle: FileSystemDirectoryHandle,
	relativePath: string
): Promise<boolean> {
	const parts = relativePath.split('/').filter((p) => p.length > 0);
	const fileName = parts.pop();
	if (fileName === undefined) {
		throw new Error('Invalid relative path.');
	}

	for (const part of parts) {
		try {
			directoryHandle = await directoryHandle.getDirectoryHandle(part);
		} catch (e: any) {
			return false;
		}
	}
	try {
		await directoryHandle.getFileHandle(fileName);
		return true;
	} catch (e) {
		return false;
	}
}

export async function directoryHandleToOpfsPath(
	directoryHandle: FileSystemDirectoryHandle
): Promise<string> {
	const root = await navigator.storage.getDirectory();
	const pathParts = await root.resolve(directoryHandle);
	if (pathParts === null) {
		throw new DOMException(
			'Unable to resolve path of OPFS directory handle.',
			'NotFoundError'
		);
	}
	return '/' + pathParts.join('/');
}

export async function clearContentsFromMountDevice(mountDevice: MountDevice) {
	const parentHandle = await directoryHandleFromMountDevice(mountDevice);
	for await (const name of parentHandle.keys()) {
		await parentHandle.removeEntry(name, {
			recursive: true,
		});
	}
}

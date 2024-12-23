import { MountHandler } from '@php-wasm/universal';

export function createNodeFsMountHandler(localPath: string): MountHandler {
	return async function (php, FS, vfsMountPoint) {
		FS.mount(FS.filesystems['NODEFS'], { root: localPath }, vfsMountPoint);
		return () => {
			FS!.unmount(vfsMountPoint);
		};
	};
}

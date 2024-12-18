import { normalizePath } from '@php-wasm/util';
import { FileTree } from './git-sparse-checkout';

export function listDescendantFiles(files: FileTree[], selectedPath: string) {
	selectedPath = normalizePath(selectedPath);
	const isRoot = ['', '.', '/'].includes(selectedPath);

	let currentTree: FileTree[] | null = files;
	if (isRoot) {
		selectedPath = '';
	} else {
		const segments = selectedPath.split('/');
		for (const segment of segments) {
			const file = currentTree?.find(
				(file) => file.name === segment
			) as FileTree;
			if (file?.type === 'folder') {
				currentTree = file.children;
			} else if (file) {
				return [file.name];
			} else {
				return [];
			}
		}
	}

	// Calculate the list of files to checkout based on the mapping
	const descendants: string[] = [];
	const stack = [{ tree: currentTree, path: selectedPath }];
	while (stack.length > 0) {
		const { tree, path } = stack.pop() as {
			tree: FileTree[];
			path: string;
		};
		for (const file of tree) {
			const filePath = `${path}${path ? '/' : ''}${file.name}`;
			if (file.type === 'folder') {
				stack.push({
					tree: file.children,
					path: filePath,
				});
			} else {
				descendants.push(filePath);
			}
		}
	}
	return descendants;
}

export function removePathPrefix(path: string, prefix: string) {
	if (path.startsWith(prefix)) {
		return path.substring(prefix.length);
	}
	return path;
}

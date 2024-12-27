export type FileNode = {
	name: string;
	type: 'file' | 'folder';
	children?: FileNode[];
	content?: File;
};

export type FileTree = {
	path: string;
	nodes: FileNode[];
};

/*
 * Import internal data parsers and structures from isomorphic-git. These
 * exports are not available in the npm version of isomorphic-git, which is why
 * we use one from the git repository.
 *
 * This file heavily relies on isomorphic-git internals to parse Git data formats
 * such as PACK, trees, deltas, etc.
 */
import './isomorphic-git.d.ts';
import { GitPktLine } from 'isomorphic-git/src/models/GitPktLine.js';
import { GitTree } from 'isomorphic-git/src/models/GitTree.js';
import { GitAnnotatedTag } from 'isomorphic-git/src/models/GitAnnotatedTag.js';
import { GitCommit } from 'isomorphic-git/src/models/GitCommit.js';
import { GitPackIndex } from 'isomorphic-git/src/models/GitPackIndex.js';
import { collect } from 'isomorphic-git/src/internal-apis.js';
import { parseUploadPackResponse } from 'isomorphic-git/src/wire/parseUploadPackResponse.js';
import { ObjectTypeError } from 'isomorphic-git/src/errors/ObjectTypeError.js';
import { Buffer } from 'buffer';

/**
 * A polyfill for the Buffer class. We need it because isomorphic-git uses it internally.
 * The isomorphic-git version released via npm shipes a Buffer implementation, but we're
 * using a version cloned from the git repository which assumes a global Buffer is available.
 */
if (typeof window !== 'undefined') {
	window.Buffer = Buffer;
}

/**
 * Downloads specific files from a git repository.
 * It uses the git protocol over HTTP to fetch the files. It only uses
 * three HTTP requests regardless of the number of paths requested.
 *
 * @param repoUrl The URL of the git repository.
 * @param fullyQualifiedBranchName The full name of the branch to fetch from (e.g., 'refs/heads/main').
 * @param filesPaths An array of all the file paths to fetch from the repository. Does **not** accept
 *                   patterns, wildcards, directory paths. All files must be explicitly listed.
 * @returns A record where keys are file paths and values are the retrieved file contents.
 */
export async function sparseCheckout(
	repoUrl: string,
	commitHash: string,
	filesPaths: string[]
) {
	const treesIdx = await fetchWithoutBlobs(repoUrl, commitHash);
	const objects = await resolveObjects(treesIdx, commitHash, filesPaths);

	const blobsIdx = await fetchObjects(
		repoUrl,
		filesPaths.map((path) => objects[path].oid)
	);

	const fetchedPaths: Record<string, any> = {};
	await Promise.all(
		filesPaths.map(async (path) => {
			fetchedPaths[path] = await extractGitObjectFromIdx(
				blobsIdx,
				objects[path].oid
			);
		})
	);
	return fetchedPaths;
}

export type FileTreeFile = {
	name: string;
	type: 'file';
};
export type FileTreeFolder = {
	name: string;
	type: 'folder';
	children: FileTree[];
};
export type FileTree = FileTreeFile | FileTreeFolder;

export type GitRef = {
	value: string;
	type?: 'branch' | 'commit' | 'refname' | 'infer';
};

/**
 * Lists all files in a git repository.
 *
 * See https://git-scm.com/book/en/v2/Git-Internals-Git-Objects for more information.
 *
 * @param repoUrl The URL of the git repository.
 * @param commitHash The commit hash to fetch from.
 * @returns A list of all files in the repository.
 */
export async function listGitFiles(
	repoUrl: string,
	commitHash: string
): Promise<FileTree[]> {
	const treesIdx = await fetchWithoutBlobs(repoUrl, commitHash);
	const rootTree = await resolveAllObjects(treesIdx, commitHash);
	if (!rootTree?.object) {
		return [];
	}

	return gitTreeToFileTree(rootTree);
}

/**
 * Resolves a ref description, e.g. a branch name, to a commit hash.
 *
 * @param repoUrl The URL of the git repository.
 * @param ref The branch name or commit hash.
 * @returns The commit hash.
 */
export async function resolveCommitHash(repoUrl: string, ref: GitRef) {
	if (ref.type === 'infer' || ref.type === undefined) {
		if (['', 'HEAD'].includes(ref.value)) {
			ref = {
				value: ref.value,
				type: 'refname',
			};
		} else if (typeof ref.value === 'string' && ref.value.length === 40) {
			ref = {
				value: ref.value,
				type: 'commit',
			};
		}
	}
	if (ref.type === 'branch') {
		ref = {
			value: `refs/heads/${ref.value}`,
			type: 'refname',
		};
	}
	switch (ref.type) {
		case 'commit':
			return ref.value;
		case 'refname': {
			const refs = await listGitRefs(repoUrl, ref.value);
			if (!(ref.value in refs)) {
				throw new Error(`Branch ${ref.value} not found`);
			}
			return refs[ref.value];
		}
		default:
			throw new Error(`Invalid ref type: ${ref.type}`);
	}
}

function gitTreeToFileTree(tree: GitTree): FileTree[] {
	return tree.object
		.map((branch) => {
			if (branch.type === 'blob') {
				return {
					name: branch.path,
					type: 'file',
				} as FileTreeFile;
			} else if (branch.type === 'tree' && branch.object) {
				return {
					name: branch.path,
					type: 'folder',
					children: gitTreeToFileTree(branch as any as GitTree),
				} as FileTreeFolder;
			}
			return undefined;
		})
		.filter((entry) => !!entry?.name) as FileTree[];
}

/**
 * Retrieves a list of refs from a git repository.
 *
 * See https://git-scm.com/book/en/v2/Git-Internals-Git-References for more information.
 *
 * @param repoUrl The URL of the git repository. For example: https://github.com/WordPress/gutenberg.git
 * @param fullyQualifiedBranchPrefix The prefix of the refs to fetch. For example: refs/heads/my-feature-branch
 * @returns A map of refs to their corresponding commit hashes.
 */
export async function listGitRefs(
	repoUrl: string,
	fullyQualifiedBranchPrefix: string
) {
	const packbuffer = Buffer.from(
		await collect([
			GitPktLine.encode(`command=ls-refs\n`),
			GitPktLine.encode(`agent=git/2.37.3\n`),
			GitPktLine.encode(`object-format=sha1\n`),
			GitPktLine.delim(),
			GitPktLine.encode(`peel\n`),
			GitPktLine.encode(`ref-prefix ${fullyQualifiedBranchPrefix}\n`),
			GitPktLine.flush(),
		])
	);

	const response = await fetch(repoUrl + '/git-upload-pack', {
		method: 'POST',
		headers: {
			Accept: 'application/x-git-upload-pack-advertisement',
			'content-type': 'application/x-git-upload-pack-request',
			'Content-Length': `${packbuffer.length}`,
			'Git-Protocol': 'version=2',
		},
		body: packbuffer,
	});

	const refs: Record<string, string> = {};
	for await (const line of parseGitResponseLines(response)) {
		const spaceAt = line.indexOf(' ');
		const ref = line.slice(0, spaceAt);
		const name = line.slice(spaceAt + 1, line.length - 1);
		refs[name] = ref;
	}
	return refs;
}

async function fetchWithoutBlobs(repoUrl: string, commitHash: string) {
	const packbuffer = Buffer.from(
		await collect([
			GitPktLine.encode(
				`want ${commitHash} multi_ack_detailed no-done side-band-64k thin-pack ofs-delta agent=git/2.37.3 filter \n`
			),
			GitPktLine.encode(`filter blob:none\n`),
			GitPktLine.encode(`shallow ${commitHash}\n`),
			GitPktLine.encode(`deepen 1\n`),
			GitPktLine.flush(),
			GitPktLine.encode(`done\n`),
			GitPktLine.encode(`done\n`),
		])
	);

	const response = await fetch(repoUrl + '/git-upload-pack', {
		method: 'POST',
		headers: {
			Accept: 'application/x-git-upload-pack-advertisement',
			'content-type': 'application/x-git-upload-pack-request',
			'Content-Length': `${packbuffer.length}`,
		},
		body: packbuffer,
	});

	const iterator = streamToIterator(response.body!);
	const parsed = await parseUploadPackResponse(iterator);
	const packfile = Buffer.from(await collect(parsed.packfile));
	const idx = await GitPackIndex.fromPack({
		pack: packfile,
	});
	const originalRead = idx.read as any;
	idx.read = async function ({ oid, ...rest }: { oid: string }) {
		const result = await originalRead.call(this, { oid, ...rest });
		result.oid = oid;
		return result;
	};
	return idx;
}

async function resolveAllObjects(idx: GitPackIndex, commitHash: string) {
	const commit = await idx.read({
		oid: commitHash,
	});
	readObject(commit);

	const rootItem = await idx.read({ oid: commit.object.tree });
	const items = [rootItem];
	while (items.length > 0) {
		const tree = items.pop();
		const readItem = await idx.read({ oid: tree.oid });
		readObject(readItem);
		tree.object = readItem.object;
		if (readItem.type === 'tree') {
			for (const subitem of readItem.object) {
				if (subitem.type === 'tree') {
					items.push(subitem);
				}
			}
		}
	}
	return rootItem;
}

async function resolveObjects(
	idx: GitPackIndex,
	commitHash: string,
	paths: string[]
) {
	const commit = await idx.read({
		oid: commitHash,
	});
	readObject(commit);

	const rootTree = await idx.read({ oid: commit.object.tree });
	readObject(rootTree);

	// Resolve refs to fetch
	const resolvedOids: Record<string, any> = {};
	for (const path of paths) {
		let currentObject = rootTree;
		const segments = path.split('/');
		for (const segment of segments) {
			if (currentObject.type !== 'tree') {
				throw new Error(`Path not found in the repo: ${path}`);
			}

			let found = false;
			for (const item of currentObject.object) {
				if (item.path === segment) {
					try {
						currentObject = await idx.read({ oid: item.oid });
						readObject(currentObject);
					} catch (e) {
						currentObject = item;
					}
					found = true;
					break;
				}
			}
			if (!found) {
				throw new Error(`Path not found in the repo: ${path}`);
			}
		}
		resolvedOids[path] = currentObject;
	}
	return resolvedOids;
}

// Request oid for each resolvedRef
async function fetchObjects(url: string, objectHashes: string[]) {
	const packbuffer = Buffer.from(
		await collect([
			...objectHashes.map((objectHash) =>
				GitPktLine.encode(
					`want ${objectHash} multi_ack_detailed no-done side-band-64k thin-pack ofs-delta agent=git/2.37.3 \n`
				)
			),
			GitPktLine.flush(),
			GitPktLine.encode(`done\n`),
		])
	);

	const response = await fetch(url + '/git-upload-pack', {
		method: 'POST',
		headers: {
			Accept: 'application/x-git-upload-pack-advertisement',
			'content-type': 'application/x-git-upload-pack-request',
			'Content-Length': `${packbuffer.length}`,
		},
		body: packbuffer,
	});

	const iterator = streamToIterator(response.body!);
	const parsed = await parseUploadPackResponse(iterator);
	const packfile = Buffer.from(await collect(parsed.packfile));
	return await GitPackIndex.fromPack({
		pack: packfile,
	});
}

async function extractGitObjectFromIdx(idx: GitPackIndex, objectHash: string) {
	const tree = await idx.read({ oid: objectHash });
	readObject(tree);

	if (tree.type === 'blob') {
		return tree.object;
	}

	const files: Record<string, any> = {};
	for (const { path, oid, type } of tree.object) {
		if (type === 'blob') {
			const object = await idx.read({ oid });
			readObject(object);
			files[path] = object.object;
		} else if (type === 'tree') {
			files[path] = await extractGitObjectFromIdx(idx, oid);
		}
	}
	return files;
}

function readObject(result: any) {
	if (!(result.object instanceof Buffer)) {
		return;
	}
	switch (result.type) {
		case 'commit':
			result.object = GitCommit.from(result.object).parse();
			break;
		case 'tree':
			result.object = (GitTree.from(result.object) as any).entries();
			break;
		case 'blob':
			result.object = new Uint8Array(result.object);
			result.format = 'content';
			break;
		case 'tag':
			result.object = GitAnnotatedTag.from(result.object).parse();
			break;
		default:
			throw new ObjectTypeError(
				result.oid,
				result.type,
				'blob|commit|tag|tree'
			);
	}
}

async function* parseGitResponseLines(response: Response) {
	const text = await response.text();
	let at = 0;

	while (at <= text.length) {
		const lineLength = parseInt(text.substring(at, at + 4), 16);
		if (lineLength === 0) {
			break;
		}
		const line = text.substring(at + 4, at + lineLength);
		yield line;
		at += lineLength;
	}
}

function streamToIterator(stream: any) {
	// Use native async iteration if it's available.
	if (stream[Symbol.asyncIterator]) {
		return stream;
	}
	const reader = stream.getReader();
	return {
		next() {
			return reader.read();
		},
		return() {
			reader.releaseLock();
			return {};
		},
		[Symbol.asyncIterator]() {
			return this;
		},
	};
}

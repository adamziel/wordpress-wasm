import React, { useEffect, useState, useCallback } from '@wordpress/element';
import {
	CreatedNode,
	FileNode,
	FilePickerTree,
} from './components/FilePickerTree';
import { store as editorStore } from '@wordpress/editor';
import { store as preferencesStore } from '@wordpress/preferences';
import { dispatch, useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addLocalFilesTab } from './add-local-files-tab';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { Spinner, Button } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import './editor.css';

// Pre-populated by plugin.php
const WP_LOCAL_FILE_POST_TYPE = window.WP_LOCAL_FILE_POST_TYPE;

let fileTreePromise = apiFetch({
	path: '/static-files-editor/v1/get-files-tree',
});

function ConnectedFilePickerTree() {
	const [fileTree, setFileTree] = useState<any>(null);
	const [isLoading, setIsLoading] = useState(true);

	// Get the current post's file path from meta
	const [meta] = useEntityProp('postType', WP_LOCAL_FILE_POST_TYPE, 'meta');
	const [selectedPath, setSelectedPath] = useState(
		meta?.local_file_path || '/'
	);

	const refreshFileTree = useCallback(async () => {
		fileTreePromise = apiFetch({
			path: '/static-files-editor/v1/get-files-tree',
		});
		setFileTree(await fileTreePromise);
	}, []);

	useEffect(() => {
		fileTreePromise
			.then((tree) => {
				setFileTree(tree);
			})
			.catch((error) => {
				console.error('Failed to load file tree:', error);
			})
			.finally(() => {
				setIsLoading(false);
			});
	}, []);

	const onNavigateToEntityRecord = useSelect(
		(select) =>
			select(blockEditorStore).getSettings().onNavigateToEntityRecord,
		[]
	);

	const handleNodeDeleted = async (path: string) => {
		const { post_id } = (await apiFetch({
			path: '/static-files-editor/v1/get-or-create-post-for-file',
			method: 'POST',
			data: { path },
		})) as { post_id: string };
		console.log({
			post_id,
			deleteUrl: `/wp/v2/${WP_LOCAL_FILE_POST_TYPE}/${post_id}`,
		});

		await apiFetch({
			// ?force=true to skip the trash and delete the file immediately
			path: `/wp/v2/${WP_LOCAL_FILE_POST_TYPE}/${post_id}?force=true`,
			headers: {
				'X-HTTP-Method-Override': 'DELETE',
			},
		});
		await refreshFileTree();
	};

	const handleFileClick = async (filePath: string, node: FileNode) => {
		if (node.type === 'folder') {
			setSelectedPath(filePath);
			return;
		}

		// 1. Create/get post for this file path
		const { post_id } = (await apiFetch({
			path: '/static-files-editor/v1/get-or-create-post-for-file',
			method: 'POST',
			data: { path: filePath },
		})) as { post_id: string };

		// 2. Switch to the new post in the editor
		onNavigateToEntityRecord({
			postId: post_id,
			postType: WP_LOCAL_FILE_POST_TYPE,
		});
	};

	const handleNodeCreated = async (node: CreatedNode) => {
		if (node.type === 'file') {
			await createEmptyFile(node.path);
		} else if (node.type === 'folder') {
			// Create an empty .gitkeep file in the new directory
			// to make sure it will actually be created in the filesystem.
			// @TODO: Rethink this approach. Ideally we could just display the
			//        directory in the tree, and let the user create files inside it.
			await createEmptyFile(node.path + '/.gitkeep');
		}
	};

	const createEmptyFile = async (newFilePath: string) => {
		try {
			const response = (await apiFetch({
				path: '/static-files-editor/v1/get-or-create-post-for-file',
				method: 'POST',
				data: {
					path: newFilePath,
					create_file: true,
				},
			})) as { post_id: string };

			await refreshFileTree();

			onNavigateToEntityRecord({
				postId: response.post_id,
				postType: WP_LOCAL_FILE_POST_TYPE,
			});
		} catch (error) {
			console.error('Failed to create file:', error);
		}
	};

	const handleNodeMoved = async ({
		fromPath,
		toPath,
	}: {
		fromPath: string;
		toPath: string;
	}) => {
		try {
			console.log('Moving file from', fromPath, 'to', toPath);
			await apiFetch({
				path: '/static-files-editor/v1/move-file',
				method: 'POST',
				data: {
					fromPath,
					toPath,
				},
			});
			await refreshFileTree();
		} catch (error) {
			console.error('Failed to move file:', error);
		}
	};

	if (isLoading) {
		return <Spinner />;
	}

	if (!fileTree) {
		return <div>No files found</div>;
	}

	return (
		<div>
			<FilePickerTree
				files={fileTree}
				onSelect={handleFileClick}
				initialPath={selectedPath}
				onNodeCreated={handleNodeCreated}
				onNodeDeleted={handleNodeDeleted}
				onNodeMoved={handleNodeMoved}
			/>
		</div>
	);
}

addLocalFilesTab({
	name: 'local-files',
	title: 'Local Files',
	panel: (
		<div>
			<ConnectedFilePickerTree />
		</div>
	),
});

dispatch(preferencesStore).set('welcomeGuide', false);
dispatch(preferencesStore).set('enableChoosePatternModal', false);
dispatch(editorStore).setIsListViewOpened(true);

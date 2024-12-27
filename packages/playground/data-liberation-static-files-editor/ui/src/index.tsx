import React, { useEffect, useState, useCallback } from '@wordpress/element';
import {
	CreatedNode,
	FileNode,
	FilePickerTree,
	FileTree,
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
		try {
			await apiFetch({
				path: '/static-files-editor/v1/delete-path',
				method: 'POST',
				data: { path },
			});
			await refreshFileTree();
		} catch (error) {
			console.error('Failed to delete file:', error);
		}
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

	const handleNodesCreated = async (tree: FileTree) => {
		try {
			const formData = new FormData();
			formData.append('path', tree.path);

			// Convert nodes to JSON, but extract files to separate form fields
			const processNode = (node: FileNode, prefix: string): any => {
				const nodeData = { ...node };
				if (node.content instanceof File) {
					formData.append(`${prefix}_content`, node.content);
					nodeData.content = `@file:${prefix}_content`;
				}
				if (node.children) {
					nodeData.children = node.children.map((child, index) =>
						processNode(child, `${prefix}_${index}`)
					);
				}
				return nodeData;
			};

			const processedNodes = tree.nodes.map((node, index) =>
				processNode(node, `file_${index}`)
			);
			formData.append('nodes', JSON.stringify(processedNodes));

			const response = (await apiFetch({
				path: '/static-files-editor/v1/create-files',
				method: 'POST',
				body: formData,
			})) as { created_files: Array<{ path: string; post_id: string }> };

			await refreshFileTree();

			if (response.created_files.length > 0) {
				onNavigateToEntityRecord({
					postId: response.created_files[0].post_id,
					postType: WP_LOCAL_FILE_POST_TYPE,
				});
			}
		} catch (error) {
			console.error('Failed to create files:', error);
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
				onNodesCreated={handleNodesCreated}
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

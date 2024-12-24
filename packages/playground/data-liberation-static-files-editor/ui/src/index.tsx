import React, { useEffect, useState, useCallback } from '@wordpress/element';
import { FileNode, FilePickerTree } from './components/FilePickerTree';
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

const fileTreePromise = apiFetch({
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
		const tree = await apiFetch({
			path: '/static-files-editor/v1/get-files-tree',
		});
		setFileTree(tree);
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

	const handleFileClick = async (filePath: string, node: FileNode) => {
		if (node.type === 'folder') {
			setSelectedPath(filePath);
			return;
		}

		// 1. Create/get post for this file path
		const response = (await apiFetch({
			path: '/static-files-editor/v1/get-or-create-post-for-file',
			method: 'POST',
			data: { path: filePath },
		})) as { post_id: string };

		// 2. Switch to the new post in the editor
		onNavigateToEntityRecord({
			postId: response.post_id,
			postType: WP_LOCAL_FILE_POST_TYPE,
		});
	};

	const handleCreateFile = async () => {
		const newFilePath = `${selectedPath}/untitled.md`.replace(/\/+/g, '/');
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

	const handleCreateDirectory = async () => {
		try {
			await apiFetch({
				path: '/static-files-editor/v1/create-directory',
				method: 'POST',
				data: {
					path: `${selectedPath}/empty`.replace(/\/+/g, '/'),
				},
			});
			await refreshFileTree();
		} catch (error) {
			console.error('Failed to create directory:', error);
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
			<div
				style={{ marginBottom: '1rem', display: 'flex', gap: '0.5rem' }}
			>
				<Button variant="primary" onClick={handleCreateFile}>
					Create File
				</Button>
				<Button variant="secondary" onClick={handleCreateDirectory}>
					Create Directory
				</Button>
			</div>
			<FilePickerTree
				files={fileTree}
				onSelect={handleFileClick}
				initialPath={selectedPath}
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

import React from 'react';
import {
	useEffect,
	useRef,
	useState,
	createContext,
	useContext,
} from '@wordpress/element';
import {
	__experimentalTreeGrid as TreeGrid,
	__experimentalTreeGridRow as TreeGridRow,
	__experimentalTreeGridCell as TreeGridCell,
	Button,
	Spinner,
	ButtonGroup,
	DropdownMenu,
} from '@wordpress/components';
import { Icon, chevronRight, chevronDown, more } from '@wordpress/icons';
import '@wordpress/components/build-style/style.css';
import css from './style.module.css';
import classNames from 'classnames';
import { folder, file } from '../icons';
import { FileTree } from './types';

export type FileNode = {
	name: string;
	type: 'file' | 'folder';
	children?: FileNode[];
	content?: File;
};

export type CreatedNode =
	| {
			type: 'file' | 'folder';
			path: string;
			content?: string | ArrayBuffer | File;
	  }
	| {
			type: 'tree';
			path: string;
			content: FileNode[];
	  };

export type FilePickerControlProps = {
	files: FileNode[];
	initialPath?: string;
	onSelect?: (path: string, node: FileNode) => void;
	onNodesCreated?: (tree: FileTree) => void;
	onNodeDeleted?: (path: string) => void;
	onNodeMoved?: ({
		fromPath,
		toPath,
	}: {
		fromPath: string;
		toPath: string;
	}) => void;
	isLoading?: boolean;
	error?: string;
};

type ExpandedNodePaths = Record<string, boolean>;

type EditedNode = {
	reason: 'rename' | 'create';
	type?: 'file' | 'folder';
	parentPath?: string;
	originalPath?: string;
};

type DragState = {
	path: string;
	hoverPath: string | null;
	hoverType: 'file' | 'folder' | null;
	isExternal?: boolean;
};

type FilePickerContextType = {
	expandedNodePaths: ExpandedNodePaths;
	expandNode: (path: string, isOpen: boolean) => void;
	selectPath: (path: string, node: FileNode) => void;
	selectedNode: string | null;
	generatePath: (node: FileNode, parentPath?: string) => string;
	editedNode: EditedNode | null;
	onEditedNodeComplete: (name: string) => void;
	onEditedNodeCancel: () => void;
	onNodeDeleted: (path: string) => void;
	startRenaming: (path: string) => void;
	onDragStart: (path: string, type: 'file' | 'folder') => void;
	onDragOver: (
		e: React.DragEvent,
		path: string,
		type: 'file' | 'folder'
	) => void;
	onDrop: (e: React.DragEvent, path: string, type: 'file' | 'folder') => void;
	onDragEnd: () => void;
	dragState: DragState | null;
};

const FilePickerContext = createContext<FilePickerContextType | null>(null);

export const FilePickerTree: React.FC<FilePickerControlProps> = ({
	isLoading = false,
	error = undefined,
	files,
	initialPath,
	onSelect = () => {},
	onNodesCreated = (tree: FileTree) => {
		console.log('onNodesCreated', tree);
	},
	onNodeDeleted = (path: string) => {
		console.log('onNodeDeleted', path);
	},
	onNodeMoved = ({
		fromPath,
		toPath,
	}: {
		fromPath: string;
		toPath: string;
	}) => {
		console.log('onNodeMoved', fromPath, toPath);
	},
}) => {
	initialPath = initialPath ? initialPath.replace(/^\/+/, '') : '/';
	const [expanded, setExpanded] = useState<ExpandedNodePaths>(() => {
		if (!initialPath) {
			return {};
		}
		const expanded: ExpandedNodePaths = {};
		const pathParts = initialPath.split('/');
		for (let i = 0; i < pathParts.length; i++) {
			const pathSoFar = pathParts.slice(0, i + 1).join('/');
			expanded[pathSoFar] = true;
		}
		return expanded;
	});
	const [selectedPath, setSelectedPath] = useState<string | null>(() =>
		initialPath ? initialPath : null
	);

	const [editedNode, setEditedNode] = useState<EditedNode | null>(null);
	const [dragState, setDragState] = useState<DragState | null>(null);

	const expandNode = (path: string, isOpen: boolean) => {
		setExpanded((prevState) => ({
			...prevState,
			[path]: isOpen,
		}));
	};

	const selectPath = (path: string, node: FileNode) => {
		setSelectedPath(path);
		onSelect(path, node);
	};

	const generatePath = (node: FileNode, parentPath = ''): string => {
		return parentPath
			? `${parentPath}/${node.name}`.replaceAll(/\/+/g, '/')
			: node.name;
	};

	const handleRenameRequest = (path: string, newName: string) => {
		setEditedNode({
			reason: 'rename',
			originalPath: path,
		});
	};

	const handleCreateNode = (type: 'file' | 'folder') => {
		if (!selectedPath) {
			setEditedNode({
				reason: 'create',
				type,
				parentPath: '',
			});
			return;
		}
		const pathParts = selectedPath.split('/') || [];
		let currentNode: FileNode | undefined = undefined;
		let currentNodes = files;

		for (const part of pathParts) {
			currentNode = currentNodes.find((n) => n.name === part);
			if (!currentNode) break;
			currentNodes = currentNode.children || [];
		}

		// If selected node is a file, use its parent path
		const parentPath =
			currentNode?.type === 'folder' && expanded[selectedPath]
				? selectedPath
				: pathParts.slice(0, -1).join('/');

		expandNode(parentPath, true);
		setEditedNode({
			reason: 'create',
			type,
			parentPath,
		});
	};

	const handleEditedNodeComplete = (name: string) => {
		if (!editedNode) return;
		setEditedNode(null);
		if (editedNode.reason === 'rename') {
			// @TODO: Replace with joinPaths()
			const fromPath = editedNode.originalPath.replace(/^\/+/, '');
			const toPath = [...fromPath.split('/').slice(0, -1), name].join(
				'/'
			);

			if (fromPath === toPath) {
				return;
			}

			onNodeMoved({
				fromPath,
				toPath,
			});
		} else {
			onNodesCreated({
				path: editedNode.parentPath,
				nodes: [
					{
						name: name,
						type: editedNode.type,
						content: null,
					},
				],
			});
		}
	};

	const handleEditedNodeCancel = () => {
		setEditedNode(null);
	};

	const handleDragStart = (path: string, type: 'file' | 'folder') => {
		setDragState({
			path,
			hoverPath: null,
			hoverType: null,
			isExternal: false,
		});
	};

	const isDescendantPath = (parentPath: string, childPath: string) => {
		return childPath.startsWith(parentPath + '/');
	};

	const handleDragOver = (
		e: React.DragEvent,
		path: string,
		type: 'file' | 'folder'
	) => {
		e.preventDefault();

		// Handle external files being dragged in
		if (e.dataTransfer.types.includes('Files')) {
			e.dataTransfer.dropEffect = 'copy';
			setDragState({
				path: '',
				hoverPath: path,
				hoverType: type,
				isExternal: true,
			});
			return;
		}

		if (dragState && dragState.path !== path) {
			// Prevent dropping a folder into its own descendant
			if (dragState.path && isDescendantPath(dragState.path, path)) {
				e.dataTransfer.dropEffect = 'none';
				return;
			}

			e.dataTransfer.dropEffect = 'move';
			setDragState({
				...dragState,
				hoverPath: path,
				hoverType: type,
			});
		}
	};

	const handleDrop = async (
		e: React.DragEvent,
		targetPath: string,
		targetType: 'file' | 'folder'
	) => {
		e.preventDefault();
		// Handle file/directory upload
		if (e.dataTransfer.items.length > 0) {
			const targetFolder =
				targetType === 'folder'
					? targetPath
					: targetPath.split('/').slice(0, -1).join('/');
			const items = Array.from(e.dataTransfer.items);

			const buildTree = async (
				entry: FileSystemEntry,
				parentPath: string = ''
			): Promise<FileNode> => {
				if (entry.isFile) {
					const fileEntry = entry as FileSystemFileEntry;
					const file = await new Promise<File>((resolve) =>
						fileEntry.file(resolve)
					);
					return {
						name: entry.name,
						type: 'file',
						content: file,
					};
				} else {
					const dirEntry = entry as FileSystemDirectoryEntry;
					const reader = dirEntry.createReader();
					const entries = await new Promise<FileSystemEntry[]>(
						(resolve) => {
							reader.readEntries((entries) => resolve(entries));
						}
					);

					const children = await Promise.all(
						entries.map((entry) => buildTree(entry))
					);

					return {
						name: entry.name,
						type: 'folder',
						children,
					};
				}
			};

			const rootNodes = await Promise.all(
				items
					.map((item) => item.webkitGetAsEntry())
					.filter((entry): entry is FileSystemEntry => entry !== null)
					.map((entry) => buildTree(entry))
			);

			onNodesCreated({
				path: targetFolder,
				nodes: rootNodes,
			});

			setDragState(null);
			return;
		}

		if (dragState) {
			// Prevent dropping a folder into its own descendant
			if (
				dragState.path &&
				targetType === 'folder' &&
				isDescendantPath(dragState.path, targetPath)
			) {
				return;
			}

			const fromPath = dragState.path.replace(/^\/+/, '');

			const targetParentPath =
				targetType === 'file'
					? targetPath.split('/').slice(0, -1).join('/')
					: targetPath;

			const toPath = [
				targetParentPath.split('/'),
				dragState.path.split('/').pop(),
			]
				.join('/')
				.replace(/^\/+/, '');

			setDragState(null);

			if (fromPath === toPath) {
				return;
			}

			onNodeMoved({
				fromPath,
				toPath,
			});
		}
	};

	const handleDragEnd = () => {
		setDragState(null);
	};

	const [searchBuffer, setSearchBuffer] = useState('');
	const searchBufferTimeoutRef = useRef<NodeJS.Timeout | null>(null);
	function handleKeyDown(event: React.KeyboardEvent<HTMLDivElement>) {
		// Don't filter if we're creating a new file or folder –
		// this would only blur and hide the filename input.
		if (editedNode) {
			return;
		}
		if (event.key.length === 1 && event.key.match(/\S/)) {
			const newSearchBuffer = searchBuffer + event.key.toLowerCase();
			setSearchBuffer(newSearchBuffer);
			// Clear the buffer after 1 second
			if (searchBufferTimeoutRef.current) {
				clearTimeout(searchBufferTimeoutRef.current);
			}
			searchBufferTimeoutRef.current = setTimeout(() => {
				setSearchBuffer('');
			}, 1000);

			if (thisContainerRef.current) {
				const buttons = Array.from(
					thisContainerRef.current.querySelectorAll(
						'.file-node-button'
					)
				);
				const activeElement = document.activeElement;
				let startIndex = 0;
				if (
					activeElement &&
					buttons.includes(activeElement as HTMLButtonElement)
				) {
					startIndex = buttons.indexOf(
						activeElement as HTMLButtonElement
					);
				}
				for (let i = 0; i < buttons.length; i++) {
					const index = (startIndex + i) % buttons.length;
					const button = buttons[index];
					if (
						button.textContent
							?.toLowerCase()
							.trim()
							.startsWith(newSearchBuffer)
					) {
						(button as HTMLButtonElement).focus();
						break;
					}
				}
			}
		} else {
			// Clear the buffer for any non-letter key press
			setSearchBuffer('');
			if (searchBufferTimeoutRef.current) {
				clearTimeout(searchBufferTimeoutRef.current);
			}
		}
	}

	const thisContainerRef = useRef<HTMLDivElement>(null);

	useEffect(() => {
		// automatically focus the first button when the files are loaded
		if (thisContainerRef.current) {
			const firstButton = initialPath
				? thisContainerRef.current.querySelector(
						`[data-path="${initialPath}"]`
				  )
				: thisContainerRef.current.querySelector('.file-node-button');
			if (firstButton) {
				(firstButton as HTMLButtonElement).focus();
			}
		}
	}, [files.length > 0]);

	if (isLoading) {
		return (
			<div className={css['loadingContainer']}>
				<Spinner />
			</div>
		);
	}

	if (error) {
		return (
			<div className={css['errorContainer']}>
				<h2>Error loading files</h2>
				<p>{error}</p>
			</div>
		);
	}

	const contextValue = {
		expandedNodePaths: expanded,
		expandNode,
		selectPath,
		selectedNode: selectedPath,
		generatePath,
		editedNode,
		onEditedNodeComplete: handleEditedNodeComplete,
		onEditedNodeCancel: handleEditedNodeCancel,
		onNodeDeleted,
		startRenaming: handleRenameRequest,
		onDragStart: handleDragStart,
		onDragOver: handleDragOver,
		onDrop: handleDrop,
		onDragEnd: handleDragEnd,
		dragState,
	};

	return (
		<FilePickerContext.Provider value={contextValue}>
			<div onKeyDown={handleKeyDown} ref={thisContainerRef}>
				<div
					style={{
						marginBottom: '1rem',
						display: 'flex',
						gap: '0.5rem',
					}}
				>
					<ButtonGroup className={css['controls']}>
						<Button
							variant="secondary"
							onClick={() => handleCreateNode('file')}
							disabled={!selectedPath}
						>
							New File
						</Button>
						<Button
							variant="secondary"
							onClick={() => handleCreateNode('folder')}
							disabled={!selectedPath}
						>
							New Folder
						</Button>
					</ButtonGroup>
				</div>

				<TreeGrid className={css['filePickerTree']}>
					<NodeRow
						key={'/'}
						node={{
							name: '',
							type: 'folder',
							children: files,
						}}
						isRoot={true}
						level={-1}
						position={1}
						setSize={files.length}
						parentPath=""
					/>
				</TreeGrid>
			</div>
		</FilePickerContext.Provider>
	);
};

const NodeRow: React.FC<{
	node: FileNode;
	level: number;
	position: number;
	setSize: number;
	isRoot: boolean;
	parentPath: string;
}> = ({ node, level, position, setSize, isRoot, parentPath }) => {
	const context = useContext(FilePickerContext);
	if (!context)
		throw new Error('NodeRow must be used within FilePickerContext');

	const {
		expandedNodePaths,
		expandNode,
		selectPath,
		generatePath,
		selectedNode,
		editedNode,
		onEditedNodeComplete,
		onEditedNodeCancel,
		onNodeDeleted,
		startRenaming,
		onDragStart,
		onDragOver,
		onDrop,
		onDragEnd,
		dragState,
	} = context;

	const path = generatePath(node, parentPath);
	const isExpanded = isRoot || expandedNodePaths[path];
	const isBeingDragged = dragState?.path === path;
	const isHovered = dragState?.hoverPath === path;

	const toggleOpen = () => expandNode(path, !isExpanded);

	const handleKeyDown = (event: any) => {
		if (event.key === 'ArrowLeft') {
			if (isExpanded) {
				toggleOpen();
			} else {
				(
					document.querySelector(
						`[data-path="${parentPath}"]`
					) as HTMLButtonElement
				)?.focus();
			}
			event.preventDefault();
			event.stopPropagation();
		} else if (event.key === 'ArrowRight') {
			if (isExpanded) {
				if (node.children?.length) {
					const firstChildPath = generatePath(node.children[0], path);
					(
						document.querySelector(
							`[data-path="${firstChildPath}"]`
						) as HTMLButtonElement
					)?.focus();
				}
			} else {
				toggleOpen();
			}
			event.preventDefault();
			event.stopPropagation();
		} else if (event.key === 'Space') {
			expandNode(path, !isExpanded);
		} else if (event.key === 'Enter') {
			const form = event.currentTarget?.closest('form');
			if (form) {
				setTimeout(() => {
					form.dispatchEvent(new Event('submit', { bubbles: true }));
				});
			}
		}
	};

	const sortedChildren = node.children
		? [...node.children].sort((a, b) => {
				if (a.type === b.type) {
					return a.name.localeCompare(b.name);
				}
				return a.type === 'folder' ? -1 : 1;
		  })
		: [];
	return (
		<>
			{!isRoot && (
				<TreeGridRow
					level={level}
					positionInSet={position}
					setSize={setSize}
				>
					<TreeGridCell>
						{() =>
							editedNode?.reason === 'rename' &&
							editedNode.originalPath === path ? (
								<FilenameForm
									type={editedNode.type}
									onComplete={onEditedNodeComplete}
									onCancel={onEditedNodeCancel}
									level={level}
									initialValue={node.name}
								/>
							) : (
								<>
									{isHovered && node.type === 'file' && (
										<div
											className={css['dropIndicator']}
											style={{
												position: 'absolute',
												left: 0,
												right: 0,
												height: '2px',
												backgroundColor: '#007cba',
												marginTop: '-1px',
											}}
										/>
									)}
									<Button
										onClick={() => {
											toggleOpen();
											selectPath(path, node);
										}}
										onKeyDown={handleKeyDown}
										className={classNames(
											css['fileNodeButton'],
											{
												[css['selected']]:
													selectedNode === path,
												[css['dragging']]:
													isBeingDragged,
												[css['dropTarget']]:
													isHovered &&
													node.type === 'folder',
												'file-node-button': true,
											}
										)}
										data-path={path}
										draggable={true}
										onDragStart={() =>
											onDragStart?.(path, node.type)
										}
										onDragOver={(e) =>
											onDragOver?.(e, path, node.type)
										}
										onDrop={(e) =>
											onDrop?.(e, path, node.type)
										}
										onDragEnd={onDragEnd}
										style={{
											opacity: isBeingDragged ? 0.5 : 1,
											backgroundColor:
												isHovered &&
												node.type === 'folder'
													? '#e5f1f8'
													: undefined,
											position: 'relative',
										}}
									>
										<FileName
											node={node}
											isOpen={
												node.type === 'folder' &&
												isExpanded
											}
											level={level}
										/>
									</Button>
									<div className={css['moreActions']}>
										<DropdownMenu
											icon={more}
											label="More actions"
											controls={[
												{
													title: 'Rename',
													onClick: () => {
														startRenaming(path);
													},
												},
												{
													title: 'Delete',
													onClick: () => {
														onNodeDeleted(path);
													},
												},
											]}
										/>
									</div>
								</>
							)
						}
					</TreeGridCell>
				</TreeGridRow>
			)}
			{editedNode &&
				editedNode.reason === 'create' &&
				editedNode.parentPath === path && (
					<TreeGridRow
						level={level + 1}
						positionInSet={1}
						setSize={1}
					>
						<TreeGridCell>
							{() => (
								<FilenameForm
									type={editedNode.type}
									onComplete={onEditedNodeComplete}
									onCancel={onEditedNodeCancel}
									level={level + 1}
								/>
							)}
						</TreeGridCell>
					</TreeGridRow>
				)}
			{isExpanded &&
				sortedChildren?.map((child, index) => (
					<NodeRow
						key={child.name}
						node={child}
						level={level + 1}
						position={index + 1}
						setSize={sortedChildren.length}
						isRoot={false}
						parentPath={path}
					/>
				))}
		</>
	);
};

const FilenameForm: React.FC<{
	type: 'file' | 'folder';
	onComplete: (name: string) => void;
	onCancel: () => void;
	level: number;
	initialValue?: string;
}> = ({ type, onComplete, onCancel, level, initialValue = '' }) => {
	const inputRef = useRef<HTMLInputElement>(null);

	useEffect(() => {
		if (inputRef.current) {
			inputRef.current.value = initialValue;
			inputRef.current.focus();
			inputRef.current.select();
		}
	}, [initialValue]);

	const handleBlur = () => {
		const value = inputRef.current?.value.trim() || '';
		if (value) {
			onComplete(value);
		} else {
			onCancel();
		}
	};

	const handleKeyDown = (e: React.KeyboardEvent) => {
		if (e.key === 'Enter') {
			const value = inputRef.current?.value.trim() || '';
			if (value) {
				onComplete(value);
			} else {
				onCancel();
			}
		} else if (e.key === 'Escape') {
			onCancel();
		}
	};

	const indent: string[] = [];
	for (let i = 0; i < level; i++) {
		indent.push('&nbsp;&nbsp;&nbsp;&nbsp;');
	}

	return (
		<div className={css['editedNodeInput']}>
			<span
				aria-hidden="true"
				dangerouslySetInnerHTML={{ __html: indent.join('') }}
			></span>
			<Icon width={16} icon={type === 'folder' ? folder : file} />
			<input
				ref={inputRef}
				type="text"
				onBlur={handleBlur}
				onKeyDown={handleKeyDown}
				placeholder={`New ${type}...`}
			/>
		</div>
	);
};

const FileName: React.FC<{
	node: FileNode;
	level: number;
	isOpen?: boolean;
}> = ({ node, level, isOpen }) => {
	const indent: string[] = [];
	for (let i = 0; i < level; i++) {
		indent.push('&nbsp;&nbsp;&nbsp;&nbsp;');
	}
	return (
		<>
			<span
				aria-hidden="true"
				dangerouslySetInnerHTML={{ __html: indent.join('') }}
			></span>
			{node.type === 'folder' ? (
				<Icon width={16} icon={isOpen ? chevronDown : chevronRight} />
			) : (
				<div style={{ width: 16 }}>&nbsp;</div>
			)}
			<Icon width={16} icon={node.type === 'folder' ? folder : file} />
			<span className={css['fileName']}>{node.name}</span>
		</>
	);
};

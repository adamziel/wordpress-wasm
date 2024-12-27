import React, { useEffect, useRef, useState } from 'react';
import {
	__experimentalTreeGrid as TreeGrid,
	__experimentalTreeGridRow as TreeGridRow,
	__experimentalTreeGridCell as TreeGridCell,
	Button,
	Spinner,
	DropdownMenu,
} from '@wordpress/components';
import { Icon, chevronRight, chevronDown, more } from '@wordpress/icons';
import '@wordpress/components/build-style/style.css';
import css from './style.module.css';
import classNames from 'classnames';
import { folder, file } from '../icons';

export type FileNode = {
	name: string;
	type: 'file' | 'folder';
	children?: FileNode[];
};

export type FilePickerControlProps = {
	files: FileNode[];
	initialPath?: string;
	onSelect?: (path: string) => void;
	isLoading?: boolean;
	error?: string;
};

type ExpandedNodePaths = Record<string, boolean>;

export const FilePickerTree: React.FC<FilePickerControlProps> = ({
	isLoading = false,
	error = undefined,
	files,
	initialPath,
	onSelect = () => {},
}) => {
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

	const expandNode = (path: string, isOpen: boolean) => {
		setExpanded((prevState) => ({
			...prevState,
			[path]: isOpen,
		}));
	};

	const selectPath = (path: string) => {
		setSelectedPath(path);
		onSelect(path);
	};

	const generatePath = (node: FileNode, parentPath = ''): string => {
		return parentPath
			? `${parentPath}/${node.name}`.replaceAll(/\/+/g, '/')
			: node.name;
	};

	const [searchBuffer, setSearchBuffer] = useState('');
	const searchBufferTimeoutRef = useRef<NodeJS.Timeout | null>(null);
	function handleKeyDown(event: React.KeyboardEvent<HTMLDivElement>) {
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

	const sortedFiles = [...files].sort((a, b) => {
		if (a.type === b.type) {
			return a.name.localeCompare(b.name);
		}
		return a.type === 'folder' ? -1 : 1;
	});

	return (
		<div onKeyDown={handleKeyDown} ref={thisContainerRef}>
			<TreeGrid className={css['filePickerTree']}>
				{sortedFiles.map((file, index) => (
					<NodeRow
						key={file.name}
						node={file}
						level={0}
						position={index + 1}
						setSize={files.length}
						expandedNodePaths={expanded}
						expandNode={expandNode}
						selectedNode={selectedPath}
						selectPath={selectPath}
						generatePath={generatePath}
					/>
				))}
			</TreeGrid>
		</div>
	);
};

const NodeRow: React.FC<{
	node: FileNode;
	level: number;
	position: number;
	setSize: number;
	expandedNodePaths: ExpandedNodePaths;
	expandNode: (path: string, isOpen: boolean) => void;
	selectPath: (path: string) => void;
	selectedNode: string | null;
	generatePath: (node: FileNode, parentPath?: string) => string;
	parentPath?: string;
	parentMapping?: string;
}> = ({
	node,
	level,
	position,
	setSize,
	expandedNodePaths,
	expandNode,
	selectPath,
	generatePath,
	parentPath = '',
	selectedNode,
}) => {
	const path = generatePath(node, parentPath);
	const isExpanded = expandedNodePaths[path];

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
			<TreeGridRow
				level={level}
				positionInSet={position}
				setSize={setSize}
			>
				<TreeGridCell>
					{() => (
						<Button
							onClick={() => {
								toggleOpen();
								selectPath(path);
							}}
							onKeyDown={handleKeyDown}
							onFocus={() => {
								selectPath(path);
							}}
							className={classNames(css['fileNodeButton'], {
								[css['selected']]: selectedNode === path,
								'file-node-button': true,
							})}
							data-path={path}
						>
							<FileName
								node={node}
								isOpen={node.type === 'folder' && isExpanded}
								level={level}
							/>
						</Button>
					)}
				</TreeGridCell>
				<TreeGridCell>
					{() => (
						<DropdownMenu
							icon={more}
							label="More actions"
							controls={[]}
						/>
					)}
				</TreeGridCell>
			</TreeGridRow>
			{isExpanded &&
				sortedChildren.map((child, index) => (
					<NodeRow
						key={child.name}
						node={child}
						level={level + 1}
						position={index + 1}
						setSize={sortedChildren.length}
						expandedNodePaths={expandedNodePaths}
						expandNode={expandNode}
						selectedNode={selectedNode}
						selectPath={selectPath}
						generatePath={generatePath}
						parentPath={path}
					/>
				))}
		</>
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

import React from 'react';
import css from './style.module.css';

export function PathPreview({ path }: { path: string }) {
	if (!path) {
		return (
			<div className={css['pathPreview']}>
				<i>Select a path</i>
			</div>
		);
	}

	const segments = path.split('/');
	let pathPreviewEnd = (segments.length > 2 ? '/' : '') + segments.pop();
	if (pathPreviewEnd.length > 10) {
		pathPreviewEnd = pathPreviewEnd.substring(pathPreviewEnd.length - 10);
	}
	const pathPreviewStart = path.substring(
		0,
		path.length - pathPreviewEnd.length
	);
	return (
		<div
			className={css['pathPreview']}
			data-content-start={pathPreviewStart}
			data-content-end={pathPreviewEnd}
		></div>
	);
}

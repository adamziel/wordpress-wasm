import React, { useEffect, useState } from 'react';
import { logger } from '@php-wasm/logger';
import { TextareaControl, TextControl } from '@wordpress/components';
import { Blueprint } from '@wp-playground/blueprints';
import { useDispatch } from 'react-redux';
import {
	PlaygroundDispatch,
	PlaygroundReduxState,
	useAppSelector,
} from '../../lib/state/redux/store';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { Modal } from '../../components/modal';
import ModalButtons from '../modal/modal-buttons';

export function ErrorReportModal(props: { blueprint: Blueprint }) {
	const activeModal = useAppSelector(
		(state: PlaygroundReduxState) => state.ui.activeModal
	);
	const dispatch: PlaygroundDispatch = useDispatch();
	const [loading, setLoading] = useState(false);
	const [text, setText] = useState('');
	const [logs, setLogs] = useState('');
	const [url, setUrl] = useState('');
	const [submitted, setSubmitted] = useState(false);
	const [submitError, setSubmitError] = useState('');

	useEffect(() => {
		resetForm();
		setLogs(logger.getLogs().join('\n'));
		setUrl(window.location.href);
	}, [activeModal, logs, setLogs]);

	function resetForm() {
		setText('');
		setLogs('');
		setUrl('');
	}

	function resetSubmission() {
		setSubmitted(false);
		setSubmitError('');
	}

	function onClose() {
		dispatch(setActiveModal(null));
		resetForm();
		resetSubmission();
	}

	function getContext() {
		return {
			...props.blueprint.preferredVersions,
			userAgent: navigator.userAgent,
			...((window.performance as any)?.memory ?? {}),
			window: {
				width: window.innerWidth,
				height: window.innerHeight,
			},
		};
	}

	async function onSubmit() {
		setLoading(true);
		const formdata = new FormData();
		formdata.append('description', text);
		if (logs) {
			formdata.append('logs', logs);
		}
		if (url) {
			formdata.append('url', url);
		}
		formdata.append('context', JSON.stringify(getContext()));
		formdata.append('blueprint', JSON.stringify(props.blueprint));
		try {
			const response = await fetch(
				'https://playground.wordpress.net/logger.php',
				{
					method: 'POST',
					body: formdata,
				}
			);
			setSubmitted(true);

			const body = await response.json();
			if (!body.ok) {
				throw new Error(body.error);
			}

			setSubmitError('');
			resetForm();
		} catch (e) {
			setSubmitError((e as Error).message);
		} finally {
			setLoading(false);
		}
	}

	function getTitle() {
		if (!submitted) {
			return 'Report error';
		} else if (submitError) {
			return 'Failed to report the error';
		} else {
			return 'Thank you for reporting the error';
		}
	}

	function getContent() {
		if (!submitted) {
			return (
				<>
					Playground crashed because of an error. You can help resolve
					the issue by sharing the error details with us.
				</>
			);
		} else if (submitError) {
			return (
				<>
					We were unable to submit the error report. Please try again
					or open an{' '}
					<a
						href="https://github.com/WordPress/wordpress-playground/issues/"
						target="_blank"
						rel="noopener noreferrer"
					>
						issue on GitHub.
					</a>
				</>
			);
		} else {
			return (
				<>
					Your report has been submitted to the{' '}
					<a
						href="https://wordpress.slack.com/archives/C06Q5DCKZ3L"
						target="_blank"
						rel="noopener noreferrer"
					>
						Making WordPress #playground-logs Slack channel
					</a>{' '}
					and will be reviewed by the team.
				</>
			);
		}
	}

	/**
	 * Show the form if the error has not been submitted or if there was an error
	 * submitting it.
	 *
	 * @return {boolean}
	 */
	function showForm() {
		return !submitted || submitError;
	}

	return (
		<Modal title={getTitle()} onRequestClose={onClose} small>
			<p>{getContent()}</p>
			{showForm() && (
				<>
					<TextareaControl
						label="How can we recreate this error?"
						help="Describe what caused the error and how can we recreate it."
						value={text}
						onChange={setText}
						required={true}
					/>
					<TextareaControl
						label="Logs"
						value={logs}
						onChange={setLogs}
					/>

					<TextControl label="Url" value={url} onChange={setUrl} />

					<ModalButtons
						areBusy={loading}
						areDisabled={loading || !text}
						onCancel={onClose}
						onSubmit={onSubmit}
						submitText="Report error"
					/>
				</>
			)}
		</Modal>
	);
}

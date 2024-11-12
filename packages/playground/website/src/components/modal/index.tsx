import React from 'react';
import { Modal } from '@wordpress/components';
import css from './style.module.css';
import { ModalProps } from '@wordpress/components/build-types/modal/types';
import classNames from 'classnames';

interface ModalComponentProps extends ModalProps {
	small?: boolean;
}
export function ModalComponent({
	small,
	className,
	children,
	...rest
}: ModalComponentProps) {
	const modalClass = classNames(css.modal, {
		[css.modalSmall]: small,
	}, className);

	return (
		<Modal
			className={modalClass}
			{...rest}
		>
			{children}
		</Modal>
	);
}

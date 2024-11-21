import React from 'react';
import { Modal as WordPressModal } from '@wordpress/components';
import type { ModalProps as WordPressModalProps } from '@wordpress/components/build-types/modal/types';
import classNames from 'classnames';
import css from './style.module.css';

interface ModalProps extends WordPressModalProps {
	small?: boolean;
}
export function Modal({ small, className, children, ...rest }: ModalProps) {
	const modalClass = classNames(
		css.modal,
		{
			[css.modalSmall]: small,
		},
		className
	);

	return (
		<WordPressModal className={modalClass} {...rest}>
			{children}
		</WordPressModal>
	);
}

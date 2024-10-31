import { Button, Flex } from '@wordpress/components';
import React from 'react';

interface ModalButtonsProps {
	submitText?: string;
	areDisabled?: boolean;
	areBusy?: boolean;
	onCancel?: () => void;
	onSubmit?: (e: any) => void;
}
export default function ModalButtons({ submitText = 'Submit', areDisabled = false, areBusy, onCancel, onSubmit }: ModalButtonsProps) {
	return (
		<Flex
			justify={'end'}
		>
			<Button
				isBusy={areBusy}
				disabled={areDisabled}
				variant="link"
				onClick={onCancel}
			>
				Cancel
			</Button>
			<Button
				isBusy={areBusy}
				disabled={areDisabled}
				variant="primary"
				onClick={onSubmit}
			>
				{submitText}
			</Button>
		</Flex>
	)
}

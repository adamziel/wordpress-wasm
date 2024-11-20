/**
 * Wordpress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { key, login } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	Icon,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import './editor.scss';
import metadata from './block.json';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, isSelected }) {
	const { username, password } = attributes;

	return (
		<p {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={login} size={28} className='step-icon' />
							<VStack spacing={0}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && username && password && (
									<HStack spacing={1}>
										<Text weight={600}>{username}</Text>
										<Icon icon={key} style={{ fill: "#949494" }} />
									</HStack>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<DataForm
								data={{
									username,
									password
								}}
								fields={[
									{
										id: 'username',
										label: 'Username',
										type: 'text',
										placeholder: 'Enter username'
									},
									{
										id: 'password',
										label: 'Password',
										type: 'text',
										placeholder: 'Enter password'
									},
								]}
								form={{
									fields: [
										'username',
										'password'
									]
								}}
								onChange={setAttributes}
							/>
						)}
					</VStack>
				}
			/>
		</p>
	);
}

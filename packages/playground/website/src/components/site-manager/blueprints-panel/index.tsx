import css from './style.module.css';
import {
	Button,
	Flex,
	FlexItem,
	Spinner,
	Icon,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { chevronLeft } from '@wordpress/icons';
import { DataViews } from '@wordpress/dataviews';
import type { Field, View } from '@wordpress/dataviews';
import classNames from 'classnames';
import { useState } from 'react';
import { PlaygroundRoute, redirectTo } from '../../../lib/state/url/router';
import useFetch from '../../../lib/hooks/use-fetch';
import { useAppDispatch } from '../../../lib/state/redux/store';
import {
	setSiteManagerOpen,
	setSiteManagerSection,
} from '../../../lib/state/redux/slice-ui';

type BlueprintsIndexEntry = {
	title: string;
	description: string;
	author: string;
	categories: string[];
	path: string;
};

export function BlueprintsPanel({
	className,
	mobileUi,
}: {
	className: string;
	mobileUi?: boolean;
}) {
	// @TODO: memoize across component loads.
	const { data, isLoading, isError } = useFetch<
		Record<string, BlueprintsIndexEntry>
	>(
		'/proxy/network-first-fetch/https://raw.githubusercontent.com/WordPress/blueprints/trunk/index.json'
	);

	const [view, setView] = useState<View>({
		type: 'list',
		fields: ['header', 'description'],
	});

	const dispatch = useAppDispatch();

	let indexEntries: BlueprintsIndexEntry[] = data
		? Object.entries(data).map(([path, entry]) => ({ ...entry, path }))
		: [];

	if (view.search) {
		indexEntries = indexEntries.filter((entry) => {
			return [entry.title, entry.description]
				.join(' ')
				.toLocaleLowerCase()
				.includes(view.search!.toLocaleLowerCase());
		});
	}

	function previewBlueprint(blueprintPath: BlueprintsIndexEntry['path']) {
		dispatch(setSiteManagerOpen(false));
		redirectTo(
			PlaygroundRoute.newTemporarySite({
				query: {
					name: 'Blueprint preview',
					// Explicitly do not use joinPaths() here as it normalizes the input and
					// rewrites https:// as https:/
					'blueprint-url': `https://raw.githubusercontent.com/WordPress/blueprints/trunk/${blueprintPath.replace(
						/^\//,
						''
					)}`,
				},
			})
		);
	}

	const fields: Field<BlueprintsIndexEntry>[] = [
		{
			id: 'header',
			label: 'Header',
			enableHiding: false,
			render: ({ item }) => {
				return (
					<HStack spacing={2} justify="space-between">
						<VStack spacing={0} style={{ flexGrow: 1 }}>
							<h3 className={css.blueprintTitle}>{item.title}</h3>
							<Text>
								By{' '}
								<a
									target="_blank"
									rel="noreferrer"
									href={`https://github.com/${item.author}`}
								>
									{item.author}
								</a>
							</Text>
						</VStack>
						<Button style={{ flexShrink: 0 }} variant="primary">
							Preview
						</Button>
					</HStack>
				);
			},
		},
		{
			id: 'description',
			label: 'Description',
			render: ({ item }) => {
				return <Text>{item.description}</Text>;
			},
		},
	];

	return (
		<section
			className={classNames(className, css.blueprintsPanel, {
				[css.isMobile]: mobileUi,
			})}
		>
			<Flex
				gap={0}
				direction="column"
				justify="flex-start"
				expanded={true}
			>
				<FlexItem style={{ flexShrink: 0, paddingBottom: 24 }}>
					<FlexItem style={{ flexShrink: 0 }}>
						<Flex
							direction="row"
							gap={2}
							justify="space-between"
							align="center"
							expanded={true}
							className={css.padded}
							style={{ paddingBottom: 10 }}
						>
							{mobileUi && (
								<FlexItem style={{ marginLeft: -17 }}>
									<Button
										variant="link"
										label="Back to sites list"
										icon={() => (
											<Icon
												icon={chevronLeft}
												size={38}
											/>
										)}
										className={css.grayLinkDark}
										onClick={() => {
											dispatch(
												setSiteManagerSection('sidebar')
											);
										}}
									/>
								</FlexItem>
							)}
							<FlexItem style={{ flexGrow: 1 }}>
								<Flex
									direction="column"
									gap={0.25}
									expanded={true}
								>
									<h2 className={css.sectionTitle}>
										Blueprints Gallery
									</h2>
								</Flex>
							</FlexItem>
						</Flex>
					</FlexItem>
					<FlexItem className={css.paddedH}>
						<p>
							Blueprints are predefined configurations for setting
							up WordPress. Here you can find all the Blueprints
							from the WordPress{' '}
							<a
								href="https://github.com/WordPress/blueprints"
								target="_blank"
								rel="noreferrer"
							>
								Blueprints gallery
							</a>
							. Try them out in Playground and learn more in the{' '}
							<a
								href="https://wordpress.github.io/wordpress-playground/blueprints"
								target="_blank"
								rel="noreferrer"
							>
								Blueprints documentation
							</a>
							.
						</p>
					</FlexItem>
				</FlexItem>
				{isLoading ? (
					<FlexItem
						style={{
							alignSelf: 'stretch',
							display: 'flex',
							justifyContent: 'center',
							alignItems: 'center',
						}}
					>
						<Spinner style={{ width: 32, height: 32 }} />
					</FlexItem>
				) : isError ? (
					<FlexItem
						className={css.paddedH}
						style={{ alignSelf: 'stretch' }}
					>
						<p>
							Could not load the Blueprints from the gallery. Try
							again later.
						</p>
					</FlexItem>
				) : (
					<FlexItem style={{ alignSelf: 'stretch' }}>
						<DataViews<BlueprintsIndexEntry>
							data={indexEntries as BlueprintsIndexEntry[]}
							view={view}
							onChangeView={setView}
							onChangeSelection={(newSelection) => {
								if (newSelection?.length) {
									previewBlueprint(newSelection[0]);
								}
							}}
							search={true}
							isLoading={isLoading}
							fields={fields}
							header={null}
							getItemId={(item) => item?.path}
							paginationInfo={{
								totalItems: indexEntries.length,
								totalPages: 1,
							}}
							defaultLayouts={{
								list: {},
							}}
						/>
					</FlexItem>
				)}
			</Flex>
		</section>
	);
}

import { useMemo } from 'react';
import css from './style.module.css';
import { Button, __experimentalVStack as VStack } from '@wordpress/components';
import { useAppSelector } from '../../../lib/state/redux/store';
import { selectSiteBySlug } from '../../../lib/state/redux/slice-sites';
import { redirectTo, PlaygroundRoute } from '../../../lib/state/url/router';
import {
	SiteFormData,
	UnconnectedSiteSettingsForm,
} from './unconnected-site-settings-form';

export function TemporarySiteSettingsForm({
	siteSlug,
	onSubmit,
}: {
	siteSlug: string;
	onSubmit?: () => void;
}) {
	const siteInfo = useAppSelector((state) =>
		selectSiteBySlug(state, siteSlug)
	)!;
	const updateSite = async (data: SiteFormData) => {
		redirectTo(
			PlaygroundRoute.newTemporarySite({
				...(siteInfo.originalUrlParams || {}),
				query: {
					...(siteInfo.originalUrlParams?.searchParams || {}),
					php: data.phpVersion,
					wp: data.wpVersion,
					networking: data.withNetworking ? 'yes' : 'no',
					language: data.language,
					multisite: data.multisite ? 'yes' : 'no',
				},
			})
		);
		onSubmit?.();
		// @TODO: Display a notification of updated site or forked site
	};
	const defaultValues = useMemo<Partial<SiteFormData>>(() => {
		const searchParams = siteInfo.originalUrlParams?.searchParams || {};
		const runtimeConf = siteInfo.metadata?.runtimeConfiguration || {};
		return {
			phpVersion: runtimeConf?.preferredVersions?.php as any,
			wpVersion: runtimeConf?.preferredVersions?.wp as any,
			withNetworking: runtimeConf?.features?.networking,
			language: 'language' in searchParams ? searchParams.language : '',
			multisite:
				'multisite' in searchParams
					? searchParams.multisite === 'yes'
					: false,
		};
	}, [siteInfo]);

	return (
		<UnconnectedSiteSettingsForm
			className="is-temporary-site"
			onSubmit={updateSite}
			defaultValues={defaultValues}
			footer={
				<VStack
					justify="flex-end"
					spacing={6}
					style={{ margin: 0 }}
					className={`${css.footer} ${css.formSection}`}
				>
					<p>
						<b>Destructive action!</b> Applying these settings will
						reset the WordPress site to its initial state.
					</p>
					<Button type="submit" variant="primary">
						Apply Settings & Reset Playground
					</Button>
				</VStack>
			}
		/>
	);
}

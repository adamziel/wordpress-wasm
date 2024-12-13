import { ProgressTracker } from '@php-wasm/progress';
import { UniversalPHP } from '@php-wasm/universal';
import { FileReference, DirectoryReference, Directory } from '../resources';
import { ActivatePluginStep } from './activate-plugin';
import { DefineSiteUrlStep } from './define-site-url';
import { InstallPluginStep, InstallPluginOptions } from './install-plugin';
import { InstallThemeStep, InstallThemeOptions } from './install-theme';
import { LoginStep } from './login';
import {
	RunWpInstallationWizardStep,
	WordPressInstallationOptions,
} from './run-wp-installation-wizard';
import { SetSiteOptionsStep, UpdateUserMetaStep } from './site-data';
import { RmStep } from './rm';
import { CpStep } from './cp';
import { RmdirStep } from './rmdir';
import { RunSqlStep } from './run-sql';
import { MkdirStep } from './mkdir';
import { MvStep } from './mv';
import { RunPHPStep } from './run-php';
import { RunPHPWithOptionsStep } from './run-php-with-options';
import { RequestStep } from './request';
import { WriteFileStep } from './write-file';
import { WriteFilesStep } from './write-files';
import { DefineWpConfigConstsStep } from './define-wp-config-consts';
import { ActivateThemeStep } from './activate-theme';
import { UnzipStep } from './unzip';
import { ImportWordPressFilesStep } from './import-wordpress-files';
import { ImportThemeStarterContentStep } from './import-theme-starter-content';
import { ImportWxrStep } from './import-wxr';
import { EnableMultisiteStep } from './enable-multisite';
import { WPCLIStep } from './wp-cli';
import { ResetDataStep } from './reset-data';
import { SetSiteLanguageStep } from './set-site-language';

export type Step = GenericStep<FileReference, DirectoryReference>;
export type StepDefinition = Step & {
	progress?: {
		weight?: number;
		caption?: string;
	};
};

export { wpContentFilesExcludedFromExport } from '../utils/wp-content-files-excluded-from-exports';

/**
 * If you add a step here, make sure to also
 * add it to the exports below.
 */
export type GenericStep<FileResource, DirectoryResource> =
	| ActivatePluginStep
	| ActivateThemeStep
	| CpStep
	| DefineWpConfigConstsStep
	| DefineSiteUrlStep
	| EnableMultisiteStep
	| ImportWxrStep<FileResource, DirectoryResource>
	| ImportThemeStarterContentStep
	| ImportWordPressFilesStep<FileResource>
	| InstallPluginStep<FileResource, DirectoryResource>
	| InstallThemeStep<FileResource, DirectoryResource>
	| LoginStep
	| MkdirStep
	| MvStep
	| ResetDataStep
	| RequestStep
	| RmStep
	| RmdirStep
	| RunPHPStep
	| RunPHPWithOptionsStep
	| RunWpInstallationWizardStep
	| RunSqlStep<FileResource>
	| SetSiteOptionsStep
	| UnzipStep<FileResource>
	| UpdateUserMetaStep
	| WriteFileStep<FileResource>
	| WriteFilesStep<DirectoryResource>
	| WPCLIStep
	| SetSiteLanguageStep;

export type {
	ActivatePluginStep,
	ActivateThemeStep,
	CpStep,
	DefineWpConfigConstsStep,
	DefineSiteUrlStep,
	EnableMultisiteStep,
	ImportWxrStep,
	ImportThemeStarterContentStep,
	ImportWordPressFilesStep,
	InstallPluginStep,
	InstallPluginOptions,
	InstallThemeStep,
	InstallThemeOptions,
	LoginStep,
	MkdirStep,
	MvStep,
	ResetDataStep,
	RequestStep,
	RmStep,
	RmdirStep,
	RunPHPStep,
	RunPHPWithOptionsStep,
	RunWpInstallationWizardStep,
	RunSqlStep,
	WordPressInstallationOptions,
	SetSiteOptionsStep,
	UnzipStep,
	UpdateUserMetaStep,
	WriteFileStep,
	WriteFilesStep,
	WPCLIStep,
	SetSiteLanguageStep,
};

/**
 * Progress reporting details.
 */
export type StepProgress = {
	tracker: ProgressTracker;
	initialCaption?: string;
};

export type StepHandler<
	S extends GenericStep<File, Directory>,
	Return = any
> = (
	/**
	 * A PHP instance or Playground client.
	 */
	php: UniversalPHP,
	args: Omit<S, 'step'>,
	progressArgs?: StepProgress
) => Return;

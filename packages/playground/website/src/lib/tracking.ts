import { Blueprint, isStepDefinition } from '@wp-playground/blueprints';
import { logger } from '@php-wasm/logger';

/**
 * Declare the global window.gtag function
 */
declare global {
	interface Window {
		gtag: any;
	}
}

/**
 * Google Analytics event names
 */
type GAEvent = 'load' | 'step' | 'installPlugin' | 'installTheme' | 'error';

/**
 * Log a tracking event to Google Analytics
 * @param GAEvent The event name
 * @param Object Event data
 */
export const logTrackingEvent = (
	event: GAEvent,
	data?: { [key: string]: string }
) => {
	try {
		if (typeof window === 'undefined' || !window.gtag) {
			return;
		}
		window.gtag('event', event, data);
	} catch (error) {
		logger.warn('Failed to log tracking event', event, data, error);
	}
};

/**
 * Log error events
 *
 * @param error The error
 */
export const logErrorEvent = (source: string) => {
	logTrackingEvent('error', {
		source,
	});
};

/**
 * Log Blueprint events
 * @param blueprint The Blueprint
 */
export const logBlueprintEvents = (blueprint: Blueprint) => {
	/**
	 * Log the names of provided Blueprint steps.
	 * Only the names (e.g. "runPhp" or "login") are logged. Step options like
	 * code, password, URLs are never sent anywhere.
	 *
	 * For installPlugin and installTheme, the plugin/theme slug is logged.
	 */
	if (blueprint.steps) {
		for (const step of blueprint.steps) {
			if (!isStepDefinition(step)) {
				continue;
			}
			logTrackingEvent('step', { step: step.step });
			if (
				step.step === 'installPlugin' &&
				(step as any).pluginData.slug
			) {
				logTrackingEvent('installPlugin', {
					plugin: (step as any).pluginData.slug,
				});
			} else if (
				step.step === 'installTheme' &&
				(step as any).themeData.slug
			) {
				logTrackingEvent('installTheme', {
					theme: (step as any).themeData.slug,
				});
			}
		}
	}

	/**
	 * Because the Blueprint isn't compiled, we need to log the plugins
	 * that are installed using the `plugins` shorthand.
	 */
	if (blueprint.plugins) {
		for (const plugin of blueprint.plugins) {
			if (typeof plugin !== 'string') {
				continue;
			}
			logTrackingEvent('step', { step: 'installPlugin' });
			logTrackingEvent('installPlugin', {
				plugin,
			});
		}
	}
};

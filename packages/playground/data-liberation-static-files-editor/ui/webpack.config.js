const defaultConfig = require('@wordpress/scripts/config/webpack.config');

/**
 * Override the css-loader to use camelCase for class names in CSS modules.
 */
module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map((rule) => {
			if (rule.test?.toString().includes('.css')) {
				return {
					...rule,
					use: rule.use.map((loader) => {
						if (loader.loader?.includes('/css-loader/')) {
							return {
								...loader,
								options: {
									...loader.options,
									modules: {
										...loader.options?.modules,
										exportLocalsConvention: 'camelCase',
									},
								},
							};
						}
						return loader;
					}),
				};
			}
			return rule;
		}),
	},
};

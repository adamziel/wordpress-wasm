module.exports = {
	meta: {
		type: 'problem',
		docs: {
			description:
				'Avoid using @wp-playground/wordpress-builds dependency',
		},
	},
	create(context) {
		return {
			ImportDeclaration: (node) => {
				if (node.source.value === '@wp-playground/wordpress-builds') {
					context.report({
						loc: node.source,
						message:
							'Avoid using @wp-playground/wordpress-builds dependency',
					});
				}
			},
		};
	},
};

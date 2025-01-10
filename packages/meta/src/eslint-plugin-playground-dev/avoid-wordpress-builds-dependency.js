const description =
	'Avoid dependency on @wp-playground/wordpress-builds ' +
	'because it is a private, unpublished package. Public, ' +
	'published packages will be broken if they have a runtime ' +
	'dependency on an unpublished package.';
module.exports = {
	meta: {
		type: 'problem',
		docs: { description },
	},
	create(context) {
		return {
			ImportDeclaration: (node) => {
				if (node.source.value === '@wp-playground/wordpress-builds') {
					context.report({
						loc: node.loc,
						message:
							description +
							' ' +
							'If you need this dependency and deem it safe, ' +
							'please disable the rule for this line and leave ' +
							'a comment explaining why.',
					});
				}
			},
		};
	},
};

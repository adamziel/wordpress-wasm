const wpBuildsDepRule = require('./avoid-wordpress-builds-dependency');
const plugin = {
	rules: {
		'avoid-wordpress-builds-dependency': wpBuildsDepRule,
	},
};
module.exports = plugin;

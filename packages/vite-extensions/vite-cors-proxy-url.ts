import virtualModule from './vite-virtual-module';

export const corsProxyUrlPlugin = (mode: string) =>
	virtualModule({
		name: 'cors-proxy-url',
		content: `
    export const corsProxyUrl = '${
		mode === 'production'
			? '/cors-proxy.php'
			: 'http://127.0.0.1:5263/cors-proxy.php'
	}';`,
	});

import * as Comlink from 'comlink';

export default function exposeComlinkAPI(desiredApi: any=null) {
    let setReady;
    const ready = new Promise((resolve) => {
        setReady = resolve;
    });

    const datasource: any = {
        root: {
            isReady: () => ready,
        },
        api: desiredApi,
        overrides: {}
    };
    const exposed = new Proxy(datasource, {
        get: (target, prop) => {
            if (prop === 'isReady') {
                return () => ready;
            }
            if (prop in target.overrides) {
                return target.overrides[prop];
            }
            return target.api![prop];
        }
    });

    Comlink.expose(
        exposed,
        typeof window !== 'undefined' ? Comlink.windowEndpoint(self.parent) : undefined
    );
    return {
        exposed,
        setReady,
        replace: (api: any) => {
            datasource.api = api;
            datasource.overrides = {};
        },
        extend: (overrideMethods: any) => {
            Object.assign(datasource.overrides, overrideMethods);
        }
    }
}

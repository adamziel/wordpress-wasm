declare global {
	interface Window {
		wp: {
			element: any;
			blockEditor: any;
			dataLiberationCreateElementDecorator: (fn: Function) => Function;
		};
		React: {
			createElement: Function & { patched?: boolean };
		};
		ReactJSXRuntime: {
			jsx: Function;
			jsxs: Function;
		};
	}
}

/**
 * Adds a new "Local files" tab to the list view sidebar.
 *
 * Don't do this at home! This function monkey-patches
 * React.createElement to ensure the "local files" tab
 * is passed as one of the <ListViewSidebar/>'s props.
 *
 * This makes assumptions about the internals of the
 * post editor and will likely break in future versions.
 *
 * A more future-proof solution would involve either:
 *
 * * Contributing a new extension point to the post editor.
 * * Creating a dedicated editor for local files. This would
 *   require heavy maintenance, though, and reconciling changes
 *   and patches from the post editor so it's not ideal.
 *
 * @param tab - The tab to add to the list view sidebar.
 */
export function addLocalFilesTab(tab: {
	name: string;
	title: string;
	panel: React.ReactElement;
}) {
	function patchArguments(args: any[]) {
		let [type, props, ...children] = args;
		const newProps = { ...props };
		if (!('tabs' in newProps)) {
			return [type, newProps, ...children];
		}
		const hasListViewTab = newProps.tabs.find(
			(tab) => tab.name === 'list-view'
		);
		if (!hasListViewTab) {
			return [type, newProps, ...children];
		}
		const hasLocalFilesTab = newProps.tabs.find(
			(tab) => tab.name === 'local-files'
		);
		if (!hasLocalFilesTab) {
			newProps.tabs.unshift(tab);
		}
		newProps.defaultTabId = 'local-files';
		return [type, newProps, ...children];
	}

	// Monkey-patch window.React.createElement
	const originalCreateElement = window.React.createElement as any;
	(window.React as any).createElement = function (...args: any[]) {
		return originalCreateElement(...patchArguments(args));
	};

	// Monkey-patch window.ReactJSXRuntime.jsx
	const originalJSX = window.ReactJSXRuntime.jsx;
	window.ReactJSXRuntime.jsx = (...args: any[]) => {
		return originalJSX(...patchArguments(args));
	};
}

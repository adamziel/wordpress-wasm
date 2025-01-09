import { PHP } from './php';
import { PHPEvent } from './universal-php';

export interface RotateOptions {
	php: PHP;
	cwd: string;
	recreateRuntime: () => Promise<number> | number;
	maxRequests: number;
}

/**
 * Listens to PHP events and swaps the internal PHP Runtime for a fresh one
 * after a certain number of run() calls (which are responsible for handling
 * HTTP requests).
 *
 * Why? Because PHP and PHP extension have a memory leak. Each request leaves
 * the memory a bit more fragmented and with a bit less available space than
 * before. Eventually, new allocations start failing.
 *
 * Rotating the PHP instance may seem like a workaround, but it's actually
 * what PHP-FPM does natively:
 *
 * https://www.php.net/manual/en/install.fpm.configuration.php#pm.max-tasks
 *
 * @return cleanup function to restore
 */
export function rotatePHPRuntime({
	php,
	cwd,
	recreateRuntime,
	/*
	 * 400 is an arbitrary number that should trigger a rotation
	 * way before the memory gets too fragmented. If it doesn't,
	 * let's explore:
	 * * Rotating based on an actual memory usage and
	 *   fragmentation.
	 * * Resetting HEAP to its initial value.
	 */
	maxRequests = 400,
}: RotateOptions) {
	let runtimeRequestCount = 0;
	async function rotateRuntime() {
		const release = await php.semaphore.acquire();
		try {
			await php.hotSwapPHPRuntime(await recreateRuntime(), cwd);

			// A new runtime has handled zero requests.
			runtimeRequestCount = 0;
		} finally {
			release();
		}
	}

	async function rotateRuntimeAfterMaxRequests() {
		if (++runtimeRequestCount < maxRequests) {
			return;
		}
		await rotateRuntime();
	}

	async function rotateRuntimeForPhpWasmError(event: PHPEvent) {
		if (event.type === 'request.error' && event.source === 'php-wasm') {
			await rotateRuntime();
		}
	}

	php.addEventListener('request.error', rotateRuntimeForPhpWasmError);
	php.addEventListener('request.end', rotateRuntimeAfterMaxRequests);

	return function () {
		php.removeEventListener('request.error', rotateRuntimeForPhpWasmError);
		php.removeEventListener('request.end', rotateRuntimeAfterMaxRequests);
	};
}

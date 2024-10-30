import { concatArrayBuffers } from '../utils';

/**
 * Implements the TLS 1.2 PRF using HMAC-SHA256.
 *
 * See https://datatracker.ietf.org/doc/html/rfc5246#section-5
 */
export async function tls12Prf(
	secret: ArrayBuffer,
	label: ArrayBuffer,
	seed: ArrayBuffer,
	outputLength: number
): Promise<ArrayBuffer> {
	const seedBytes = concatArrayBuffers([label, seed]);

	// Import the secret as a CryptoKey
	const hmacKey = await crypto.subtle.importKey(
		'raw',
		secret,
		{ name: 'HMAC', hash: { name: 'SHA-256' } },
		false,
		['sign']
	);

	let A = seedBytes; // Initialize A(0) = seedBytes
	const resultBuffers: ArrayBuffer[] = [];

	while (concatArrayBuffers(resultBuffers).byteLength < outputLength) {
		// A(i) = HMAC_hash(secret, A(i-1))
		A = await hmacSha256(hmacKey, A);

		// Compute HMAC_hash(secret, A(i) + seedBytes)
		const hmacInput = concatArrayBuffers([A, seedBytes]);
		const fragment = await hmacSha256(hmacKey, hmacInput);

		resultBuffers.push(fragment);
	}

	// Concatenate and trim the result to the desired length
	const fullResult = concatArrayBuffers(resultBuffers);
	return fullResult.slice(0, outputLength);
}

export async function hmacSha256(
	key: CryptoKey,
	data: ArrayBuffer
): Promise<ArrayBuffer> {
	return await crypto.subtle.sign(
		{ name: 'HMAC', hash: 'SHA-256' },
		key,
		data
	);
}

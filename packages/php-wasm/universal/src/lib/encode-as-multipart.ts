/**
 * Encodes a multipart/form-data request body.
 *
 * @param   data - The form data to encode.
 * @returns The encoded body and a correctly formatted content type header.
 */
export async function encodeAsMultipart(
	data: Record<string, string | Uint8Array | File>
) {
	const boundary = `----${Math.random().toString(36).slice(2)}`;
	const contentType = `multipart/form-data; boundary=${boundary}`;

	const textEncoder = new TextEncoder();
	const parts: (string | Uint8Array)[] = [];
	for (const [name, value] of Object.entries(data)) {
		parts.push(`--${boundary}\r\n`);
		parts.push(`Content-Disposition: form-data; name="${name}"`);
		if (value instanceof File) {
			parts.push(`; filename="${value.name}"`);
		}
		parts.push(`\r\n`);
		if (value instanceof File) {
			parts.push(`Content-Type: application/octet-stream`);
			parts.push(`\r\n`);
		}
		parts.push(`\r\n`);
		if (value instanceof File) {
			parts.push(await fileToUint8Array(value));
		} else {
			parts.push(value);
		}
		parts.push(`\r\n`);
	}
	parts.push(`--${boundary}--\r\n`);

	const length = parts.reduce((acc, part) => acc + part.length, 0);
	const bytes = new Uint8Array(length);
	let offset = 0;
	for (const part of parts) {
		bytes.set(
			typeof part === 'string' ? textEncoder.encode(part) : part,
			offset
		);
		offset += part.length;
	}
	return { bytes, contentType };
}

function fileToUint8Array(file: File): Promise<Uint8Array> {
	/**
	 * @mbuella: Use File.arrayBuffer() to get a Uint8Array from a file, avoiding FileReader
	 * which is browser-specific. This method is supported in major browsers and NodeJS/Deno runtimes.
	 */
	return file.arrayBuffer().then((fileBuffer) => new Uint8Array(fileBuffer));
}

export function flipObject(obj: Record<any, any>) {
	return Object.fromEntries(Object.entries(obj).map(([k, v]) => [v, k]));
}

export function concatUint8Arrays(arrays: Uint8Array[]): Uint8Array {
	let totalLength = 0;
	arrays.forEach((a) => (totalLength += a.length));
	const result = new Uint8Array(totalLength);
	let offset = 0;
	arrays.forEach((a) => {
		result.set(a, offset);
		offset += a.length;
	});
	return result;
}

export function concatArrayBuffers(buffers: ArrayBuffer[]): ArrayBuffer {
	return concatUint8Arrays(buffers.map((b) => new Uint8Array(b))).buffer;
}

export function as2Bytes(value: number): Uint8Array {
	return new Uint8Array([(value >> 8) & 0xff, value & 0xff]);
}

export function as3Bytes(value: number): Uint8Array {
	return new Uint8Array([
		(value >> 16) & 0xff,
		(value >> 8) & 0xff,
		value & 0xff,
	]);
}

export function as8Bytes(value: number): Uint8Array {
	const buffer = new ArrayBuffer(8);
	const view = new DataView(buffer);
	view.setBigUint64(0, BigInt(value), false); // false for big-endian
	return new Uint8Array(buffer);
}
export class ArrayBufferReader {
	private view: DataView;
	offset = 0;
	constructor(private buffer: ArrayBuffer) {
		this.view = new DataView(buffer);
	}

	readUint8(): number {
		const value = this.view.getUint8(this.offset);
		this.offset += 1;
		return value;
	}
	readUint16(): number {
		const value = this.view.getUint16(this.offset);
		this.offset += 2;
		return value;
	}
	readUint32(): number {
		const value = this.view.getUint32(this.offset);
		this.offset += 4;
		return value;
	}
	readUint8Array(length: number): Uint8Array {
		const value = this.buffer.slice(this.offset, this.offset + length);
		this.offset += length;
		return new Uint8Array(value);
	}

	isFinished() {
		return this.offset >= this.buffer.byteLength;
	}
}

export class ArrayBufferWriter {
	buffer: ArrayBuffer;
	view: DataView;
	uint8Array: Uint8Array;

	private offset = 0;

	constructor(length: number) {
		this.buffer = new ArrayBuffer(length);
		this.uint8Array = new Uint8Array(this.buffer);
		this.view = new DataView(this.buffer);
	}

	writeUint8(value: number) {
		this.view.setUint8(this.offset, value);
		this.offset += 1;
	}

	writeUint16(value: number) {
		this.view.setUint16(this.offset, value);
		this.offset += 2;
	}

	writeUint32(value: number) {
		this.view.setUint32(this.offset, value);
		this.offset += 4;
	}

	writeUint8Array(value: Uint8Array) {
		this.uint8Array.set(value, this.offset);
		this.offset += value.length;
	}
}

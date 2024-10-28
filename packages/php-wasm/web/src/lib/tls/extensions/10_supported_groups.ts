/**
 * TLS supported_groups extension
 * https://www.iana.org/go/rfc7919
 * https://www.iana.org/go/rfc8422
 */

import { ArrayBufferReader, ArrayBufferWriter } from '../utils';
import { ExtensionTypes } from './types';
import { flipObject } from '../utils';

export const SupportedGroups = {
	secp256r1: 23,
	secp384r1: 24,
	secp521r1: 25,
	x25519: 29,
	x448: 30,
} as const;
export const SupportedGroupsNames = flipObject(SupportedGroups);
export type SupportedGroup = keyof typeof SupportedGroups;

export type ParsedSupportedGroups = (keyof typeof SupportedGroups)[];

export class SupportedGroupsExtension {
	/**
	 * +--------------------------------------------------+
	 * | Payload Length                            [2B]   |
	 * +--------------------------------------------------+
	 * | Supported Groups List Length              [2B]   |
	 * +--------------------------------------------------+
	 * | Supported Group 1                         [2B]   |
	 * +--------------------------------------------------+
	 * | Supported Group 2                         [2B]   |
	 * +--------------------------------------------------+
	 * | ...                                              |
	 * +--------------------------------------------------+
	 * | Supported Group n                         [2B]   |
	 * +--------------------------------------------------+
	 */
	static decodeFromClient(data: Uint8Array): ParsedSupportedGroups {
		const reader = new ArrayBufferReader(data.buffer);
		// Skip the length field
		reader.readUint16();
		const groups = [];
		while (!reader.isFinished()) {
			const group = reader.readUint16();
			if (!(group in SupportedGroupsNames)) {
				continue;
			}
			groups.push(SupportedGroupsNames[group]);
		}
		return groups;
	}

	/**
	 * +--------------------------------------------------+
	 * | Extension Type (supported_groups)         [2B]   |
	 * | 0x00 0x0A                                        |
	 * +--------------------------------------------------+
	 * | Extension Length                          [2B]   |
	 * +--------------------------------------------------+
	 * | Selected Group                            [2B]   |
	 * +--------------------------------------------------+
	 */
	static encodeForClient(group: SupportedGroup): Uint8Array {
		const writer = new ArrayBufferWriter(6);
		writer.writeUint16(ExtensionTypes.supported_groups);
		writer.writeUint16(2);
		writer.writeUint16(SupportedGroups[group]);
		return writer.uint8Array;
	}
}

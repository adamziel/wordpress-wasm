/**
 * TLS server_name extension
 * https://www.rfc-editor.org/rfc/rfc6066.html
 */

import { ArrayBufferWriter } from '../utils';
import { ExtensionTypes } from './types';
import { flipObject } from '../utils';

export interface ServerNameList {
	server_name_list: ServerName[];
}

export interface ServerName {
	name_type: typeof ServerNameTypes;
	name: {
		host_name: string;
	};
}

export const ServerNameTypes = {
	host_name: 0,
} as const;
export type ServerNameType =
	(typeof ServerNameTypes)[keyof typeof ServerNameTypes];
export const ServerNameNames = flipObject(ServerNameTypes);

export class ServerNameExtension {
	static decodeFromClient(data: Uint8Array): ServerNameList {
		const view = new DataView(data.buffer);
		let offset = 0;

		const listLength = view.getUint16(offset);
		offset += 2;

		const serverNameList: ServerName[] = [];

		while (offset < listLength + 2) {
			const nameType = data[offset] as ServerNameType;
			offset += 1;

			const valueLength = view.getUint16(offset);
			offset += 2;

			const value = data.slice(offset, offset + valueLength);
			offset += valueLength;

			switch (nameType) {
				case ServerNameTypes.host_name:
					serverNameList.push({
						name_type: ServerNameNames[nameType],
						name: {
							host_name: new TextDecoder().decode(value),
						},
					});
					break;
				default:
					throw new Error(`Unsupported name type ${nameType}`);
			}
		}

		return { server_name_list: serverNameList };
	}

	/**
	 * Encode the server_name extension
	 *
	 * +------------------------------------+
	 * | Extension Type (server_name) [2B]  |
	 * | 0x00 0x00                          |
	 * +------------------------------------+
	 * | Extension Length             [2B]  |
	 * | 0x00 0x00                          |
	 * +------------------------------------+
	 */
	static encodeForClient(serverNames?: ServerNameList) {
		if (serverNames?.server_name_list.length) {
			throw new Error(
				'Encoding non-empty lists for ClientHello is not supported yet. ' +
					'Only empty lists meant for ServerHello are supported today.'
			);
		}

		const writer = new ArrayBufferWriter(4);
		writer.writeUint16(ExtensionTypes.server_name);
		// Zero body length encoded using two bytes
		writer.writeUint16(0);
		return writer.uint8Array;
	}
}

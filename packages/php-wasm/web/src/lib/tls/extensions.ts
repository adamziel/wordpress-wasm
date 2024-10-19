import { ArrayBufferReader } from '../tls';
import { ServerNameExtension, ServerNameList } from './0_server_name';
import {
	ParsedSupportedGroups,
	SupportedGroupsExtension,
} from './10_supported_groups';
import {
	ParsedECPointFormats,
	ECPointFormatsExtension,
} from './11_ec_point_formats';
import {
	SignatureAlgorithms,
	SignatureAlgorithmsExtension,
} from './13_signature_algorithms';
import { Padding, PaddingExtension } from './21_padding';
import {
	CertificateAuthorities,
	CertificateAuthoritiesExtension,
} from './47_certificate_authorities';
import { ExtensionNames } from './extensions-types';

export const TLSExtensionsHandlers = {
	certificate_authorities: CertificateAuthoritiesExtension,
	padding: PaddingExtension,
	server_name: ServerNameExtension,
	signature_algorithms: SignatureAlgorithmsExtension,
	supported_groups: SupportedGroupsExtension,
	ec_point_formats: ECPointFormatsExtension,
} as const;

export type SupportedTLSExtension = keyof typeof TLSExtensionsHandlers;

export type ParsedExtension =
	| {
			type: 'certificate_authorities';
			data: CertificateAuthorities;
			raw: Uint8Array;
	  }
	| {
			type: 'padding';
			data: Padding;
			raw: Uint8Array;
	  }
	| {
			type: 'server_name';
			data: ServerNameList;
			raw: Uint8Array;
	  }
	| {
			type: 'signature_algorithms';
			data: SignatureAlgorithms;
			raw: Uint8Array;
	  }
	| {
			type: 'ec_point_formats';
			data: ParsedECPointFormats;
			raw: Uint8Array;
	  }
	| {
			type: 'supported_groups';
			data: ParsedSupportedGroups;
			raw: Uint8Array;
	  };

/*
The extensions in a ClientHello message are encoded as follows:

struct {
    ExtensionType extension_type;
    opaque extension_data<0..2^16-1>;
} Extension;

The overall extensions structure is:

Extension extensions<0..2^16-1>;

This means:
	•	There's a 2-byte length field for the entire extensions block.
	•	Followed by zero or more individual extensions.

## Binary Data Layout

+-----------------------------+
| Extension 1 Type (2 bytes)  |
+-----------------------------+
| Extension 1 Length (2 bytes)|
+-----------------------------+
| Extension 1 Data (variable) |
+-----------------------------+
| Extension 2 Type (2 bytes)  |
+-----------------------------+
| Extension 2 Length (2 bytes)|
+-----------------------------+
| Extension 2 Data (variable) |
+-----------------------------+
| ... (more extensions)       |
+-----------------------------+

 * 
 * @param data 
 * @returns 
 */
export function parseHelloExtensions(data: Uint8Array) {
	const reader = new ArrayBufferReader(data.buffer);

	const parsed: ParsedExtension[] = [];
	while (!reader.isFinished()) {
		const initialOffset = reader.offset;
		const extensionType = reader.readUint16();
		const extensionTypeName = ExtensionNames[extensionType];
		const extensionLength = reader.readUint16();
		const extensionBytes = reader.readUint8Array(extensionLength);

		if (!(extensionTypeName in TLSExtensionsHandlers)) {
			// throw new Error(`Unsupported extension type: ${extensionType}`);
			console.warn(
				`Unsupported extension: ${extensionTypeName}(${extensionType})`
			);
			continue;
		}

		const handler =
			TLSExtensionsHandlers[
				extensionTypeName as keyof typeof TLSExtensionsHandlers
			];
		parsed.push({
			type: extensionTypeName,
			data: handler.decode(extensionBytes),
			raw: data.slice(initialOffset, initialOffset + 4 + extensionLength),
		});
	}

	return parsed;
}

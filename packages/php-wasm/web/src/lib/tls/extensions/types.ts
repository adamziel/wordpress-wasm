/**
 * IANA maintains a list of TLS extensions here:
 * https://www.iana.org/assignments/tls-extensiontype-values/tls-extensiontype-values.xhtml
 */
export const ExtensionTypes = {
	server_name: 0,
	max_fragment_length: 1,
	client_certificate_url: 2,
	trusted_ca_keys: 3,
	truncated_hmac: 4,
	status_request: 5,
	user_mapping: 6,
	client_authz: 7,
	server_authz: 8,
	cert_type: 9,
	supported_groups: 10,
	ec_point_formats: 11,
	srp: 12,
	signature_algorithms: 13,
	use_srtp: 14,
	heartbeat: 15,
	application_layer_protocol_negotiation: 16,
	status_request_v2: 17,
	signed_certificate_timestamp: 18,
	client_certificate_type: 19,
	server_certificate_type: 20,
	padding: 21,
	encrypt_then_mac: 22,
	extended_master_secret: 23,
	token_binding: 24,
	cached_info: 25,
	tls_its: 26,
	compress_certificate: 27,
	record_size_limit: 28,
	pwd_protect: 29,
	pwo_clear: 30,
	password_salt: 31,
	ticket_pinning: 32,
	tls_cert_with_extern_psk: 33,
	delegated_credential: 34,
	session_ticket: 35,
	TLMSP: 36,
	TLMSP_proxying: 37,
	TLMSP_delegate: 38,
	supported_ekt_ciphers: 39,
	pre_shared_key: 41,
	early_data: 42,
	supported_versions: 43,
	cookie: 44,
	psk_key_exchange_modes: 45,
	reserved: 46,
	certificate_authorities: 47,
	oid_filters: 48,
	post_handshake_auth: 49,
	signature_algorithms_cert: 50,
	key_share: 51,
	transparency_info: 52,
	connection_id: 54,
} as const;

import { flipObject } from '../utils';

export type ExtensionType = keyof typeof ExtensionTypes;

export const ExtensionNames = flipObject(ExtensionTypes);
export type ExtensionName = keyof typeof ExtensionNames;

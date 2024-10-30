/**
 * TLS 1.2 Record layer types defined after the structs
 * from the TLS 1.2 RFC.
 * https://datatracker.ietf.org/doc/html/rfc5246#section-6.2
 */

import { ParsedExtension } from '../extensions/parse-extensions';
import { flipObject } from '../utils';

export const enum CompressionMethod {
	Null = 0,
	Deflate = 1,
}

/**
 * TLS 1.2 Record layer types defined after the structs
 * from the TLS 1.2 RFC.
 * https://datatracker.ietf.org/doc/html/rfc5246#section-6.2.1
 */

export interface TLSRecord {
	type: ContentType; // 1 byte
	version: ProtocolVersion; // 2 bytes
	length: number; // 2 bytes
	fragment: Uint8Array; // variable length
}

export interface ProtocolVersion {
	major: number;
	minor: number;
}

export interface GenericStreamCipher {
	content: Uint8Array;
	MAC: Uint8Array;
}

export interface GenericBlockCipher {
	IV: Uint8Array;
	block_ciphered: BlockCiphered;
}

export interface BlockCiphered {
	content: Uint8Array;
	MAC: Uint8Array;
	padding: Uint8Array;
	padding_length: number;
}

export interface GenericAEADCipher {
	nonce_explicit: Uint8Array;
	aead_encrypted: Uint8Array;
}

/**
 * TLS 1.2 Handshake types defined after the structs
 * from the TLS 1.2 RFC.
 * https://datatracker.ietf.org/doc/html/rfc5246#section-7.4
 */

export type TLSMessage =
	| AlertMessage
	| HandshakeMessage<any>
	| ChangeCipherSpecMessage
	| ApplicationDataMessage;

export interface AlertMessage {
	type: typeof ContentTypes.Alert;
	level: AlertLevel;
	description: AlertDescription;
}

export const AlertLevels = {
	Warning: 1,
	Fatal: 2,
} as const;
export type AlertLevel = (typeof AlertLevels)[keyof typeof AlertLevels];
export const AlertLevelNames = flipObject(AlertLevels);

export const AlertDescriptions = {
	CloseNotify: 0,
	UnexpectedMessage: 10,
	BadRecordMac: 20,
	DecryptionFailed: 21,
	RecordOverflow: 22,
	DecompressionFailure: 30,
	HandshakeFailure: 40,
	NoCertificate: 41,
	BadCertificate: 42,
	UnsupportedCertificate: 43,
	CertificateRevoked: 44,
	CertificateExpired: 45,
	CertificateUnknown: 46,
	IllegalParameter: 47,
	UnknownCa: 48,
	AccessDenied: 49,
	DecodeError: 50,
	DecryptError: 51,
	ExportRestriction: 60,
	ProtocolVersion: 70,
	InsufficientSecurity: 71,
	InternalError: 80,
	UserCanceled: 90,
	NoRenegotiation: 100,
	UnsupportedExtension: 110,
} as const;
export type AlertDescription =
	(typeof AlertDescriptions)[keyof typeof AlertDescriptions];
export const AlertDescriptionNames = flipObject(AlertDescriptions);

export interface ChangeCipherSpecMessage {
	type: typeof ContentTypes.ChangeCipherSpec;
	body: Uint8Array;
}

export interface ApplicationDataMessage {
	type: typeof ContentTypes.ApplicationData;
	body: Uint8Array;
}

export const ContentTypes = {
	ChangeCipherSpec: 20,
	Alert: 21,
	Handshake: 22,
	ApplicationData: 23,
} as const;
export type ContentType = (typeof ContentTypes)[keyof typeof ContentTypes];

export const enum HandshakeType {
	HelloRequest = 0,
	ClientHello = 1,
	ServerHello = 2,
	Certificate = 11,
	ServerKeyExchange = 12,
	CertificateRequest = 13,
	ServerHelloDone = 14,
	CertificateVerify = 15,
	ClientKeyExchange = 16,

	Finished = 20,
}

export type HandshakeMessageBody =
	| HelloRequest
	| ClientHello
	| ServerHello
	| Certificate
	| ServerKeyExchange
	| CertificateRequest
	| ServerHelloDone
	| CertificateVerify
	| ClientKeyExchange
	| Finished;

export interface HandshakeMessage<Body extends HandshakeMessageBody> {
	type: typeof ContentTypes.Handshake;
	msg_type: HandshakeType; // 1 byte
	length: number; // 2 bytes
	// Custom property to hold the raw body of the message
	body: Body;
}

// Specific Handshake Message Types
export interface HelloRequest {} // Empty for TLS 1.2

/**
 * 1 byte
 */
export type SessionId = Uint8Array;
export interface ClientHello {
	client_version: Uint8Array; // 2 bytes
	random: Uint8Array; // 32 bytes
	session_id: SessionId;
	cipher_suites: string[];
	compression_methods: Uint8Array;
	extensions: ParsedExtension[];
}

export interface ServerHello {
	server_version: Uint8Array; // 2 bytes
	random: Uint8Array; // 32 bytes
	session_id: Uint8Array;
	cipher_suite: Uint8Array; // 2 bytes
	compression_method: number;
	extensions?: Uint8Array;
}

export interface Certificate {
	certificate_list: Uint8Array[];
}

export interface ServerKeyExchange {
	params: Uint8Array;
	signed_params: Uint8Array;
}

/**
 * ECCurveType from
 * https://datatracker.ietf.org/doc/html/rfc4492#section-5.4
 */
export const ECCurveTypes = {
	/**
	 * Indicates the elliptic curve domain parameters are
	 * conveyed verbosely, and the underlying finite field is a prime
	 * field.
	 */
	ExplicitPrime: 1,
	/**
	 * Indicates the elliptic curve domain parameters are
	 * conveyed verbosely, and the underlying finite field is a
	 * characteristic-2 field.
	 */
	ExplicitChar2: 2,
	/**
	 * Indicates that a named curve is used.  This option
	 * SHOULD be used when applicable.
	 */
	NamedCurve: 3,
	/**
	 * Values 248 through 255 are reserved for private use.
	 */
};

/**
 * Named elliptic curves from
 * https://datatracker.ietf.org/doc/html/rfc4492#section-5.1.1
 */
export const ECNamedCurves = {
	sect163k1: 1,
	sect163r1: 2,
	sect163r2: 3,
	sect193r1: 4,
	sect193r2: 5,
	sect233k1: 6,
	sect233r1: 7,
	sect239k1: 8,
	sect283k1: 9,
	sect283r1: 10,
	sect409k1: 11,
	sect409r1: 12,
	secp256k1: 22,
	secp256r1: 23,
	secp384r1: 24,
	secp521r1: 25,
	arbitrary_explicit_prime_curves: 0xff01,
	arbitrary_explicit_char2_curves: 0xff02,
};

export interface CertificateRequest {
	certificate_types: Uint8Array;
	supported_signature_algorithms: Uint8Array;
	certificate_authorities: Uint8Array;
}

export interface ServerHelloDone {} // Empty for TLS 1.2

export interface CertificateVerify {
	algorithm: Uint8Array;
	signature: Uint8Array;
}

export interface ClientKeyExchange {
	exchange_keys: Uint8Array;
}

export interface Finished {
	verify_data: Uint8Array;
}

export type SessionKeys = {
	masterSecret: Uint8Array;
	clientWriteKey: CryptoKey;
	serverWriteKey: CryptoKey;
	clientIV: Uint8Array;
	serverIV: Uint8Array;
};

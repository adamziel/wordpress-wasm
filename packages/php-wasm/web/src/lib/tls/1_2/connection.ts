import { ServerNameExtension } from '../extensions/0_server_name';
import { CipherSuitesNames } from '../cipher-suites';
import { CipherSuites } from '../cipher-suites';
import { SupportedGroupsExtension } from '../extensions/10_supported_groups';
import { ECPointFormatsExtension } from '../extensions/11_ec_point_formats';
import { parseClientHelloExtensions } from '../extensions/parse-extensions';
import {
	ArrayBufferReader,
	as2Bytes,
	as3Bytes,
	as8Bytes,
	concatUint8Arrays,
} from '../utils';
import {
	HashAlgorithms,
	SignatureAlgorithms,
	SignatureAlgorithmsExtension,
} from '../extensions/13_signature_algorithms';
import { tls12Prf } from './prf';
import {
	CompressionMethod,
	SessionKeys,
	HandshakeType,
	ContentTypes,
	HandshakeMessage,
	ClientHello,
	ClientKeyExchange,
	Finished,
	ApplicationDataMessage,
	AlertMessage,
	ChangeCipherSpecMessage,
	TLSMessage,
	ContentType,
	TLSRecord,
	AlertLevelNames,
	AlertDescriptionNames,
	HandshakeMessageBody,
	HelloRequest,
	ECCurveTypes,
	ECNamedCurves,
} from './types';

class TLSConnectionClosed extends Error {}
const TLS_Version_1_2 = new Uint8Array([0x03, 0x03]);

/**
 * Reuse the same ECDHE key pair for all connections to
 * save some CPU cycles. We can do this because this TLS
 * implementation is not meant to be secure.
 */
const generalEcdheKeyPair = crypto.subtle.generateKey(
	{
		name: 'ECDH',
		namedCurve: 'P-256', // Use secp256r1 curve
	},
	true, // Extractable
	['deriveKey', 'deriveBits'] // Key usage
);

/**
 * This isomorphic class implements the server end of
 * the client <-> server TLS 1.2 connection. It has two ends:
 *
 * * Client end, that emits and accepts TLS encrypted data.
 * * Server end, that emits and accepts unencrypted data.
 *
 * The API consumer is responsible for connecting both ends
 * to the appropriate handlers.
 *
 * See https://datatracker.ietf.org/doc/html/rfc5246.
 *
 * ## Warning
 *
 * **WARNING** NEVER USE THIS CODE AS A SERVER-SIDE TLS HANDLER.
 *
 * This code is not secure. It is a minimal subset required
 * to decrypt the TLS traffic from a PHP-wasm worker. Yes,
 * it can speak TLS. No, it won't protect your data.
 *
 * ## Rationale
 *
 * This is useful for running PHP.wasm in web browsers.
 * Function calls such as `file_get_contents("https://w.org")`
 * emit encrypted TLS traffic. With this class, you
 * can decrypt it, serve the requested data, and encrypt
 * the response before passing it back to the PHP.wasm
 * module.
 *
 * ## Implementation details
 *
 * TLS_1_2_Connection implements the minimal subset of TLS 1.2
 * required to exchange encrypted data with PHP.wasm:
 *
 * * TLS Handshake
 * * All TLS 1.2 record types, including messages spanning multiple
 *   records and empty records.
 * * Encryption and decryption of application data.
 * * Auto-chunking long data blobs before encrypting them to
 *   respect the AES-GCM record size limit.
 *
 * The logic is based on numerous RFCs:
 *
 * * RFC 5246: The TLS Protocol Version 1.2
 * * RFC 8446: TLS 1.3
 * * RFC 6066: TLS Extensions
 * * RFC 4492: Elliptic Curve Cryptography (ECC) Cipher Suites for TLS
 * * RFC 5288: AES Galois Counter Mode (GCM) Cipher Suites for TLS
 * * RFC 6070: PKCS #5: Password-Based Key Derivation Function 2 (PBKDF2) Test Vectors
 *
 * ... and a few others.
 *
 * ## Limitations
 *
 * * Multiple ChangeCipherSpec messages are not supported.
 * * Only uncompressed mode (compression method 0) is supported.
 * * Only the TLS1_CK_ECDHE_RSA_WITH_AES_128_GCM_SHA256 cipher suite is
 *   supported, primarily because `crypto.subtle` supports AES-GCM.
 *   For AES-GCM details, see https://datatracker.ietf.org/doc/html/rfc5288.
 */
export class TLS_1_2_Connection {
	/**
	 * Sequence number of the last received TLS  record.
	 *
	 * AES-GCM requires transmitting the sequence number
	 * in the clear in the additional data to prevent a
	 * potential attacker from re-transmitting the same
	 * TLS record in a different context.
	 */
	private receivedRecordSequenceNumber = 0;

	/**
	 * Sequence number of the last sent TLS record.
	 *
	 * AES-GCM requires transmitting the sequence number
	 * in the clear in the additional data to prevent a
	 * potential attacker from re-transmitting the same
	 * TLS record in a different context.
	 */
	private sentRecordSequenceNumber = 0;

	/**
	 * Encryption keys for this connection derived during
	 * the TLS handshake.
	 */
	private sessionKeys: SessionKeys | undefined;

	/**
	 * Whether this connection have been closed.
	 */
	private closed = false;

	/**
	 * Bytes received from the client but not yet parsed
	 * as TLS records.
	 */
	private receivedBytesBuffer: Uint8Array = new Uint8Array();

	/**
	 * TLS records received from the client but not yet
	 * parsed as TLS messages.
	 */
	private receivedTLSRecords: Array<TLSRecord> = [];

	/**
	 * TLS messages can span multiple TLS records. This
	 * map holds partial TLS messages that are still incomplete
	 * after parsing one or more TLS records.
	 */
	private partialTLSMessages: Partial<Record<ContentType, Uint8Array>> = {};

	/**
	 * A log of all the exchanged TLS handshake messages.
	 * This is required to build the Finished message and
	 * verify the integrity of the handshake.
	 */
	private handshakeMessages: Uint8Array[] = [];

	/**
	 * Maximum chunk size supported by the cipher suite used
	 * in this TLS implementation.
	 */
	private MAX_CHUNK_SIZE = 1024 * 16;

	/**
	 * The client end of the TLS connection.
	 * This is where the WASM module can write and read the
	 * encrypted data.
	 */
	clientEnd = {
		// We don't need to chunk the encrypted data.
		// OpenSSL already done that for us.
		upstream: new TransformStream<Uint8Array, Uint8Array>(),
		downstream: new TransformStream<Uint8Array, Uint8Array>(),
	};

	private clientDownstreamWriter =
		this.clientEnd.downstream.writable.getWriter();
	private clientUpstreamReader = this.clientEnd.upstream.readable.getReader();

	/**
	 * The server end of the TLS connection.
	 * This is where the JavaScript handler can write and read the
	 * unencrypted data.
	 */
	serverEnd = {
		upstream: new TransformStream<Uint8Array, Uint8Array>(),
		/**
		 * Chunk the data before encrypting it. The
		 * TLS1_CK_ECDHE_RSA_WITH_AES_128_GCM_SHA256 cipher suite
		 * only supports up to 16KB of data per record.
		 *
		 * This will spread some messages across multiple records,
		 * but TLS supports it so that's fine.
		 */
		downstream: chunkStream(this.MAX_CHUNK_SIZE),
	};

	private serverUpstreamWriter = this.serverEnd.upstream.writable.getWriter();

	constructor() {
		// eslint-disable-next-line @typescript-eslint/no-this-alias
		const tlsConnection = this;
		// Whenever the "server handler" produces data, encrypt it
		// and send it back to the client.
		this.serverEnd.downstream.readable
			.pipeTo(
				new WritableStream({
					async write(chunk) {
						await tlsConnection.writeTLSRecord(
							ContentTypes.ApplicationData,
							chunk
						);
					},
					async abort(e) {
						tlsConnection.clientDownstreamWriter.releaseLock();
						tlsConnection.clientEnd.downstream.writable.abort(e);
						tlsConnection.close();
					},
					close() {
						tlsConnection.close();
					},
				})
			)
			.catch(() => {
				// Ignore failures arising from stream errors. The caller
				// should react to the readable stream erroring out.
			});
	}

	/**
	 * Marks this connections as closed and closes all the associated
	 * streams.
	 */
	async close() {
		if (this.closed) {
			return;
		}
		this.closed = true;
		try {
			await this.clientDownstreamWriter.close();
		} catch (e) {
			// Ignore
		}
		try {
			await this.clientUpstreamReader.cancel();
		} catch (e) {
			// Ignore
		}
		try {
			await this.serverUpstreamWriter.close();
		} catch (e) {
			// Ignore
		}
		try {
			await this.clientEnd.upstream.readable.cancel();
		} catch (e) {
			// Ignore
		}
		try {
			await this.clientEnd.downstream.writable.close();
		} catch (e) {
			// Ignore
		}
	}

	/**
	 * TLS handshake as per RFC 5246.
	 *
	 * https://datatracker.ietf.org/doc/html/rfc5246#section-7.4
	 */
	async TLSHandshake(
		certificatePrivateKey: CryptoKey,
		certificatesDER: Uint8Array[]
	): Promise<void> {
		// Step 1: Receive ClientHello
		const clientHelloRecord = await this.readNextHandshakeMessage(
			HandshakeType.ClientHello
		);
		if (!clientHelloRecord.body.cipher_suites.length) {
			throw new Error(
				'Client did not propose any supported cipher suites.'
			);
		}

		// Step 2: Choose hashing, encryption etc. and tell the
		//         client about our choices. Also share the certificate
		//         and the encryption keys.
		const serverRandom = crypto.getRandomValues(new Uint8Array(32));
		await this.writeTLSRecord(
			ContentTypes.Handshake,
			MessageEncoder.serverHello(
				clientHelloRecord.body,
				serverRandom,
				CompressionMethod.Null
			)
		);

		await this.writeTLSRecord(
			ContentTypes.Handshake,
			MessageEncoder.certificate(certificatesDER)
		);

		const ecdheKeyPair = await generalEcdheKeyPair;
		const clientRandom = clientHelloRecord.body.random;
		const serverKeyExchange = await MessageEncoder.ECDHEServerKeyExchange(
			clientRandom,
			serverRandom,
			ecdheKeyPair,
			certificatePrivateKey
		);
		await this.writeTLSRecord(ContentTypes.Handshake, serverKeyExchange);
		await this.writeTLSRecord(
			ContentTypes.Handshake,
			MessageEncoder.serverHelloDone()
		);

		// Step 3: Receive the client's response, encryption keys, and
		//         decrypt the first encrypted message.
		const clientKeyExchangeRecord = await this.readNextHandshakeMessage(
			HandshakeType.ClientKeyExchange
		);
		await this.readNextMessage(ContentTypes.ChangeCipherSpec);

		this.sessionKeys = await this.deriveSessionKeys({
			clientRandom,
			serverRandom,
			serverPrivateKey: ecdheKeyPair.privateKey,
			clientPublicKey: await crypto.subtle.importKey(
				'raw',
				clientKeyExchangeRecord.body.exchange_keys,
				{ name: 'ECDH', namedCurve: 'P-256' },
				false,
				[]
			),
		});

		await this.readNextHandshakeMessage(HandshakeType.Finished);

		// We're not actually verifying the hash provided by client
		// as we're not concerned with the client's identity.

		await this.writeTLSRecord(
			ContentTypes.ChangeCipherSpec,
			MessageEncoder.changeCipherSpec()
		);
		await this.writeTLSRecord(
			ContentTypes.Handshake,
			await MessageEncoder.createFinishedMessage(
				this.handshakeMessages,
				this.sessionKeys!.masterSecret
			)
		);
		// Clean up the handshake messages as they are no longer needed.
		this.handshakeMessages = [];

		this.pollForClientMessages();
	}

	/**
	 * Derives the session keys from the random values and the
	 * pre-master secret – as per RFC 5246.
	 */
	private async deriveSessionKeys({
		clientRandom,
		serverRandom,
		serverPrivateKey,
		clientPublicKey,
	}: {
		clientRandom: Uint8Array;
		serverRandom: Uint8Array;
		serverPrivateKey: CryptoKey;
		clientPublicKey: CryptoKey;
	}): Promise<SessionKeys> {
		const preMasterSecret = await crypto.subtle.deriveBits(
			{
				name: 'ECDH',
				public: clientPublicKey,
			},
			serverPrivateKey,
			256 // Length of the derived secret (256 bits for P-256)
		);

		const masterSecret = new Uint8Array(
			await tls12Prf(
				preMasterSecret,
				new TextEncoder().encode('master secret'),
				concatUint8Arrays([clientRandom, serverRandom]),
				48
			)
		);

		const keyBlock = await tls12Prf(
			masterSecret,
			new TextEncoder().encode('key expansion'),
			concatUint8Arrays([serverRandom, clientRandom]),
			// Client key, server key, client IV, server IV
			16 + 16 + 4 + 4
		);

		const reader = new ArrayBufferReader(keyBlock);
		const clientWriteKey = reader.readUint8Array(16);
		const serverWriteKey = reader.readUint8Array(16);
		const clientIV = reader.readUint8Array(4);
		const serverIV = reader.readUint8Array(4);

		return {
			masterSecret,
			clientWriteKey: await crypto.subtle.importKey(
				'raw',
				clientWriteKey,
				{ name: 'AES-GCM' },
				false,
				['encrypt', 'decrypt']
			),
			serverWriteKey: await crypto.subtle.importKey(
				'raw',
				serverWriteKey,
				{ name: 'AES-GCM' },
				false,
				['encrypt', 'decrypt']
			),
			clientIV,
			serverIV,
		};
	}

	private async readNextHandshakeMessage(
		messageType: HandshakeType.ClientHello
	): Promise<HandshakeMessage<ClientHello>>;
	private async readNextHandshakeMessage(
		messageType: HandshakeType.ClientKeyExchange
	): Promise<HandshakeMessage<ClientKeyExchange>>;
	private async readNextHandshakeMessage(
		messageType: HandshakeType.Finished
	): Promise<HandshakeMessage<Finished>>;
	private async readNextHandshakeMessage(
		messageType:
			| HandshakeType.ClientHello
			| HandshakeType.ClientKeyExchange
			| HandshakeType.Finished
	): Promise<HandshakeMessage<any>> {
		const message = await this.readNextMessage(ContentTypes.Handshake);
		if (message.msg_type !== messageType) {
			throw new Error(`Expected ${messageType} message`);
		}
		return message;
	}

	private async readNextMessage(
		requestedType: (typeof ContentTypes)['Handshake']
	): Promise<HandshakeMessage<any>>;
	private async readNextMessage(
		requestedType: (typeof ContentTypes)['ApplicationData']
	): Promise<ApplicationDataMessage>;
	private async readNextMessage(
		requestedType: (typeof ContentTypes)['Alert']
	): Promise<AlertMessage>;
	private async readNextMessage(
		requestedType: (typeof ContentTypes)['ChangeCipherSpec']
	): Promise<ChangeCipherSpecMessage>;
	private async readNextMessage<Message extends TLSMessage>(
		requestedType: ContentType
	): Promise<Message> {
		let record: TLSRecord;
		let accumulatedPayload: false | Uint8Array = false;
		do {
			record = await this.readNextTLSRecord(requestedType);
			accumulatedPayload = await this.accumulateUntilMessageIsComplete(
				record
			);
		} while (accumulatedPayload === false);

		const message = TLSDecoder.TLSMessage(
			record.type,
			accumulatedPayload
		) as Message;
		if (record.type === ContentTypes.Handshake) {
			this.handshakeMessages.push(record.fragment);
		}
		return message;
	}

	private async readNextTLSRecord(
		requestedType: ContentType
	): Promise<TLSRecord> {
		while (true) {
			// First, check if we have a complete record of the requested type.
			for (let i = 0; i < this.receivedTLSRecords.length; i++) {
				const record = this.receivedTLSRecords[i];
				if (record.type !== requestedType) {
					continue;
				}
				this.receivedTLSRecords.splice(i, 1);
				return record;
			}

			// We don't have a complete record of the requested type yet.
			// Let's read the next TLS record, then.
			const header = await this.pollBytes(5);
			const length = (header[3] << 8) | header[4];
			const type = header[0];
			const fragment = await this.pollBytes(length);
			const record = {
				type,
				version: {
					major: header[1],
					minor: header[2],
				},
				length,
				fragment:
					this.sessionKeys && type !== ContentTypes.ChangeCipherSpec
						? await this.decryptData(type, fragment)
						: fragment,
			} as TLSRecord;

			if (record.type === ContentTypes.Alert) {
				const severity = AlertLevelNames[record.fragment[0]];
				const description = AlertDescriptionNames[record.fragment[1]];
				/**
				 * @TODO: Handle TLS warnings, e.g. this one:
				 *
				 * close_notify
				 *     Either party may initiate a close by sending a close_notify alert.
				 *     Any data received after a closure alert is ignored.
				 *
				 *     Unless some other fatal alert has been transmitted, each party is
				 *     required to send a close_notify alert before closing the write side
				 *     of the connection.  The other party MUST respond with a close_notify
				 *     alert of its own and close down the connection immediately,
				 *     discarding any pending writes.
				 */
				throw new Error(
					`TLS non-warning alert received: ${severity} ${description}`
				);
			}

			this.receivedTLSRecords.push(record);
		}
	}

	/**
	 * Returns the requested number of bytes from the client.
	 * Waits for the bytes to arrive if necessary.
	 */
	private async pollBytes(length: number) {
		while (this.receivedBytesBuffer.length < length) {
			const { value, done } = await this.clientUpstreamReader.read();
			if (done) {
				await this.close();
				throw new TLSConnectionClosed('TLS connection closed');
			}
			this.receivedBytesBuffer = concatUint8Arrays([
				this.receivedBytesBuffer,
				value,
			]);
			if (this.receivedBytesBuffer.length >= length) {
				break;
			}

			// Patience is the key...
			await new Promise((resolve) => setTimeout(resolve, 100));
		}
		const requestedBytes = this.receivedBytesBuffer.slice(0, length);
		this.receivedBytesBuffer = this.receivedBytesBuffer.slice(length);
		return requestedBytes;
	}

	/**
	 * Listens for all incoming messages and passes them to the
	 * server handler.
	 */
	private async pollForClientMessages() {
		try {
			while (true) {
				const appData = await this.readNextMessage(
					ContentTypes.ApplicationData
				);
				this.serverUpstreamWriter.write(appData.body);
			}
		} catch (e) {
			if (e instanceof TLSConnectionClosed) {
				// Connection closed, no more application data to emit.
				return;
			}
			throw e;
		}
	}

	/**
	 * Decrypts data in a TLS 1.2-compliant manner using
	 * the AES-GCM algorithm.
	 */
	private async decryptData(
		contentType: number,
		payload: Uint8Array
	): Promise<Uint8Array> {
		const implicitIV = this.sessionKeys!.clientIV;
		// Part of the IV is randomly generated for each record
		// and prepended in its unencrypted form before the
		// ciphertext.
		const explicitIV = payload.slice(0, 8);
		const iv = new Uint8Array([...implicitIV, ...explicitIV]);

		const decrypted = await crypto.subtle.decrypt(
			{
				name: 'AES-GCM',
				iv,
				additionalData: new Uint8Array([
					...as8Bytes(this.receivedRecordSequenceNumber),
					contentType,
					...TLS_Version_1_2,
					// Payload length without IV and tag
					...as2Bytes(payload.length - 8 - 16),
				]),
				tagLength: 128,
			},
			this.sessionKeys!.clientWriteKey,
			// Payload without the explicit IV
			payload.slice(8)
		);
		++this.receivedRecordSequenceNumber;

		return new Uint8Array(decrypted);
	}

	private async accumulateUntilMessageIsComplete(
		record: TLSRecord
	): Promise<false | Uint8Array> {
		this.partialTLSMessages[record.type] = concatUint8Arrays([
			this.partialTLSMessages[record.type] || new Uint8Array(),
			record.fragment,
		]);
		const message = this.partialTLSMessages[record.type]!;
		switch (record.type) {
			case ContentTypes.Handshake: {
				// We don't have the message header yet, let's wait for more data.
				if (message.length < 4) {
					return false;
				}
				const length = (message[1] << 8) | message[2];
				if (message.length < 3 + length) {
					return false;
				}
				break;
			}
			case ContentTypes.Alert: {
				if (message.length < 2) {
					return false;
				}
				break;
			}
			case ContentTypes.ChangeCipherSpec:
			case ContentTypes.ApplicationData:
				break;
			default:
				throw new Error(`TLS: Unsupported record type ${record.type}`);
		}
		delete this.partialTLSMessages[record.type];
		return message;
	}

	/**
	 * Passes a TLS record to the client.
	 *
	 * Accepts unencrypted data and ensures it gets encrypted
	 * if needed before sending it to the client. The encryption
	 * only kicks in after the handshake is complete.
	 */
	private async writeTLSRecord(
		contentType: number,
		payload: Uint8Array
	): Promise<void> {
		if (contentType === ContentTypes.Handshake) {
			this.handshakeMessages.push(payload);
		}
		if (this.sessionKeys && contentType !== ContentTypes.ChangeCipherSpec) {
			payload = await this.encryptData(contentType, payload);
		}

		const version = TLS_Version_1_2;
		const length = payload.length;
		const header = new Uint8Array(5);
		header[0] = contentType;
		header[1] = version[0];
		header[2] = version[1];
		header[3] = (length >> 8) & 0xff;
		header[4] = length & 0xff;

		const record = concatUint8Arrays([header, payload]);
		this.clientDownstreamWriter.write(record);
	}

	/**
	 * Encrypts data in a TLS 1.2-compliant manner using
	 * the AES-GCM algorithm.
	 */
	private async encryptData(
		contentType: number,
		payload: Uint8Array
	): Promise<Uint8Array> {
		const implicitIV = this.sessionKeys!.serverIV;
		// Part of the IV is randomly generated for each record
		// and prepended in its unencrypted form before the
		// ciphertext.
		const explicitIV = crypto.getRandomValues(new Uint8Array(8));
		const iv = new Uint8Array([...implicitIV, ...explicitIV]);

		const additionalData = new Uint8Array([
			...as8Bytes(this.sentRecordSequenceNumber),
			contentType,
			...TLS_Version_1_2,
			// Payload length without IV and tag
			...as2Bytes(payload.length),
		]);
		const ciphertextWithTag = await crypto.subtle.encrypt(
			{
				name: 'AES-GCM',
				iv,
				additionalData,
				tagLength: 128,
			},
			this.sessionKeys!.serverWriteKey,
			payload
		);
		++this.sentRecordSequenceNumber;

		const encrypted = concatUint8Arrays([
			explicitIV,
			new Uint8Array(ciphertextWithTag),
		]);

		return encrypted;
	}
}

class TLSDecoder {
	static TLSMessage(
		type: ContentType,
		accumulatedPayload: Uint8Array
	): TLSMessage {
		switch (type) {
			case ContentTypes.Handshake: {
				return TLSDecoder.clientHandshake(accumulatedPayload);
			}
			case ContentTypes.Alert: {
				return TLSDecoder.alert(accumulatedPayload);
			}
			case ContentTypes.ChangeCipherSpec: {
				return TLSDecoder.changeCipherSpec();
			}
			case ContentTypes.ApplicationData: {
				return TLSDecoder.applicationData(accumulatedPayload);
			}
			default:
				throw new Error(`TLS: Unsupported TLS record type ${type}`);
		}
	}

	/**
	 * Parses the cipher suites from the server hello message.
	 *
	 * The cipher suites are encoded as a list of 2-byte values.
	 *
	 * Binary layout:
	 *
	 * +----------------------------+
	 * | Cipher Suites Length       |  2 bytes
	 * +----------------------------+
	 * | Cipher Suite 1             |  2 bytes
	 * +----------------------------+
	 * | Cipher Suite 2             |  2 bytes
	 * +----------------------------+
	 * | ...                        |
	 * +----------------------------+
	 * | Cipher Suite n             |  2 bytes
	 * +----------------------------+
	 *
	 * The full list of supported cipher suites values is available at:
	 *
	 * https://www.iana.org/assignments/tls-parameters/tls-parameters.xhtml#tls-parameters-4
	 */
	static parseCipherSuites(buffer: ArrayBuffer): string[] {
		const reader = new ArrayBufferReader(buffer);
		// Skip the length of the cipher suites
		reader.readUint16();

		const cipherSuites = [];
		while (!reader.isFinished()) {
			const suite = reader.readUint16();
			if (!(suite in CipherSuitesNames)) {
				continue;
			}
			cipherSuites.push(CipherSuitesNames[suite]);
		}
		return cipherSuites;
	}

	static applicationData(message: Uint8Array): ApplicationDataMessage {
		return {
			type: ContentTypes.ApplicationData,
			body: message,
		};
	}

	static changeCipherSpec(): ChangeCipherSpecMessage {
		return {
			type: ContentTypes.ChangeCipherSpec,
			body: new Uint8Array(),
		};
	}

	static alert(message: Uint8Array): AlertMessage {
		return {
			type: ContentTypes.Alert,
			level: AlertLevelNames[message[0]],
			description: AlertDescriptionNames[message[1]],
		};
	}

	static clientHandshake<T extends HandshakeMessageBody>(
		message: Uint8Array
	): HandshakeMessage<T> {
		const msg_type = message[0];
		const length = (message[1] << 16) | (message[2] << 8) | message[3];
		const bodyBytes = message.slice(4);
		let body: HandshakeMessageBody | undefined = undefined;
		switch (msg_type) {
			case HandshakeType.HelloRequest:
				body = TLSDecoder.clientHelloRequestPayload();
				break;
			case HandshakeType.ClientHello:
				body = TLSDecoder.clientHelloPayload(bodyBytes);
				break;
			case HandshakeType.ClientKeyExchange:
				body = TLSDecoder.clientKeyExchangePayload(bodyBytes);
				break;
			case HandshakeType.Finished:
				body = TLSDecoder.clientFinishedPayload(bodyBytes);
				break;
			default:
				throw new Error(`Invalid handshake type ${msg_type}`);
		}
		return {
			type: ContentTypes.Handshake,
			msg_type,
			length,
			body: body as T,
		};
	}

	static clientHelloRequestPayload(): HelloRequest {
		return {};
	}

	/**
	 *	Offset  Size    Field
	 *	(bytes) (bytes)
	 *	+------+------+---------------------------+
	 *	| 0000 |  1   | Handshake Type (1 = ClientHello)
	 *	+------+------+---------------------------+
	 *	| 0001 |  3   | Length of ClientHello
	 *	+------+------+---------------------------+
	 *	| 0004 |  2   | Protocol Version
	 *	+------+------+---------------------------+
	 *	| 0006 |  32  | Client Random
	 *	|      |      | (4 bytes timestamp +
	 *	|      |      |  28 bytes random)
	 *	+------+------+---------------------------+
	 *	| 0038 |  1   | Session ID Length
	 *	+------+------+---------------------------+
	 *	| 0039 |  0+  | Session ID (variable)
	 *	|      |      | (0-32 bytes)
	 *	+------+------+---------------------------+
	 *	| 003A*|  2   | Cipher Suites Length
	 *	+------+------+---------------------------+
	 *	| 003C*|  2+  | Cipher Suites
	 *	|      |      | (2 bytes each)
	 *	+------+------+---------------------------+
	 *	| xxxx |  1   | Compression Methods Length
	 *	+------+------+---------------------------+
	 *	| xxxx |  1+  | Compression Methods
	 *	|      |      | (1 byte each)
	 *	+------+------+---------------------------+
	 *	| xxxx |  2   | Extensions Length
	 *	+------+------+---------------------------+
	 *	| xxxx |  2   | Extension Type
	 *	+------+------+---------------------------+
	 *	| xxxx |  2   | Extension Length
	 *	+------+------+---------------------------+
	 *	| xxxx |  v   | Extension Data
	 *	+------+------+---------------------------+
	 *	|      |      | (Additional extensions...)
	 *	+------+------+---------------------------+
	 */
	static clientHelloPayload(data: Uint8Array): ClientHello {
		const reader = new ArrayBufferReader(data.buffer);
		const buff: Partial<ClientHello> = {
			client_version: reader.readUint8Array(2),
			/**
			 * Technically this consists of a GMT timestamp
			 * and 28 random bytes, but we don't need to
			 * parse this further.
			 */
			random: reader.readUint8Array(32),
		};
		const sessionIdLength = reader.readUint8();
		buff.session_id = reader.readUint8Array(sessionIdLength);

		const cipherSuitesLength = reader.readUint16();
		buff.cipher_suites = TLSDecoder.parseCipherSuites(
			reader.readUint8Array(cipherSuitesLength).buffer
		);

		const compressionMethodsLength = reader.readUint8();
		buff.compression_methods = reader.readUint8Array(
			compressionMethodsLength
		);

		const extensionsLength = reader.readUint16();
		buff.extensions = parseClientHelloExtensions(
			reader.readUint8Array(extensionsLength)
		);
		return buff as ClientHello;
	}

	/**
	 * Binary layout:
	 *
	 *	+------------------------------------+
	 *	| ECDH Client Public Key Length [1B] |
	 *	+------------------------------------+
	 *	| ECDH Client Public Key   [variable]|
	 *	+------------------------------------+
	 */
	static clientKeyExchangePayload(data: Uint8Array): ClientKeyExchange {
		return {
			// Skip the first byte, which is the length of the public key
			exchange_keys: data.slice(1, data.length),
		};
	}

	static clientFinishedPayload(data: Uint8Array): Finished {
		return {
			verify_data: data,
		};
	}
}

/**
 * Creates a stream that emits chunks not larger than
 * the specified size.
 */
function chunkStream(chunkSize: number) {
	return new TransformStream({
		transform(chunk, controller) {
			while (chunk.length > 0) {
				controller.enqueue(chunk.slice(0, chunkSize));
				chunk = chunk.slice(chunkSize);
			}
		},
	});
}

class MessageEncoder {
	static certificate(certificatesDER: ArrayBuffer[]): Uint8Array {
		const certsBodies: Uint8Array[] = [];
		for (const cert of certificatesDER) {
			certsBodies.push(as3Bytes(cert.byteLength));
			certsBodies.push(new Uint8Array(cert));
		}
		const certsBody = concatUint8Arrays(certsBodies);
		const body = new Uint8Array([
			...as3Bytes(certsBody.byteLength),
			...certsBody,
		]);
		return new Uint8Array([
			HandshakeType.Certificate,
			...as3Bytes(body.length),
			...body,
		]);
	}

	/*
	 * Byte layout of the ServerKeyExchange message:
	 *
	 * +-----------------------------------+
	 * |    ServerKeyExchange Message      |
	 * +-----------------------------------+
	 * | Handshake type (1 byte)           |
	 * +-----------------------------------+
	 * | Length (3 bytes)                  |
	 * +-----------------------------------+
	 * | Curve Type (1 byte)               |
	 * +-----------------------------------+
	 * | Named Curve (2 bytes)             |
	 * +-----------------------------------+
	 * | EC Point Format (1 byte)          |
	 * +-----------------------------------+
	 * | Public Key Length (1 byte)        |
	 * +-----------------------------------+
	 * | Public Key (variable)             |
	 * +-----------------------------------+
	 * | Signature Algorithm (2 bytes)     |
	 * +-----------------------------------+
	 * | Signature Length (2 bytes)        |
	 * +-----------------------------------+
	 * | Signature (variable)              |
	 * +-----------------------------------+
	 *
	 * @param clientRandom - 32 bytes from ClientHello
	 * @param serverRandom - 32 bytes from ServerHello
	 * @param ecdheKeyPair - ECDHE key pair
	 * @param rsaPrivateKey - RSA private key for signing
	 * @returns
	 */
	static async ECDHEServerKeyExchange(
		clientRandom: Uint8Array,
		serverRandom: Uint8Array,
		ecdheKeyPair: CryptoKeyPair,
		rsaPrivateKey: CryptoKey
	): Promise<Uint8Array> {
		// 65 bytes (04 followed by x and y coordinates)
		const publicKey = new Uint8Array(
			await crypto.subtle.exportKey('raw', ecdheKeyPair.publicKey)
		);

		/*
		 * The ServerKeyExchange message is extended as follows.
		 *
		 * select (KeyExchangeAlgorithm) {
		 *     case ec_diffie_hellman:
		 *         ServerECDHParams    params;
		 *         Signature           signed_params;
		 * } ServerKeyExchange;
		 *
		 * struct {
		 *   ECCurveType     curve_type;
		 *   NamedCurve      namedcurve;
		 *   ECPoint         public;
		 * } ServerECDHParams;
		 */
		const params = new Uint8Array([
			// Curve type (1 byte)
			ECCurveTypes.NamedCurve,
			// Curve name (2 bytes)
			...as2Bytes(ECNamedCurves.secp256r1),

			// Public key length (1 byte)
			publicKey.byteLength,

			// Public key (65 bytes, uncompressed format)
			...publicKey,
		]);

		/**
		 * signed_params:
		 *    A hash of the params, with the signature appropriate to that hash
		 *    applied.  The private key corresponding to the certified public key
		 *    in the server's Certificate message is used for signing.
		 *
		 *    Signature = encrypt(SHA(
		 *       ClientHello.random + ServerHello.random + ServerECDHParams
		 *    ));
		 */
		const signedParams = await crypto.subtle.sign(
			{
				name: 'RSASSA-PKCS1-v1_5',
				hash: 'SHA-256',
			},
			rsaPrivateKey,
			new Uint8Array([...clientRandom, ...serverRandom, ...params])
		);
		const signatureBytes = new Uint8Array(signedParams);

		/**
		 * SignatureAlgorithm is "rsa" for the ECDHE_RSA key exchange
		 * These cases are defined in TLS [RFC2246].
		 */
		const signatureAlgorithm = new Uint8Array([
			HashAlgorithms.sha256,
			SignatureAlgorithms.rsa,
		]);

		// Step 6: Construct the complete Server Key Exchange message
		const body = new Uint8Array([
			...params,
			...signatureAlgorithm,
			...as2Bytes(signatureBytes.length),
			...signatureBytes,
		]);

		return new Uint8Array([
			HandshakeType.ServerKeyExchange,
			...as3Bytes(body.length),
			...body,
		]);
	}

	/**
	 * +------------------------------------+
	 * | Content Type (Handshake)     [1B]  |
	 * | 0x16                               |
	 * +------------------------------------+
	 * | Version (TLS 1.2)            [2B]  |
	 * | 0x03 0x03                          |
	 * +------------------------------------+
	 * | Length                       [2B]  |
	 * +------------------------------------+
	 * | Handshake Type (ServerHello) [1B]  |
	 * | 0x02                               |
	 * +------------------------------------+
	 * | Handshake Length             [3B]  |
	 * +------------------------------------+
	 * | Server Version               [2B]  |
	 * +------------------------------------+
	 * | Server Random               [32B]  |
	 * +------------------------------------+
	 * | Session ID Length            [1B]  |
	 * +------------------------------------+
	 * | Session ID             [0-32B]     |
	 * +------------------------------------+
	 * | Cipher Suite                 [2B]  |
	 * +------------------------------------+
	 * | Compression Method           [1B]  |
	 * +------------------------------------+
	 * | Extensions Length            [2B]  |
	 * +------------------------------------+
	 * | Extension: ec_point_formats        |
	 * |   Type (0x00 0x0B)           [2B]  |
	 * |   Length                     [2B]  |
	 * |   EC Point Formats Length    [1B]  |
	 * |   EC Point Format            [1B]  |
	 * +------------------------------------+
	 * | Other Extensions...                |
	 * +------------------------------------+
	 */
	static serverHello(
		clientHello: ClientHello,
		serverRandom: Uint8Array,
		compressionAlgorithm: number
	): Uint8Array {
		const extensionsParts: Uint8Array[] = clientHello.extensions
			.map((extension) => {
				switch (extension['type']) {
					case 'server_name':
						/**
						 * The server SHALL include an extension of type "server_name" in the
						 * (extended) server hello.  The "extension_data" field of this extension
						 * SHALL be empty.
						 *
						 * Source: dfile:///Users/cloudnik/Library/Application%20Support/Dash/User%20Contributed/RFCs/RFCs.docset/Contents/Resources/Documents/rfc6066.html#section-3
						 */
						return ServerNameExtension.encodeForClient();
					case 'supported_groups':
						return SupportedGroupsExtension.encodeForClient(
							'secp256r1'
						);
					case 'ec_point_formats':
						return ECPointFormatsExtension.encodeForClient(
							'uncompressed'
						);
					case 'signature_algorithms':
						return SignatureAlgorithmsExtension.encodeforClient(
							'sha256',
							'rsa'
						);
				}
				return undefined;
			})
			.filter((x): x is Uint8Array => x !== undefined);
		const extensions = concatUint8Arrays(extensionsParts);

		const body = new Uint8Array([
			// Version field – 0x03, 0x03 means TLS 1.2
			...TLS_Version_1_2,

			...serverRandom,

			clientHello.session_id.length,
			...clientHello.session_id,

			...as2Bytes(CipherSuites.TLS1_CK_ECDHE_RSA_WITH_AES_128_GCM_SHA256),

			compressionAlgorithm,

			// Extensions length (2 bytes)
			...as2Bytes(extensions.length),

			...extensions,
		]);

		return new Uint8Array([
			HandshakeType.ServerHello,
			...as3Bytes(body.length),
			...body,
		]);
	}

	static serverHelloDone(): Uint8Array {
		return new Uint8Array([HandshakeType.ServerHelloDone, ...as3Bytes(0)]);
	}

	/**
	 * Server finished message.
	 * The structure is defined in:
	 * https://datatracker.ietf.org/doc/html/rfc5246#section-7.4.9
	 *
	 * struct {
	 *     opaque verify_data[verify_data_length];
	 * } Finished;
	 *
	 * verify_data
	 *    PRF(master_secret, finished_label, Hash(handshake_messages))
	 *       [0..verify_data_length-1];
	 *
	 * finished_label
	 *    For Finished messages sent by the client, the string
	 *    "client finished".  For Finished messages sent by the server,
	 *    the string "server finished".
	 */
	static async createFinishedMessage(
		handshakeMessages: Uint8Array[],
		masterSecret: ArrayBuffer
	): Promise<Uint8Array> {
		// Step 1: Compute the hash of the handshake messages
		const handshakeHash = await crypto.subtle.digest(
			'SHA-256',
			concatUint8Arrays(handshakeMessages)
		);

		// Step 2: Compute the verify_data using the PRF
		const verifyData = new Uint8Array(
			await tls12Prf(
				masterSecret,
				new TextEncoder().encode('server finished'),
				handshakeHash,
				// verify_data length. TLS 1.2 specifies 12 bytes for verify_data
				12
			)
		);

		// Step 3: Construct the Finished message
		return new Uint8Array([
			HandshakeType.Finished,
			...as3Bytes(verifyData.length),
			...verifyData,
		]);
	}

	static changeCipherSpec(): Uint8Array {
		return new Uint8Array([0x01]);
	}
}

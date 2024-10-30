import { generateCertificate, certificateToPEM } from './certificates';
import { execSync } from 'child_process';
import { writeFileSync, mkdirSync, rmdirSync } from 'fs';
import { tmpdir } from 'os';
import { join } from 'path';

describe('generateCertificate', () => {
	let tempDir: string;
	let caKeyPath: string;
	let caCertPath: string;
	let siteKeyPath: string;
	let siteCertPath: string;

	beforeAll(() => {
		tempDir = join(tmpdir(), 'cert-test');
		try {
			mkdirSync(tempDir);
		} catch (error) {
			// Ignore error if directory already exists
		}
		caKeyPath = join(tempDir, 'ca_key.pem');
		caCertPath = join(tempDir, 'ca_cert.crt');
		siteKeyPath = join(tempDir, 'site_key.pem');
		siteCertPath = join(tempDir, 'site_cert.crt');
	});

	afterAll(() => {
		rmdirSync(tempDir, { recursive: true });
	});

	it('should generate a valid CA certificate', async () => {
		const CAroot = await generateCertificate({
			subject: {
				countryName: 'US',
				organizationName: 'Playground CA',
				commonName: 'playground-CA.com',
			},
			basicConstraints: {
				ca: true,
			},
		});

		const caCertPEM = certificateToPEM(CAroot.certificate);
		writeFileSync(caCertPath, caCertPEM);
		const caKey = await crypto.subtle.exportKey(
			'pkcs8',
			CAroot.keyPair.privateKey
		);
		writeFileSync(caKeyPath, new Uint8Array(caKey));

		// Verify the certificate
		const certInfo1 = execSync(
			`openssl x509 -in ${caCertPath} -text -noout | grep Subject`
		).toString();
		expect(certInfo1).toMatch(
			/Subject:\s*C\s*=\s*US, O\s*=\s*Playground CA, CN\s*=\s*playground-CA\.com/
		);
		const certInfo2 = execSync(
			`openssl x509 -in ${caCertPath} -text -noout`
		).toString();
		expect(certInfo2).toMatch(
			/X509v3 Basic Constraints:\s*\n\s*CA:\s*TRUE/
		);
	});

	it('should generate a valid site certificate signed by the CA', async () => {
		const CAroot = await generateCertificate({
			subject: {
				countryName: 'US',
				organizationName: 'Playground CA',
				commonName: 'playground-CA.com',
			},
			basicConstraints: {
				ca: true,
			},
		});

		const SiteCert = await generateCertificate(
			{
				subject: {
					countryName: 'US',
					organizationName: 'Playground Site',
					commonName: 'playground-site',
				},
				issuer: {
					countryName: 'US',
					organizationName: 'Playground CA',
					commonName: 'playground-CA.com',
				},
			},
			CAroot.keyPair
		);

		const caCertPEM = certificateToPEM(CAroot.certificate);
		writeFileSync(caCertPath, caCertPEM);
		const caKey = await crypto.subtle.exportKey(
			'pkcs8',
			CAroot.keyPair.privateKey
		);
		writeFileSync(caKeyPath, new Uint8Array(caKey));

		const siteCertPEM = certificateToPEM(SiteCert.certificate);
		writeFileSync(siteCertPath, siteCertPEM);
		const siteKey = await crypto.subtle.exportKey(
			'pkcs8',
			SiteCert.keyPair.privateKey
		);
		writeFileSync(siteKeyPath, new Uint8Array(siteKey));

		// Verify the site certificate
		const certInfo = execSync(
			`openssl x509 -in ${siteCertPath} -text -noout | grep Subject`
		).toString();

		expect(certInfo).toMatch(
			/Subject:\s*C\s*=\s*US, O\s*=\s*Playground Site, CN\s*=\s*playground-site/
		);

		const certInfo2 = execSync(
			`openssl x509 -in ${siteCertPath} -text -noout | grep Issuer`
		).toString();
		expect(certInfo2).toMatch(
			/Issuer:\s*C\s*=\s*US, O\s*=\s*Playground CA, CN\s*=\s*playground-CA\.com/
		);

		// Verify the certificate chain
		const verifyResult = execSync(
			`openssl verify -CAfile ${caCertPath} ${siteCertPath}`
		).toString();
		expect(verifyResult).toContain(': OK');
	});

	it('should generate certificates with matching key moduli', async () => {
		const CAroot = await generateCertificate({
			subject: {
				countryName: 'US',
				organizationName: 'Playground CA',
				commonName: 'playground-CA.com',
			},
			basicConstraints: {
				ca: true,
			},
		});

		const caCertPEM = certificateToPEM(CAroot.certificate);
		writeFileSync(caCertPath, caCertPEM);
		const caKey = await crypto.subtle.exportKey(
			'pkcs8',
			CAroot.keyPair.privateKey
		);
		writeFileSync(caKeyPath, new Uint8Array(caKey));

		const caCertModulus = execSync(
			`openssl x509 -noout -modulus -in ${caCertPath} | openssl sha1`
		).toString();
		const caKeyModulus = execSync(
			`openssl rsa -noout -modulus -in ${caKeyPath} | openssl sha1`
		).toString();

		expect(caCertModulus).toBe(caKeyModulus);
	});

	it('should generate certificates that can be used for encryption and decryption', async () => {
		const SiteCert = await generateCertificate({
			subject: {
				commonName: 'playground-site',
				organizationName: 'Playground Site',
				countryName: 'US',
			},
		});

		const siteCertPEM = certificateToPEM(SiteCert.certificate);
		writeFileSync(siteCertPath, siteCertPEM);
		const siteKey = await crypto.subtle.exportKey(
			'pkcs8',
			SiteCert.keyPair.privateKey
		);
		writeFileSync(siteKeyPath, new Uint8Array(siteKey));

		const testMessage = 'Hello, World';
		const testFilePath = join(tempDir, 'test.txt');
		const cipherPath = join(tempDir, 'cipher.txt');
		const decryptedPath = join(tempDir, 'decrypted.txt');
		const pubKeyPath = join(tempDir, 'site_cert.pub.pem');

		writeFileSync(testFilePath, testMessage);

		execSync(
			`openssl x509 -in ${siteCertPath} -noout -pubkey > ${pubKeyPath}`
		);
		execSync(
			`openssl pkeyutl -encrypt -in ${testFilePath} -pubin -inkey ${pubKeyPath} -out ${cipherPath}`
		);
		execSync(
			`openssl pkeyutl -decrypt -inkey ${siteKeyPath} -in ${cipherPath} -out ${decryptedPath}`
		);

		const decryptedMessage = execSync(`cat ${decryptedPath}`)
			.toString()
			.trim();
		expect(decryptedMessage).toBe(testMessage);
	});

	it('should generate certificates that can be used for signing and verification', async () => {
		const SiteCert = await generateCertificate({
			subject: {
				commonName: 'playground-site',
				organizationName: 'Playground Site',
				countryName: 'US',
			},
		});

		const siteCertPEM = certificateToPEM(SiteCert.certificate);
		writeFileSync(siteCertPath, siteCertPEM);
		const siteKey = await crypto.subtle.exportKey(
			'pkcs8',
			SiteCert.keyPair.privateKey
		);
		writeFileSync(siteKeyPath, new Uint8Array(siteKey));

		const testMessage = 'Hello, World';
		const testFilePath = join(tempDir, 'test.txt');
		const signaturePath = join(tempDir, 'test.sig');
		const pubKeyPath = join(tempDir, 'site_cert.pub.pem');

		writeFileSync(testFilePath, testMessage);

		execSync(
			`openssl x509 -in ${siteCertPath} -noout -pubkey > ${pubKeyPath}`
		);
		execSync(
			`openssl dgst -sha256 -sign ${siteKeyPath} -out ${signaturePath} ${testFilePath}`
		);

		const verifyResult = execSync(
			`openssl dgst -sha256 -verify ${pubKeyPath} -signature ${signaturePath} ${testFilePath}`
		).toString();
		expect(verifyResult.trim()).toBe('Verified OK');
	});
});

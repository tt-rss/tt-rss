<?php
class Crypt {

	/** the only algo supported at the moment */
	private const ENCRYPT_ALGO = 'xchacha20poly1305_ietf';

	/** currently only generates keys using sodium_crypto_aead_chacha20poly1305_keygen() i.e. one supported Crypt::ENCRYPT_ALGO
	 * @return string random 256-bit (for ChaCha20-Poly1305) binary string
	*/
	public static function generate_key() : string {
		return sodium_crypto_aead_chacha20poly1305_keygen();
	}

	/** encrypts provided ciphertext using Config::ENCRYPTION_KEY into an encrypted object
	 *
	 * @return array{'algo': string, 'nonce': string, 'payload': string} encrypted data object containing algo, nonce, and encrypted data
	*/
	public static function encrypt_string(string $ciphertext) : array {
		$key = Config::get(Config::ENCRYPTION_KEY);

		if (!$key)
			throw new Exception("Crypt::encrypt_string() failed to encrypt - key is not available");

		$nonce = \random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

		$payload = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($ciphertext, '', $nonce, hex2bin($key));

		if ($payload) {
			$encrypted_data = [
				'algo' => self::ENCRYPT_ALGO,
				'nonce' => $nonce,
				'payload' => $payload,
			];

			return $encrypted_data;
		}

		throw new Exception("Crypt::encrypt_string() failed to encrypt ciphertext");
	}

	/** decrypts payload of a valid encrypted object using Config::ENCRYPTION_KEY
	 *
	 * @param array{'algo': string, 'nonce': string, 'payload': string} $encrypted_data
	 *
	 * @return string decrypted string payload
	 */
	public static function decrypt_string(array $encrypted_data) : string {
		$key = Config::get(Config::ENCRYPTION_KEY);

		if (!$key)
			throw new Exception("Crypt::decrypt_string() failed to decrypt - key is not available");
        // only one is supported for the time being
        return match ($encrypted_data['algo']) {
            self::ENCRYPT_ALGO => sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($encrypted_data['payload'], '', $encrypted_data['nonce'], hex2bin($key)),
            default => throw new Exception('Crypt::decrypt_string() failed to decrypt passed encrypted data object, unsupported algo: ' . $encrypted_data['algo']),
        };
	}

}
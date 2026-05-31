<?php
use PHPUnit\Framework\TestCase;

final class CryptTest extends TestCase {

    /**
     * Set a value in the Config singleton.
     */
    private function setConfigParam(string $param, string $value): void {
        Config::set($param, $value);
    }

    /**
     * Ensure the Config singleton is in a clean state before each test.
     */
    private function resetConfig(): void {
        Config::set('ENCRYPTION_KEY', '');
    }

    public function test_generate_key_returns_valid_length(): void {
        $key = Crypt::generate_key();

        // xchacha20-poly1305 uses a 256-bit (32-byte) key
        $this->assertSame(32, strlen($key));
        // key should be raw binary data (not hex-encoded)
        $this->assertNotEquals(bin2hex($key), $key, 'Key should be binary, not hex-encoded');
    }

    public function test_generate_key_produces_unique_keys(): void {
        $key1 = Crypt::generate_key();
        $key2 = Crypt::generate_key();

        $this->assertNotSame($key1, $key2, 'Two generated keys should be unique');
    }

    public function test_encrypt_string_requires_encryption_key(): void {
        $this->resetConfig();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Crypt::encrypt_string() failed to encrypt - key is not available');

        Crypt::encrypt_string('sensitive data');
    }

    public function test_encrypt_string_returns_valid_structure(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $encrypted = Crypt::encrypt_string('hello world');

        $this->assertArrayHasKey('algo', $encrypted);
        $this->assertArrayHasKey('nonce', $encrypted);
        $this->assertArrayHasKey('payload', $encrypted);
        $this->assertSame('xchacha20poly1305_ietf', $encrypted['algo']);
        // nonce should be the correct length for xchacha20poly1305_ietf (24 bytes)
        $this->assertSame(24, strlen($encrypted['nonce']));
        // payload should be non-empty binary data
        $this->assertNotEmpty($encrypted['payload']);
    }

    public function test_encrypt_string_with_empty_string(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $encrypted = Crypt::encrypt_string('');

        $this->assertSame('xchacha20poly1305_ietf', $encrypted['algo']);
        $this->assertNotEmpty($encrypted['payload']);
    }

    public function test_encrypt_string_with_unicode(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $original = 'こんにちは世界 🌍 Привет мир';
        $encrypted = Crypt::encrypt_string($original);

        $this->assertNotEmpty($encrypted['payload']);
    }

    public function test_encrypt_string_with_large_payload(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $largeData = str_repeat('A', 100000);
        $encrypted = Crypt::encrypt_string($largeData);

        $this->assertNotEmpty($encrypted['payload']);
    }

    public function test_decrypt_string_requires_encryption_key(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $encrypted = Crypt::encrypt_string('test data');

        // Now clear the key
        $this->setConfigParam('ENCRYPTION_KEY', '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Crypt::decrypt_string() failed to decrypt - key is not available');

        Crypt::decrypt_string($encrypted);
    }

    public function test_decrypt_string_with_wrong_algo(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $badEncrypted = [
            'algo' => 'unsupported_algo',
            'nonce' => random_bytes(24),
            'payload' => random_bytes(64),
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/unsupported algo: unsupported_algo/');

        Crypt::decrypt_string($badEncrypted);
    }

    public function test_decrypt_string_roundtrip(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $original = 'hello world';
        $encrypted = Crypt::encrypt_string($original);
        $decrypted = Crypt::decrypt_string($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function test_decrypt_string_roundtrip_with_unicode(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $original = 'こんにちは世界 🌍 Привет мир 你好世界';
        $encrypted = Crypt::encrypt_string($original);
        $decrypted = Crypt::decrypt_string($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function test_decrypt_string_roundtrip_with_empty_string(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $original = '';
        $encrypted = Crypt::encrypt_string($original);
        $decrypted = Crypt::decrypt_string($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function test_decrypt_string_roundtrip_with_large_payload(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $original = str_repeat('A', 100000);
        $encrypted = Crypt::encrypt_string($original);
        $decrypted = Crypt::decrypt_string($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function test_decrypt_string_fails_with_tampered_payload(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $encrypted = Crypt::encrypt_string('secret message');

        // Tamper with the payload by appending bytes (changes the auth tag)
        $tampered = $encrypted;
        $tampered['payload'] .= 'XX';

        // sodium_crypto_aead_xchacha20poly1305_ietf_decrypt returns false on tampered data,
        // which PHP coerces to '' due to the string return type
        $result = Crypt::decrypt_string($tampered);
        $this->assertSame('', $result, 'Tampered ciphertext should return empty string');
    }

    public function test_decrypt_string_fails_with_tampered_nonce(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $encrypted = Crypt::encrypt_string('secret message');

        // Tamper with the nonce (append a byte to change it)
        $tampered = $encrypted;
        $tampered['nonce'] = $encrypted['nonce'] . 'XX';

        $this->expectException(\SodiumException::class);

        Crypt::decrypt_string($tampered);
    }

    public function test_encrypt_string_with_different_keys_produces_different_ciphertext(): void {
        $key1 = bin2hex(Crypt::generate_key());
        $key2 = bin2hex(Crypt::generate_key());

        $this->setConfigParam('ENCRYPTION_KEY', $key1);
        $encrypted1 = Crypt::encrypt_string('same plaintext');

        $this->setConfigParam('ENCRYPTION_KEY', $key2);
        $encrypted2 = Crypt::encrypt_string('same plaintext');

        // Different keys should produce different ciphertext
        $this->assertNotSame($encrypted1, $encrypted2);

        // But each should decrypt correctly with its own key
        $this->setConfigParam('ENCRYPTION_KEY', $key1);
        $this->assertSame('same plaintext', Crypt::decrypt_string($encrypted1));

        $this->setConfigParam('ENCRYPTION_KEY', $key2);
        $this->assertSame('same plaintext', Crypt::decrypt_string($encrypted2));
    }

    public function test_encrypt_decrypt_same_key_different_calls(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        // Multiple encrypt/decrypt cycles with the same key
        for ($i = 0; $i < 5; $i++) {
            $original = "message $i";
            $encrypted = Crypt::encrypt_string($original);
            $decrypted = Crypt::decrypt_string($encrypted);
            $this->assertSame($original, $decrypted, "Roundtrip failed on iteration $i");
        }
    }

    public function test_decrypt_string_with_invalid_payload(): void {
        $key = bin2hex(Crypt::generate_key());
        $this->setConfigParam('ENCRYPTION_KEY', $key);

        $invalidData = [
            'algo' => 'xchacha20poly1305_ietf',
            'nonce' => random_bytes(24),
            'payload' => 'not-valid-ciphertext',
        ];

        // Invalid ciphertext should return empty string (false coerced by string return type)
        $result = Crypt::decrypt_string($invalidData);
        $this->assertSame('', $result, 'Invalid ciphertext should return empty string');
    }

}

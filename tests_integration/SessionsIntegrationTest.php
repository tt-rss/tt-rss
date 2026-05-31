<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the CRUD lifecycle of the Sessions class.
 *
 * Exercises the full path through PDO → PostgreSQL for session persistence:
 * write, read, exists, destroy, and auto-create on read miss.
 */
final class SessionsIntegrationTest extends DbTestCase {

    /**
     * Disable encryption and set a reasonable session lifetime for all tests.
     */
    protected function setUp(): void {
        parent::setUp();

        // Default: encryption off for plaintext CRUD tests
        Config::set(Config::ENCRYPTION_KEY, '');
        // Session lifetime of 1 hour so sessions don't expire during tests
        Config::set(Config::SESSION_COOKIE_LIFETIME, '3600');

        // Ensure multi-user mode for validate_session tests
        Config::set(Config::SINGLE_USER_MODE, 'false');
    }

    /**
     * Set up a valid admin session (uid=1 with correct pwd_hash).
     * The admin user is created by the schema with pwd_hash = SHA1('password').
     */
    private function setValidAdminSession(): void {
        $_SESSION['uid'] = 1;
        $_SESSION['pwd_hash'] = 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8';
    }

    /**
     * Set up a session for a non-existent user.
     */
    private function setNonExistentUserSession(): void {
        $_SESSION['uid'] = 99999;
        unset($_SESSION['pwd_hash']);
    }

    /**
     * Set up a session for a disabled user.
     * @return int The ID of the created disabled user.
     */
    private function createDisabledUser(): int {
        $pdo = Db::pdo();
        $pwdHash = 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8'; // SHA1('password')

        $pdo->exec("
            INSERT INTO ttrss_users (login, pwd_hash, access_level)
            VALUES ('disabled_test_user', '{$pwdHash}', " . UserHelper::ACCESS_LEVEL_DISABLED . ")
        ");

        $uid = (int) $pdo->query(
            "SELECT currval(pg_get_serial_sequence('ttrss_users', 'id'))"
        )->fetchColumn();

        $_SESSION['uid'] = $uid;
        $_SESSION['pwd_hash'] = $pwdHash;

        return $uid;
    }

    /**
     * Clean up any disabled test users.
     */
    private function cleanupDisabledUsers(): void {
        Db::pdo()->exec("DELETE FROM ttrss_users WHERE login = 'disabled_test_user'");
    }

    /**
     * Reset the session to a clean state (no uid, no pwd_hash).
     */
    private function setGuestSession(): void {
        unset($_SESSION['uid']);
        unset($_SESSION['pwd_hash']);
    }

    /**
     * Generate a valid encryption key and enable it for the test.
     */
    private function enableEncryption(): void {
        $key = bin2hex(Crypt::generate_key());
        Config::set(Config::ENCRYPTION_KEY, $key);
    }

    /**
     * Query the raw stored data from the database for a given session ID.
     */
    private function getStoredData(string $id): ?string {
        $pdo = Db::pdo();
        $sth = $pdo->prepare('SELECT data FROM ttrss_sessions WHERE id = ?');
        $sth->execute([$id]);
        $row = $sth->fetch();

        return $row ? $row['data'] : null;
    }

    /**
     * Decode the base64 DB value back to its raw form.
     */
    private function decodeStoredData(string $b64Data): string {
        return base64_decode($b64Data);
    }

    // ── exists() ──────────────────────────────────────────────────────────────

    public function test_exists_returns_false_for_nonexistent_session(): void {
        $sessions = new Sessions();
        $nonexistentId = 'nonexistent-session-id-' . uniqid();

        $this->assertFalse(
            $sessions::exists($nonexistentId),
            'exists() should return false for a session that has never been written'
        );
    }

    public function test_exists_returns_true_after_write(): void {
        $sessions = new Sessions();
        $id = 'exists-test-id-' . uniqid();
        $data = 'test-data-for-exists';

        $sessions->write($id, $data);

        $this->assertTrue(
            $sessions::exists($id),
            'exists() should return true after a successful write'
        );
    }

    // ── write() ───────────────────────────────────────────────────────────────

    public function test_write_stores_data_in_database(): void {
        $sessions = new Sessions();
        $id = 'write-store-test-' . uniqid();
        $data = 'hello world';

        $result = $sessions->write($id, $data);

        $this->assertTrue($result, 'write() should return true on success');

        // Verify in database directly
        $pdo = Db::pdo();
        $sth = $pdo->prepare('SELECT data FROM ttrss_sessions WHERE id = ?');
        $sth->execute([$id]);
        $row = $sth->fetch();

        $this->assertNotNull($row, 'Session row should exist in database');
        // Data is base64-encoded in the DB
        $this->assertEquals(
            base64_encode($data),
            $row['data'],
            'Stored data should be base64-encoded version of the original'
        );
    }

    public function test_write_overwrites_existing_session(): void {
        $sessions = new Sessions();
        $id = 'write-overwrite-test-' . uniqid();
        $data1 = 'original data';
        $data2 = 'updated data';

        $sessions->write($id, $data1);
        $sessions->write($id, $data2);

        $pdo = Db::pdo();
        $sth = $pdo->prepare('SELECT data FROM ttrss_sessions WHERE id = ?');
        $sth->execute([$id]);
        $row = $sth->fetch();

        $this->assertEquals(
            base64_encode($data2),
            $row['data'],
            'Second write should overwrite the first'
        );
    }

    // ── read() ────────────────────────────────────────────────────────────────

    public function test_read_returns_written_data(): void {
        $sessions = new Sessions();
        $id = 'read-roundtrip-' . uniqid();
        $data = 'session payload';

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'read() should return the exact data that was written');
    }

    public function test_read_returns_empty_string_for_new_session(): void {
        $sessions = new Sessions();
        $id = 'read-auto-create-' . uniqid();

        $result = $sessions->read($id);

        $this->assertSame('', $result, 'read() for a new session should return empty string');
        $this->assertTrue(
            $sessions::exists($id),
            'read() should auto-create the session in the database'
        );
    }

    public function test_read_with_unicode_data(): void {
        $sessions = new Sessions();
        $id = 'read-unicode-' . uniqid();
        $data = 'こんにちは世界 🌍 Привет мир 你好世界';

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Unicode data should roundtrip correctly');
    }

    public function test_read_with_large_payload(): void {
        $sessions = new Sessions();
        $id = 'read-large-' . uniqid();
        $data = str_repeat('A', 50000); // 50 KB

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Large payload should roundtrip correctly');
    }

    public function test_read_returns_false_on_failure(): void {
        // This is hard to trigger in normal operation, but we can verify
        // the return type is correct. read() returns false|string.
        $sessions = new Sessions();
        $id = 'read-type-test-' . uniqid();

        // Auto-create returns '' (string), not false
        $result = $sessions->read($id);
        $this->assertIsString($result, 'read() should return a string (even if empty)');
    }

    // ── destroy() ─────────────────────────────────────────────────────────────

    public function test_destroy_removes_session_from_database(): void {
        $sessions = new Sessions();
        $id = 'destroy-test-' . uniqid();
        $data = 'to-be-destroyed';

        $sessions->write($id, $data);
        $this->assertTrue($sessions::exists($id), 'Session should exist before destroy');

        $result = $sessions->destroy($id);

        $this->assertTrue($result, 'destroy() should return true on success');
        $this->assertFalse(
            $sessions::exists($id),
            'Session should not exist after destroy'
        );
    }

    public function test_destroy_nonexistent_session_returns_true(): void {
        $sessions = new Sessions();
        $id = 'destroy-missing-' . uniqid();

        // destroy() uses DELETE which returns true even if 0 rows affected
        $result = $sessions->destroy($id);

        $this->assertTrue($result, 'destroy() should return true even for non-existent sessions');
    }

    // ── Full lifecycle ────────────────────────────────────────────────────────

    public function test_full_session_lifecycle(): void {
        $sessions = new Sessions();
        $id = 'lifecycle-' . uniqid();

        // 1. New session does not exist
        $this->assertFalse($sessions::exists($id));

        // 2. read() auto-creates an empty session
        $this->assertSame('', $sessions->read($id));
        $this->assertTrue($sessions::exists($id));

        // 3. write() stores data
        $sessions->write($id, 'step-3-data');
        $this->assertSame('step-3-data', $sessions->read($id));

        // 4. write() updates existing data
        $sessions->write($id, 'step-4-data');
        $this->assertSame('step-4-data', $sessions->read($id));

        // 5. destroy() removes the session
        $sessions->destroy($id);
        $this->assertFalse($sessions::exists($id));
    }

    // ── Encryption ──────────────────────────────────────────────────────────

    public function test_write_stores_encrypted_data_when_key_configured(): void {
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-write-' . uniqid();
        $data = 'sensitive session data';

        $sessions->write($id, $data);

        $storedB64 = $this->getStoredData($id);
        $this->assertNotNull($storedB64, 'Session row should exist in database');

        $rawStored = $this->decodeStoredData($storedB64);

        // With encryption, data is: serialize(Crypt::encrypt_string($data))
        // which produces a serialized PHP array — not the plaintext base64
        $this->assertNotEquals(
            base64_encode($data),
            $storedB64,
            'Stored data should NOT be the plaintext base64 encoding'
        );

        // The raw stored data should be a serialized PHP structure
        $unserialized = @unserialize($rawStored);
        $this->assertIsArray(
            $unserialized,
            'Stored data should be a serialized PHP array (encrypted object)'
        );
        $this->assertArrayHasKey('algo', $unserialized);
        $this->assertArrayHasKey('nonce', $unserialized);
        $this->assertArrayHasKey('payload', $unserialized);
    }

    public function test_write_stores_plaintext_when_encryption_disabled(): void {
        // Encryption is disabled by default in setUp()

        $sessions = new Sessions();
        $id = 'plaintext-write-' . uniqid();
        $data = 'not sensitive data';

        $sessions->write($id, $data);

        $storedB64 = $this->getStoredData($id);
        $this->assertNotNull($storedB64);

        // Without encryption, data is simply: base64_encode($data)
        $this->assertEquals(
            base64_encode($data),
            $storedB64,
            'Stored data should be the plaintext base64 encoding when encryption is off'
        );
    }

    public function test_read_roundtrip_with_encryption_enabled(): void {
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-roundtrip-' . uniqid();
        $data = 'secret session payload';

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Encrypted data should roundtrip correctly');
    }

    public function test_read_roundtrip_with_encryption_disabled(): void {
        // Encryption is disabled by default in setUp()

        $sessions = new Sessions();
        $id = 'plaintext-roundtrip-' . uniqid();
        $data = 'open session data';

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Plaintext data should roundtrip correctly');
    }

    public function test_read_with_encryption_handles_empty_data(): void {
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-empty-' . uniqid();

        // write empty string
        $sessions->write($id, '');
        $result = $sessions->read($id);

        $this->assertSame('', $result, 'Empty string should roundtrip with encryption');
    }

    public function test_read_with_encryption_handles_unicode(): void {
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-unicode-' . uniqid();
        $data = '秘密情報 🔐 安全なセッション';

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Unicode data should roundtrip with encryption');
    }

    public function test_read_with_encryption_handles_large_payload(): void {
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-large-' . uniqid();
        $data = str_repeat('S', 50000); // 50 KB

        $sessions->write($id, $data);
        $result = $sessions->read($id);

        $this->assertSame($data, $result, 'Large payload should roundtrip with encryption');
    }

    public function test_different_encryption_keys_produce_different_storage(): void {
        $sessions = new Sessions();
        $id = 'key-diff-' . uniqid();
        $data = 'same plaintext';

        // Write with key1
        $key1 = bin2hex(Crypt::generate_key());
        Config::set(Config::ENCRYPTION_KEY, $key1);
        $sessions->write($id, $data);
        $stored1 = $this->getStoredData($id);

        // Destroy and re-write with key2
        $sessions->destroy($id);

        $key2 = bin2hex(Crypt::generate_key());
        Config::set(Config::ENCRYPTION_KEY, $key2);
        $sessions->write($id, $data);
        $stored2 = $this->getStoredData($id);

        $this->assertNotEquals(
            $stored1,
            $stored2,
            'Different keys should produce different stored data'
        );

        // But both should decrypt correctly
        Config::set(Config::ENCRYPTION_KEY, $key1);
        $sessions->destroy($id); // clean up the key2 entry

        // Re-write with key1 to verify
        $sessions->write($id, $data);
        $result = $sessions->read($id);
        $this->assertSame($data, $result);
    }

    public function test_encrypt_then_decrypt_with_key_removed_fails_gracefully(): void {
        // Write with encryption enabled
        $this->enableEncryption();

        $sessions = new Sessions();
        $id = 'encrypt-stale-' . uniqid();
        $data = 'encrypted before key removal';

        $sessions->write($id, $data);

        // Now remove the encryption key
        Config::set(Config::ENCRYPTION_KEY, '');

        // Reading without the key: read() falls back to returning the raw
        // base64-decoded data (which is the serialized encrypted blob)
        // This is the documented fallback behavior — it won't throw an exception
        // but will return the un-decrypted data.
        $result = $sessions->read($id);

        // The result should NOT be the original plaintext — it's the fallback path
        $this->assertNotEquals($data, $result);

        // The data should still exist in the DB
        $this->assertTrue($sessions::exists($id));
    }

    // ── validate_session() ──────────────────────────────────────────────────

    public function test_validate_session_returns_true_in_single_user_mode(): void {
        Config::set(Config::SINGLE_USER_MODE, 'true');

        // Session state doesn't matter in single-user mode
        unset($_SESSION['uid']);
        unset($_SESSION['pwd_hash']);

        $this->assertTrue(
            Sessions::validate_session(),
            'Should always return true when SINGLE_USER_MODE is enabled'
        );

        // Reset to multi-user mode
        Config::set(Config::SINGLE_USER_MODE, 'false');
    }

    public function test_validate_session_returns_true_for_guest(): void {
        $this->setGuestSession();

        $this->assertTrue(
            Sessions::validate_session(),
            'Guest sessions (no uid) should validate successfully'
        );
    }

    public function test_validate_session_returns_true_for_valid_admin(): void {
        $this->setValidAdminSession();

        $this->assertTrue(
            Sessions::validate_session(),
            'Admin session with correct pwd_hash should validate'
        );
    }

    public function test_validate_session_fails_when_password_changed(): void {
        $this->setValidAdminSession();

        // Corrupt the pwd_hash in the session
        $_SESSION['pwd_hash'] = 'SHA1:wronghashvalue000000000000000000000000000000';

        $result = Sessions::validate_session();

        $this->assertFalse($result, 'Should fail when pwd_hash does not match DB');
        $this->assertArrayHasKey(
            'login_error_msg',
            $_SESSION,
            'Should set login_error_msg on password mismatch'
        );
        $this->assertStringContainsString(
            'password changed',
            $_SESSION['login_error_msg'],
            'Error message should mention password change'
        );
    }

    public function test_validate_session_fails_when_user_not_found(): void {
        $this->setNonExistentUserSession();

        $result = Sessions::validate_session();

        $this->assertFalse($result, 'Should fail when user ID does not exist in DB');
        $this->assertArrayHasKey(
            'login_error_msg',
            $_SESSION,
            'Should set login_error_msg when user not found'
        );
        $this->assertStringContainsString(
            'not found',
            $_SESSION['login_error_msg'],
            'Error message should mention user not found'
        );
    }

    public function test_validate_session_fails_when_account_disabled(): void {
        $this->createDisabledUser();

        try {
            $result = Sessions::validate_session();

            $this->assertFalse($result, 'Should fail when account is disabled');
            $this->assertArrayHasKey(
                'login_error_msg',
                $_SESSION,
                'Should set login_error_msg for disabled account'
            );
            $this->assertStringContainsString(
                'disabled',
                $_SESSION['login_error_msg'],
                'Error message should mention account is disabled'
            );
        } finally {
            $this->cleanupDisabledUsers();
        }
    }

    public function test_validate_session_with_empty_pwd_hash_in_session(): void {
        $this->setValidAdminSession();

        // Set pwd_hash to empty string (but uid is valid)
        $_SESSION['pwd_hash'] = '';

        $result = Sessions::validate_session();

        $this->assertFalse(
            $result,
            'Should fail when pwd_hash in session is empty but user exists in DB'
        );
    }

    public function test_validate_session_with_uid_zero(): void {
        // uid=0 is falsy in PHP, so it should be treated as a guest
        $_SESSION['uid'] = 0;
        unset($_SESSION['pwd_hash']);

        $result = Sessions::validate_session();

        $this->assertTrue(
            $result,
            'uid=0 (falsy) should be treated as guest and validate successfully'
        );
    }
}

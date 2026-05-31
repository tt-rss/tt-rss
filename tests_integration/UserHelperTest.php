<?php
/** @group integration */
final class UserHelperTest extends DbTestCase {

    private string $testSalt;
    private int $testUid = 0;

    protected function setUp(): void {
        parent::setUp();

        // unset($_SESSION["uid"]);

        // Ensure multi-user mode
        // Config::get_instance()->set(Config::SINGLE_USER_MODE, "false");

        putenv("TTRSS_SINGLE_USER_MODE=");

        Config::get_instance()->reload();

        // Create test user with known password hash
        $this->testSalt = bin2hex(random_bytes(16));
        $password = 'testpass123';
        $hash = UserHelper::hash_password($password, $this->testSalt, UserHelper::HASH_ALGO_SSHA512);

        $pdo = Db::pdo();
        $pdo->exec("
            INSERT INTO ttrss_users (login, pwd_hash, salt, access_level)
            VALUES ('testuser', '{$hash}', '{$this->testSalt}', 0)
        ");
        $this->testUid = (int) $pdo->query(
            "SELECT currval(pg_get_serial_sequence('ttrss_users', 'id'))"
        )->fetchColumn();
    }

    protected function tearDown(): void {
        parent::tearDown();

        $pdo = Db::pdo();

        $pdo->exec("DELETE FROM ttrss_users WHERE login = 'testuser'");
        $pdo->exec("DELETE FROM ttrss_users WHERE login = 'disabled'");
    }

    // ── Happy paths ──────────────────────────────────────────────────────────

    public function test_correct_credentials_returns_true_and_sets_session(): void {
        $result = UserHelper::authenticate('testuser', 'testpass123');

        $this->assertTrue($result);
        $this->assertEquals($this->testUid, $_SESSION['uid']);
        $this->assertEquals('testuser', $_SESSION['name']);
        $this->assertEquals(UserHelper::ACCESS_LEVEL_USER, $_SESSION['access_level']);
        $this->assertEquals('auth_internal', $_SESSION['auth_module']);
    }

    public function test_admin_credentials_work(): void {
        $result = UserHelper::authenticate('admin', 'password');

        $this->assertTrue($result);
        $this->assertEquals(1, $_SESSION['uid']);
        $this->assertEquals(UserHelper::ACCESS_LEVEL_ADMIN, $_SESSION['access_level']);
    }

    public function test_authenticate_regenerates_session_id(): void {
        $before = session_id();
        UserHelper::authenticate('testuser', 'testpass123');

        $this->assertNotEquals($before, session_id());
    }

    public function test_authenticate_updates_last_login(): void {
        usleep(100_000);
        UserHelper::authenticate('testuser', 'testpass123');

        $lastLogin = Db::pdo()->query(
            "SELECT last_login FROM ttrss_users WHERE login = 'testuser'"
        )->fetchColumn();

        $this->assertNotNull($lastLogin);
    }

    // ── Failures ─────────────────────────────────────────────────────────────

    public function test_wrong_password_returns_false(): void {
        $this->assertFalse(UserHelper::authenticate('testuser', 'wrong'));
    }

    public function test_nonexistent_user_returns_false(): void {
        $this->assertFalse(UserHelper::authenticate('ghost', 'anything'));
    }

    public function test_login_is_case_insensitive(): void {
        $this->assertTrue(UserHelper::authenticate('TESTUSER', 'testpass123'));
    }

    // ── Disabled user ────────────────────────────────────────────────────────

    public function test_disabled_user_cannot_authenticate(): void {
        $hash = UserHelper::hash_password('nope', 'saltxx', UserHelper::HASH_ALGO_SSHA512);
        Db::pdo()->exec("
            INSERT INTO ttrss_users (login, pwd_hash, salt, access_level)
            VALUES ('disabled', '{$hash}', 'saltxx', -2)
        ");

        $this->assertFalse(UserHelper::authenticate('disabled', 'nope'));
    }

}

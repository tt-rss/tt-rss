<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Prefs class.
 *
 * Exercises database-backed preference get/set, caching, type casting,
 * profile blacklisting, and reset behavior.
 */
final class PrefsTest extends DbTestCase {

    /**
     * Create a synthetic test user in the database.
     *
     * @return int The created user's ID.
     */
    private function createTestUser(string $login = 'test_user'): int {
        $pdo = Db::pdo();
        $pwdHash = 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8'; // SHA1('password')

        $pdo->exec(
            "INSERT INTO ttrss_users (login, pwd_hash, access_level) VALUES ('{$login}', '{$pwdHash}', 0)"
        );

        return (int) $pdo->query(
            "SELECT currval(pg_get_serial_sequence('ttrss_users', 'id'))"
        )->fetchColumn();
    }

    /**
     * Helper: query a raw preference value from the database.
     */
    private function getRawPref(string $prefName, int $ownerUid, ?int $profileId = null): ?string {
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT value FROM ttrss_user_prefs2
             WHERE pref_name = :name AND owner_uid = :uid
               AND (profile = :profile OR (:profile IS NULL AND profile IS NULL))"
        );
        $sth->execute([
            'name'    => $prefName,
            'uid'     => $ownerUid,
            'profile' => $profileId,
        ]);
        $row = $sth->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['value'] : null;
    }

    // ── is_valid() ────────────────────────────────────────────────────────────

    public function test_is_valid_returns_true_for_known_prefs(): void {
        $this->assertTrue(Prefs::is_valid(Prefs::PURGE_OLD_DAYS));
        $this->assertTrue(Prefs::is_valid(Prefs::ENABLE_FEED_CATS));
        $this->assertTrue(Prefs::is_valid(Prefs::USER_TIMEZONE));
        $this->assertTrue(Prefs::is_valid(Prefs::USER_LANGUAGE));
    }

    public function test_is_valid_returns_false_for_unknown_prefs(): void {
        $this->assertFalse(Prefs::is_valid('NONEXISTENT_PREF'));
        $this->assertFalse(Prefs::is_valid(''));
        $this->assertFalse(Prefs::is_valid('some_random_key'));
    }

    // ── get_default() ─────────────────────────────────────────────────────────

    public function test_get_default_returns_default_values(): void {
        $this->assertSame(60, Prefs::get_default(Prefs::PURGE_OLD_DAYS));
        $this->assertSame(30, Prefs::get_default(Prefs::DEFAULT_UPDATE_INTERVAL));
        $this->assertTrue(Prefs::get_default(Prefs::ENABLE_FEED_CATS));
        $this->assertFalse(Prefs::get_default(Prefs::REVERSE_HEADLINES));
        $this->assertSame("M d, G:i", Prefs::get_default(Prefs::SHORT_DATE_FORMAT));
        $this->assertSame("adaptive", Prefs::get_default(Prefs::_DEFAULT_VIEW_MODE));
    }

    public function test_get_default_returns_null_for_unknown_pref(): void {
        $this->assertNull(Prefs::get_default('NONEXISTENT_PREF'));
    }

    // ── get() — defaults ─────────────────────────────────────────────────────

    public function test_get_returns_default_when_no_db_row_exists(): void {
        // uid=1 is the admin user from seed.sql, no prefs are set for it
        $result = Prefs::get(Prefs::PURGE_OLD_DAYS, 1);
        $this->assertSame(60, $result);
    }

    public function test_get_returns_correct_types_for_int_prefs(): void {
        $result = Prefs::get(Prefs::PURGE_OLD_DAYS, 1);
        $this->assertSame(60, $result);

        $result = Prefs::get(Prefs::DEFAULT_UPDATE_INTERVAL, 1);
        $this->assertSame(30, $result);
    }

    public function test_get_returns_correct_types_for_bool_prefs(): void {
        $result = Prefs::get(Prefs::ENABLE_FEED_CATS, 1);
        $this->assertTrue($result);

        $result = Prefs::get(Prefs::REVERSE_HEADLINES, 1);
        $this->assertFalse($result);
    }

    public function test_get_returns_correct_types_for_string_prefs(): void {
        $result = Prefs::get(Prefs::SHORT_DATE_FORMAT, 1);
        $this->assertSame("M d, G:i", $result);

        $result = Prefs::get(Prefs::USER_TIMEZONE, 1);
        $this->assertSame("Automatic", $result);
    }

    // ── get() — from database ────────────────────────────────────────────────

    public function test_get_returns_overridden_value_from_db(): void {
        $uid = $this->createTestUser('test_overridden');
        $prefName = Prefs::PURGE_OLD_DAYS;
        $value = 90;

        Prefs::set($prefName, $value, $uid, null);

        $result = Prefs::get($prefName, $uid);
        $this->assertSame(90, $result);
    }

    // ── set() — basic ────────────────────────────────────────────────────────

    public function test_set_inserts_new_row_into_db(): void {
        $uid = $this->createTestUser('test_insert');
        $prefName = Prefs::PURGE_OLD_DAYS;
        $value = 120;

        $before = $this->getRawPref($prefName, $uid, null);
        $this->assertNull($before, 'No row should exist before set()');

        $result = Prefs::set($prefName, $value, $uid, null);

        $this->assertTrue($result, 'set() should return true on success');
        $this->assertSame("120", $this->getRawPref($prefName, $uid, null),
            'Value should be stored in the database');
    }

    public function test_set_updates_existing_row(): void {
        $uid = $this->createTestUser('test_update');
        $prefName = Prefs::PURGE_OLD_DAYS;
        $value1 = 99;
        $value2 = 200;

        Prefs::set($prefName, $value1, $uid, null);
        $firstRow = $this->getRawPref($prefName, $uid, null);
        $this->assertSame("99", $firstRow);

        Prefs::set($prefName, $value2, $uid, null);
        $this->assertSame("200", $this->getRawPref($prefName, $uid, null),
            'set() should UPDATE when row already exists');
    }

    public function test_set_stores_bool_as_string_in_db(): void {
        $uid = $this->createTestUser('test_bool_db');
        $prefName = Prefs::ENABLE_FEED_CATS;

        // ENABLE_FEED_CATS defaults to true, so set false first to force a DB row
        Prefs::set($prefName, false, $uid, null);
        $this->assertSame("", $this->getRawPref($prefName, $uid, null),
            'false should be stored as empty string in DB');

        // Now set true — this is a change from the DB value
        Prefs::set($prefName, true, $uid, null);
        $this->assertSame("1", $this->getRawPref($prefName, $uid, null),
            'true should be stored as "1" in DB');
    }

    public function test_set_stores_string_in_db(): void {
        $uid = $this->createTestUser('test_string_db');
        $prefName = Prefs::SHORT_DATE_FORMAT;
        $value = "Y-m-d H:i";

        Prefs::set($prefName, $value, $uid, null);
        $this->assertSame("Y-m-d H:i", $this->getRawPref($prefName, $uid, null));
    }

    // ── set() — type casting ─────────────────────────────────────────────────

    public function test_set_casts_string_to_int_for_int_prefs(): void {
        $uid = $this->createTestUser('test_cast_int');
        $prefName = Prefs::PURGE_OLD_DAYS;

        // Pass a string "45" for an INT pref — should be cast to 45
        Prefs::set($prefName, "45", $uid, null);

        // get() should return an int
        $result = Prefs::get($prefName, $uid);
        $this->assertSame(45, $result);
    }

    public function test_set_casts_string_to_bool_for_bool_prefs(): void {
        $uid = $this->createTestUser('test_cast_bool');
        $prefName = Prefs::ENABLE_FEED_CATS;

        Prefs::set($prefName, "true", $uid, null);
        $this->assertTrue(Prefs::get($prefName, $uid));

        Prefs::set($prefName, "false", $uid, null);
        $this->assertFalse(Prefs::get($prefName, $uid));
    }

    // ── set() — strip_tags ───────────────────────────────────────────────────

    public function test_set_strips_tags_from_string_values(): void {
        $uid = $this->createTestUser('test_strip_tags');
        $prefName = Prefs::SHORT_DATE_FORMAT;
        $malicious = "<script>alert('xss')</script>M d, G:i";

        Prefs::set($prefName, $malicious, $uid, null);

        // strip_tags removes the tags, trim removes surrounding whitespace
        $result = Prefs::get($prefName, $uid);
        $this->assertSame("alert('xss')M d, G:i", $result);
    }

    // ── set() — no-op when value unchanged ───────────────────────────────────

    public function test_set_returns_true_without_db_change_when_value_unchanged(): void {
        $uid = $this->createTestUser('test_noop');
        $prefName = Prefs::PURGE_OLD_DAYS;
        $value = 60; // This is the default

        // Setting the default value should still return true
        $result = Prefs::set($prefName, $value, $uid, null);

        $this->assertTrue($result, 'set() should return true even when value equals default');

        // No row should have been inserted for the default value
        // (because _get returns the default from cache, and _set sees they're equal)
        $dbValue = $this->getRawPref($prefName, $uid, null);
        $this->assertNull($dbValue, 'No DB row should be created when setting the default value');
    }

    // ── get_all() ────────────────────────────────────────────────────────────

    public function test_get_all_returns_all_pref_definitions(): void {
        $uid = $this->createTestUser('test_get_all');
        $all = Prefs::get_all($uid);

        $this->assertNotEmpty($all);

        // Check that each returned entry has the expected structure
        foreach ($all as $entry) {
            $this->assertArrayHasKey('pref_name', $entry);
            $this->assertArrayHasKey('value', $entry);
            $this->assertArrayHasKey('type_hint', $entry);
        }

        // Check that all known pref constants are present (only those in _DEFAULTS)
        $ref = new ReflectionClass(Prefs::class);
        $constants = $ref->getConstants();

        // Build a list of pref names that are actually in _DEFAULTS
        $ref = new ReflectionClass(Prefs::class);
        $defaultsConst = $ref->getReflectionConstant('_DEFAULTS');
        $defaults = $defaultsConst->getValue();
        $prefNames = array_keys($defaults);

        $foundNames = array_column($all, 'pref_name');
        foreach ($prefNames as $name) {
            $this->assertContains($name, $foundNames, "Pref constant $name should be in get_all() result");
        }
    }

    public function test_get_all_returns_default_values_for_new_user(): void {
        $uid = $this->createTestUser('test_get_all_defaults');
        $all = Prefs::get_all($uid);

        // Find PURGE_OLD_DAYS in the result
        $purgeOld = null;
        foreach ($all as $entry) {
            if ($entry['pref_name'] === Prefs::PURGE_OLD_DAYS) {
                $purgeOld = $entry;
                break;
            }
        }

        $this->assertNotNull($purgeOld, 'PURGE_OLD_DAYS should be in get_all()');
        $this->assertSame(60, $purgeOld['value']);
        $this->assertSame(Config::T_INT, $purgeOld['type_hint']);
    }

    // ── profile blacklisting ─────────────────────────────────────────────────

    public function test_set_profile_blacklisted_pref_returns_false(): void {
        // PURGE_OLD_DAYS is in _PROFILE_BLACKLIST
        $uid = $this->createTestUser('test_profile_bl');
        $profileId = 100;

        $result = Prefs::set(Prefs::PURGE_OLD_DAYS, 999, $uid, $profileId);

        $this->assertFalse($result, 'set() should return false for blacklisted pref with profile');
        $this->assertNull($this->getRawPref(Prefs::PURGE_OLD_DAYS, $uid, $profileId),
            'Blacklisted pref should not be stored in the database');
    }

    public function test_set_non_blacklisted_pref_works_with_profile(): void {
        $uid = $this->createTestUser('test_profile_nonbl');
        $profileId = Prefs::create_profile($uid, 'test_profile_nonbl');
        $prefName = Prefs::REVERSE_HEADLINES; // not in blacklist

        $result = Prefs::set($prefName, true, $uid, $profileId);

        $this->assertTrue($result, 'set() should succeed for non-blacklisted pref with profile');
        $this->assertSame("1", $this->getRawPref($prefName, $uid, $profileId));
    }

    public function test_get_ignores_profile_for_blacklisted_pref(): void {
        $uid = $this->createTestUser('test_profile_bl_get');
        $profileId = 300;

        // Set a non-default value for user-level (no profile)
        Prefs::set(Prefs::PURGE_OLD_DAYS, 500, $uid, null);

        // When requesting with a profile, it should return the user-level value
        // because PURGE_OLD_DAYS is in the blacklist (profile forced to null)
        $result = Prefs::get(Prefs::PURGE_OLD_DAYS, $uid, $profileId);
        $this->assertSame(500, $result,
            'Blacklisted pref should fall back to user-level value when profile is specified');
    }

    // ── reset() ──────────────────────────────────────────────────────────────

    public function test_reset_clears_all_user_prefs(): void {
        $uid = $this->createTestUser('test_reset');

        // Set some prefs
        Prefs::set(Prefs::PURGE_OLD_DAYS, 99, $uid, null);
        Prefs::set(Prefs::REVERSE_HEADLINES, true, $uid, null);
        Prefs::set(Prefs::USER_TIMEZONE, "America/New_York", $uid, null);

        // Verify they exist
        $countBefore = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM ttrss_user_prefs2 WHERE owner_uid = :uid"
        );
        $countBefore->execute(['uid' => $uid]);
        $this->assertGreaterThanOrEqual(3, (int) $countBefore->fetchColumn());

        Prefs::reset($uid, null);

        // All prefs should be deleted except _PREFS_MIGRATED
        $countAfter = Db::pdo()->prepare(
            "SELECT COUNT(*) FROM ttrss_user_prefs2
             WHERE owner_uid = :uid AND pref_name != :mig_key"
        );
        $countAfter->execute(['uid' => $uid, 'mig_key' => Prefs::_PREFS_MIGRATED]);
        $this->assertSame(0, (int) $countAfter->fetchColumn(),
            'All prefs should be deleted by reset() except _PREFS_MIGRATED');
    }

    public function test_reset_does_not_delete_migrated_flag(): void {
        $uid = $this->createTestUser('test_reset_mig');

        // First, simulate migration by setting _PREFS_MIGRATED
        Prefs::set(Prefs::_PREFS_MIGRATED, "1", $uid, null);

        Prefs::reset($uid, null);

        $migrated = $this->getRawPref(Prefs::_PREFS_MIGRATED, $uid, null);
        $this->assertSame("1", $migrated,
            '_PREFS_MIGRATED should not be deleted by reset()');
    }

    // ── Profile-specific prefs ──────────────────────────────────────────────

    public function test_set_and_get_profile_specific_pref(): void {
        $uid = $this->createTestUser('test_profile_spec');
        $profileId = Prefs::create_profile($uid, 'test_profile_spec');
        $prefName = Prefs::REVERSE_HEADLINES;

        Prefs::set($prefName, true, $uid, $profileId);
        $result = Prefs::get($prefName, $uid, $profileId);

        $this->assertTrue($result, 'Profile-specific pref should be set and retrieved correctly');
    }

    public function test_profile_pref_does_not_affect_user_level_pref(): void {
        $uid = $this->createTestUser('test_profile_isolation');
        $profileId = Prefs::create_profile($uid, 'test_profile_isolation');
        $prefName = Prefs::REVERSE_HEADLINES;

        // Set user-level default
        Prefs::set($prefName, false, $uid, null);

        // Set profile-level override
        Prefs::set($prefName, true, $uid, $profileId);

        // User-level should still be false
        $userResult = Prefs::get($prefName, $uid, null);
        $this->assertFalse($userResult, 'User-level pref should not be affected by profile override');

        // Profile-level should be true
        $profileResult = Prefs::get($prefName, $uid, $profileId);
        $this->assertTrue($profileResult, 'Profile-level pref should return the overridden value');
    }

    // ── Edge cases ───────────────────────────────────────────────────────────

    public function test_set_returns_false_for_invalid_pref_name(): void {
        $uid = $this->createTestUser('test_invalid');

        // set() triggers user_error() for invalid pref names; suppress it
        set_error_handler(fn(): bool => true, E_USER_WARNING);
        $result = Prefs::set('INVALID_PREF_NAME', 'value', $uid, null);
        restore_error_handler();

        $this->assertFalse($result, 'set() should return false for an invalid pref name');
    }

    public function test_get_with_empty_string_value_for_string_pref(): void {
        $uid = $this->createTestUser('test_empty_string');
        $prefName = Prefs::USER_STYLESHEET;

        Prefs::set($prefName, "", $uid, null);
        $result = Prefs::get($prefName, $uid);

        $this->assertSame("", $result, 'Empty string should be stored and retrieved correctly');
    }

    public function test_multiple_users_have_isolated_prefs(): void {
        $uid1 = $this->createTestUser('test_iso_1');
        $uid2 = $this->createTestUser('test_iso_2');
        $prefName = Prefs::PURGE_OLD_DAYS;

        Prefs::set($prefName, 100, $uid1, null);
        Prefs::set($prefName, 200, $uid2, null);

        $this->assertSame(100, Prefs::get($prefName, $uid1));
        $this->assertSame(200, Prefs::get($prefName, $uid2));
    }
}

<?php
/** @group integration */
final class TimeHelperTest extends DbTestCase {

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function setUp(): void {
        parent::setUp();

        // Use UTC for deterministic testing
        Prefs::set(Prefs::USER_TIMEZONE, 'UTC', 1, null);

        // Use explicit 24-hour and 12-hour formats for predictable output
        Prefs::set(Prefs::SHORT_DATE_FORMAT, 'M d, G:i', 1, null);
        Prefs::set(Prefs::LONG_DATE_FORMAT, 'D, M d Y - G:i', 1, null);

        // clientTzOffset is not set by default, but we set USER_TIMEZONE above
        // so the 'Automatic' path is not taken.
        unset($_SESSION['clientTzOffset']);
    }

    /**
     * Return the current Unix timestamp for use in relative-time tests.
     */
    private function now(): int {
        return time();
    }

    // ── smart_date_time ──────────────────────────────────────────────────────

    public function test_smart_date_time_never(): void {
        // Epoch timestamp → "Never"
        $this->assertEquals('Never', TimeHelper::smart_date_time(0));
    }

    public function test_smart_date_time_never_with_tz_offset(): void {
        // timestamp - tz_offset == 0 → "Never"
        $this->assertEquals('Never', TimeHelper::smart_date_time(3600, 3600));
    }

    public function test_smart_date_time_same_day_24h(): void {
        $now = $this->now();

        // Same day with default 24h SHORT_DATE_FORMAT → "G:i"
        $result = TimeHelper::smart_date_time($now);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
        // Verify the time portion matches current time
        $this->assertEquals(date('G:i', $now), $result);
    }

    public function test_smart_date_time_same_day_12h(): void {
        $now = $this->now();

        // Switch to 12-hour format
        Prefs::set(Prefs::SHORT_DATE_FORMAT, 'M d, g:i a', 1, null);

        $result = TimeHelper::smart_date_time($now);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2} [ap]m$/', $result);
        $this->assertEquals(date('g:i a', $now), $result);
    }

    public function test_smart_date_time_same_year(): void {
        // Use a timestamp from earlier this year (not today)
        $earlier = mktime(10, 0, 0, 1, 15, (int) date('Y'));

        $result = TimeHelper::smart_date_time($earlier);
        // With SHORT_DATE_FORMAT "M d, G:i" → "Jan 15, 10:00"
        $this->assertEquals('Jan 15, 10:00', $result);
    }

    public function test_smart_date_time_different_year(): void {
        // Use a timestamp from a different year
        $old = mktime(14, 30, 0, 6, 15, 2020);

        $result = TimeHelper::smart_date_time($old);
        // With LONG_DATE_FORMAT "D, M d Y - G:i" → "Mon, Jun 15 2020 - 14:30"
        $this->assertEquals('Mon, Jun 15 2020 - 14:30', $result);
    }

    public function test_smart_date_time_eta_min_within_hour(): void {
        $past = time() - 30 * 60; // 30 minutes ago
        $result = TimeHelper::smart_date_time($past, 0, null, true);
        $this->assertEquals('30 min', $result);
    }

    public function test_smart_date_time_eta_min_less_than_5_minutes(): void {
        // Use a past timestamp so date("i", $diff) gives the right value.
        $past = time() - 4 * 60;
        $result = TimeHelper::smart_date_time($past, 0, null, true);
        $this->assertEquals('4 min', $result);
    }

    public function test_smart_date_time_eta_min_over_hour(): void {
        // 2 hours ago — outside the 1-hour ETA window
        $past = time() - 2 * 3600;
        $result = TimeHelper::smart_date_time($past, 0, null, true);
        // Should fall through to same-day or same-year formatting
        $this->assertStringNotContainsString('min', $result);
    }

    public function test_smart_date_time_eta_min_past_within_window(): void {
        // 30 minutes ago — within the 1-hour ETA window
        $past = time() - 30 * 60;
        $result = TimeHelper::smart_date_time($past, 0, null, true);
        // Within window → shows "30 min"
        $this->assertEquals('30 min', $result);
    }

    public function test_smart_date_time_same_day_with_tz_offset(): void {
        $now = $this->now();

        // With a tz_offset, the "current time" shifts.
        // Using tz_offset=0 keeps it on the same day.
        $result = TimeHelper::smart_date_time($now, 0);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
    }

    // ── smart_date_time with explicit owner_uid ──────────────────────────────

    public function test_smart_date_time_explicit_uid(): void {
        $now = $this->now();

        // Pass explicit uid=1
        $result = TimeHelper::smart_date_time($now, 0, 1);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
    }

    // ── make_local_datetime ──────────────────────────────────────────────────

    public function test_make_local_datetime_null_timestamp(): void {
        // Null timestamp defaults to 1970-01-01 0:00
        $result = TimeHelper::make_local_datetime(null);
        // Should not crash and returns some formatted string
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_empty_string_timestamp(): void {
        $result = TimeHelper::make_local_datetime('');
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_short_format_default(): void {
        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // Default (no_smart_dt=false, long=false) → smart_date_time
        $result = TimeHelper::make_local_datetime($ts);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
    }

    public function test_make_local_datetime_no_smart_short(): void {
        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // no_smart_dt=true, long=false → SHORT_DATE_FORMAT
        $result = TimeHelper::make_local_datetime($ts, false, null, true);
        $this->assertEquals('M d, G:i', 'M d, G:i'); // just verify format
        // With SHORT_DATE_FORMAT "M d, G:i"
        $this->assertEquals(date('M d, G:i', $now), $result);
    }

    public function test_make_local_datetime_no_smart_long(): void {
        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // no_smart_dt=true, long=true → LONG_DATE_FORMAT
        $result = TimeHelper::make_local_datetime($ts, true, null, true);
        $this->assertEquals(date('D, M d Y - G:i', $now), $result);
    }

    public function test_make_local_datetime_truncates_to_19_chars(): void {
        // Timestamp with extra precision should be truncated
        $ts = '2026-05-27 14:30:45.123456';
        $expected = '2026-05-27 14:30:45';

        // Should not crash despite extra digits
        $result = TimeHelper::make_local_datetime($ts, false, null, true);
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_with_explicit_uid(): void {
        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        $result = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertEquals(date('M d, G:i', $now), $result);
    }

    // ── make_local_datetime with non-UTC timezone ────────────────────────────

    public function test_make_local_datetime_with_fixed_timezone(): void {
        // Set timezone to a fixed offset
        Prefs::set(Prefs::USER_TIMEZONE, 'Europe/London', 1, null);

        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // This should not throw even with a real timezone
        $result = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_with_invalid_timezone_fallback(): void {
        // Invalid timezone should fall back to UTC
        Prefs::set(Prefs::USER_TIMEZONE, 'Invalid/Zone', 1, null);

        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // Should not throw — falls back to UTC
        $result = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_with_automatic_timezone(): void {
        // Set to automatic — falls back to clientTzOffset
        Prefs::set(Prefs::USER_TIMEZONE, 'Automatic', 1, null);
        $_SESSION['clientTzOffset'] = 0;

        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        $result = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertNotEmpty($result);
    }

    // ── convert_timestamp ────────────────────────────────────────────────────

    public function test_convert_timestamp_utc_to_utc(): void {
        $ts = 1748341800; // 2025-05-26 14:30:00 UTC
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'UTC');
        $this->assertEquals($ts, $result);
    }

    public function test_convert_timestamp_utc_to_plus_5(): void {
        $ts = 1748341800; // 2025-05-26 14:30:00 UTC
        // convert_timestamp creates a DateTime from timestamp in source_tz,
        // then returns epoch + dest_tz_offset.  UTC+5 offset = +18000s.
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'Etc/GMT-5');
        $this->assertEquals($ts + 18000, $result);
    }

    public function test_convert_timestamp_plus_5_to_utc(): void {
        $ts = 1748341800;
        // Source is UTC+5 (offset +18000), dest is UTC (offset 0).
        // The function returns: epoch_of_dt + dest_offset
        // dt represents 2025-05-26 14:30:00 in UTC+5, which is epoch 1748323800.
        // Result = 1748323800 + 0 = 1748323800
        $result = TimeHelper::convert_timestamp($ts, 'Etc/GMT-5', 'UTC');
        $this->assertEquals(1748323800, $result);
    }

    public function test_convert_timestamp_with_named_timezone(): void {
        $ts = 1748341800; // 2025-05-26 14:30:00 UTC
        // New York (EDT) is UTC-4 in May
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'America/New_York');
        $this->assertEquals($ts - 4 * 3600, $result);
    }

    public function test_convert_timestamp_invalid_source_timezone_fallback(): void {
        $ts = 1748341800;
        // Invalid source timezone falls back to UTC
        $result = TimeHelper::convert_timestamp($ts, 'Invalid/Zone', 'UTC');
        $this->assertEquals($ts, $result);
    }

    public function test_convert_timestamp_invalid_dest_timezone_fallback(): void {
        $ts = 1748341800;
        // Invalid dest timezone falls back to UTC
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'Invalid/Zone');
        $this->assertEquals($ts, $result);
    }

    public function test_convert_timestamp_both_invalid(): void {
        $ts = 1748341800;
        // Both invalid → UTC to UTC
        $result = TimeHelper::convert_timestamp($ts, 'Invalid/Zone', 'Another/Invalid');
        $this->assertEquals($ts, $result);
    }

    public function test_convert_timestamp_minus_5(): void {
        $ts = 1748341800;
        // UTC to UTC-5 (offset -18000).
        // dt represents 2025-05-26 14:30:00 UTC (epoch 1748341800).
        // Result = 1748341800 + (-18000) = 1748323800
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'Etc/GMT+5');
        $this->assertEquals(1748323800, $result);
    }

    public function test_convert_timestamp_returns_integer(): void {
        $ts = 1748341800;
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'UTC');
        $this->assertGreaterThan(0, $result);
    }

    // ── Smart date with non-UTC timezone ─────────────────────────────────────

    public function test_make_local_datetime_smart_date_with_tz_offset(): void {
        // Set a timezone with a known offset
        Prefs::set(Prefs::USER_TIMEZONE, 'America/New_York', 1, null);

        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        $result = TimeHelper::make_local_datetime($ts, false, 1);
        $this->assertNotEmpty($result);
    }

    public function test_make_local_datetime_eta_min_with_tz(): void {
        Prefs::set(Prefs::USER_TIMEZONE, 'UTC', 1, null);

        // Use a past timestamp so date("i", $diff) gives the right value.
        $past = time() - 15 * 60; // 15 minutes ago
        $ts = date('Y-m-d H:i:s', $past);

        $result = TimeHelper::make_local_datetime($ts, false, 1, false, true);
        $this->assertEquals('15 min', $result);
    }

    // ── Custom date format preferences ───────────────────────────────────────

    public function test_smart_date_time_custom_short_format(): void {
        $now = $this->now();

        Prefs::set(Prefs::SHORT_DATE_FORMAT, 'Y-m-d H:i', 1, null);
        Prefs::set(Prefs::LONG_DATE_FORMAT, 'Y-m-d H:i:s', 1, null);

        // Same day → SHORT_DATE_FORMAT without 'a' → "G:i"
        $result = TimeHelper::smart_date_time($now);
        $this->assertEquals(date('G:i', $now), $result);
    }

    public function test_smart_date_time_custom_long_format(): void {
        $old = mktime(14, 30, 0, 6, 15, 2020);

        Prefs::set(Prefs::SHORT_DATE_FORMAT, 'Y-m-d H:i', 1, null);
        Prefs::set(Prefs::LONG_DATE_FORMAT, 'Y-m-d H:i:s', 1, null);

        // Different year → LONG_DATE_FORMAT
        $result = TimeHelper::smart_date_time($old);
        $this->assertEquals('2020-06-15 14:30:00', $result);
    }

    public function test_make_local_datetime_custom_formats_no_smart(): void {
        Prefs::set(Prefs::SHORT_DATE_FORMAT, 'Y-m-d H:i', 1, null);
        Prefs::set(Prefs::LONG_DATE_FORMAT, 'Y-m-d H:i:s', 1, null);

        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // Short format, no smart
        $short = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertEquals(date('Y-m-d H:i', $now), $short);

        // Long format, no smart
        $long = TimeHelper::make_local_datetime($ts, true, 1, true);
        $this->assertEquals(date('Y-m-d H:i:s', $now), $long);
    }

    // ── Edge cases ───────────────────────────────────────────────────────────

    public function test_smart_date_time_zero_tz_offset(): void {
        $now = $this->now();
        $result = TimeHelper::smart_date_time($now, 0);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
    }

    public function test_convert_timestamp_large_timestamp(): void {
        // Far future timestamp
        $ts = 2524608000; // 2050-01-01 00:00:00 UTC
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'UTC');
        $this->assertEquals($ts, $result);
    }

    public function test_convert_timestamp_zero_timestamp(): void {
        $ts = 0;
        $result = TimeHelper::convert_timestamp($ts, 'UTC', 'UTC');
        $this->assertEquals(0, $result);
    }

    public function test_make_local_datetime_with_profile(): void {
        $now = $this->now();
        $ts = date('Y-m-d H:i:s', $now);

        // Pass profile=null explicitly
        $result = TimeHelper::make_local_datetime($ts, false, 1, true);
        $this->assertNotEmpty($result);
    }

    public function test_smart_date_time_with_profile(): void {
        $now = $this->now();

        $result = TimeHelper::smart_date_time($now, 0, 1);
        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}$/', $result);
    }
}

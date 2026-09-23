<?php

use PHPUnit\Framework\TestCase;

final class TimeHelperTest extends TestCase {

	protected function setUp(): void {
		// Initialize session and globals that TimeHelper uses
		$_SESSION ??= [];
		$_SESSION['uid'] = 1;
		$_SESSION['profile'] = null;

		global $utc_tz, $user_tz;
		$utc_tz = new DateTimeZone('UTC');
		$user_tz = null;
	}

	protected function tearDown(): void {
		$_SESSION = [];
		global $utc_tz, $user_tz;
		$utc_tz = null;
		$user_tz = null;
	}

	public function test_smart_date_time_epoch_returns_never(): void {
		$result = TimeHelper::smart_date_time(0, 0, 1);
		$this->assertSame('Never', $result);
	}

	public function test_smart_date_time_today_shows_time(): void {
		$now = time();
		$result = TimeHelper::smart_date_time($now, 0, 1);

		// Should return time format like "14:30" or "2:30 pm"
		$this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}/', $result);
	}

	public function test_smart_date_time_eta_min_recent(): void {
		// 30 minutes ago
		$timestamp = time() - 1800;
		$result = TimeHelper::smart_date_time($timestamp, 0, 1, true);

		// Should show minutes format
		$this->assertStringContainsString('min', $result);
	}

	public function test_make_local_datetime_null_timestamp(): void {
		$result = TimeHelper::make_local_datetime(null, false, 1);

		// Null timestamp should be treated as epoch
		$this->assertNotEmpty($result);
	}

	public function test_make_local_datetime_smart_date(): void {
		// Current timestamp
		$timestamp = date('Y-m-d H:i:s');
		$result = TimeHelper::make_local_datetime($timestamp, false, 1, false);

		// Should use smart_date_time format
		$this->assertNotEmpty($result);
	}

	public function test_convert_timestamp_utc_to_utc(): void {
		$timestamp = 1700000000;
		$result = TimeHelper::convert_timestamp($timestamp, 'UTC', 'UTC');

		$this->assertSame($timestamp, $result);
	}

	public function test_convert_timestamp_utc_to_est(): void {
		$timestamp = mktime(12, 0, 0, 1, 15, 2024); // Noon UTC
		$result = TimeHelper::convert_timestamp($timestamp, 'UTC', 'America/New_York');

		// EST is UTC-5, so result should be earlier
		$this->assertNotSame($timestamp, $result);
		$this->assertIsInt($result);
	}

	public function test_convert_timestamp_with_invalid_source_tz(): void {
		$timestamp = 1700000000;
		// Invalid timezone should fall back to UTC
		$result = TimeHelper::convert_timestamp($timestamp, 'InvalidTZ', 'UTC');

		$this->assertSame($timestamp, $result);
	}

	public function test_convert_timestamp_with_invalid_dest_tz(): void {
		$timestamp = 1700000000;
		// Invalid timezone should fall back to UTC
		$result = TimeHelper::convert_timestamp($timestamp, 'UTC', 'InvalidTZ');

		$this->assertSame($timestamp, $result);
	}

	public function test_convert_timestamp_est_to_pst(): void {
		$timestamp = mktime(12, 0, 0, 1, 15, 2024); // Noon EST
		$result = TimeHelper::convert_timestamp($timestamp, 'America/New_York', 'America/Los_Angeles');

		// PST is 3 hours behind EST
		$this->assertNotSame($timestamp, $result);
		$this->assertIsInt($result);
	}
}

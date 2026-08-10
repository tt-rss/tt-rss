<?php
class TimeHelper {

	public static function smart_date_time(int $timestamp, int $tz_offset = 0, ?int $owner_uid = null, bool $eta_min = false): string {
		// i.e. if the Unix epoch
		if ($timestamp - $tz_offset === 0)
			return __('Never');

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];
		$profile = isset($_SESSION['uid']) && $owner_uid == $_SESSION['uid'] && isset($_SESSION['profile']) ? $_SESSION['profile'] : null;

		if ($eta_min && time() + $tz_offset - $timestamp < 3600) {
			return T_sprintf("%d min", date("i", time() + $tz_offset - $timestamp));
		} else if (date("Y.m.d", $timestamp) == date("Y.m.d", time() + $tz_offset)) {
			$format = Prefs::get(Prefs::SHORT_DATE_FORMAT, $owner_uid, $profile);
			if (!str_contains((strtolower($format)), "a"))
				return date("G:i", $timestamp);
			else
				return date("g:i a", $timestamp);
		} else if (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
			$format = Prefs::get(Prefs::SHORT_DATE_FORMAT, $owner_uid, $profile);
			return date($format, $timestamp);
		} else {
			$format = Prefs::get(Prefs::LONG_DATE_FORMAT, $owner_uid, $profile);
			return date($format, $timestamp);
		}
	}

	/**
	 * @param bool $long Whether to display the datetime in a 'long' format.  Only used if $no_smart_dt is true.
	 */
	public static function make_local_datetime(?string $timestamp, bool $long = false, ?int $owner_uid = null,
					bool $no_smart_dt = false, bool $eta_min = false): string {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];
		$profile = isset($_SESSION['uid']) && $owner_uid == $_SESSION['uid'] && isset($_SESSION['profile']) ? $_SESSION['profile'] : null;

		if (!$timestamp) $timestamp = '1970-01-01 0:00';

		global $utc_tz;
		global $user_tz;

		if (!$utc_tz) $utc_tz = new DateTimeZone('UTC');

		$timestamp = substr($timestamp, 0, 19);

		# We store date in UTC internally
		$dt = new DateTime($timestamp, $utc_tz);

		$user_tz_string = Prefs::get(Prefs::USER_TIMEZONE, $owner_uid);

		if ($user_tz_string != 'Automatic') {

			try {
				if (!$user_tz) $user_tz = new DateTimeZone($user_tz_string);
			} catch (Exception) {
				$user_tz = $utc_tz;
			}

			$tz_offset = $user_tz->getOffset($dt);
		} else {
			$tz_offset = (int) -($_SESSION["clientTzOffset"] ?? 0);
		}

		$user_timestamp = $dt->format('U') + $tz_offset;

		if (!$no_smart_dt) {
			return self::smart_date_time($user_timestamp,
				$tz_offset, $owner_uid, $eta_min);
		} else {
			if ($long)
				$format = Prefs::get(Prefs::LONG_DATE_FORMAT, $owner_uid, $profile);
			else
				$format = Prefs::get(Prefs::SHORT_DATE_FORMAT, $owner_uid, $profile);

			return date($format, $user_timestamp);
		}
	}

	public static function convert_timestamp(int $timestamp, string $source_tz, string $dest_tz): int {

		try {
			$source_tz = new DateTimeZone($source_tz);
		} catch (Exception) {
			$source_tz = new DateTimeZone('UTC');
		}

		try {
			$dest_tz = new DateTimeZone($dest_tz);
		} catch (Exception) {
			$dest_tz = new DateTimeZone('UTC');
		}

		$dt = new DateTime(date('Y-m-d H:i:s', $timestamp), $source_tz);

		return (int)$dt->format('U') + $dest_tz->getOffset($dt);
	}

}

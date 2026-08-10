<?php
class Db_Prefs {
	// this class is a stub for the time being (to be removed)

	public function read(string $pref_name, ?int $user_id = null, bool $die_on_error = false): bool|int|null|string {
		return Prefs::get($pref_name, $user_id ?: $_SESSION['uid'], $_SESSION['profile'] ?? null);
	}

	public function write(string $pref_name, mixed $value, ?int $user_id = null, bool $strip_tags = true): bool {
		return Prefs::set($pref_name, $value, $user_id ?: $_SESSION['uid'], $_SESSION['profile'] ?? null, $strip_tags);
	}
}

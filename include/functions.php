<?php
	define('LABEL_BASE_INDEX', -1024);
	define('PLUGIN_FEED_BASE_INDEX', -128);

	/** @deprecated by Config::SCHEMA_VERSION */
	define('SCHEMA_VERSION', Config::SCHEMA_VERSION);

	libxml_use_internal_errors(true);

	// separate test because this is included before sanity checks
	if (function_exists("mb_internal_encoding")) mb_internal_encoding("UTF-8");

	date_default_timezone_set('UTC');

	error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

	ini_set('display_errors', "false");
	ini_set('display_startup_errors', "false");

	// config.php is optional
	$config_path = stream_resolve_include_path('config.php');

	if ($config_path)
		require_once $config_path;

	require_once "autoload.php";

	/** @deprecated use the 'SUBSTRING_FOR_DATE' string directly */
	const SUBSTRING_FOR_DATE = 'SUBSTRING_FOR_DATE';

	/**
	 * @deprecated by Prefs::get()
	 */
	function get_pref(string $pref_name, ?int $owner_uid = null): bool|int|null|string {
		return Prefs::get($pref_name, $owner_uid ?: $_SESSION["uid"], $_SESSION["profile"] ?? null);
	}

	/**
	 * @deprecated by Prefs::set()
	 */
	function set_pref(string $pref_name, bool|int|string $value, ?int $owner_uid = null, bool $strip_tags = true): bool {
		return Prefs::set($pref_name, $value, $owner_uid ?: $_SESSION["uid"], $_SESSION["profile"] ?? null, $strip_tags);
	}

	/**
	 * @return array<string, string>
	 */
	function get_translations(): array {
		$t = [
					"auto"  => __("Detect automatically"),
					"ar_SA" => "العربيّة (Arabic)",
					"be"    => "Беларуская",
					"bg_BG" => "Bulgarian",
					"da_DA" => "Dansk",
					"ca_CA" => "Català",
					"cs_CZ" => "Česky",
					"en_US" => "English",
					"el_GR" => "Ελληνικά",
					"es"    => "Español (España)",
					"de_DE" => "Deutsch",
					"fa"    => "Persian (Farsi)",
					"fr_FR" => "Français",
					"gl"    => "Galego",
					"hu_HU" => "Magyar (Hungarian)",
					"it_IT" => "Italiano",
					"ja_JP" => "日本語 (Japanese)",
					"lv_LV" => "Latviešu",
					"nb_NO" => "Norwegian bokmål",
					"nl_NL" => "Dutch",
					"pl_PL" => "Polski",
					"ru_RU" => "Русский",
					"pt_BR" => "Portuguese/Brazil",
					"pt_PT" => "Portuguese/Portugal",
					"zh_CN" => "Simplified Chinese",
					"zh_TW" => "Traditional Chinese",
					"uk_UA" => "Українська",
					"sv_SE" => "Svenska",
					"fi_FI" => "Suomi",
					"ta"    => "Tamil",
					"tr_TR" => "Türkçe"];

		return $t;
	}

	require_once __DIR__ . '/../lib/gettext/gettext.inc.php';

	function startup_gettext(): void {

		$selected_locale = "";

		// https://www.codingwithjesse.com/blog/use-accept-language-header/
		if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$valid_langs = [];
			$translations = array_keys(get_translations());

			array_shift($translations); // remove "auto"

			// full locale first
			foreach ($translations as $t) {
				$lang = strtolower(str_replace("_", "-", (string)$t));
				$valid_langs[$lang] = $t;

				$lang = substr($lang, 0, 2);
				$valid_langs[$lang] ??= $t;
			}

			// break up string into pieces (languages and q factors)
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
				$_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

			if (count($lang_parse[1])) {
				// create a list like "en" => 0.8
				$langs = array_combine($lang_parse[1], $lang_parse[4]);

				// set default to 1 for any without q factor
				foreach ($langs as $lang => $val) {
					if ($val === '') $langs[$lang] = 1;
				}

				// sort list based on value
				arsort($langs, SORT_NUMERIC);

				foreach (array_keys($langs) as $lang) {
					$lang = strtolower($lang);

					foreach ($valid_langs as $vlang => $vlocale) {
						if ($vlang == $lang) {
							$selected_locale = $vlocale;
							break 2;
						}
					}
				}
			}
		}

		if (!empty($_SESSION["uid"]) && Config::get_schema_version() >= 120) {
			$pref_locale = Prefs::get(Prefs::USER_LANGUAGE, $_SESSION["uid"], $_SESSION["profile"] ?? null);

			if (!empty($pref_locale) && $pref_locale != 'auto') {
				$selected_locale = $pref_locale;
			}
		}

		if ($selected_locale) {
			if (defined('LC_MESSAGES')) {
				_setlocale(LC_MESSAGES, $selected_locale);
			} else if (defined('LC_ALL')) {
				_setlocale(LC_ALL, $selected_locale);
			}

			_bindtextdomain("messages", "locale");
			_textdomain("messages");
			_bind_textdomain_codeset("messages", "UTF-8");
		}
	}

	require_once 'controls.php';
	require_once 'controls_compat.php';

	ini_set('user_agent', Config::get_user_agent());

	/* compat shims */

	/**
	 * @deprecated by Config::get_version()
	 *
	 * @return array<string, mixed>|string
	 */
	function get_version(): array|string {
		return Config::get_version();
	}

	/** @deprecated by Config::get_schema_version() */
	function get_schema_version(): int {
		return Config::get_schema_version();
	}

	/** @deprecated by Debug::log() */
	function _debug(string $msg): void {
		Debug::log($msg);
	}


	/** @deprecated by Feeds::_get_counters()
	 * @param int|string $feed feed id or tag name
	 * @param bool $is_cat
	 * @return int
	 * @throws PDOException
	 */
	function getFeedUnread(int|string $feed, bool $is_cat = false): int {
		return Feeds::_get_counters($feed, $is_cat, true, $_SESSION["uid"]);
	}

	/**
	 * @deprecated by Sanitizer::sanitize()
	 *
	 * @param array<int, string>|null $highlight_words Words to highlight in the HTML output.
	 *
	 * @return false|string The HTML, or false if an error occurred.
	 */
	function sanitize(string $str, bool $force_remove_images = false, ?int $owner = null, ?string $site_url = null, ?array $highlight_words = null, ?int $article_id = null): false|string {
		return Sanitizer::sanitize($str, $force_remove_images, $owner, $site_url, $highlight_words, $article_id);
	}

	/**
	 * @deprecated by UrlHelper::fetch()
	 *
	 * @param array<string, bool|int|string>|string $params
	 * @return false|string false if something went wrong, otherwise string contents
	 */
	function fetch_file_contents(array|string $params): false|string {
		return UrlHelper::fetch($params);
	}

	/**
	 * @deprecated by UrlHelper::rewrite_relative()
	 *
	 * Converts a (possibly) relative URL to a absolute one, using provided base URL.
	 * Provides some exceptions for additional schemes like data: if called with owning element/attribute.
	 *
	 * @param string $base_url     Base URL (i.e. from where the document is)
	 * @param string $rel_url Possibly relative URL in the document
	 *
	 * @return string Absolute URL
	 */
	function rewrite_relative_url($base_url, $rel_url) {
		return UrlHelper::rewrite_relative($base_url, $rel_url);
	}

	/**
	 * @deprecated by UrlHelper::validate()
	 *
	 * @return false|string false if something went wrong, otherwise the URL string
	 */
	function validate_url(string $url): false|string {
		return UrlHelper::validate($url);
	}

	/** @deprecated by UserHelper::authenticate() */
	function authenticate_user(?string $login = null, ?string $password = null, bool $check_only = false, ?string $service = null): bool {
		return UserHelper::authenticate($login, $password, $check_only, $service);
	}

	/** @deprecated by TimeHelper::smart_date_time() */
	function smart_date_time(int $timestamp, int $tz_offset = 0, ?int $owner_uid = null, bool $eta_min = false): string {
		return TimeHelper::smart_date_time($timestamp, $tz_offset, $owner_uid, $eta_min);
	}

	/** @deprecated by TimeHelper::make_local_datetime() */
	function make_local_datetime(string $timestamp, bool $long = false, ?int $owner_uid = null, bool $no_smart_dt = false, bool $eta_min = false): string {
		return TimeHelper::make_local_datetime($timestamp, $long, $owner_uid, $no_smart_dt, $eta_min);
	}

	// this returns Config::SELF_URL_PATH sans ending slash
	/** @deprecated by Config::get_self_url() */
	function get_self_url_prefix(): string {
		return Config::get_self_url();
	}

	/* end compat shims */

	/**
	 * This is used for user HTTP parameters unless HTML code is actually needed.
	 *
	 * @template T
	 *
	 * @param T $param
	 *
	 * @return T
	 */
	function clean(mixed $param): mixed {
		$filter = static fn($v) => is_string($v) ? trim(strip_tags($v)) : $v;

		return match (true) {
			is_array($param) => array_map($filter, $param),
			is_string($param) => $filter($param),
			default => $param,
		};
	}

	function with_trailing_slash(string $str) : string {
		return str_ends_with($str, '/') ? $str : "$str/";
	}

	function make_password(int $length = 12): string {
		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ*%+^";

		$i = 0;

		while ($i < $length) {

			try {
				$idx = function_exists("random_int") ? random_int(0, strlen($possible) - 1) : mt_rand(0, strlen($possible) - 1);
			} catch (Exception) {
				$idx = mt_rand(0, strlen($possible) - 1);
			}

			$char = substr($possible, $idx, 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}

		return $password;
	}

	function validate_csrf(?string $csrf_token): bool {
		return isset($csrf_token) && hash_equals($_SESSION['csrf_token'] ?? "", $csrf_token);
	}

	function truncate_string(string $str, int $max_len, string $suffix = '&hellip;'): string {
		if (mb_strlen($str, "utf-8") > $max_len) {
			return mb_substr($str, 0, $max_len, "utf-8") . $suffix;
		} else {
			return $str;
		}
	}

	function mb_substr_replace(string $original, string $replacement, int $position, int $length): string {
		$startString = mb_substr($original, 0, $position, "UTF-8");
		$endString = mb_substr($original, $position + $length, mb_strlen($original), "UTF-8");

		$out = $startString . $replacement . $endString;

		return $out;
	}

	function truncate_middle(string $str, int $max_len, string $suffix = '&hellip;'): string {
		if (mb_strlen($str) > $max_len) {
			return mb_substr_replace($str, $suffix, $max_len / 2, mb_strlen($str) - $max_len);
		} else {
			return $str;
		}
	}

	/** Convert values accepted by tt-rss as true/false to PHP booleans
	 * @see https://tt-rss.org/docs/API-Reference.html#boolean-values
	 * @param null|string $s null values are considered false
	 */
	function sql_bool_to_bool(?string $s): bool {
		return $s && ($s !== "f" && $s !== "false"); //no-op for PDO, backwards compat for legacy layer
	}

	/** workaround for PDO casting all query parameters to string unless type is specified explicitly,
	 * which breaks booleans having false value because they become empty string literals ("") causing
	 * DB type mismatches and breaking SQL queries */
	function bool_to_sql_bool(bool $s): int {
		return (int)$s;
	}

	function file_is_locked(string $filename): bool {
		if (file_exists(Config::get(Config::LOCK_DIRECTORY) . "/$filename")) {
			$fp = @fopen(Config::get(Config::LOCK_DIRECTORY) . "/$filename", "r");
			if ($fp) {
				if (flock($fp, LOCK_EX | LOCK_NB)) {
					flock($fp, LOCK_UN);
					fclose($fp);
					return false;
				}
				fclose($fp);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @return resource|false A file pointer resource on success, or false on error.
	 */
	function make_lockfile(string $filename) {
		$fp = fopen(Config::get(Config::LOCK_DIRECTORY) . "/$filename", "w");

		if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
			$stat_h = fstat($fp);
			$stat_f = stat(Config::get(Config::LOCK_DIRECTORY) . "/$filename");

			if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
				if ($stat_h["ino"] != $stat_f["ino"] ||
						$stat_h["dev"] != $stat_f["dev"]) {

					return false;
				}
			}

			if (function_exists('posix_getpid')) {
				fwrite($fp, posix_getpid() . "\n");
			}
			return $fp;
		} else {
			return false;
		}
	}

	/** checkbox-specific workaround for PDO casting all query parameters to string unless type is
	 * specified explicitly, which breaks booleans having false value because they become empty
	 * string literals ("") causing DB type mismatches and breaking SQL queries
	 * @param mixed $val
	 */
	function checkbox_to_sql_bool($val): int {
		return ($val === "on") ? 1 : 0;
	}

	function uniqid_short(): string {
		return uniqid(base_convert((string)random_int(0, mt_getrandmax()), 10, 36));
	}

	function T_sprintf(): string {
		$args = func_get_args();
		return vsprintf(__(array_shift($args)), $args);
	}

	function T_nsprintf(): string {
		$args = func_get_args();
		return vsprintf(_ngettext(array_shift($args), array_shift($args), array_shift($args)), $args);
	}

	function init_plugins(): bool {
		PluginHost::getInstance()->load(Config::get(Config::PLUGINS), PluginHost::KIND_ALL);

		return true;
	}

	if (!function_exists('gzdecode')) {
		/**
		 * @return false|string The decoded string or false if an error occurred.
		 */
		function gzdecode(string $string): false|string { // no support for 2nd argument
			return file_get_contents('compress.zlib://data:who/cares;base64,'.
				base64_encode($string));
		}
	}

	function get_random_bytes(int $length): string {
		if (function_exists('random_bytes')) {
			return random_bytes($length);
		} else if (function_exists('openssl_random_pseudo_bytes')) {
			return openssl_random_pseudo_bytes($length);
		} else {
			$output = "";

			for ($i = 0; $i < $length; $i++)
				$output .= chr(mt_rand(0, 255));

			return $output;
		}
	}

	function read_stdin(): ?string {
		$fp = fopen("php://stdin", "r");

		if ($fp) {
			$line = trim(fgets($fp));
			fclose($fp);
			return $line;
		}

		return null;
	}

	function implements_interface(object|string $class, string $interface): bool {
		$class_implemented_interfaces = class_implements($class);

		if ($class_implemented_interfaces) {
			return in_array($interface, $class_implemented_interfaces);
		}
		return false;
	}

	function get_theme_path(string $theme, string $default = ""): string {
		$check = "themes/$theme";
		if (file_exists($check)) return $check;

		$check = "themes.local/$theme";
		if (file_exists($check)) return $check;

		return $default;
	}

	function theme_exists(string $theme): bool {
		return file_exists("themes/$theme") || file_exists("themes.local/$theme");
	}

	/**
	 * @param array<int, mixed> $arr
	 */
	function arr_qmarks(array $arr): string {
		return str_repeat('?,', count($arr) - 1) . '?';
	}

	function get_scripts_timestamp(): int {
		$files = glob("js/*.js");
		$ts = 0;

		foreach ($files as $file) {
			$file_ts = filemtime($file);
			if ($file_ts > $ts) $ts = $file_ts;
		}

		return $ts;
	}

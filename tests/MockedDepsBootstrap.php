<?php
// Bootstrap file for unit tests that require mocked dependencies
// Defines mock classes BEFORE loading vendor autoload to prevent DB initialization
//
// Use this bootstrap for tests annotated with @group mocked
//
// Key insight: Many tt-rss classes (Prefs, PluginHost, Config) have static methods that
// internally instantiate objects, and those constructors call Db::pdo(), which fails without a database.
// We must mock these classes to prevent instantiation entirely.

if (!class_exists('Config')) {
	class Config {
		const DB_TYPE = 'DB_TYPE';
		const SELF_URL_PATH = 'SELF_URL_PATH';
		const SCHEMA_VERSION = 151;
		const ENCRYPTION_KEY = 'ENCRYPTION_KEY';

		public static function get(string $key) {
			$values = [
				'SELF_URL_PATH' => 'http://localhost/tt-rss',
				'DB_TYPE' => 'pgsql',
				'ENCRYPTION_KEY' => null, // No encryption key in tests
			];
			return $values[$key] ?? null;
		}

		public static function get_self_url(bool $always_detect = false): string {
			return 'http://localhost/tt-rss';
		}

		public static function is_server_https(): bool {
			return false;
		}

		public static function get_user_agent(): string {
			return 'Tiny Tiny RSS/test (https://tt-rss.org/)';
		}
	}
}

if (!class_exists('PluginHost')) {
	class PluginHost {
		const HOOK_IFRAME_WHITELISTED = 1;
		const HOOK_SANITIZE = 2;
		const HOOK_ARTICLE_BUTTON = 3;
		const HOOK_ARTICLE_FILTER = 4;
		const HOOK_PREFS_TAB = 5;
		const HOOK_PREFS_TAB_SECTION = 6;
		const HOOK_PREFS_TABS = 7;
		const HOOK_FEED_PARSED = 8;
		const HOOK_UPDATE_TASK = 9;
		const HOOK_AUTH_USER = 10;
		const HOOK_HOTKEY_MAP = 11;
		const HOOK_RENDER_ARTICLE = 12;
		const HOOK_RENDER_ARTICLE_CDM = 13;
		const HOOK_FEED_FETCHED = 14;
		const HOOK_RENDER_ARTICLE_API = 16;
		const HOOK_TOOLBAR_BUTTON = 17;
		const HOOK_ACTION_ITEM = 18;
		const HOOK_HEADLINE_TOOLBAR_BUTTON = 19;
		const HOOK_HOTKEY_INFO = 20;
		const HOOK_ARTICLE_LEFT_BUTTON = 21;
		const HOOK_PREFS_EDIT_FEED = 22;
		const HOOK_PREFS_SAVE_FEED = 23;
		const HOOK_FETCH_FEED = 24;
		const HOOK_QUERY_HEADLINES = 25;
		const HOOK_HOUSE_KEEPING = 26;
		const HOOK_SEARCH = 27;
		const HOOK_FORMAT_ENCLOSURES = 28;
		const HOOK_SUBSCRIBE_FEED = 29;
		const HOOK_HEADLINES_BEFORE = 30;
		const HOOK_RENDER_ENCLOSURE = 31;
		const HOOK_ARTICLE_FILTER_ACTION = 32;
		const HOOK_ARTICLE_EXPORT_FEED = 33;
		const HOOK_MAIN_TOOLBAR_BUTTON = 34;
		const HOOK_ENCLOSURE_ENTRY = 35;
		const HOOK_FORMAT_ARTICLE = 36;
		const HOOK_FORMAT_ARTICLE_CDM = 37;
		const HOOK_FEED_BASIC_INFO = 38;
		const HOOK_SEND_LOCAL_FILE = 39;
		const HOOK_UNSUBSCRIBE_FEED = 40;
		const HOOK_SEND_MAIL = 41;
		const HOOK_FILTER_TRIGGERED = 42;
		const HOOK_GET_FULL_TEXT = 43;
		const HOOK_ARTICLE_IMAGE = 44;
		const HOOK_FEED_TREE = 45;
		const HOOK_HEADLINES_CUSTOM_SORT_MAP = 46;
		const HOOK_HEADLINES_CUSTOM_SORT_OVERRIDE = 47;
		const HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM = 48;
		const HOOK_POST_LOGOUT = 49;

		private static $instance = null;

		public static function getInstance(): PluginHost {
			self::$instance ??= new self();
			return self::$instance;
		}

		public function run_hooks_until($hook, $check, ...$params) {
			// Mock: always return false (no plugin handled it)
			return false;
		}

		public function chain_hooks_callback($hook, $callback, ...$params) {
			// Mock: just call the callback with the original params
			call_user_func($callback, ...$params);
		}

		public function get_plugin_names(): array {
			return [];
		}
	}
}

if (!class_exists('Prefs')) {
	class Prefs {
		// Define all constants that Sanitizer might use
		const STRIP_IMAGES = 'STRIP_IMAGES';
		const ENABLE_API_ACCESS = 'ENABLE_API_ACCESS';
		const USER_TIMEZONE = 'USER_TIMEZONE';
		const DIGEST_ENABLE = 'DIGEST_ENABLE';
		const ENABLE_FEED_CATS = 'ENABLE_FEED_CATS';
		const SHOW_CONTENT_PREVIEW = 'SHOW_CONTENT_PREVIEW';
		const SHORT_DATE_FORMAT = 'SHORT_DATE_FORMAT';
		const LONG_DATE_FORMAT = 'LONG_DATE_FORMAT';

		// CRITICAL: Override the static get() method to prevent instantiation
		// The real Prefs::get() calls get_instance() which calls __construct()
		// which tries to call Db::pdo() - we must avoid that entirely
		public static function get(string $pref_name, ?int $owner_uid = null, $profile = null) {
			// Mock: return false for all preferences without touching the database
			return false;
		}

		// Also mock get_instance() to prevent accidental instantiation
		private static $instance = null;

		public static function get_instance(): Prefs {
			self::$instance ??= new self();
			return self::$instance;
		}

		// Empty constructor to prevent DB access
		public function __construct() {
			// Do nothing - don't call Db::pdo()
		}
	}
}

if (!class_exists('Db')) {
	class Db {
		public static function pdo(): never {
			throw new Exception('Database access not available in Sanitizer tests - check your mocks');
		}
	}
}

// Now load vendor autoloader (for PHPUnit and dependencies)
require_once __DIR__ . '/../vendor/autoload.php';

// Manually load classes that Sanitizer depends on
// These are safe - they don't access the database
require_once __DIR__ . '/../classes/UrlHelper.php';
require_once __DIR__ . '/../classes/RSSUtils.php';
require_once __DIR__ . '/../classes/Sanitizer.php';

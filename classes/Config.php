<?php
class Config {
	private const _ENVVAR_PREFIX = "TTRSS_";

	const T_BOOL = 1;
	const T_STRING = 2;
	const T_INT = 3;

	const SCHEMA_VERSION = 151;

	/** override default values, defined below in _DEFAULTS[], prefixing with _ENVVAR_PREFIX:
	 *
	 * DB_TYPE becomes:
	 *
	 * .env (docker environment):
	 *
	 * TTRSS_DB_TYPE=pgsql
	 *
	 * or config.php:
	 *
	 * putenv('TTRSS_DB_HOST=my-patroni.example.com');
	 *
	 * note lack of quotes and spaces before and after "=".
	 *
	*/

	/** this is kept for backwards/plugin compatibility, the only supported database is PostgreSQL
	 *
	 * @deprecated usages of `Config::get(Config::DB_TYPE)` should be replaced with default (and only) value: `pgsql` or removed
	*/
	const DB_TYPE = "DB_TYPE";

	/** database server hostname */
	const DB_HOST = "DB_HOST";

	/** database user */
	const DB_USER = "DB_USER";

	/** database name */
	const DB_NAME = "DB_NAME";

	/** database password */
	const DB_PASS = "DB_PASS";

	/** database server port */
	const DB_PORT = "DB_PORT";

	/** PostgreSQL SSL mode (prefer, require, disabled) */
	const DB_SSLMODE = "DB_SSLMODE";

	/**
	 * The fully-qualified tt-rss URL accessed by users.
	 * This value is only used when running via the CLI SAPI or when FORCE_SELF_URL_PATH_USAGE is enabled.
	 * By default the 'Host' request header seen by tt-rss is used to construct the 'self URL'.
	 */
	const SELF_URL_PATH = "SELF_URL_PATH";

	/**
	 * By default the tt-rss self URL will be built using the 'Host' request header.
	 * Enabling this option will force tt-rss to instead always use the SELF_URL_PATH value,
	 * which may simplify things in certain scenarios (e.g. multiple proxies).
	 *
	 * NOTE: This was introduced instead of always using 'SELF_URL_PATH' since 'SELF_URL_PATH' may already exist
	 * in configs but be invalid (which was a common setup issue prior to auto-detection).
	 */
	const FORCE_SELF_URL_PATH_USAGE = 'FORCE_SELF_URL_PATH_USAGE';

	/** operate in single user mode, disables all functionality related to
	 * multiple users and authentication. enabling this assumes you have
	 * your tt-rss directory protected by other means (e.g. http auth). */
	const SINGLE_USER_MODE = "SINGLE_USER_MODE";

	/** use this PHP CLI executable to start various tasks */
	const PHP_EXECUTABLE = "PHP_EXECUTABLE";

	/** base directory for lockfiles (must be writable) */
	const LOCK_DIRECTORY = "LOCK_DIRECTORY";

	/** base directory for local cache (must be writable) */
	const CACHE_DIR = "CACHE_DIR";

	/** auto create users authenticated via external modules */
	const AUTH_AUTO_CREATE = "AUTH_AUTO_CREATE";

	/** auto log in users authenticated via external modules i.e. auth_remote */
	const AUTH_AUTO_LOGIN = "AUTH_AUTO_LOGIN";

	/** unconditinally purge all articles older than this amount, in days
	 * overrides user-controlled purge interval */
	const FORCE_ARTICLE_PURGE = "FORCE_ARTICLE_PURGE";

	/** default lifetime of a session (e.g. login) cookie. In seconds,
	 * 0 means cookie will be deleted when browser closes. */
	const SESSION_COOKIE_LIFETIME = "SESSION_COOKIE_LIFETIME";

	/** send email using this name */
	const SMTP_FROM_NAME = "SMTP_FROM_NAME";

	/** send email using this address */
	const SMTP_FROM_ADDRESS = "SMTP_FROM_ADDRESS";

	/** default subject for email digest */
	const DIGEST_SUBJECT = "DIGEST_SUBJECT";

	/** enable built-in update checker, both for core code and plugins (using git) */
	const CHECK_FOR_UPDATES = "CHECK_FOR_UPDATES";

	/** system plugins enabled for all users, comma separated list, no quotes
	 * keep at least one auth module in there (i.e. auth_internal) */
	const PLUGINS = "PLUGINS";

	/** available options: sql (default, event log), syslog, stdout (for debugging) */
	const LOG_DESTINATION = "LOG_DESTINATION";

	/** link this stylesheet on all pages (if it exists), should be placed in themes.local */
	const LOCAL_OVERRIDE_STYLESHEET = "LOCAL_OVERRIDE_STYLESHEET";

	/** same but this javascript file (you can use that for polyfills), should be placed in themes.local */
	const LOCAL_OVERRIDE_JS = "LOCAL_OVERRIDE_JS";

	/** in seconds, terminate update tasks that ran longer than this interval */
	const DAEMON_MAX_CHILD_RUNTIME = "DAEMON_MAX_CHILD_RUNTIME";

	/** max concurrent update jobs forking update daemon starts */
	const DAEMON_MAX_JOBS = "DAEMON_MAX_JOBS";

	/** log level for update daemon */
	const DAEMON_LOG_LEVEL = "DAEMON_LOG_LEVEL";

	/** How long to wait for response when requesting feed from a site (seconds)  */
	const FEED_FETCH_TIMEOUT = "FEED_FETCH_TIMEOUT";

	/** How long to wait for response when requesting uncached feed from a site (seconds)  */
	const FEED_FETCH_NO_CACHE_TIMEOUT = "FEED_FETCH_NO_CACHE_TIMEOUT";

	/** Default timeout when fetching files from remote sites */
	const FILE_FETCH_TIMEOUT = "FILE_FETCH_TIMEOUT";

	/** How long to wait for initial response from website when fetching remote files */
	const FILE_FETCH_CONNECT_TIMEOUT = "FILE_FETCH_CONNECT_TIMEOUT";

	/** stop updating feeds if user haven't logged in for X days */
	const DAEMON_UPDATE_LOGIN_LIMIT = "DAEMON_UPDATE_LOGIN_LIMIT";

	/** how many feeds to update in one batch */
	const DAEMON_FEED_LIMIT = "DAEMON_FEED_LIMIT";

	/** default sleep interval between feed updates (sec) */
	const DAEMON_SLEEP_INTERVAL = "DAEMON_SLEEP_INTERVAL";

	/** do not cache files larger than that (bytes) */
	const MAX_CACHE_FILE_SIZE = "MAX_CACHE_FILE_SIZE";

	/** do not download files larger than that (bytes) */
	const MAX_DOWNLOAD_FILE_SIZE = "MAX_DOWNLOAD_FILE_SIZE";

	/** max file size for downloaded favicons (bytes) */
	const MAX_FAVICON_FILE_SIZE = "MAX_FAVICON_FILE_SIZE";

	/** max age in days for various automatically cached (temporary) files */
	const CACHE_MAX_DAYS = "CACHE_MAX_DAYS";

	/** max interval between forced unconditional updates for servers
	 * not complying with http if-modified-since (seconds) */
	const MAX_CONDITIONAL_INTERVAL = "MAX_CONDITIONAL_INTERVAL";

	/** automatically disable updates for feeds which failed to
	 * update for this amount of days; 0 disables */
	const DAEMON_UNSUCCESSFUL_DAYS_LIMIT = "DAEMON_UNSUCCESSFUL_DAYS_LIMIT";

	/** log all sent emails in the event log */
	const LOG_SENT_MAIL = "LOG_SENT_MAIL";

	/** use HTTP proxy for requests */
	const HTTP_PROXY = "HTTP_PROXY";

	/** prevent users from changing passwords */
	const FORBID_PASSWORD_CHANGES = "FORBID_PASSWORD_CHANGES";

	/** default session cookie name */
	const SESSION_NAME = "SESSION_NAME";

	/** enable plugin update checker (using git) */
	const CHECK_FOR_PLUGIN_UPDATES = "CHECK_FOR_PLUGIN_UPDATES";

	/** allow installing first party plugins using plugin installer in prefs */
	const ENABLE_PLUGIN_INSTALLER = "ENABLE_PLUGIN_INSTALLER";

	/** minimum amount of seconds required between authentication attempts */
	const AUTH_MIN_INTERVAL = "AUTH_MIN_INTERVAL";

	/** http user agent (changing this is not recommended) */
	const HTTP_USER_AGENT = "HTTP_USER_AGENT";

	/** delay updates for this feed if received HTTP 429 (Too Many Requests) for this amount of seconds (base value, actual delay is base...base*2) */
	const HTTP_429_THROTTLE_INTERVAL = "HTTP_429_THROTTLE_INTERVAL";

	/** disables login form controls except HOOK_LOGINFORM_ADDITIONAL_BUTTONS (for SSO providers), also prevents logging in through auth_internal */
	const DISABLE_LOGIN_FORM = "DISABLE_LOGIN_FORM";

	/** optional key to transparently encrypt sensitive data (currently limited to sessions and feed passwords),
	 * key is a 32 byte hex string which may be generated using `update.php --gen-encryption-key` */
	const ENCRYPTION_KEY = "ENCRYPTION_KEY";

	/** scheduled task to purge orphaned articles, value should be valid cron expression
	 * @see https://github.com/dragonmantank/cron-expression/blob/master/README.md#cron-expressions
	*/
	const SCHEDULE_PURGE_ORPHANS = "SCHEDULE_PURGE_ORPHANS";

	/** scheduled task to expire disk cache, value should be valid cron expression */
	const SCHEDULE_DISK_CACHE_EXPIRE_ALL = "SCHEDULE_DISK_CACHE_EXPIRE_ALL";

	/** scheduled task, value should be valid cron expression */
	const SCHEDULE_DISABLE_FAILED_FEEDS = "SCHEDULE_DISABLE_FAILED_FEEDS";

	/** scheduled task to cleanup feed icons, value should be valid cron expression */
	const SCHEDULE_CLEANUP_FEED_ICONS = "SCHEDULE_CLEANUP_FEED_ICONS";

	/** scheduled task to disable feed updates of inactive users, value should be valid cron expression */
	const SCHEDULE_LOG_DAEMON_UPDATE_LOGIN_LIMIT_USERS = "SCHEDULE_LOG_DAEMON_UPDATE_LOGIN_LIMIT_USERS";

	/** scheduled task to cleanup error log, value should be valid cron expression */
	const SCHEDULE_EXPIRE_ERROR_LOG = "SCHEDULE_EXPIRE_ERROR_LOG";

	/** scheduled task to cleanup update daemon lock files, value should be valid cron expression */
	const SCHEDULE_EXPIRE_LOCK_FILES = "SCHEDULE_EXPIRE_LOCK_FILES";

	/** scheduled task to send digests, value should be valid cron expression */
	const SCHEDULE_SEND_HEADLINES_DIGESTS = "SCHEDULE_SEND_HEADLINES_DIGESTS";

	/** default (fallback) light theme path */
	const DEFAULT_LIGHT_THEME = "DEFAULT_LIGHT_THEME";

	/** default (fallback) dark (night) theme path */
	const DEFAULT_DARK_THEME = "DEFAULT_DARK_THEME";

	/** default values for all global configuration options */
	private const _DEFAULTS = [
		Config::DB_TYPE => [ "pgsql", 									Config::T_STRING ],
		Config::DB_HOST => [ "db", 										Config::T_STRING ],
		Config::DB_USER => [ "",											Config::T_STRING ],
		Config::DB_NAME => [ "", 											Config::T_STRING ],
		Config::DB_PASS => [ "", 											Config::T_STRING ],
		Config::DB_PORT => [ "5432",										Config::T_STRING ],
		Config::DB_SSLMODE => [ "prefer",                        Config::T_STRING ],
		Config::SELF_URL_PATH => [ "https://example.com/tt-rss", Config::T_STRING ],
		Config::FORCE_SELF_URL_PATH_USAGE => ['false', Config::T_BOOL],
		Config::SINGLE_USER_MODE => [ "",								Config::T_BOOL ],
		Config::PHP_EXECUTABLE => [ "/usr/bin/php",					Config::T_STRING ],
		Config::LOCK_DIRECTORY => [ "lock",								Config::T_STRING ],
		Config::CACHE_DIR => [ "cache",									Config::T_STRING ],
		Config::AUTH_AUTO_CREATE => [ "true",							Config::T_BOOL ],
		Config::AUTH_AUTO_LOGIN => [ "true",							Config::T_BOOL ],
		Config::FORCE_ARTICLE_PURGE => [ 0,								Config::T_INT ],
		Config::SESSION_COOKIE_LIFETIME => [ 86400,					Config::T_INT ],
		Config::SMTP_FROM_NAME => [ "Tiny Tiny RSS",					Config::T_STRING ],
		Config::SMTP_FROM_ADDRESS => [ "noreply@localhost",		Config::T_STRING ],
		Config::DIGEST_SUBJECT => [ "[tt-rss] New headlines for last 24 hours",
																					Config::T_STRING ],
		Config::CHECK_FOR_UPDATES => [ "true",							Config::T_BOOL ],
		Config::PLUGINS => [ "auth_internal",							Config::T_STRING ],
		Config::LOG_DESTINATION => [ Logger::LOG_DEST_SQL,			Config::T_STRING ],
		Config::LOCAL_OVERRIDE_STYLESHEET => [ "local-overrides.css",
																					Config::T_STRING ],
		Config::LOCAL_OVERRIDE_JS => [ "local-overrides.js",
																					Config::T_STRING ],
		Config::DAEMON_MAX_CHILD_RUNTIME => [ 1800,					Config::T_INT ],
		Config::DAEMON_MAX_JOBS => [ 2,									Config::T_INT ],
		Config::DAEMON_LOG_LEVEL => [ Debug::LOG_NORMAL,			Config::T_INT ],
		Config::FEED_FETCH_TIMEOUT => [ 45,								Config::T_INT ],
		Config::FEED_FETCH_NO_CACHE_TIMEOUT => [ 15,					Config::T_INT ],
		Config::FILE_FETCH_TIMEOUT => [ 45,								Config::T_INT ],
		Config::FILE_FETCH_CONNECT_TIMEOUT => [ 15,					Config::T_INT ],
		Config::DAEMON_UPDATE_LOGIN_LIMIT => [ 30,					Config::T_INT ],
		Config::DAEMON_FEED_LIMIT => [ 50,								Config::T_INT ],
		Config::DAEMON_SLEEP_INTERVAL => [ 120,						Config::T_INT ],
		Config::MAX_CACHE_FILE_SIZE => [ 64*1024*1024,				Config::T_INT ],
		Config::MAX_DOWNLOAD_FILE_SIZE => [ 16*1024*1024,			Config::T_INT ],
		Config::MAX_FAVICON_FILE_SIZE => [ 1*1024*1024,				Config::T_INT ],
		Config::CACHE_MAX_DAYS => [ 7,									Config::T_INT ],
		Config::MAX_CONDITIONAL_INTERVAL => [ 3600*12,				Config::T_INT ],
		Config::DAEMON_UNSUCCESSFUL_DAYS_LIMIT => [ 30,				Config::T_INT ],
		Config::LOG_SENT_MAIL => [ "",									Config::T_BOOL ],
		Config::HTTP_PROXY => [ "",										Config::T_STRING ],
		Config::FORBID_PASSWORD_CHANGES => [ "",						Config::T_BOOL ],
		Config::SESSION_NAME => [ "ttrss_sid",							Config::T_STRING ],
		Config::CHECK_FOR_PLUGIN_UPDATES => [ "true",				Config::T_BOOL ],
		Config::ENABLE_PLUGIN_INSTALLER => [ "true",					Config::T_BOOL ],
		Config::AUTH_MIN_INTERVAL => [ 5,								Config::T_INT ],
		Config::HTTP_USER_AGENT => [ 'Tiny Tiny RSS/%s (https://github.com/tt-rss/tt-rss)',
																					Config::T_STRING ],
		Config::HTTP_429_THROTTLE_INTERVAL => [ 3600,				Config::T_INT ],
		Config::DISABLE_LOGIN_FORM => [ "",								Config::T_BOOL ],
		Config::ENCRYPTION_KEY => [ "",                  			Config::T_STRING ],
		Config::SCHEDULE_PURGE_ORPHANS => ["@daily", 				Config::T_STRING],
		Config::SCHEDULE_DISK_CACHE_EXPIRE_ALL => ["@daily", 		Config::T_STRING],
		Config::SCHEDULE_DISABLE_FAILED_FEEDS => ["@daily", 		Config::T_STRING],
		Config::SCHEDULE_CLEANUP_FEED_ICONS => ["@daily", 			Config::T_STRING],
		Config::SCHEDULE_LOG_DAEMON_UPDATE_LOGIN_LIMIT_USERS =>
																	["@daily",	Config::T_STRING],
		Config::SCHEDULE_EXPIRE_ERROR_LOG => ["@hourly", 			Config::T_STRING],
		Config::SCHEDULE_EXPIRE_LOCK_FILES => ["@hourly", 			Config::T_STRING],
		Config::SCHEDULE_SEND_HEADLINES_DIGESTS => ["@hourly", 	Config::T_STRING],
		Config::DEFAULT_LIGHT_THEME => [ "light.css",     			Config::T_STRING],
		Config::DEFAULT_DARK_THEME => [ "night.css",     			Config::T_STRING],
	];

	private static ?Config $instance = null;

	/** @var array<string, array<bool|int|string>> */
	private array $params = [];

	/** @var array<string, mixed> */
	private array $version;

	private Db_Migrations $migrations;

	public static function get_instance() : Config {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	private function __clone() {
		//
	}

	function __construct() {
		$ref = new ReflectionClass(static::class);

		foreach ($ref->getConstants() as $const => $cvalue) {
			if (isset(self::_DEFAULTS[$const])) {
				$override = getenv(self::_ENVVAR_PREFIX . $const);

				[$defval, $deftype] = self::_DEFAULTS[$const];

				$this->params[$cvalue] = [ self::cast_to($override !== false ? $override : $defval, $deftype), $deftype ];
			}
		}
	}

	/** determine tt-rss version (using git)
	 *
	 * package maintainers who don't use git: if version_static.txt exists in tt-rss root
	 * directory, its contents are displayed instead of git commit-based version, this could be generated
	 * based on source git tree commit used when creating the package
	 * @return array<string, mixed>|string
	 */
	static function get_version(bool $as_string = true): array|string {
		return self::get_instance()->_get_version($as_string);
	}

	// returns version showing (if possible) full timestamp of commit id
	static function get_version_html() : string {
		$version = self::get_version(false);

		return sprintf("<span title=\"%s\n%s\n%s\">%s</span>",
			date("Y-m-d H:i:s", ($version['timestamp'] ?? 0)),
				$version['commit'] ?? '',
				$version['branch'] ?? '',
				$version['version']);
	}

	/**
	 * @return array{branch: string, timestamp: int, version: string, commit: string, status: int}|array{version: string}|string
	 */
	private function _get_version(bool $as_string = true): array|string {
		$root_dir = self::get_self_dir();

		if (empty($this->version)) {
			if (getenv('CI_COMMIT_SHORT_SHA') && getenv('CI_COMMIT_TIMESTAMP')) {
				$this->version = [
					'branch' => getenv('CI_COMMIT_BRANCH'),
					'timestamp' => strtotime(getenv('CI_COMMIT_TIMESTAMP')),
					'commit' => getenv('CI_COMMIT_SHORT_SHA'),
					'status' => 0,
				];
				$this->version['version'] = sprintf('%s-%s', date('y.m', $this->version['timestamp']), getenv('CI_COMMIT_SHORT_SHA'));
			} else if (PHP_OS === 'Darwin') {
				$this->version = ['version' => 'UNKNOWN (Unsupported, Darwin)', 'status' => -1];
			} else if (file_exists("$root_dir/version_static.txt")) {
				$this->version = ['version' => trim(file_get_contents("$root_dir/version_static.txt")) . ' (Unsupported)', 'status' => -1];
			} else if (ini_get("open_basedir")) {
				$this->version = ['version' => 'UNKNOWN (Unsupported, open_basedir)', 'status' => -1];
			} else if (is_dir("$root_dir/.git")) {
				$this->version = self::get_version_from_git($root_dir);

				if ($this->version['status'] != 0) {
					user_error('Unable to determine version: ' . $this->version['version'], E_USER_WARNING);

					$this->version = ['version' => 'UNKNOWN (Unsupported, Git error)', 'status' => -1];
				} else if (!getenv('SCRIPT_ROOT') || !file_exists('/.dockerenv')) {
					$this->version['version'] .= ' (Unsupported)';
				}
			} else {
				$this->version = ['version' => 'UNKNOWN (Unsupported)', 'status' => -1];
			}
		}

		return $as_string ? $this->version['version'] : $this->version;
	}

	/**
	 * @return array{status: int, version: string, branch: string, commit: string, timestamp: string}
	 */
	static function get_version_from_git(string $dir): array {
		$descriptorspec = [
			1 => ["pipe", "w"], // STDOUT
			2 => ["pipe", "w"], // STDERR
		];

		$rv = [
			"status" => -1,
			"version" => "",
			"branch" => "",
			"commit" => "",
			"timestamp" => "0",
		];

		$proc = proc_open('git --no-pager log --pretty="version-%ct-%h" --abbrev=8 -n1 HEAD',
						$descriptorspec, $pipes, $dir);

		if (is_resource($proc)) {
			$stdout = trim(stream_get_contents($pipes[1]));
			$stderr = trim(stream_get_contents($pipes[2]));
			$status = proc_close($proc);

			$rv["status"] = $status;

			[$check, $timestamp, $commit] = explode("-", $stdout);

			if ($check == "version") {

				$rv["version"] = sprintf("%s-%s", date("y.m", (int)$timestamp), $commit);
				$rv["commit"] = $commit;
				$rv["timestamp"] = $timestamp;

				// proc_close() may return -1 even if command completed successfully
				// so if it looks like we got valid data, we ignore it

				if ($rv["status"] == -1)
					$rv["status"] = 0;

			} else {
				$rv["version"] = T_sprintf("Git error [RC=%d]: %s", $status, $stderr);
			}
		}

		return $rv;
	}

	static function get_migrations() : Db_Migrations {
		return self::get_instance()->_get_migrations();
	}

	private function _get_migrations() : Db_Migrations {
		if (empty($this->migrations)) {
			$this->migrations = new Db_Migrations();
			$this->migrations->initialize(self::get_self_dir() . "/sql", "ttrss_version", true, self::SCHEMA_VERSION);
		}

		return $this->migrations;
	}

	static function is_migration_needed() : bool {
		return self::get_migrations()->is_migration_needed();
	}

	static function get_schema_version() : int {
		return self::get_migrations()->get_version();
	}

	static function cast_to(string $value, int $type_hint): bool|int|string {
		return match ($type_hint) {
			self::T_BOOL => sql_bool_to_bool($value),
			self::T_INT => (int) $value,
			default => $value,
		};
	}

	private function _get(string $param): bool|int|string {
		[$value, $type_hint] = $this->params[$param];

		return static::cast_to($value, $type_hint);
	}

	private function _add(string $param, string $default, int $type_hint): void {
		$override = getenv(self::_ENVVAR_PREFIX . $param);

		$this->params[$param] = [ self::cast_to($override !== false ? $override : $default, $type_hint), $type_hint ];
	}

	static function add(string $param, string $default, int $type_hint = Config::T_STRING): void {
		$instance = self::get_instance();

		$instance->_add($param, $default, $type_hint);
	}

	static function get(string $param): bool|int|string {
		$instance = self::get_instance();

		return $instance->_get($param);
	}

	static function is_server_https() : bool {
		return (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off')) ||
			(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
	}

	/**
	 * Returns the fully-qualified external URL to tt-rss (with no trailing slash).
	 * The SELF_URL_PATH configuration variable is always used when running under
	 * the CLI SAPI or when FORCE_SELF_URL_PATH_USAGE is enabled.
	 */
	static function get_self_url(bool $always_detect = false) : string {
		if ((!$always_detect && php_sapi_name() == 'cli') || self::get(self::FORCE_SELF_URL_PATH_USAGE)) {
			$self_url_path = self::get(Config::SELF_URL_PATH);
		} else {
			$proto = self::is_server_https() ? 'https' : 'http';

			$self_url_path = $proto . '://' . $_SERVER["HTTP_HOST"] . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
			$self_url_path = preg_replace("/(\/api\/{1,})?(\w+\.php)?(\?.*$)?$/", "", $self_url_path);
			$self_url_path = preg_replace("/(\/plugins(.local)?)\/.{1,}$/", "", $self_url_path);
		}

		return rtrim($self_url_path, '/');
	}
	/* sanity check stuff */

	static function sanity_check(): void {

		/*
			we don't actually need the DB object right now but some checks below might use ORM which won't be initialized
			because it is set up in the Db constructor, which is why it's a good idea to invoke it as early as possible

			it is a bit of a hack, maybe ORM should be initialized somewhere else (functions.php?)
		*/
		$pdo = Db::pdo();

		$errors = [];

		if (!str_contains(self::get(Config::PLUGINS), "auth_")) {
			$errors[] = 'Please enable at least one authentication module via PLUGINS';
		}

		/* we assume our dependencies are sane under docker, so some sanity checks are skipped.
			this also allows tt-rss process to run under root if requested (I'm using this for development
			under podman because of uidmapping issues with rootless containers, don't use in production -fox) */
		if (!getenv('container')) {
			if (function_exists('posix_getuid') && posix_getuid() == 0)
				$errors[] = "Please don't run this script as root.";

			if (version_compare(PHP_VERSION, '8.2.0', '<')) {
				$errors[] = "PHP version 8.2.0 or newer required. You're using " . PHP_VERSION . '.';
			}

			if (!class_exists('UConverter'))
				$errors[] = "PHP UConverter class is missing, it's provided by the Internationalization (intl) module.";

			if (!function_exists('curl_init') && !ini_get('allow_url_fopen'))
				$errors[] = 'PHP configuration option allow_url_fopen is disabled, and CURL functions are not present. Either enable allow_url_fopen or install PHP extension for CURL.';

			if (!function_exists('json_encode'))
				$errors[] = 'PHP support for JSON is required, but was not found.';

			if (!function_exists('flock'))
				$errors[] = 'PHP support for flock() function is required.';

			if (!class_exists('PDO'))
				$errors[] = 'PHP support for PDO is required but was not found.';

			if (!function_exists('mb_strlen'))
				$errors[] = 'PHP support for mbstring functions is required but was not found.';

			if (!function_exists('hash'))
				$errors[] = 'PHP support for hash() function is required but was not found.';

			if (ini_get('safe_mode'))
				$errors[] = 'PHP safe mode setting is obsolete and not supported by tt-rss.';

			if (!function_exists('mime_content_type'))
				$errors[] = 'PHP function mime_content_type() is missing, try enabling fileinfo module.';

			if (!class_exists('DOMDocument'))
				$errors[] = 'PHP support for DOMDocument is required, but was not found.';
		}

		if (!is_writable(self::get(Config::CACHE_DIR) . '/images'))
			$errors[] = 'Image cache is not writable (chmod -R 777 ' . self::get(Config::CACHE_DIR) . '/images)';

		if (!is_writable(self::get(Config::CACHE_DIR) . '/upload'))
			$errors[] = 'Upload cache is not writable (chmod -R 777 ' . self::get(Config::CACHE_DIR) . '/upload)';

		if (!is_writable(self::get(Config::CACHE_DIR) . '/export'))
			$errors[] = 'Data export cache is not writable (chmod -R 777 ' . self::get(Config::CACHE_DIR) . '/export)';

		if (!is_writable(self::get(Config::LOCK_DIRECTORY)))
			$errors[] = 'LOCK_DIRECTORY is not writable (chmod -R 777 ' . self::get(Config::LOCK_DIRECTORY) . ').';

		// ttrss_users won't be there on initial startup (before migrations are done)
		if (!Config::is_migration_needed() && self::get(Config::SINGLE_USER_MODE)) {
			if (UserHelper::get_login_by_id(1) != 'admin')
				$errors[] = 'SINGLE_USER_MODE is enabled but default admin account (ID: 1) is not found.';
		}

		// skip check for CLI scripts so that we could install database schema if it is missing.
		if (php_sapi_name() != 'cli') {
			if (self::get_schema_version() < 0)
				$errors[] = 'Base database schema is missing. Either load it manually or perform a migration (<code>update.php --update-schema</code>)';
		}

		if (count($errors) > 0 && php_sapi_name() != 'cli') {
			http_response_code(503); ?>

			<!DOCTYPE html>
			<html>
				<head>
					<title>Startup failed</title>
					<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
					<link rel="stylesheet" type="text/css" href="themes/light.css">
				</head>
				<body class="sanity_failed flat ttrss_utility">
					<div class="content">
						<h1>Startup failed</h1>

						<p>Please fix errors indicated by the following messages:</p>

						<?php foreach ($errors as $error) { echo self::format_error($error); } ?>

						<p>You might want to check the tt-rss <a target="_blank" rel="noreferrer" href="https://tt-rss.org/">documentation</a> or
							<a target="_blank" rel="noreferrer" href="https://github.com/tt-rss/tt-rss/discussions">discussions</a> for more information.
							Please search before creating a new topic for your question.</p>
					</div>
				</body>
			</html>

		<?php
			die;
		} else if (count($errors) > 0) {
			echo "Please fix errors indicated by the following messages:\n\n";

			foreach ($errors as $error) {
				echo " * " . strip_tags($error)."\n";
			}

			echo "\nYou might want to check the tt-rss wiki or forums for more information.\n";
			echo "Please search the forums before creating a new topic for your question.\n";

			exit(1);
		}
	}

	private static function format_error(string $msg): string {
		return "<div class=\"alert alert-danger\">$msg</div>";
	}

	static function get_override_links(): string {
		$rv = "";

		$local_css = get_theme_path(self::get(self::LOCAL_OVERRIDE_STYLESHEET));
		if ($local_css) $rv .= stylesheet_tag($local_css);

		$local_js = get_theme_path(self::get(self::LOCAL_OVERRIDE_JS));
		if ($local_js) $rv .= javascript_tag($local_js);

		return $rv;
	}

	static function get_user_agent(): string {
		return sprintf(self::get(self::HTTP_USER_AGENT), self::get_version());
	}

	static function get_self_dir() : string {
		return dirname(__DIR__); # we're in classes/Config.php
	}

}

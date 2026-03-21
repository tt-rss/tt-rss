<?php
class PluginHost {
	private ?PDO $pdo = null;

	/**
	 * separate handle for plugin data so transaction while saving wouldn't clash with possible main
	 * tt-rss code transactions; only initialized when first needed
	 */
	private ?PDO $pdo_data = null;

	/** @var array<PluginHost::HOOK_*, array<int, array<int, Plugin>>> hook types -> priority levels -> Plugins */
	private array $hooks = [];

	/** @var array<string, Plugin> */
	private array $plugins = [];

	/** @var array<string, array<string, Plugin>> handler type -> method type -> Plugin */
	private array $handlers = [];

	/** @var array<string, array{'description': string, 'suffix': string, 'arghelp': string, 'class': Plugin}> command type -> details array */
	private array $commands = [];

	/** @var array<string, array<string, mixed>> plugin name -> (potential profile array) -> key -> value  */
	private array $storage = [];

	/** @var array<int, array{'id': int, 'title': string, 'sender': Plugin, 'icon': string}> */
	private array $special_feeds = [];

	/** @var array<string, Plugin> API method name, Plugin sender */
	private array $api_methods = [];

	/** @var array<string, array<int, array{'action': string, 'description': string, 'sender': Plugin}>> */
	private array $plugin_actions = [];

	private ?int $owner_uid = null;

	private bool $data_loaded = false;

	private static ?PluginHost $instance = null;

	private ?Scheduler $scheduler = null;

	const API_VERSION = 2;
	const PUBLIC_METHOD_DELIMITER = "--";

	/** @see Plugin::hook_article_button() */
	const HOOK_ARTICLE_BUTTON = "hook_article_button";

	/** @see Plugin::hook_article_filter() */
	const HOOK_ARTICLE_FILTER = "hook_article_filter";

	/** @see Plugin::hook_prefs_tab() */
	const HOOK_PREFS_TAB = "hook_prefs_tab";

	/** @see Plugin::hook_prefs_tab_section() */
	const HOOK_PREFS_TAB_SECTION = "hook_prefs_tab_section";

	/** @see Plugin::hook_prefs_tabs() */
	const HOOK_PREFS_TABS = "hook_prefs_tabs";

	/** @see Plugin::hook_feed_parsed() */
	const HOOK_FEED_PARSED = "hook_feed_parsed";

	/** @see Plugin::hook_update_task() */
	const HOOK_UPDATE_TASK = "hook_update_task"; //*1

	/** @see Plugin::hook_auth_user() */
	const HOOK_AUTH_USER = "hook_auth_user";

	/** @see Plugin::hook_hotkey_map() */
	const HOOK_HOTKEY_MAP = "hook_hotkey_map";

	/** @see Plugin::hook_render_article() */
	const HOOK_RENDER_ARTICLE = "hook_render_article";

	/** @see Plugin::hook_render_article_cdm() */
	const HOOK_RENDER_ARTICLE_CDM = "hook_render_article_cdm";

	/** @see Plugin::hook_feed_fetched() */
	const HOOK_FEED_FETCHED = "hook_feed_fetched";

	/** @see Plugin::hook_sanitize() */
	const HOOK_SANITIZE = "hook_sanitize";

	/** @see Plugin::hook_render_article_api() */
	const HOOK_RENDER_ARTICLE_API = "hook_render_article_api";

	/** @see Plugin::hook_toolbar_button() */
	const HOOK_TOOLBAR_BUTTON = "hook_toolbar_button";

	/** @see Plugin::hook_action_item() */
	const HOOK_ACTION_ITEM = "hook_action_item";

	/** @see Plugin::hook_headline_toolbar_button() */
	const HOOK_HEADLINE_TOOLBAR_BUTTON = "hook_headline_toolbar_button";

	/** @see Plugin::hook_hotkey_info() */
	const HOOK_HOTKEY_INFO = "hook_hotkey_info";

	/** @see Plugin::hook_article_left_button() */
	const HOOK_ARTICLE_LEFT_BUTTON = "hook_article_left_button";

	/** @see Plugin::hook_prefs_edit_feed() */
	const HOOK_PREFS_EDIT_FEED = "hook_prefs_edit_feed";

	/** @see Plugin::hook_prefs_save_feed() */
	const HOOK_PREFS_SAVE_FEED = "hook_prefs_save_feed";

	/** @see Plugin::hook_fetch_feed() */
	const HOOK_FETCH_FEED = "hook_fetch_feed";

	/** @see Plugin::hook_query_headlines() */
	const HOOK_QUERY_HEADLINES = "hook_query_headlines";

	/** @see Plugin::hook_house_keeping() */
	const HOOK_HOUSE_KEEPING = "hook_house_keeping"; //*1

	/** @see Plugin::hook_search() */
	const HOOK_SEARCH = "hook_search";

	/** @see Plugin::hook_format_enclosures() */
	const HOOK_FORMAT_ENCLOSURES = "hook_format_enclosures";

	/** @see Plugin::hook_subscribe_feed() */
	const HOOK_SUBSCRIBE_FEED = "hook_subscribe_feed";

	/** @see Plugin::hook_headlines_before() */
	const HOOK_HEADLINES_BEFORE = "hook_headlines_before";

	/** @see Plugin::hook_render_enclosure() */
	const HOOK_RENDER_ENCLOSURE = "hook_render_enclosure";

	/** @see Plugin::hook_article_filter_action() */
	const HOOK_ARTICLE_FILTER_ACTION = "hook_article_filter_action";

	/** @see Plugin::hook_article_export_feed() */
	const HOOK_ARTICLE_EXPORT_FEED = "hook_article_export_feed";

	/** @see Plugin::hook_main_toolbar_button() */
	const HOOK_MAIN_TOOLBAR_BUTTON = "hook_main_toolbar_button";

	/** @see Plugin::hook_enclosure_entry() */
	const HOOK_ENCLOSURE_ENTRY = "hook_enclosure_entry";

	/** @see Plugin::hook_format_article() */
	const HOOK_FORMAT_ARTICLE = "hook_format_article";

	/** @see Plugin::hook_feed_basic_info() */
	const HOOK_FEED_BASIC_INFO = "hook_feed_basic_info";

	/** @see Plugin::hook_send_local_file() */
	const HOOK_SEND_LOCAL_FILE = "hook_send_local_file";

	/** @see Plugin::hook_unsubscribe_feed() */
	const HOOK_UNSUBSCRIBE_FEED = "hook_unsubscribe_feed";

	/** @see Plugin::hook_send_mail() */
	const HOOK_SEND_MAIL = "hook_send_mail";

	/** @see Plugin::hook_filter_triggered() */
	const HOOK_FILTER_TRIGGERED = "hook_filter_triggered";

	/** @see Plugin::hook_get_full_text() */
	const HOOK_GET_FULL_TEXT = "hook_get_full_text";

	/** @see Plugin::hook_article_image() */
	const HOOK_ARTICLE_IMAGE = "hook_article_image";

	/** @see Plugin::hook_feed_tree() */
	const HOOK_FEED_TREE = "hook_feed_tree";

	/** @see Plugin::hook_iframe_whitelisted() */
	const HOOK_IFRAME_WHITELISTED = "hook_iframe_whitelisted";

	/** @see Plugin::hook_enclosure_imported() */
	const HOOK_ENCLOSURE_IMPORTED = "hook_enclosure_imported";

	/** @see Plugin::hook_headlines_custom_sort_map() */
	const HOOK_HEADLINES_CUSTOM_SORT_MAP = "hook_headlines_custom_sort_map";

	/** @see Plugin::hook_headlines_custom_sort_override() */
	const HOOK_HEADLINES_CUSTOM_SORT_OVERRIDE = "hook_headlines_custom_sort_override";

	/** @see Plugin::hook_headline_toolbar_select_menu_item()
	 * @deprecated removed, see PluginHost::HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM2
	*/
	const HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM = "hook_headline_toolbar_select_menu_item";

	/** @see Plugin::hook_headline_toolbar_select_menu_item() */
	const HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM2 = "hook_headline_toolbar_select_menu_item2";

	/** @see Plugin::hook_pre_subscribe() */
	const HOOK_PRE_SUBSCRIBE = "hook_pre_subscribe";

	/** @see Plugin::hook_post_logout() */
	const HOOK_POST_LOGOUT = "hook_post_logout";

	/** @see Plugin::hook_loginform_additional_buttons() */
	const HOOK_LOGINFORM_ADDITIONAL_BUTTONS = "hook_loginform_additional_buttons";

	/** @see Plugin::hook_validate_session() */
	const HOOK_VALIDATE_SESSION = "hook_validate_session";

	/** @see Plugin::hook_articles_mark_toggled() */
	const HOOK_ARTICLES_MARK_TOGGLED = "hook_articles_mark_toggled";

	/** @see Plugin::hook_articles_publish_toggled() */
	const HOOK_ARTICLES_PUBLISH_TOGGLED = "hook_articles_publish_toggled";

	const KIND_ALL = 1;
	const KIND_SYSTEM = 2;
	const KIND_USER = 3;

	static function object_to_domain(Plugin $plugin): string {
		return strtolower($plugin::class);
	}

	function __construct() {
		$this->pdo = Db::pdo();
		$this->scheduler = new Scheduler('PluginHost Scheduler');
		$this->storage = [];
	}

	private function __clone() {
		//
	}

	public static function getInstance(): PluginHost {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	private function register_plugin(string $name, Plugin $plugin): void {
		$this->plugins[$name] = $plugin;
	}

	/** needed for compatibility with API 1 */
	function get_link(): bool {
		return false;
	}

	/** needed for compatibility with API 2 (?) */
	function get_dbh(): bool {
		return false;
	}

	function get_pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * @return array<int, string>
	 */
	function get_plugin_names(): array {
		$names = [];

		foreach ($this->plugins as $p)
			$names[] = $p::class;

		return $names;
	}

	/**
	 * @return array<Plugin>
	 */
	function get_plugins(): array {
		return $this->plugins;
	}

	function get_plugin(string $name): ?Plugin {
		return $this->plugins[strtolower($name)] ?? null;
	}

	/**
	 * @param PluginHost::HOOK_* $hook
	 * @param mixed $args
	 */
	function run_hooks(string $hook, ...$args): void {

		$method = strtolower((string)$hook);

		foreach ($this->get_hooks((string)$hook) as $plugin) {
			//Debug::log("invoking: " . get_class($plugin) . "->$hook()", Debug::$LOG_VERBOSE);

			try {
				$plugin->$method(...$args);
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	/**
	 * @param PluginHost::HOOK_* $hook
	 * @param mixed $args
	 * @param mixed $check
	 */
	function run_hooks_until(string $hook, $check, ...$args): bool {
		$method = strtolower((string)$hook);

		foreach ($this->get_hooks((string)$hook) as $plugin) {
			try {
				$result = $plugin->$method(...$args);

				if ($result == $check)
					return true;

			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}

		return false;
	}

	/**
	 * @param PluginHost::HOOK_* $hook
	 * @param mixed $args
	 */
	function run_hooks_callback(string $hook, Closure $callback, ...$args): void {
		$method = strtolower((string)$hook);

		foreach ($this->get_hooks((string)$hook) as $plugin) {
			//Debug::log("invoking: " . get_class($plugin) . "->$hook()", Debug::$LOG_VERBOSE);

			try {
				if ($callback($plugin->$method(...$args), $plugin))
					break;
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	/**
	 * @param PluginHost::HOOK_* $hook
	 * @param mixed $args
	 */
	function chain_hooks_callback(string $hook, Closure $callback, &...$args): void {
		$method = strtolower((string)$hook);

		foreach ($this->get_hooks((string)$hook) as $plugin) {
			try {
				if ($callback($plugin->$method(...$args), $plugin))
					break;
			} catch (Exception $ex) {
				user_error($ex, E_USER_WARNING);
			} catch (Error $err) {
				user_error($err, E_USER_WARNING);
			}
		}
	}

	/**
	 * @param PluginHost::HOOK_* $type
	 */
	function add_hook(string $type, Plugin $sender, int $priority = 50): void {
		$priority = (int) $priority;

		if (!method_exists($sender, strtolower((string)$type))) {
			user_error(
				sprintf("Plugin %s tried to register a hook without implementation: %s",
					$sender::class, $type),
				E_USER_WARNING
			);
			return;
		}

		if (empty($this->hooks[$type])) {
			$this->hooks[$type] = [];
		}

		if (empty($this->hooks[$type][$priority])) {
			$this->hooks[$type][$priority] = [];
		}

		$this->hooks[$type][$priority][] = $sender;
		ksort($this->hooks[$type]);
	}

	/**
	 * @param PluginHost::HOOK_* $type
	 */
	function del_hook(string $type, Plugin $sender): void {
		if (array_key_exists($type, $this->hooks)) {
			foreach (array_keys($this->hooks[$type]) as $prio) {
				$key = array_search($sender, $this->hooks[$type][$prio]);

				if ($key !== false) {
					unset($this->hooks[$type][$prio][$key]);
				}
			}
		}
	}

	/**
	 * @param PluginHost::HOOK_* $type
	 * @return array<int, Plugin>
	 */
	function get_hooks(string $type) {
		if (isset($this->hooks[$type])) {
			$tmp = [];

			foreach (array_keys($this->hooks[$type]) as $prio) {
				array_push($tmp, ...$this->hooks[$type][$prio]);
			}

			return $tmp;
		}
		return [];
	}

	/**
	 * @param PluginHost::KIND_* $kind
	 */
	function load_all(int $kind, ?int $owner_uid = null, bool $skip_init = false): void {
		$plugin_root_dir = Config::get(Config::LOCAL_PLUGINS_DIR);
		$plugins = [...(glob("plugins/*") ?: []), ...(glob("$plugin_root_dir/*") ?: [])];
		$plugins = array_filter($plugins, is_dir(...));
		$plugins = array_map(basename(...), $plugins);

		asort($plugins);

		$this->load(join(",", $plugins), (int)$kind, $owner_uid, $skip_init);
	}

	/**
	 * @param PluginHost::KIND_* $kind
	 */
	function load(string $classlist, int $kind, ?int $owner_uid = null, bool $skip_init = false): void {
		$plugins = explode(",", $classlist);

		$this->owner_uid = (int) $owner_uid;

		if ($this->owner_uid) {
			$this->set_scheduler_name("PluginHost Scheduler for UID $owner_uid");
		}

		foreach ($plugins as $class) {
			$class = trim($class);
			$class_file = strtolower(basename(clean($class)));

			// try system plugin directory first
			$file = Config::get_self_dir() . "/plugins/$class_file/init.php";

			if (!file_exists($file)) {
				$plugin_root_dir = Config::get(Config::LOCAL_PLUGINS_DIR);
				$file = "$plugin_root_dir/$class_file/init.php";

				if (!file_exists($file)) {
					continue;
				}
			}

			if (!isset($this->plugins[$class])) {
				// WIP hack
				// we can't catch incompatible method signatures via Throwable
				// this also enables global tt-rss safe mode in case there are more plugins like this
				if (!getenv('TTRSS_XDEBUG_ENABLED') && ($_SESSION["plugin_blacklist"][$class] ?? 0)) {

					// only report once per-plugin per-session
					if ($_SESSION["plugin_blacklist"][$class] < 2) {
						user_error("Plugin $class has caused a PHP fatal error so it won't be loaded again in this session.", E_USER_WARNING);
						$_SESSION["plugin_blacklist"][$class] = 2;
					}

					$_SESSION["safe_mode"] = 1;
					continue;
				}

				try {
					$_SESSION["plugin_blacklist"][$class] = 1;
					require_once $file;
					unset($_SESSION["plugin_blacklist"][$class]);

				} catch (Error $err) {
					user_error($err, E_USER_WARNING);
					continue;
				}

				if (class_exists($class) && is_subclass_of($class, "Plugin")) {
					$plugin = new $class($this);
					$plugin_api = $plugin->api_version();

					if ($plugin_api < self::API_VERSION) {
						user_error("Plugin $class is not compatible with current API version (need: " . self::API_VERSION . ", got: $plugin_api)", E_USER_WARNING);
						continue;
					}

					if (file_exists(dirname($file) . "/locale")) {
						_bindtextdomain($class, dirname($file) . "/locale");
						_bind_textdomain_codeset($class, "UTF-8");
					}

					try {
						switch ($kind) {
							case $this::KIND_SYSTEM:
								if ($this->is_system($plugin)) {
									if (!$skip_init) $plugin->init($this);
									$this->register_plugin($class, $plugin);
								}
								break;
							case $this::KIND_USER:
								if (!$this->is_system($plugin)) {
									if (!$skip_init) $plugin->init($this);
									$this->register_plugin($class, $plugin);
								}
								break;
							case $this::KIND_ALL:
								if (!$skip_init) $plugin->init($this);
								$this->register_plugin($class, $plugin);
								break;
							}
					} catch (Exception $ex) {
						user_error($ex, E_USER_WARNING);
					} catch (Error $err) {
						user_error($err, E_USER_WARNING);
					}
				}
			}
		}

		$this->load_data();
	}

	function is_system(Plugin $plugin): bool {
		$about = $plugin->about();

		return ($about[3] ?? false) === true;
	}

	// only system plugins are allowed to modify routing
	function add_handler(string $handler, string $method, Plugin $sender): void {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			$this->handlers[$handler] ??= [];
			$this->handlers[$handler][$method] = $sender;
		}
	}

	function del_handler(string $handler, string $method, Plugin $sender): void {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if ($this->is_system($sender)) {
			unset($this->handlers[$handler][$method]);
		}
	}

	/**
	 * @return false|Plugin false if the handler couldn't be found, otherwise the Plugin/handler
	 */
	function lookup_handler(string $handler, string $method): false|Plugin {
		$handler = str_replace("-", "_", strtolower($handler));
		$method = strtolower($method);

		if (isset($this->handlers[$handler])) {
			return $this->handlers[$handler]["*"] ?? $this->handlers[$handler][$method];
		}

		return false;
	}

	function add_command(string $command, string $description, Plugin $sender, string $suffix = "", string $arghelp = ""): void {
		$command = str_replace("-", "_", strtolower($command));

		$this->commands[$command] = ["description" => $description,
			"suffix" => $suffix,
			"arghelp" => $arghelp,
			"class" => $sender];
	}

	function del_command(string $command): void {
		$command = "-" . strtolower($command);

		unset($this->commands[$command]);
	}

	/**
	 * @return false|Plugin false if the command couldn't be found, otherwise the registered Plugin
	 */
	function lookup_command(string $command): false|Plugin {
		$command = "-" . strtolower($command);

		if (array_key_exists($command, $this->commands)) {
			return $this->commands[$command]["class"];
		} else {
			return false;
		}
	}

	/** @return array<string, array{'description': string, 'suffix': string, 'arghelp': string, 'class': Plugin}> command type -> details array */
	function get_commands() {
		return $this->commands;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	function run_commands(array $args): void {
		foreach ($this->get_commands() as $command => $data) {
			if (isset($args[$command])) {
				$command = str_replace("-", "", $command);
				$data["class"]->$command($args);
			}
		}
	}

	private function load_data(): void {
		if ($this->owner_uid && !$this->data_loaded && Config::get_schema_version() > 100)  {
			$sth = $this->pdo->prepare("SELECT name, content FROM ttrss_plugin_storage
				WHERE owner_uid = ?");
			$sth->execute([$this->owner_uid]);

			while ($line = $sth->fetch()) {
				$this->storage[$line["name"]] = unserialize($line["content"]);
			}

			$this->data_loaded = true;
		}
	}

	private function save_data(string $plugin): void {
		if ($this->owner_uid) {
			if (!$this->pdo_data)
				$this->pdo_data = Db::instance()->pdo_connect();

			$this->pdo_data->beginTransaction();

			$sth = $this->pdo_data->prepare("SELECT id FROM ttrss_plugin_storage WHERE
				owner_uid= ? AND name = ?");
			$sth->execute([$this->owner_uid, $plugin]);

			$this->storage[$plugin] ??= [];

			$content = serialize($this->storage[$plugin]);

			if ($sth->fetch()) {
				$sth = $this->pdo_data->prepare("UPDATE ttrss_plugin_storage SET content = ?
					WHERE owner_uid= ? AND name = ?");
				$sth->execute([$content, $this->owner_uid, $plugin]);

			} else {
				$sth = $this->pdo_data->prepare("INSERT INTO ttrss_plugin_storage
					(name,owner_uid,content) VALUES
					(?, ?, ?)");
				$sth->execute([$plugin, $this->owner_uid, $content]);
			}

			$this->pdo_data->commit();
		}
	}

	/**
	 * same as set(), but sets data to current preference profile
	 *
	 * @param mixed $value
	 */
	function profile_set(Plugin $sender, string $name, $value): void {
		$profile_id = $_SESSION["profile"] ?? null;

		if ($profile_id) {
			$idx = $sender::class;

			$this->storage[$idx] ??= [];
			$this->storage[$idx][$profile_id] ??= [];
			$this->storage[$idx][$profile_id][$name] = $value;

			$this->save_data($sender::class);
		} else {
			$this->set($sender, $name, $value);
		}
	}

	/**
	 * @param mixed $value
	 */
	function set(Plugin $sender, string $name, $value): void {
		$idx = $sender::class;

		$this->storage[$idx] ??= [];
		$this->storage[$idx][$name] = $value;

		$this->save_data($sender::class);
	}

	/**
	 * @param array<int|string, mixed> $params
	 */
	function set_array(Plugin $sender, array $params): void {
		$idx = $sender::class;

		$this->storage[$idx] ??= [];

		foreach ($params as $name => $value)
			$this->storage[$idx][$name] = $value;

		$this->save_data($sender::class);
	}

	/**
	 * same as get(), but sets data to current preference profile
	 *
	 * @param mixed $default_value
	 * @return mixed
	 */
	function profile_get(Plugin $sender, string $name, $default_value = false) {
		$profile_id = $_SESSION["profile"] ?? null;

		if ($profile_id) {
			$idx = $sender::class;
			$this->load_data();
			return $this->storage[$idx][$profile_id][$name] ?? $default_value;
		} else {
			return $this->get($sender, $name, $default_value);
		}
	}

	/**
	 * @param mixed $default_value
	 * @return mixed
	 */
	function get(Plugin $sender, string $name, $default_value = false) {
		$idx = $sender::class;
		$this->load_data();
		return $this->storage[$idx][$name] ?? $default_value;
	}

	/**
	 * @param array<int|string, mixed> $default_value
	 * @return array<int|string, mixed>
	 */
	function get_array(Plugin $sender, string $name, array $default_value = []): array {
		$tmp = $this->get($sender, $name);

		if (!is_array($tmp)) $tmp = $default_value;

		return $tmp;
	}

	/**
	 * @return array<string, mixed>
	 */
	function get_all(Plugin $sender) {
		$idx = $sender::class;

		return $this->storage[$idx] ?? [];
	}

	function clear_data(Plugin $sender): void {
		if ($this->owner_uid) {
			$idx = $sender::class;

			unset($this->storage[$idx]);

			$sth = $this->pdo->prepare("DELETE FROM ttrss_plugin_storage WHERE name = ?
				AND owner_uid = ?");
			$sth->execute([$idx, $this->owner_uid]);
		}
	}

	/**
	 * Add a special (plugin-provided) feed
	 *
	 * @param int $cat_id only -1 (Feeds::CATEGORY_SPECIAL) is supported
	 * @return false|int false if the feed wasn't added (e.g. $cat_id wasn't Feeds::CATEGORY_SPECIAL),
	 *                   otherwise an integer "feed ID" that might change between executions
	 */
	function add_feed(int $cat_id, string $title, string $icon, Plugin $sender): false|int {
		if ($cat_id !== Feeds::CATEGORY_SPECIAL)
			return false;

		$id = count($this->special_feeds);

		$this->special_feeds[] = [
			'id' => $id,
			'title' => $title,
			'sender' => $sender,
			'icon' => $icon,
		];

		return $id;
	}

	/**
	 * Get special (plugin-provided) feeds
	 *
	 * @param int $cat_id only -1 (Feeds::CATEGORY_SPECIAL) is supported
	 * @return array<int, array{'id': int, 'title': string, 'sender': Plugin, 'icon': string}>
	 */
	function get_feeds(int $cat_id) {
		return $cat_id === Feeds::CATEGORY_SPECIAL ? $this->special_feeds : [];
	}

	/**
	 * Get the Plugin handling a specific virtual feed.
	 *
	 * Convert feed_id (e.g. -129) to pfeed_id first.
	 *
	 * @return (Plugin&IVirtualFeed)|null a Plugin that implements IVirtualFeed, otherwise null
	 */
	function get_feed_handler(int $pfeed_id): ?Plugin {
		foreach ($this->special_feeds as $feed) {
			if ($feed['id'] == $pfeed_id) {
				/** @var Plugin&IVirtualFeed $feed['sender'] */
				return implements_interface($feed['sender'], 'IVirtualFeed') ? $feed['sender'] : null;
			}
		}
		return null;
	}

	static function pfeed_to_feed_id(int $pfeed): int {
		return PLUGIN_FEED_BASE_INDEX - 1 - abs($pfeed);
	}

	static function feed_to_pfeed_id(int $feed): int {
		return PLUGIN_FEED_BASE_INDEX - 1 + abs($feed);
	}

	function add_api_method(string $name, Plugin $sender): void {
		if ($this->is_system($sender)) {
			$this->api_methods[strtolower($name)] = $sender;
		}
	}

	function get_api_method(string $name): ?Plugin {
		return $this->api_methods[$name] ?? null;
	}

	function add_filter_action(Plugin $sender, string $action_name, string $action_desc): void {
		$sender_class = $sender::class;

		$this->plugin_actions[$sender_class] ??= [];

		$this->plugin_actions[$sender_class][] = [
			"action" => $action_name,
			"description" => $action_desc,
			"sender" => $sender,
		];
	}

	/**
	 * @return array<string, array<int, array{'action': string, 'description': string, 'sender': Plugin}>>
	 */
	function get_filter_actions() {
		return $this->plugin_actions;
	}

	function get_owner_uid(): ?int {
		return $this->owner_uid;
	}

	/**
	 * handled by classes/PluginHandler.php, requires valid session
	 *
	 * @param array<int|string, mixed> $params
	 */
	function get_method_url(Plugin $sender, string $method, array $params = []): string  {
		return Config::get_self_url() . "/backend.php?" .
			http_build_query([
				'op' => 'pluginhandler',
				'plugin' => strtolower($sender::class),
				'method' => $method,
				...$params,
			]);
	}

	// shortcut syntax (disabled for now)
	/* function get_method_url(Plugin $sender, string $method, $params)  {
		return Config::get_self_url() . "/backend.php?" .
			http_build_query(
				array_merge(
					[
						"op" => strtolower(get_class($sender) . self::PUBLIC_METHOD_DELIMITER . $method),
					],
					$params));
	} */

	/**
	 * WARNING: endpoint in public.php, exposed to unauthenticated users
	 *
	 * @param array<int|string, mixed> $params
	 */
	function get_public_method_url(Plugin $sender, string $method, array $params = []): ?string  {
		if ($sender->is_public_method($method)) {
			return Config::get_self_url() . "/public.php?" .
				http_build_query([
					'op' => strtolower($sender::class . self::PUBLIC_METHOD_DELIMITER . $method),
					...$params,
				]);
		}
		user_error("get_public_method_url: requested method '$method' of '" . $sender::class . "' is private.");
		return null;
	}

	function get_plugin_dir(Plugin $plugin): string {
		$ref = new ReflectionClass($plugin::class);
		return dirname($ref->getFileName());
	}

	function is_local(Plugin $plugin): bool {
		$plugin_root_dir = Config::get(Config::LOCAL_PLUGINS_DIR);
		$plugin_dir = $this->get_plugin_dir($plugin);
		return realpath($plugin_root_dir) == realpath(dirname($plugin_dir));
	}

	/**
	 * This exposes sheduled tasks functionality to plugins. For system plugins, tasks registered here are
	 * executed (if due) during housekeeping. For user plugins, tasks are only run after any feeds owned by
	 * the user have been processed in an update batch (which means user is not inactive).
	 *
	 * This behaviour mirrors that of `HOOK_HOUSE_KEEPING` for user plugins.
	 *
	 * @see Scheduler::add_scheduled_task()
	 * @see Plugin::hook_house_keeping()
	*/
	function add_scheduled_task(Plugin $sender, string $task_name, string $cron_expression, Closure $callback): bool {
		if ($this->is_system($sender))
			$task_name = $sender::class . ':' . $task_name;
		else
			$task_name = $sender::class . ':' . $task_name . ':' . $this->owner_uid;

		return $this->scheduler->add_scheduled_task($task_name, $cron_expression, $callback);
	}

	function run_due_tasks() : void {
		$this->scheduler->run_due_tasks();
	}

	private function set_scheduler_name(string $name) : void {
		$this->scheduler->set_name($name);
	}
}

<?php
require_once __DIR__ . '/../include/colors.php';

class RSSUtils {

	/**
	 * @link https://developer.mozilla.org/docs/Web/HTTP/MIME_types/Common_types
	 */
	const FAVICON_ALLOWED_MIME_TYPES = [
		'image/bmp',
		'image/gif',
		'image/jpeg',
		'image/png',
		'image/vnd.microsoft.icon',
	];

	/**
	 * @param array<string, mixed> $article
	 */
	static function calculate_article_hash(array $article, PluginHost $pluginhost): string {
		$tmp = "";

		$ignored_fields = [ "feed", "guid", "guid_hashed", "owner_uid", "force_catchup" ];

		foreach ($article as $k => $v) {
			if (in_array($k, $ignored_fields))
				continue;

			if ($k != "feed" && isset($v)) {
				$x = strip_tags(
					is_array($v) ? implode(",", array_keys($v)) : $v);

				$tmp .= sha1("$k:" . sha1($x));
			}
		}

		return sha1(implode(",", $pluginhost->get_plugin_names()) . $tmp);
	}

	static function cleanup_feed_icons(): void {
		$pdo = Db::pdo();
		$sth = $pdo->prepare("SELECT id FROM ttrss_feeds WHERE id = ?");

		$cache = DiskCache::instance('feed-icons');

		if ($cache->is_writable()) {
			$dh = opendir($cache->get_full_path(""));

			if ($dh) {
				while (($icon = readdir($dh)) !== false) {
					if (preg_match('/^[0-9]{1,}$/', $icon) && $cache->get_mtime($icon) < time() - 86400 * Config::get(Config::CACHE_MAX_DAYS)) {

						$sth->execute([(int)$icon]);

						if ($sth->fetch()) {
							$cache->put($icon, $cache->get($icon));
						} else {
							$icon_path = $cache->get_full_path($icon);

							Debug::log("Removing orphaned feed icon: $icon_path");
							unlink($icon_path);
						}
					}
				}

				closedir($dh);
			}
		}
	}

	/**
	 * @param array<string, false|string> $options
	 */
	static function update_daemon_common(int $limit = 0, array $options = []): int {
		if (!$limit) $limit = Config::get(Config::DAEMON_FEED_LIMIT);

		if (Config::get_schema_version() != Config::SCHEMA_VERSION) {
			print("Schema version is wrong, please upgrade the database.\n");
			exit(1);
		}

		self::init_housekeeping_tasks();

		$pdo = Db::pdo();

		$feeds_in_the_future = ORM::for_table('ttrss_feeds')
			->where_raw("last_updated > NOW() OR last_update_started > NOW()")
			->limit(25)
			->find_many();

		if (count($feeds_in_the_future) > 0) {
			Debug::log("found feeds (limit 25) with update times in the future (current server time: ".date("Y-m-d H:i:s", time())."):");
			foreach ($feeds_in_the_future as $feed) {
				Debug::log("=> {$feed->feed_url} (ID: {$feed->id}, U: {$feed->owner_uid}): last updated {$feed->last_updated}, update started: {$feed->last_update_started}");
			}
		}

		if (!Config::get(Config::SINGLE_USER_MODE) && Config::get(Config::DAEMON_UPDATE_LOGIN_LIMIT) > 0) {
			$login_thresh_qpart = "AND last_login >= NOW() - INTERVAL '" . Config::get(Config::DAEMON_UPDATE_LOGIN_LIMIT) . " day'";
		} else {
			$login_thresh_qpart = "";
		}

		$default_interval = (int) Prefs::get_default(Prefs::DEFAULT_UPDATE_INTERVAL);

		$update_limit_qpart = "AND ((
				update_interval = 0
					AND (p.value IS NULL OR p.value != '-1')
					AND last_updated < NOW() - CAST((COALESCE(p.value, '$default_interval') || ' minutes') AS INTERVAL)
			) OR (
				update_interval > 0
				AND last_updated < NOW() - CAST((update_interval || ' minutes') AS INTERVAL)
			) OR (
				update_interval >= 0
					AND (p.value IS NULL OR p.value != '-1')
					AND (last_updated = '1970-01-01 00:00:00' OR last_updated IS NULL)
			))";

		$query_limit = $limit ? sprintf("LIMIT %d", $limit) : "";

		// The 'last_update_started' check is to determine if the feed is currently being updated by another process.
		// Update the least recently updated feeds first.
		$query = "SELECT f.feed_url, f.last_updated
			FROM
				ttrss_feeds f, ttrss_users u LEFT JOIN ttrss_user_prefs2 p ON
					(p.owner_uid = u.id AND profile IS NULL AND pref_name = 'DEFAULT_UPDATE_INTERVAL')
			WHERE
				f.owner_uid = u.id AND
				u.access_level NOT IN (".sprintf("%d, %d", UserHelper::ACCESS_LEVEL_DISABLED, UserHelper::ACCESS_LEVEL_READONLY).")
				$login_thresh_qpart
				$update_limit_qpart
				AND (last_update_started IS NULL OR last_update_started < NOW() - INTERVAL '10 minute')
				ORDER BY last_updated NULLS FIRST $query_limit";

		Debug::log("base feed query: $query", Debug::LOG_EXTENDED);

		$res = $pdo->query($query);

		$feeds_to_update = [];
		while ($line = $res->fetch())
			$feeds_to_update[] = $line['feed_url'];

		Debug::log(sprintf("Scheduled %d feeds to update...", count($feeds_to_update)));

		// Update last_update_started before actually starting the batch
		// in order to minimize collision risk for parallel daemon tasks
		if (count($feeds_to_update) > 0) {
			$feeds_qmarks = arr_qmarks($feeds_to_update);

			$tmph = $pdo->prepare("UPDATE ttrss_feeds SET last_update_started = NOW()
				WHERE feed_url IN ($feeds_qmarks)");
			$tmph->execute($feeds_to_update);
		}

		$nf = 0;
		$bstarted = microtime(true);

		$batch_owners = [];

		$user_query = "SELECT f.id,
				last_updated,
				f.owner_uid,
				u.login AS owner,
				f.title
			FROM ttrss_feeds f, ttrss_users u LEFT JOIN ttrss_user_prefs2 p ON
					(p.owner_uid = u.id AND profile IS NULL AND pref_name = 'DEFAULT_UPDATE_INTERVAL')
			WHERE
				f.owner_uid = u.id AND
				u.access_level NOT IN (".sprintf("%d, %d", UserHelper::ACCESS_LEVEL_DISABLED, UserHelper::ACCESS_LEVEL_READONLY).")
				AND feed_url = :feed
				$login_thresh_qpart
				$update_limit_qpart
			ORDER BY f.id $query_limit";

		Debug::log("per-user feed query: $user_query", Debug::LOG_EXTENDED);

		// since we have feed xml cached, we can deal with other feeds with the same url
		$usth = $pdo->prepare($user_query);

		foreach ($feeds_to_update as $feed) {
			Debug::log("Base feed: $feed");

			$usth->execute(["feed" => $feed]);

			if ($tline = $usth->fetch()) {
				Debug::log(sprintf("=> %s (ID: %d, U: %s [%d]), last updated: %s", $tline["title"], $tline["id"],
					$tline["owner"], $tline["owner_uid"],
					$tline["last_updated"] ?: "never"));

				if (!in_array($tline['owner_uid'], $batch_owners))
					$batch_owners[] = $tline['owner_uid'];

				$fstarted = microtime(true);

				$quiet = (isset($options["quiet"])) ? "--quiet" : "";
				$log = function_exists("flock") && isset($options['log']) ? '--log '.$options['log'] : '';
				$log_level = isset($options['log-level']) ? '--log-level '.$options['log-level'] : '';

				/* shared hosting may have this disabled and it's not strictly required */
				if (self::function_enabled('passthru')) {
					$exit_code = 0;

					passthru(Config::get(Config::PHP_EXECUTABLE) . " update.php --update-feed " . $tline["id"] . " --pidlock feed-" . $tline["id"] . " $quiet $log $log_level", $exit_code);

					Debug::log(sprintf("<= %.4f (sec) exit code: %d", microtime(true) - $fstarted, $exit_code));

					// -1 can be caused by a SIGCHLD handler which daemon master process installs (not every setup, apparently)
					if ($exit_code != 0 && $exit_code != -1) {
						$festh = $pdo->prepare("SELECT last_error FROM ttrss_feeds WHERE id = ?");
						$festh->execute([$tline["id"]]);

						if ($ferow = $festh->fetch()) {
							$error_message = $ferow["last_error"];
						} else {
							$error_message = "N/A";
						}

						Debug::log("!! Last error: $error_message");

						Logger::log(E_USER_NOTICE,
							sprintf("Update process for feed %d (%s, owner UID: %d) failed with exit code: %d (%s).",
								$tline["id"], clean($tline["title"]), $tline["owner_uid"], $exit_code, clean($error_message)));

						$combined_error_message = sprintf("Update process failed with exit code: %d (%s)",
							$exit_code, clean($error_message));

						# mark failed feed as having an update error (unless it is already marked)
						$fusth = $pdo->prepare("UPDATE ttrss_feeds SET last_error = ? WHERE id = ? AND last_error = ''");
						$fusth->execute([$combined_error_message, $tline["id"]]);
					}

				} else {
					try {
						if (!self::update_rss_feed($tline["id"], true)) {
							Logger::log(E_USER_NOTICE,
								sprintf("Update request for feed %d (%s, owner UID: %d) failed: %s.",
									$tline["id"], clean($tline["title"]), $tline["owner_uid"], clean(UrlHelper::$fetch_last_error)));
						}

						Debug::log(sprintf("<= %.4f (sec) (not using a separate process)", microtime(true) - $fstarted));

					} catch (PDOException $e) {
						Logger::log_error(E_USER_WARNING, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());

						try {
							$pdo->rollback();
						} catch (PDOException) {
							// it doesn't matter if there wasn't actually anything to rollback, PDO Exception can be
							// thrown outside of an active transaction during feed update
						}
					}
				}

				++$nf;
			}
		}

		if ($nf > 0) {
			Debug::log(sprintf("Processed %d feeds in %.4f (sec), %.4f (sec/feed avg)", $nf,
				microtime(true) - $bstarted, (microtime(true) - $bstarted) / $nf));
		}

		foreach ($batch_owners as $owner_uid) {
			Debug::log("Running housekeeping tasks for user $owner_uid...");

			self::housekeeping_user($owner_uid);
		}

		return $nf;
	}

	/** this is used when subscribing */
	static function update_basic_info(int $feed_id): void {
		$feed = ORM::for_table('ttrss_feeds')
			->select_many('id', 'owner_uid', 'feed_url', 'auth_pass', 'auth_login', 'title', 'site_url')
			->find_one($feed_id);

		if ($feed) {
			$pluginhost = new PluginHost();
			$user_plugins = Prefs::get(Prefs::_ENABLED_PLUGINS, $feed->owner_uid);

			$pluginhost->load(Config::get(Config::PLUGINS), PluginHost::KIND_ALL);
			$pluginhost->load((string)$user_plugins, PluginHost::KIND_USER, $feed->owner_uid);
			//$pluginhost->load_data();

			$feed_auth_pass_plaintext = Feeds::decrypt_feed_pass($feed->auth_pass);

			$basic_info = [];

			$pluginhost->run_hooks_callback(PluginHost::HOOK_FEED_BASIC_INFO, function ($result) use (&$basic_info) {
				if ($result && (!empty($result['title']) || !empty($result['site_url']))) {
					$basic_info = $result;
					return true;
				}
			}, $basic_info, $feed->feed_url, $feed->owner_uid, $feed_id, $feed->auth_login, $feed_auth_pass_plaintext);

			if (!$basic_info) {
				$feed_data = UrlHelper::fetch([
					'url' => $feed->feed_url,
					'login' => $feed->auth_login,
					'pass' => $feed_auth_pass_plaintext,
					'timeout' => Config::get(Config::FEED_FETCH_TIMEOUT),
				]);

				$feed_data = trim($feed_data);

				if ($feed_data) {
					$rss = new FeedParser($feed_data);

					if ($rss->init()) {
						$basic_info = [
							'title' => mb_substr(clean($rss->get_title()), 0, 199),
							'site_url' => mb_substr(UrlHelper::rewrite_relative($feed->feed_url, clean($rss->get_link())), 0, 245),
						];
					} else {
						Debug::log(sprintf("unable to parse feed for basic info: %s", $rss->error()), Debug::LOG_VERBOSE);
					}
				} else {
					Debug::log(sprintf("unable to fetch feed for basic info: %s [%s]", UrlHelper::$fetch_last_error, UrlHelper::$fetch_last_error_code), Debug::LOG_VERBOSE);
				}
			}

			if ($basic_info && is_array($basic_info)) {
				if (!empty($basic_info['title']) && (!$feed->title || $feed->title == '[Unknown]')) {
					$feed->title = $basic_info['title'];
				}

				if (!empty($basic_info['site_url']) && $feed->site_url != $basic_info['site_url']) {
					$feed->site_url = $basic_info['site_url'];
				}

				$feed->save();
			}
		}
	}

	static function update_rss_feed(int $feed, bool $no_cache = false, bool $html_output = false) : bool {

		Debug::enable_html($html_output);
		Debug::log("start", Debug::LOG_VERBOSE);

		$pdo = Db::pdo();

		/** @var DiskCache $cache */
		$cache = DiskCache::instance('feeds');

		$feed_obj = ORM::for_table('ttrss_feeds')
			->select('ttrss_feeds.*')
			->select_many_expr([
				'last_unconditional' => 'SUBSTRING_FOR_DATE(last_unconditional, 1, 19)',
				'favicon_needs_check' => "(favicon_is_custom IS NOT TRUE AND
					(favicon_last_checked IS NULL OR favicon_last_checked < NOW() - INTERVAL '12 hour'))",
			])
			->find_one($feed);

		if ($feed_obj) {
			$feed_obj->last_update_started = Db::NOW();
			$feed_obj->save();

			$feed_language = mb_strtolower($feed_obj->feed_language);

			if (!$feed_language) $feed_language = mb_strtolower(Prefs::get(Prefs::DEFAULT_SEARCH_LANGUAGE, $feed_obj->owner_uid));
			if (!$feed_language) $feed_language = 'simple';

			$user = ORM::for_table('ttrss_users')->find_one($feed_obj->owner_uid);

			if ($user) {
				if ($user->access_level == UserHelper::ACCESS_LEVEL_READONLY) {
					Debug::log("error: denied update for $feed: permission denied by owner access level");
					return false;
				}
			} else {
				// this would indicate database corruption of some kind
				Debug::log("error: owner not found for feed: $feed");
				return false;
			}

		} else {
			Debug::log("error: feeds table record not found for feed: $feed");
			return false;
		}

		// feed was batch-subscribed or something, we need to get basic info
		// this is not optimal currently as it fetches stuff separately TODO: optimize
		if ($feed_obj->title == "[Unknown]" || empty($feed_obj->title) || empty($feed_obj->site_url)) {
			Debug::log("setting basic feed info for $feed...");
			self::update_basic_info($feed);
		}

		$date_feed_processed = date('Y-m-d H:i');

		$cache_filename = sha1($feed_obj->feed_url) . ".xml";

		$pluginhost = new PluginHost();
		$user_plugins = Prefs::get(Prefs::_ENABLED_PLUGINS, $feed_obj->owner_uid);

		$pluginhost->load(Config::get(Config::PLUGINS), PluginHost::KIND_ALL);
		$pluginhost->load((string)$user_plugins, PluginHost::KIND_USER, $feed_obj->owner_uid);

		$rss_hash = false;

		$force_refetch = isset($_REQUEST["force_refetch"]);
		$dump_feed_xml = isset($_REQUEST["dump_feed_xml"]);
		$feed_data = "";

		Debug::log("running HOOK_FETCH_FEED handlers...", Debug::LOG_VERBOSE);

		$start_ts = microtime(true);
		$last_article_timestamp = 0;

		$hff_owner_uid = $feed_obj->owner_uid;
		$hff_feed_url = $feed_obj->feed_url;

		$feed_auth_pass_plaintext = Feeds::decrypt_feed_pass($feed_obj->auth_pass);

		// transparently encrypt plaintext password if possible
		if ($feed_obj->auth_pass && $feed_auth_pass_plaintext === $feed_obj->auth_pass) {
			$key = Config::get(Config::ENCRYPTION_KEY);

			if ($key) {
				Debug::log("encrypting stored plaintext feed password...", Debug::LOG_VERBOSE);

				$feed_obj->auth_pass = base64_encode(serialize(Crypt::encrypt_string($feed_auth_pass_plaintext)));
				$feed_obj->save();
			}
		}

		$pluginhost->chain_hooks_callback(PluginHost::HOOK_FETCH_FEED,
			function ($result, $plugin) use (&$feed_data, $start_ts) {
				$feed_data = $result;
				Debug::log(sprintf("=== %.4f (sec) %s", microtime(true) - $start_ts, $plugin::class), Debug::LOG_VERBOSE);
			},
			$feed_data, $hff_feed_url, $hff_owner_uid, $feed, $last_article_timestamp, $feed_obj->auth_login, $feed_auth_pass_plaintext);

		if ($feed_data) {
			Debug::log("feed data has been modified by a plugin.", Debug::LOG_VERBOSE);
		} else {
			Debug::log("feed data has not been modified by a plugin.", Debug::LOG_VERBOSE);
		}

		// try cache
		if (!$feed_data &&
			$cache->exists($cache_filename) &&
			!$feed_obj->auth_login && !$feed_obj->auth_pass &&
			$cache->get_mtime($cache_filename) > time() - 30) {

			Debug::log("using local cache: {$cache_filename}.", Debug::LOG_VERBOSE);

			$feed_data = $cache->get($cache_filename);

			if ($feed_data) {
				$rss_hash = sha1($feed_data);
			}

		} else {
			Debug::log("local cache will not be used for this feed", Debug::LOG_VERBOSE);
		}

		// fetch feed from source
		if (!$feed_data) {
			Debug::log("last unconditional update request: {$feed_obj->last_unconditional}", Debug::LOG_VERBOSE);

			if (ini_get("open_basedir") && function_exists("curl_init")) {
				Debug::log("not using CURL due to open_basedir restrictions", Debug::LOG_VERBOSE);
			}

			if (time() - strtotime($feed_obj->last_unconditional ?? "") > Config::get(Config::MAX_CONDITIONAL_INTERVAL)) {
				Debug::log("maximum allowed interval for conditional requests exceeded, forcing refetch", Debug::LOG_VERBOSE);

				$force_refetch = true;
			} else {
				Debug::log("stored last modified for conditional request: {$feed_obj->last_modified}", Debug::LOG_VERBOSE);
			}

			Debug::log("fetching {$feed_obj->feed_url} (force_refetch: $force_refetch)...", Debug::LOG_VERBOSE);

			$feed_data = UrlHelper::fetch([
				"url" => $feed_obj->feed_url,
				"login" => $feed_obj->auth_login,
				"pass" => $feed_auth_pass_plaintext,
				"timeout" => $no_cache ? Config::get(Config::FEED_FETCH_NO_CACHE_TIMEOUT) : Config::get(Config::FEED_FETCH_TIMEOUT),
				"last_modified" => $force_refetch ? "" : $feed_obj->last_modified
			]);

			$feed_data = trim($feed_data);

			Debug::log("fetch done.", Debug::LOG_VERBOSE);
			Debug::log(sprintf("effective URL (after redirects): %s (IP: %s) ", UrlHelper::$fetch_effective_url, UrlHelper::$fetch_effective_ip_addr), Debug::LOG_VERBOSE);
			Debug::log("server last modified: " . UrlHelper::$fetch_last_modified, Debug::LOG_VERBOSE);

			if ($feed_data && UrlHelper::$fetch_last_modified != $feed_obj->last_modified) {
				$feed_obj->last_modified = substr(UrlHelper::$fetch_last_modified, 0, 245);
				$feed_obj->save();
			}

			// cache vanilla feed data for re-use
			if ($feed_data && !$feed_obj->auth_pass && !$feed_obj->auth_login && $cache->is_writable()) {
				$new_rss_hash = sha1($feed_data);

				if ($new_rss_hash != $rss_hash) {
					Debug::log("saving to local cache: $cache_filename", Debug::LOG_VERBOSE);
					$cache->put($cache_filename, $feed_data);
				}
			}
		}

		if (!$feed_data) {
			Debug::log(sprintf("unable to fetch: %s [%s]", UrlHelper::$fetch_last_error, UrlHelper::$fetch_last_error_code), Debug::LOG_VERBOSE);

			// If-Modified-Since
			if (UrlHelper::$fetch_last_error_code == 304) {
				Debug::log("source claims data not modified (304), nothing to do.", Debug::LOG_VERBOSE);
				$error_message = "";

				$now = Db::NOW();

				$feed_obj->set([
					'last_error' => '',
					'last_successful_update' => $now,
					'last_updated' => $now,
				]);

				$feed_obj->save();

			} else if (UrlHelper::$fetch_last_error_code == 429) {

				// randomize interval using Config::HTTP_429_THROTTLE_INTERVAL as a base value (1-2x)
				$http_429_throttle_interval = random_int(Config::get(Config::HTTP_429_THROTTLE_INTERVAL),
					Config::get(Config::HTTP_429_THROTTLE_INTERVAL)*2);

				$error_message = UrlHelper::$fetch_last_error;

				Debug::log("source claims we're requesting too often (429), throttling updates for $http_429_throttle_interval seconds.",
					Debug::LOG_VERBOSE);

				$feed_obj->set([
					'last_error' => $error_message . " (updates throttled for $http_429_throttle_interval seconds.)",
					'last_successful_update' => Db::NOW($http_429_throttle_interval),
					'last_updated' => Db::NOW($http_429_throttle_interval),
				]);

				$feed_obj->save();
			} else {
				$error_message = UrlHelper::$fetch_last_error;

				$feed_obj->set([
					'last_error' => $error_message,
					'last_updated' => Db::NOW(),
				]);

				$feed_obj->save();
			}

			return $error_message == "";
		}

		Debug::log("running HOOK_FEED_FETCHED handlers...", Debug::LOG_VERBOSE);
		$feed_data_checksum = md5($feed_data);

		// because chain_hooks_callback() accepts variables by value
		$pff_owner_uid = $feed_obj->owner_uid;
		$pff_feed_url = $feed_obj->feed_url;

		if ($dump_feed_xml) {
			Debug::log("feed data before hooks:", Debug::LOG_VERBOSE);

			Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);
			print("<code class='feed-xml'>" . htmlspecialchars($feed_data). "</code>\n");
			Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);
		}

		$start_ts = microtime(true);
		$pluginhost->chain_hooks_callback(PluginHost::HOOK_FEED_FETCHED,
			function ($result, $plugin) use (&$feed_data, $start_ts) {
				$feed_data = $result;
				Debug::log(sprintf("=== %.4f (sec) %s", microtime(true) - $start_ts, $plugin::class), Debug::LOG_VERBOSE);
			},
			$feed_data, $pff_feed_url, $pff_owner_uid, $feed);

		if (md5($feed_data) != $feed_data_checksum) {
			Debug::log("feed data has been modified by a plugin.", Debug::LOG_VERBOSE);
		} else {
			Debug::log("feed data has not been modified by a plugin.", Debug::LOG_VERBOSE);
		}

		if ($dump_feed_xml) {
			Debug::log("feed data after hooks:", Debug::LOG_VERBOSE);

			Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);
			print("<code class='feed-xml'>" . htmlspecialchars($feed_data). "</code>\n");
			Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);
		}

		$rss = new FeedParser($feed_data);

		if ($rss->init()) {

			Debug::log("running HOOK_FEED_PARSED handlers...", Debug::LOG_VERBOSE);

			// We use local pluginhost here because we need to load different per-user feed plugins

			$start_ts = microtime(true);
			$pluginhost->chain_hooks_callback(PluginHost::HOOK_FEED_PARSED,
				function($result, $plugin) use ($start_ts) {
					Debug::log(sprintf("=== %.4f (sec) %s", microtime(true) - $start_ts, $plugin::class), Debug::LOG_VERBOSE);
				},
				$rss, $feed);

			Debug::log("language: $feed_language", Debug::LOG_VERBOSE);
			Debug::log("processing feed data...", Debug::LOG_VERBOSE);

			// this is a fallback, in case RSSUtils::update_basic_info() fails.
			// TODO: is this necessary? remove unless it is.
			if (empty($feed_obj->site_url)) {
				$feed_obj->site_url = mb_substr(UrlHelper::rewrite_relative($feed_obj->feed_url, clean($rss->get_link())), 0, 245);
				$feed_obj->save();
			}

			Debug::log("site_url: {$feed_obj->site_url}", Debug::LOG_VERBOSE);
			Debug::log("feed_title: {$rss->get_title()}", Debug::LOG_VERBOSE);

			Debug::log('favicon: needs check: ' . ($feed_obj->favicon_needs_check ? 'true' : 'false')
				. ', is custom: ' . ($feed_obj->favicon_is_custom ? 'true' : 'false')
				. ", avg color: {$feed_obj->favicon_avg_color}",
				Debug::LOG_VERBOSE);

			if ($feed_obj->favicon_needs_check || $force_refetch
				|| ($feed_obj->favicon_is_custom && !$feed_obj->favicon_avg_color)) {

				// restrict update attempts to once per 12h
				$feed_obj->favicon_last_checked = Db::NOW();
				$feed_obj->save();

				$favicon_cache = DiskCache::instance('feed-icons');

				$favicon_modified = $favicon_cache->exists($feed) ? $favicon_cache->get_mtime($feed) : -1;

				// don't try to redownload custom favicons
				if (!$feed_obj->favicon_is_custom) {
					Debug::log("favicon: trying to update favicon...", Debug::LOG_VERBOSE);
					self::update_favicon($feed_obj->site_url, $feed);

					if (!$favicon_cache->exists($feed) || $favicon_cache->get_mtime($feed) > $favicon_modified) {
						$feed_obj->favicon_avg_color = null;
						$feed_obj->save();
					}
				}

				/* terrible hack: if we crash on floicon shit here, we won't check
				 * the icon avgcolor again (unless icon got updated) */
				if (file_exists($favicon_cache->get_full_path($feed)) && function_exists("imagecreatefromstring") && empty($feed_obj->favicon_avg_color)) {
					Debug::log("favicon: trying to calculate average color...", Debug::LOG_VERBOSE);

					$feed_obj->favicon_avg_color = 'fail';
					$feed_obj->save();

					$calculated_avg_color = \Colors\calculate_avg_color($favicon_cache->get_full_path($feed));
					if ($calculated_avg_color) {
						$feed_obj->favicon_avg_color = $calculated_avg_color;
						$feed_obj->save();
					}

					Debug::log("favicon: calculated avg color: {$calculated_avg_color}, setting avg color: {$feed_obj->favicon_avg_color}", Debug::LOG_VERBOSE);

				} else if ($feed_obj->favicon_avg_color == 'fail') {
					Debug::log("floicon failed on $feed or a suitable avg color couldn't be determined, not trying to recalculate avg color", Debug::LOG_VERBOSE);
				}
			}

			Debug::log("loading filters & labels...", Debug::LOG_VERBOSE);

			$filters = self::load_filters($feed, $feed_obj->owner_uid);

			if (Debug::get_loglevel() >= Debug::LOG_EXTENDED)
				Debug::log(print_r($filters, true), Debug::LOG_VERBOSE);

			Debug::log("" . count($filters) . " filters loaded.", Debug::LOG_VERBOSE);

			$items = $rss->get_items();

			if (count($items) === 0) {
				Debug::log("no articles found.", Debug::LOG_VERBOSE);

				$now = Db::NOW();

				$feed_obj->set([
					'last_updated' => $now,
					'last_unconditional' => $now,
					'last_successful_update' => $now,
					'last_error' => '',
				]);

				$feed_obj->save();
				return true; // no articles
			}

			Debug::log("processing articles...", Debug::LOG_VERBOSE);

			$tstart = time();

			foreach ($items as $item) {
				Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);

				if (Debug::get_loglevel() >= 3)
					Debug::log(print_r($item, true), Debug::LOG_VERBOSE);

				if (ini_get("max_execution_time") > 0 && time() - $tstart >= ((float)ini_get("max_execution_time") * 0.7)) {
					Debug::log("looks like there's too many articles to process at once, breaking out.", Debug::LOG_VERBOSE);
					break;
				}

				$entry_guid = strip_tags($item->get_id());
				if (!$entry_guid) $entry_guid = strip_tags($item->get_link());
				if (!$entry_guid) $entry_guid = self::make_guid_from_title($item->get_title());

				if (!$entry_guid) {
					continue;
				}

				$entry_guid_hashed_compat = 'SHA1:' . sha1("{$feed_obj->owner_uid},$entry_guid");
				$entry_guid_hashed = json_encode(["ver" => 2, "uid" => $feed_obj->owner_uid, "hash" => 'SHA1:' . sha1($entry_guid)]);
				$entry_guid = "$feed_obj->owner_uid,$entry_guid";

				Debug::log("guid $entry_guid (hash: $entry_guid_hashed compat: $entry_guid_hashed_compat)", Debug::LOG_VERBOSE);

				$entry_timestamp = (int)$item->get_date();

				Debug::log(sprintf("orig date: %s (%s)", $item->get_date(), date("Y-m-d H:i:s", $item->get_date())),
					Debug::LOG_VERBOSE);

				$entry_title = strip_tags($item->get_title());

				$entry_link = UrlHelper::rewrite_relative($feed_obj->site_url, clean($item->get_link()), "a", "href");

				$entry_language = mb_substr(trim($item->get_language()), 0, 2);

				Debug::log("title $entry_title", Debug::LOG_VERBOSE);
				Debug::log("link $entry_link", Debug::LOG_VERBOSE);
				Debug::log("language $entry_language", Debug::LOG_VERBOSE);

				if (!$entry_title) $entry_title = date("Y-m-d H:i:s", $entry_timestamp);;

				$entry_content = $item->get_content();
				if (!$entry_content) $entry_content = $item->get_description();

				if (Debug::get_loglevel() >= 3) {
					print "content: ";
					print htmlspecialchars($entry_content);
					print "\n";
				}

				$entry_comments = mb_substr(strip_tags($item->get_comments_url()), 0, 245);
				$num_comments = $item->get_comments_count();

				$entry_author = strip_tags($item->get_author());
				$entry_guid = mb_substr($entry_guid, 0, 245);

				Debug::log("author $entry_author", Debug::LOG_VERBOSE);
				Debug::log("looking for tags...", Debug::LOG_VERBOSE);

				$entry_tags = $item->get_categories();
				Debug::log("tags found: " . join(", ", $entry_tags), Debug::LOG_VERBOSE);

				Debug::log("done collecting data.", Debug::LOG_VERBOSE);

				$sth = $pdo->prepare("SELECT id, content_hash, lang FROM ttrss_entries
					WHERE guid IN (?, ?, ?)");
				$sth->execute([$entry_guid, $entry_guid_hashed, $entry_guid_hashed_compat]);

				if ($row = $sth->fetch()) {
					$base_entry_id = $row["id"];
					$entry_stored_hash = $row["content_hash"];
					$article_labels = Article::_get_labels($base_entry_id, $feed_obj->owner_uid);

					$existing_tags = Article::_get_tags($base_entry_id, $feed_obj->owner_uid);
					$entry_tags = array_unique([...$entry_tags, ...$existing_tags]);
				} else {
					$base_entry_id = false;
					$entry_stored_hash = "";
					$article_labels = [];
				}

				Debug::log("looking for enclosures...", Debug::LOG_VERBOSE);

				// enclosures

				/** @var array<int, FeedEnclosure> */
				$enclosures = [];

				$encs = $item->get_enclosures();

				foreach ($encs as $e) {
					$pluginhost->chain_hooks_callback(PluginHost::HOOK_ENCLOSURE_IMPORTED,
						function ($result) use (&$e) {
							$e = $result;
						},
						$e, $feed);

					$e->link = UrlHelper::rewrite_relative($feed_obj->site_url, $e->link, "", "", $e->type);

					if (!$e->link) {
						Debug::log('Skipping enclosure whose link failed validation.', Debug::LOG_VERBOSE);
						continue;
					}

					$enclosures[] = $e;
				}

				$article = ["owner_uid" => $feed_obj->owner_uid, // read only
					"guid" => $entry_guid, // read only
					"guid_hashed" => $entry_guid_hashed, // read only
					"title" => $entry_title,
					"content" => $entry_content,
					"link" => $entry_link,
					"labels" => $article_labels, // current limitation: can add labels to article, can't remove them
					"tags" => $entry_tags,
					"author" => $entry_author,
					"force_catchup" => false, // ugly hack for the time being
					"score_modifier" => 0, // no previous value, plugin should recalculate score modifier based on content if needed
					"language" => $entry_language,
					"timestamp" => $entry_timestamp,
					"num_comments" => $num_comments,
					"enclosures" => $enclosures,
					"feed" => ["id" => $feed,
						"fetch_url" => $feed_obj->feed_url,
						"site_url" => $feed_obj->site_url,
						"cache_images" => $feed_obj->cache_images]
				];

				$entry_plugin_data = "";
				$entry_current_hash = self::calculate_article_hash($article, $pluginhost);

				Debug::log("article hash: $entry_current_hash [stored=$entry_stored_hash]", Debug::LOG_VERBOSE);

				if ($entry_current_hash == $entry_stored_hash && !isset($_REQUEST["force_rehash"])) {
					Debug::log("stored article seems up to date [IID: $base_entry_id], updating timestamp only.", Debug::LOG_VERBOSE);

					// we keep encountering the entry in feeds, so we need to
					// update date_updated column so that we don't get horrible
					// dupes when the entry gets purged and reinserted again e.g.
					// in the case of SLOW SLOW OMG SLOW updating feeds

					ORM::for_table('ttrss_entries')
						->find_one($base_entry_id)
						->set('date_updated', Db::NOW())
						->save();

					continue;
				}

				Debug::log("hash differs, running HOOK_ARTICLE_FILTER handlers...", Debug::LOG_VERBOSE);

				$start_ts = microtime(true);

				$pluginhost->chain_hooks_callback(PluginHost::HOOK_ARTICLE_FILTER,
					function ($result, $plugin) use (&$article, &$entry_plugin_data, $start_ts) {
						$article = $result;

						$entry_plugin_data .= mb_strtolower($plugin::class) . ",";

						Debug::log(sprintf("=== %.4f (sec) %s", microtime(true) - $start_ts, $plugin::class),
							Debug::LOG_VERBOSE);
					},
					$article);

				if (Debug::get_loglevel() >= 3) {
					print "processed content: ";
					print htmlspecialchars($article["content"]);
					print "\n";
				}

				Debug::log("plugin data: {$entry_plugin_data}", Debug::LOG_VERBOSE);

				/* Collect article tags here so we could filter by them: */

				$matched_rules = [];
				$matched_filters = [];

				$article_filter_actions = self::eval_article_filters($filters, $article["title"],
					$article["content"], $article["link"], $article["author"],
					$article["tags"], $matched_rules, $matched_filters);

				$pluginhost->run_hooks(PluginHost::HOOK_FILTER_TRIGGERED,
					$feed, $feed_obj->owner_uid, $article, $matched_filters, $matched_rules, $article_filter_actions);

				$matched_filter_ids = array_map(fn(array $f) => $f['id'], $matched_filters);

				if (count($matched_filter_ids) > 0) {
					$filter_objs = ORM::for_table('ttrss_filters2')
						->where('owner_uid', $feed_obj->owner_uid)
						->where_in('id', $matched_filter_ids)
						->find_many();

					foreach ($filter_objs as $filter_obj) {
						$filter_obj->set('last_triggered', Db::NOW());
						$filter_obj->save();
					}
				}

				if (Debug::get_loglevel() >= Debug::LOG_EXTENDED) {
					Debug::log("matched filters: ", Debug::LOG_VERBOSE);

					if (count($matched_filters) != 0)
						Debug::log(print_r($matched_filters, true), Debug::LOG_VERBOSE);

					Debug::log("matched filter rules: ", Debug::LOG_VERBOSE);

					if (count($matched_rules) != 0)
						Debug::log(print_r($matched_rules, true), Debug::LOG_VERBOSE);

					Debug::log("filter actions: ", Debug::LOG_VERBOSE);

					if (count($article_filter_actions) != 0)
						Debug::log(print_r($article_filter_actions, true), Debug::LOG_VERBOSE);
				}

				// filter actions of type 'plugin' sourced from filters that matched the article
				$plugin_filter_actions = self::find_article_filter_actions($article_filter_actions, "plugin");

				// the actual set of available plugin actions registered via PluginHost#add_filter_action()
				$pluginhost_filter_actions = $pluginhost->get_filter_actions();

				if (count($plugin_filter_actions) > 0) {
					Debug::log("applying plugin filter actions...", Debug::LOG_VERBOSE);

					foreach ($plugin_filter_actions as $pfa) {
						[$pfclass, $pfaction] = explode(":", $pfa["param"]);

						if (isset($pluginhost_filter_actions[$pfclass])) {
							$plugin = $pluginhost->get_plugin($pfclass);

							Debug::log("... $pfclass: $pfaction", Debug::LOG_VERBOSE);

							if ($plugin) {
								$start = microtime(true);
								$article = $plugin->hook_article_filter_action($article, $pfaction);

								Debug::log(sprintf("=== %.4f (sec)", microtime(true) - $start), Debug::LOG_VERBOSE);
							} else {
								Debug::log("??? $pfclass: plugin object not found.", Debug::LOG_VERBOSE);
							}
						} else {
							Debug::log("??? $pfclass: filter plugin not registered.", Debug::LOG_VERBOSE);
						}
					}
				}

				$entry_tags = $article["tags"];
				$entry_title = strip_tags($article["title"]);
				$entry_author = mb_substr(strip_tags($article["author"]), 0, 245);
				$entry_link = strip_tags($article["link"]);
				$entry_content = $article["content"]; // escaped below
				$entry_force_catchup = $article["force_catchup"];
				$article_labels = $article["labels"];
				$entry_score_modifier = (int) $article["score_modifier"];
				$entry_language = $article["language"];
				$entry_timestamp = $article["timestamp"];
				$num_comments = $article["num_comments"];
				/** @var array<int, FeedEnclosure> */
				$enclosures = $article["enclosures"];

				if ($entry_timestamp == -1 || !$entry_timestamp || $entry_timestamp > time()) {
					$entry_timestamp = time();
				}

				$entry_timestamp_fmt = date("Y/m/d H:i:s", $entry_timestamp);

				Debug::log("date: $entry_timestamp ($entry_timestamp_fmt)", Debug::LOG_VERBOSE);
				Debug::log("num_comments: $num_comments", Debug::LOG_VERBOSE);

				if (Debug::get_loglevel() >= Debug::LOG_EXTENDED) {
					Debug::log("article labels:", Debug::LOG_VERBOSE);

					if (count($article_labels) != 0)
						Debug::log(print_r($article_labels, true), Debug::LOG_VERBOSE);
				}

				Debug::log("force catchup: $entry_force_catchup", Debug::LOG_VERBOSE);

				if ($feed_obj->cache_images)
					self::cache_media($entry_content, $feed_obj->site_url);

				$csth = $pdo->prepare("SELECT id FROM ttrss_entries
					WHERE guid IN (?, ?, ?)");
				$csth->execute([$entry_guid, $entry_guid_hashed, $entry_guid_hashed_compat]);

				if ($row = $csth->fetch()) {
					Debug::log("select returned RID: " . $row['id'], Debug::LOG_VERBOSE);
					$base_record_created = false;

				} else {
					Debug::log("base guid [$entry_guid or $entry_guid_hashed] not found, creating...", Debug::LOG_VERBOSE);

					// base post entry does not exist, create it
					$isth = $pdo->prepare(
						"INSERT INTO ttrss_entries
							(title,
							tsvector_combined,
							guid,
							link,
							updated,
							content,
							content_hash,
							no_orig_date,
							date_updated,
							date_entered,
							comments,
							num_comments,
							plugin_data,
							lang,
							author)
						VALUES
							(:title,
							to_tsvector(:ts_lang, :ts_content),
							:guid,
							:link,
							:updated,
							:content,
							:content_hash,
							false,
							NOW(),
							:date_entered,
							:comments,
							:num_comments,
							:plugin_data,
							:lang,
							:author) RETURNING id");

						$isth->execute([":title" => $entry_title,
							":ts_lang" => $feed_language,
							":ts_content" => mb_substr(strip_tags($entry_title) . " " . \Soundasleep\Html2Text::convert($entry_content), 0, 900000),
							":guid" => $entry_guid_hashed,
							":link" => $entry_link,
							":updated" => $entry_timestamp_fmt,
							":content" => $entry_content,
							":content_hash" => $entry_current_hash,
							":date_entered" => $date_feed_processed,
							":comments" => $entry_comments,
							":num_comments" => (int)$num_comments,
							":plugin_data" => $entry_plugin_data,
							":lang" => $entry_language,
							":author" => $entry_author]);

						$row = $isth->fetch();

						Debug::log("insert returned RID: " . $row['id'], Debug::LOG_VERBOSE);
						$base_record_created = true;
					}

				$entry_ref_id = 0;
				$entry_int_id = 0;

				if ($row['id']) {

					Debug::log("base record with RID: " . $row['id'] . " found, checking for user record", Debug::LOG_VERBOSE);

					$ref_id = $row['id'];
					$entry_ref_id = $ref_id;

					if (self::has_article_filter_action($article_filter_actions, "filter")) {
						Debug::log("article is filtered out, nothing to do.", Debug::LOG_VERBOSE);
						continue;
					}

					$score = self::calculate_article_score($article_filter_actions) + $entry_score_modifier;

					Debug::log("initial score: $score [including plugin modifier: $entry_score_modifier]", Debug::LOG_VERBOSE);

					Debug::log("begin pdo transaction...", Debug::LOG_VERBOSE);
					$pdo->beginTransaction();

					// check for user post link to main table
					$sth = $pdo->prepare("SELECT ref_id, int_id FROM ttrss_user_entries WHERE
							ref_id = ? AND owner_uid = ?");
					$sth->execute([$ref_id, $feed_obj->owner_uid]);

					// okay it doesn't exist - create user entry
					if ($row = $sth->fetch()) {
						$entry_ref_id = $row["ref_id"];
						$entry_int_id = $row["int_id"];

						Debug::log("user record FOUND: RID: $entry_ref_id, IID: $entry_int_id", Debug::LOG_VERBOSE);
					} else {

						Debug::log("user record not found, creating...", Debug::LOG_VERBOSE);

						if ($score >= -500 && !self::has_article_filter_action($article_filter_actions, 'catchup') && !$entry_force_catchup) {
							$unread = 1;
							$last_read_qpart = null;
						} else {
							$unread = 0;
							$last_read_qpart = date("Y-m-d H:i"); // we can't use NOW() here because it gets quoted
						}

						if (self::has_article_filter_action($article_filter_actions, 'mark') || $score > 1000) {
							$marked = 1;
						} else {
							$marked = 0;
						}

						if (self::has_article_filter_action($article_filter_actions, 'publish')) {
							$published = 1;
						} else {
							$published = 0;
						}

						$last_marked = ($marked == 1) ? 'NOW()' : 'NULL';
						$last_published = ($published == 1) ? 'NOW()' : 'NULL';

						$sth = $pdo->prepare(
							"INSERT INTO ttrss_user_entries
								(ref_id, owner_uid, feed_id, unread, last_read, marked,
								published, score, tag_cache, label_cache, uuid,
								last_marked, last_published)
							VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', '', ".$last_marked.", ".$last_published.")
							RETURNING int_id");

						$sth->execute([$ref_id, $feed_obj->owner_uid, $feed, $unread, $last_read_qpart, $marked,
							$published, $score]);

						if ($row = $sth->fetch())
							$entry_int_id = $row['int_id'];

						if ($marked)
							PluginHost::getInstance()->run_hooks(PluginHost::HOOK_ARTICLES_MARK_TOGGLED, [$ref_id]);

						if ($published)
							PluginHost::getInstance()->run_hooks(PluginHost::HOOK_ARTICLES_PUBLISH_TOGGLED, [$ref_id]);
					}

					Debug::log("resulting RID: $entry_ref_id, IID: $entry_int_id", Debug::LOG_VERBOSE);

					// it's pointless to update base record we've just created
					if (!$base_record_created) {
						$sth = $pdo->prepare('UPDATE ttrss_entries
							SET title = :title,
								tsvector_combined = to_tsvector(:ts_lang, :ts_content),
								content = :content,
								content_hash = :content_hash,
								updated = :updated,
								date_updated = NOW(),
								num_comments = :num_comments,
								plugin_data = :plugin_data,
								author = :author,
								lang = :lang
							WHERE id = :id');

						$sth->execute([
							':title' => $entry_title,
							':content' => "$entry_content",
							':content_hash' => $entry_current_hash,
							':updated' => $entry_timestamp_fmt,
							':num_comments' => (int)$num_comments,
							':plugin_data' => $entry_plugin_data,
							':author' => "$entry_author",
							':lang' => $entry_language,
							':id' => $ref_id,
							':ts_lang' => $feed_language,
							':ts_content' => mb_substr(strip_tags($entry_title) . ' ' . \Soundasleep\Html2Text::convert($entry_content), 0, 900000),
						]);
					}

					// update aux data
					$sth = $pdo->prepare("UPDATE ttrss_user_entries
							SET score = ? WHERE ref_id = ?");
					$sth->execute([$score, $ref_id]);

					if ($feed_obj->mark_unread_on_update &&
						!$entry_force_catchup &&
						!self::has_article_filter_action($article_filter_actions, 'catchup')) {

						Debug::log("article updated, marking unread as requested.", Debug::LOG_VERBOSE);

						$sth = $pdo->prepare("UPDATE ttrss_user_entries
							SET last_read = null, unread = true WHERE ref_id = ?");
						$sth->execute([$ref_id]);
					} else {
						Debug::log("article updated, but we're forbidden to mark it unread.", Debug::LOG_VERBOSE);
					}
				}

				Debug::log("assigning labels [other]...", Debug::LOG_VERBOSE);

				foreach ($article_labels as $label) {
					Labels::add_article($entry_ref_id, $label[1], $feed_obj->owner_uid);
				}

				Debug::log("assigning labels [filters]...", Debug::LOG_VERBOSE);

				self::assign_article_to_label_filters($entry_ref_id, $article_filter_actions,
					$feed_obj->owner_uid, $article_labels);

				if ($feed_obj->cache_images)
					self::cache_enclosures($enclosures, $feed_obj->site_url);

				if (Debug::get_loglevel() >= Debug::LOG_EXTENDED) {
					Debug::log("article enclosures:", Debug::LOG_VERBOSE);
					Debug::log(print_r($enclosures, true), Debug::LOG_VERBOSE);
				}

				$esth = $pdo->prepare("SELECT id FROM ttrss_enclosures
						WHERE content_url = ? AND content_type = ? AND post_id = ?");

				$usth = $pdo->prepare("INSERT INTO ttrss_enclosures
							(content_url, content_type, title, duration, post_id, width, height) VALUES
							(?, ?, ?, ?, ?, ?, ?)");

				foreach ($enclosures as $enc) {
					$esth->execute([$enc->link, $enc->type, $entry_ref_id]);

					if (!$esth->fetch()) {
						$usth->execute([$enc->link, $enc->type, (string)$enc->title, (int)$enc->length, $entry_ref_id, (int)$enc->width, (int)$enc->height]);
					}
				}

				// check for manual tags (we have to do it here since they're loaded from filters)
				foreach ($article_filter_actions as $fa) {
					if ($fa["type"] == "tag") {
						$entry_tags = [...$entry_tags, ...FeedItem_Common::normalize_categories(explode(",", $fa["param"]))];
					}
				}

				// like boring tags, but filter-based
				foreach ($article_filter_actions as $fa) {
					if ($fa["type"] == "ignore-tag") {
						$entry_tags = array_diff($entry_tags,
							FeedItem_Common::normalize_categories(explode(",", $fa["param"])));
					}
				}

				// Skip boring tags
				$entry_tags = FeedItem_Common::normalize_categories(
						array_diff($entry_tags,
							FeedItem_Common::normalize_categories(explode(",",
								Prefs::get(Prefs::BLACKLISTED_TAGS, $feed_obj->owner_uid)))));

				Debug::log("resulting article tags: " . implode(", ", $entry_tags), Debug::LOG_VERBOSE);

				// Save article tags in the database
				if (count($entry_tags) > 0) {

					$tsth = $pdo->prepare("SELECT id FROM ttrss_tags
							WHERE tag_name = ? AND post_int_id = ? AND
							owner_uid = ? LIMIT 1");

					$usth = $pdo->prepare("INSERT INTO ttrss_tags
									(owner_uid,tag_name,post_int_id)
									VALUES (?, ?, ?)");

					foreach ($entry_tags as $tag) {
						$tsth->execute([$tag, $entry_int_id, $feed_obj->owner_uid]);

						if (!$tsth->fetch()) {
							$usth->execute([$feed_obj->owner_uid, $tag, $entry_int_id]);
						}
					}

					/* update the cache */

					$tsth = $pdo->prepare("UPDATE ttrss_user_entries
						SET tag_cache = ? WHERE ref_id = ?
						AND owner_uid = ?");

					$tsth->execute([
						join(",", $entry_tags),
						$entry_ref_id,
						$feed_obj->owner_uid
					]);
				}

				Debug::log("commit pdo transaction...", Debug::LOG_VERBOSE);
				$pdo->commit();

				Debug::log("article processed.", Debug::LOG_VERBOSE);
			}

			Debug::log(Debug::SEPARATOR, Debug::LOG_VERBOSE);

			Debug::log("purging feed...", Debug::LOG_VERBOSE);

			Feeds::_purge($feed, 0);

			$now = Db::NOW();

			$feed_obj->set([
				'last_updated' => $now,
				'last_unconditional' => $now,
				'last_successful_update' => $now,
				'last_error' => '',
			]);

			$feed_obj->save();

		} else {

			$error_msg = mb_substr($rss->error(), 0, 245);

			Debug::log("fetch error: $error_msg", Debug::LOG_VERBOSE);

			if (count($rss->errors()) > 1) {
				foreach ($rss->errors() as $error) {
					Debug::log("+ $error", Debug::LOG_VERBOSE);
				}
			}

			$now = Db::NOW();

			$feed_obj->set([
				'last_updated' => $now,
				'last_unconditional' => $now,
				'last_error' => $error_msg,
			]);

			$feed_obj->save();

			unset($rss);

			Debug::log("update failed.", Debug::LOG_VERBOSE);
			return false;
		}

		Debug::log("update done.", Debug::LOG_VERBOSE);
		return true;
	}

	/**
	 * TODO: move to DiskCache?
	 *
	 * @param array<int, FeedEnclosure> $enclosures
	 * @see RSSUtils::update_rss_feed()
	 * @see FeedEnclosure
	 */
	static function cache_enclosures(array $enclosures, string $site_url): void {
		$cache = DiskCache::instance("images");

		if ($cache->is_writable()) {
			foreach ($enclosures as $enc) {

				if (preg_match("/(image|audio|video)/", $enc->type)) {
					$src = UrlHelper::rewrite_relative($site_url, $enc->link);

					$local_filename = sha1($src);

					Debug::log("cache_enclosures: downloading: $src to $local_filename", Debug::LOG_VERBOSE);

					if (!$cache->exists($local_filename)) {
						$file_content = UrlHelper::fetch(["url" => $src,
							"http_referrer" => $src,
							"max_size" => Config::get(Config::MAX_CACHE_FILE_SIZE)]);

						if ($file_content) {
							$cache->put($local_filename, $file_content);
						} else {
							Debug::log("cache_enclosures: failed with ".UrlHelper::$fetch_last_error_code.": ".UrlHelper::$fetch_last_error);
						}
					}
				}
			}
		}
	}

	/* TODO: move to DiskCache? */
	static function cache_media_url(DiskCache $cache, string $url, string $site_url): void {
		$url = UrlHelper::rewrite_relative($site_url, $url);
		$local_filename = sha1($url);

		Debug::log("cache_media: checking $url", Debug::LOG_VERBOSE);

		if (!$cache->exists($local_filename)) {
			Debug::log("cache_media: downloading: $url to $local_filename", Debug::LOG_VERBOSE);

			$file_content = UrlHelper::fetch(["url" => $url,
				"http_referrer" => $url,
				"max_size" => Config::get(Config::MAX_CACHE_FILE_SIZE)]);

			if ($file_content) {
				$cache->put($local_filename, $file_content);
			} else {
				Debug::log("cache_media: failed with ".UrlHelper::$fetch_last_error_code.": ".UrlHelper::$fetch_last_error);
			}
		}
	}

	/* TODO: move to DiskCache? */
	static function cache_media(string $html, string $site_url): void {
		$cache = DiskCache::instance("images");

		if ($html && $cache->is_writable()) {
			$doc = new DOMDocument();
			if (@$doc->loadHTML($html)) {
				$xpath = new DOMXPath($doc);

				$entries = $xpath->query('(//img[@src]|//source[@src|@srcset]|//video[@poster|@src])');

				/** @var DOMElement $entry */
				foreach ($entries as $entry) {
					foreach (['src', 'poster'] as $attr) {
						if ($entry->hasAttribute($attr) && !str_starts_with($entry->getAttribute($attr), "data:")) {
							self::cache_media_url($cache, $entry->getAttribute($attr), $site_url);
						}
					}

					if ($entry->hasAttribute("srcset")) {
						$matches = self::decode_srcset($entry->getAttribute('srcset'));

						for ($i = 0; $i < count($matches); $i++) {
							self::cache_media_url($cache, $matches[$i]["url"], $site_url);
						}
					}
				}
			}
		}
	}

	static function expire_error_log(): void {
		Debug::log("Removing old error log entries...");
		$pdo = Db::pdo();
		$pdo->query("DELETE FROM ttrss_error_log WHERE created_at < NOW() - INTERVAL '1 week'");
	}

	static function expire_lock_files(): void {
		Debug::log("Removing old lock files...", Debug::LOG_VERBOSE);

		$num_deleted = 0;

		if (is_writable(Config::get(Config::LOCK_DIRECTORY))) {
			$files = glob(Config::get(Config::LOCK_DIRECTORY) . "/*.lock");

			if ($files) {
				foreach ($files as $file) {
					if (!file_is_locked(basename($file)) && time() - filemtime($file) > 86400*2) {
						unlink($file);
						++$num_deleted;
					}
				}
			}
		}

		Debug::log("Removed $num_deleted old lock files.");
	}

	/**
	 * Evaluate filter rules against an article.
	 *
	 * @param array<int, array<string, mixed>> $filters
	 * @param array<int, string> $tags
	 * @param array<int, array<string, mixed>>|null &$matched_rules An array of the last rule from each matching filter, otherwise null (default) or the original value
	 * @param array<int, array<string, mixed>>|null &$matched_filters An array of the matching filters, otherwise null (default) or the original value
	 *
	 * @return array<int, array{'type': string, 'param': string}> An array of filter actions from matched filters
	 */
	static function eval_article_filters(array $filters, string $title, string $content, string $link, string $author, array $tags, ?array &$matched_rules = null, ?array &$matched_filters = null): array {
		$matches = [];

		foreach ($filters as $filter) {
			$match_any_rule = $filter["match_any_rule"] ?? false;
			$inverse = $filter["inverse"] ?? false;
			$filter_match = false;
			$last_processed_rule = false;
			$regexp_matches = [];

			/** @var array{reg_exp: string, type: string, inverse: bool} $rule */
			foreach ($filter["rules"] as $rule) {
				$match = false;
				$reg_exp = str_replace('/', '\/', (string)$rule["reg_exp"]);
				$reg_exp = str_replace("\n", "", $reg_exp); // reg_exp may be formatted with CRs now because of textarea, we need to strip those
				$last_processed_rule = $rule;

				if (empty($reg_exp))
					continue;

				switch ($rule["type"]) {
					case "title":
						$match = @preg_match("/$reg_exp/iu", $title, $regexp_matches);
						break;
					case "content":
						// we don't need to deal with multiline regexps
						$content = (string)preg_replace("/[\r\n\t]/", "", $content);

						$match = @preg_match("/$reg_exp/iu", $content, $regexp_matches);
						break;
					case "both":
						// we don't need to deal with multiline regexps
						$content = (string)preg_replace("/[\r\n\t]/", "", $content);

						$match = (@preg_match("/$reg_exp/iu", $title, $regexp_matches) || @preg_match("/$reg_exp/iu", $content, $regexp_matches));
						break;
					case "link":
						$match = @preg_match("/$reg_exp/iu", $link, $regexp_matches);
						break;
					case "author":
						$match = @preg_match("/$reg_exp/iu", $author, $regexp_matches);
						break;
					case "tag":
						if (count($tags) == 0)
							$tags[] = ''; // allow matching if there are no tags

						foreach ($tags as $tag) {
							if (@preg_match("/$reg_exp/iu", $tag, $regexp_matches)) {
								$match = true;
								break;
							}
						}
						break;
				}

				if ($rule['inverse'])
					$match = !$match;

				if ($match_any_rule) {
					if ($match) {
						$filter_match = true;
						break;
					}
				} else {
					$filter_match = $match;
					if (!$match) {
						break;
					}
				}
			}

			if ($inverse) $filter_match = !$filter_match;

			if ($filter_match) {
				$last_processed_rule["regexp_matches"] = $regexp_matches;

				if (is_array($matched_rules))
					$matched_rules[] = $last_processed_rule;

				if (is_array($matched_filters))
					$matched_filters[] = $filter;

				foreach ($filter['actions'] as $action) {
					$matches[] = $action;

					// if Stop action encountered, perform no further processing
					if (isset($action['type']) && $action['type'] == 'stop')
						return $matches;
				}
			}
		}

		return $matches;
	}

	/**
	 * @param array<int, array{'type': string, 'param': string}> $filter_actions An array of all filter actions from filters that matched an article
	 *
	 * @return bool Whether a filter action of type $filter_action_type exists
	 */
	static function has_article_filter_action(array $filter_actions, string $filter_action_type): bool {
		foreach ($filter_actions as $fa) {
			if ($fa["type"] == $filter_action_type) {
				return true;
			};
		}
		return false;
	}

	/**
	 * @param array<int, array{'type': string, 'param': string}> $filter_actions An array of all filter actions from filters that matched an article
	 *
	 * @return array<int, array{'type': string, 'param': string}> An array of filter actions of type $filter_action_type
	 */
	static function find_article_filter_actions(array $filter_actions, string $filter_action_type): array {
		return array_filter($filter_actions, fn(array $fa) => $fa['type'] === $filter_action_type);
	}

	/**
	 * @param array<int, array{'type': string, 'param': string}> $filter_actions An array of all filter actions from filters that matched an article
	 */
	static function calculate_article_score(array $filter_actions): int {
		$score = 0;

		foreach ($filter_actions as $fa) {
			if ($fa["type"] == "score") {
				$score += $fa["param"];
			};
		}
		return $score;
	}

	/**
	 * @param array<int, array<int, int|string>> $labels An array of label arrays like [int $feed_id, string $caption, string $fg_color, string $bg_color]
	 *
	 * @see Article::_get_labels()
	 */
	static function labels_contains_caption(array $labels, string $caption): bool {
		foreach ($labels as $label) {
			if ($label[1] == $caption) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, array{'type': string, 'param': string}> $filter_actions An array of filter actions from matched filters
	 * @param array<int, array<int, int|string>> $article_labels An array of label arrays like [int $feed_id, string $caption, string $fg_color, string $bg_color]
	 */
	static function assign_article_to_label_filters(int $id, array $filter_actions, int $owner_uid, $article_labels): void {
		foreach ($filter_actions as $fa) {
			if ($fa["type"] == "label") {
				if (!self::labels_contains_caption($article_labels, $fa["param"])) {
					Labels::add_article($id, $fa["param"], $owner_uid);
				}
			}
		}
	}

	static function make_guid_from_title(string $title): ?string {
		return preg_replace("/[ \"\',.:;]/", "-",
			mb_strtolower(strip_tags($title), 'utf-8'));
	}

	static function disable_failed_feeds(): void {
		if (Config::get(Config::DAEMON_UNSUCCESSFUL_DAYS_LIMIT) > 0) {

			$pdo = Db::pdo();

			$pdo->beginTransaction();

			$failing_feeds_qpart = "update_interval != -1 AND last_successful_update IS NOT NULL "
				. "AND last_successful_update < NOW() - INTERVAL '" . Config::get(Config::DAEMON_UNSUCCESSFUL_DAYS_LIMIT) . " day' "
				. "AND last_updated > NOW() - INTERVAL '1 day'";

			$sth = $pdo->prepare("SELECT id, title, owner_uid FROM ttrss_feeds WHERE $failing_feeds_qpart");
			$sth->execute();

			while ($row = $sth->fetch()) {
				Logger::log(E_USER_NOTICE,
					sprintf("Auto disabling feed %d (%s, UID: %d) because it failed to update for %d days.",
						$row["id"], clean($row["title"]), $row["owner_uid"], Config::get(Config::DAEMON_UNSUCCESSFUL_DAYS_LIMIT)));

				Debug::log(sprintf("Auto-disabling feed %d (%s) (failed to update for %d days).", $row["id"],
					clean($row["title"]), Config::get(Config::DAEMON_UNSUCCESSFUL_DAYS_LIMIT)));
			}

			$sth = $pdo->prepare("UPDATE ttrss_feeds SET update_interval = -1 WHERE $failing_feeds_qpart");
			$sth->execute();

			$pdo->commit();
		}
	}

	static function housekeeping_user(int $owner_uid): void {
		$tmph = new PluginHost();

		UserHelper::load_user_plugins($owner_uid, $tmph);

		$tmph->run_due_tasks();
		$tmph->run_hooks(PluginHost::HOOK_HOUSE_KEEPING);
	}

	/** Init all system tasks which are run periodically by updater in housekeeping_common() */
	static function init_housekeeping_tasks() : void {
		Debug::log('Registering scheduled tasks for housekeeping...');

		$scheduler = Scheduler::getInstance();

		$scheduler->add_scheduled_task('purge_orphans', Config::get(Config::SCHEDULE_PURGE_ORPHANS),
			function() {
				Article::_purge_orphans();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('disk_cache_expire_all', Config::get(Config::SCHEDULE_DISK_CACHE_EXPIRE_ALL),
			function() {
				$cache = DiskCache::instance("");
				$cache->expire_all();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('disable_failed_feeds', Config::get(Config::SCHEDULE_DISABLE_FAILED_FEEDS),
			function() {
				self::disable_failed_feeds();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('cleanup_feed_icons', Config::get(Config::SCHEDULE_CLEANUP_FEED_ICONS),
			function() {
				self::cleanup_feed_icons();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('log_daemon_update_login_limit_users', Config::get(Config::SCHEDULE_LOG_DAEMON_UPDATE_LOGIN_LIMIT_USERS),
			function() {
				$login_limit = Config::get(Config::DAEMON_UPDATE_LOGIN_LIMIT);

				if (!Config::get(Config::SINGLE_USER_MODE) && $login_limit > 0) {
					$not_logged_in_users = ORM::for_table('ttrss_users')
						->select_many('login', 'last_login')
						->where_not_in('access_level', [UserHelper::ACCESS_LEVEL_DISABLED, UserHelper::ACCESS_LEVEL_READONLY])
						->where_raw("last_login < NOW() - INTERVAL '$login_limit days'")
						->find_many();

					if (count($not_logged_in_users) > 0) {
						Debug::log("Feeds will not be updated for these users because of DAEMON_UPDATE_LOGIN_LIMIT check ({$login_limit} days):");
						foreach ($not_logged_in_users as $user) {
							Debug::log("=> {$user->login}, last logged in: {$user->last_login}");
						}
					}
				}

				return 0;
			}
		);

		$scheduler->add_scheduled_task('expire_error_log', Config::get(Config::SCHEDULE_EXPIRE_ERROR_LOG),
			function() {
				self::expire_error_log();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('expire_lock_files', Config::get(Config::SCHEDULE_EXPIRE_LOCK_FILES),
			function() {
				self::expire_lock_files();

				return 0;
			}
		);

		$scheduler->add_scheduled_task('send_headlines_digests', Config::get(Config::SCHEDULE_SEND_HEADLINES_DIGESTS),
			function() {
				Digest::send_headlines_digests();

				return 0;
			}
		);
	}

	static function housekeeping_common(): void {
		Scheduler::getInstance()->run_due_tasks();

		$pluginhost = PluginHost::getInstance();

		$pluginhost->run_due_tasks();
		$pluginhost->run_hooks(PluginHost::HOOK_HOUSE_KEEPING);
	}

	static function update_favicon(string $site_url, int $feed): false|string {
		$favicon_urls = self::get_favicon_urls($site_url);

		if (count($favicon_urls) == 0) {
			Debug::log("favicon: couldn't find any favicon URLs for $site_url", Debug::LOG_VERBOSE);
			return false;
		}

		// i guess we'll have to go through all of them until something looks valid...
		foreach ($favicon_urls as $favicon_url) {

			// Limiting to "image" type misses those served with text/plain
			$contents = UrlHelper::fetch([
				'url' => $favicon_url,
				'max_size' => Config::get(Config::MAX_FAVICON_FILE_SIZE),
				//'type' => 'image',
			]);

			if (!$contents) {
				Debug::log("favicon: fetching $favicon_url failed.  Skipping...", Debug::LOG_VERBOSE);
				break;
			}

			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$mime_type = $finfo->buffer($contents);

			if ($mime_type === false) {
				Debug::log("favicon: $favicon_url MIME type couldn't be detected.  Skipping...", Debug::LOG_VERBOSE);
				break;
			}

			Debug::log("favicon: $favicon_url MIME type '$mime_type'", Debug::LOG_VERBOSE);

			if (!in_array($mime_type, self::FAVICON_ALLOWED_MIME_TYPES)) {
				Debug::log("favicon: $favicon_url MIME type '$mime_type' is not allowed.  Skipping...", Debug::LOG_VERBOSE);
				break;
			}

			$favicon_cache = DiskCache::instance('feed-icons');

			if ($favicon_cache->is_writable()) {
				Debug::log("favicon: $favicon_url looks valid, saving to cache", Debug::LOG_VERBOSE);

				// we deal with this manually
				if (!$favicon_cache->exists(".no-auto-expiry"))
					$favicon_cache->put(".no-auto-expiry", "");

				return $favicon_cache->put((string)$feed, $contents);
			} else {
				Debug::log("favicon: $favicon_url skipping, local cache is not writable", Debug::LOG_VERBOSE);
			}
		}

		return false;
	}

	static function is_gzipped(string $feed_data): bool {
		return str_starts_with(substr($feed_data, 0, 3), "\x1f" . "\x8b" . "\x08");
	}

	/**
	 * @return array<int, array{'id': int, 'match_any_rule': bool, 'inverse': bool, 'rules': array<int,mixed>, 'actions': array<int,mixed>}> An array of filters
	 */
	static function load_filters(int $feed_id, int $owner_uid): array {
		$filters = [];

		$feed_id = (int) $feed_id;
		$cat_id = Feeds::_cat_of($feed_id);

		$null_cat_qpart = $cat_id ? '' : 'cat_id IS NULL OR';

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT * FROM ttrss_filters2 WHERE
				owner_uid = ? AND enabled = true ORDER BY order_id, title");
		$sth->execute([$owner_uid]);

		$check_cats = [...Feeds::_get_parent_cats($cat_id, $owner_uid), $cat_id];

		$check_cats_str = join(",", $check_cats);
		$check_cats_fullids = array_map(fn(int $a) => "CAT:$a", $check_cats);

		while ($line = $sth->fetch()) {
			$filter_id = $line["id"];

			$match_any_rule = sql_bool_to_bool($line["match_any_rule"]);

			$sth2 = $pdo->prepare("SELECT
					r.reg_exp, r.inverse, r.feed_id, r.cat_id, r.cat_filter, r.match_on, t.name AS type_name
					FROM ttrss_filters2_rules AS r,
					ttrss_filter_types AS t
					WHERE
						(match_on IS NOT NULL OR
						  (($null_cat_qpart (cat_id IS NULL AND cat_filter = false) OR cat_id IN ($check_cats_str)) AND
						  (feed_id IS NULL OR feed_id = ?))) AND
						filter_type = t.id AND filter_id = ?");
			$sth2->execute([$feed_id, $filter_id]);

			$rules = [];
			$actions = [];

			while ($rule_line = $sth2->fetch()) {
				#				print_r($rule_line);

				if ($rule_line["match_on"]) {
					$match_on = json_decode($rule_line["match_on"], true);

					if (in_array("0", $match_on) || in_array($feed_id, $match_on) || count(array_intersect($check_cats_fullids, $match_on)) > 0) {
						$rules[] = [
							'reg_exp' => $rule_line['reg_exp'],
							'type' => $rule_line['type_name'],
							'inverse' => sql_bool_to_bool($rule_line['inverse']),
						];
					} else if (!$match_any_rule) {
						// this filter contains a rule that doesn't match to this feed/category combination
						// thus filter has to be rejected

						$rules = [];
						break;
					}

				} else {
					$rules[] = [
						'reg_exp' => $rule_line['reg_exp'],
						'type' => $rule_line['type_name'],
						'inverse' => sql_bool_to_bool($rule_line['inverse']),
					];
				}
			}

			if (count($rules) > 0) {
				$sth2 = $pdo->prepare("SELECT a.action_param,t.name AS type_name
						FROM ttrss_filters2_actions AS a,
						ttrss_filter_actions AS t
						WHERE
							action_id = t.id AND filter_id = ?");
				$sth2->execute([$filter_id]);

				while ($action_line = $sth2->fetch()) {
					$actions[] = [
						'type' => $action_line['type_name'],
						'param' => $action_line['action_param'],
					];
				}
			}

			if (count($rules) > 0 && count($actions) > 0) {
				$filters[] = [
					'id' => $filter_id,
					'match_any_rule' => sql_bool_to_bool($line['match_any_rule']),
					'inverse' => sql_bool_to_bool($line['inverse']),
					'rules' => $rules,
					'actions' => $actions,
				];
			}
		}

		return $filters;
	}

	/**
	 * Returns first determined favicon URL for a feed.
	 * @param string $url A feed or page URL
	 * @access public
	 * @return false|string The favicon URL string, or false if none was found.
	 */
	static function get_favicon_url(string $url): false|string {
		$favicon_urls = self::get_favicon_urls($url);
		return count($favicon_urls) > 0 ? $favicon_urls[0] : false;
	}

	/**
	 * Try to determine all favicon URLs for a feed.
	 * adapted from wordpress favicon plugin by Jeff Minard (http://thecodepro.com/)
	 * http://dev.wp-plugins.org/file/favatars/trunk/favatars.php
	 *
	 * @param string $url A feed or page URL
	 * @access public
	 * @return array<string> List of all determined favicon URLs or an empty array
	 */
	static function get_favicon_urls(string $url) : array {

		$favicon_urls = [];

		if ($html = @UrlHelper::fetch(['url' => $url])) {

			$doc = new DOMDocument();
			if (@$doc->loadHTML($html)) {
				$xpath = new DOMXPath($doc);

				$base = $xpath->query('/html/head/base[@href]');

				/** @var DOMElement $b */
				foreach ($base as $b) {
					$url = UrlHelper::rewrite_relative($url, $b->getAttribute("href"));
					break;
				}

				$entries = $xpath->query('/html/head/link[@rel="shortcut icon" or @rel="icon" or @rel="alternate icon"]');

				/** @var DOMElement $entry */
				foreach ($entries as $entry) {
					$favicon_url = UrlHelper::rewrite_relative($url, $entry->getAttribute("href"));

					if ($favicon_url)
						$favicon_urls[] = $favicon_url;
				}
			}
		}

		if (count($favicon_urls) == 0) {
			$favicon_url = UrlHelper::rewrite_relative($url, '/favicon.ico');

			if ($favicon_url)
				$favicon_urls[] = $favicon_url;
		}

		return $favicon_urls;
	}

	/**
	 * @return array<int, array{url: string, size: string}> An array of srcset subitem arrays
	 */
	static function decode_srcset(string $srcset): array {
		$matches = [];

		preg_match_all(
			'/(?:\A|,)\s*(?P<url>(?!,)\S+(?<!,))\s*(?P<size>\s\d+w|\s\d+(?:\.\d+)?(?:[eE][+-]?\d+)?x|)\s*(?=,|\Z)/',
			$srcset, $matches, PREG_SET_ORDER
		);

		return array_map(fn(array $m) => ['url' => trim($m['url']), 'size' => trim($m['size'])], $matches);
	}

	/**
	 * @param array<int, array{url: string, size: string}> $matches An array of srcset subitem arrays
	 */
	static function encode_srcset(array $matches): string {
		return implode(',', array_map(fn(array $m) => trim($m['url']) . ' ' . trim($m['size']), $matches));
	}

	static function function_enabled(string $func): bool {
		return !in_array($func,
						explode(',', str_replace(" ", "", ini_get('disable_functions'))));
	}
}

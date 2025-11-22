<?php
class Handler_Public extends Handler {

	/**
	 * @param string $feed may be a feed ID or tag
	 */
	private function generate_syndicated_feed(int $owner_uid, string $feed, bool $is_cat,
		int $limit, int $offset, string $search, string $view_mode = "",
		string $format = 'atom', string $order = "", string $orig_guid = "", string $start_ts = ""): void {

		// fail early if the requested format isn't recognized
		if (!in_array($format, ['atom', 'json'])) {
			header('Content-Type: text/plain; charset=utf-8');
			print "Unknown format: $format.";
			return;
		}

		$note_style = 'color: #9a8c59; background-color: #fff7d5; '
			. 'border: 1px dashed #e7d796; padding: 5px; margin-bottom: 1em;';

		if (!$limit)
			$limit = 60;

		[$override_order, $skip_first_id_check] = Feeds::_order_to_override_query($order, $feed);

		if (!$override_order) {
			$override_order = match (true) {
				$feed == Feeds::FEED_PUBLISHED && !$is_cat => 'last_published DESC',
				$feed == Feeds::FEED_STARRED && !$is_cat => 'last_marked DESC',
				default => 'date_entered DESC, updated DESC',
			};
		}

		$params = [
			"owner_uid" => $owner_uid,
			"feed" => $feed,
			"limit" => $limit,
			"view_mode" => $view_mode,
			"cat_view" => $is_cat,
			"search" => $search,
			"override_order" => $override_order,
			"include_children" => true,
			"ignore_vfeed_group" => true,
			"offset" => $offset,
			"start_ts" => $start_ts
		];

		if (!$is_cat && is_numeric($feed) && $feed < PLUGIN_FEED_BASE_INDEX && $feed > LABEL_BASE_INDEX) {
			// TODO: _ENABLED_PLUGINS is profile-specific, so use of the default profile's plugins here should
			// be called out in the docs, and/or access key stuff (see 'rss()') should also consider the profile
			$user_plugins = Prefs::get(Prefs::_ENABLED_PLUGINS, $owner_uid);

			$tmppluginhost = new PluginHost();
			$tmppluginhost->load(Config::get(Config::PLUGINS), PluginHost::KIND_ALL);
			$tmppluginhost->load((string)$user_plugins, PluginHost::KIND_USER, $owner_uid);
			//$tmppluginhost->load_data();

			$handler = $tmppluginhost->get_feed_handler(
				PluginHost::feed_to_pfeed_id((int)$feed));

			if ($handler) {
				$qfh_ret = $handler->get_headlines(PluginHost::feed_to_pfeed_id((int)$feed), $params);
			} else {
				user_error("Failed to find handler for plugin feed ID: $feed", E_USER_ERROR);

				return;
			}

		} else {
			$qfh_ret = Feeds::_get_headlines($params);
		}

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		/* $last_error = $qfh_ret[3]; */

		$feed_self_url = Config::get_self_url() .
			"/public.php?op=rss&id=$feed&key=" .
			Feeds::_get_access_key($feed, false, $owner_uid);

		if (!$feed_site_url)
			$feed_site_url = Config::get_self_url();

		if ($format == 'atom') {
			$tpl = new Templator();

			$tpl->readTemplateFromFile("generated_feed.txt");

			$tpl->setVariable('FEED_TITLE', $feed_title, true);
			$tpl->setVariable('FEED_UPDATED', date('c'), true);
			$tpl->setVariable('VERSION', Config::get_version(), true);
			$tpl->setVariable('FEED_URL', htmlspecialchars($feed_self_url), true);

			$tpl->setVariable('SELF_URL', htmlspecialchars(Config::get_self_url()), true);

			while ($line = $result->fetch()) {

				$line["content_preview"] = Sanitizer::sanitize(truncate_string(strip_tags($line["content"]), 100, '...'));
				$line["tags"] = Article::_get_tags($line["id"], $owner_uid);

				$max_excerpt_length = 250;

				PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_QUERY_HEADLINES,
					function ($result) use (&$line) {
						$line = $result;
					},
					$line, $max_excerpt_length);

				PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_ARTICLE_EXPORT_FEED,
					function ($result) use (&$line) {
						$line = $result;
					},
					$line, $feed, $is_cat, $owner_uid);

				$tpl->setVariable('ARTICLE_ID',
					htmlspecialchars($orig_guid ? $line['link'] :
							$this->_make_article_tag_uri($line['id'], $line['date_entered'])), true);
				$tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']), true);
				$tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']), true);
				$tpl->setVariable('ARTICLE_EXCERPT', $line["content_preview"], true);

				$content = Sanitizer::sanitize($line["content"], false, $owner_uid,
					$feed_site_url, null, $line["id"]);

				$content = DiskCache::rewrite_urls($content);

				if ($line['note']) {
					$content = "<div style=\"$note_style\">Article note: " . $line['note'] . "</div>" . $content;
					$tpl->setVariable('ARTICLE_NOTE', htmlspecialchars($line['note']), true);
				}

				$tpl->setVariable('ARTICLE_CONTENT', $content, true);

				$tpl->setVariable('ARTICLE_UPDATED_ATOM',
					date('c', strtotime($line["updated"] ?? '')), true);
				$tpl->setVariable('ARTICLE_UPDATED_RFC822',
					date(DATE_RFC822, strtotime($line["updated"] ?? '')), true);

				$tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']), true);

				$tpl->setVariable('ARTICLE_SOURCE_LINK', htmlspecialchars($line['site_url'] ? $line["site_url"] : Config::get_self_url()), true);
				$tpl->setVariable('ARTICLE_SOURCE_TITLE', htmlspecialchars($line['feed_title'] ?? $feed_title), true);

				foreach ($line["tags"] as $tag) {
					$tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag), true);
					$tpl->addBlock('category');
				}

				$enclosures = Article::_get_enclosures($line["id"]);

				if (count($enclosures) > 0) {
					foreach ($enclosures as $e) {
						$type = htmlspecialchars($e['content_type']);
						$url = htmlspecialchars($e['content_url']);
						$length = $e['duration'] ?: 1;

						$tpl->setVariable('ARTICLE_ENCLOSURE_URL', $url, true);
						$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', $type, true);
						$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', $length, true);

						$tpl->addBlock('enclosure');
					}
				} else {
					$tpl->setVariable('ARTICLE_ENCLOSURE_URL', "", true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', "", true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', "", true);
				}

				[$og_image, $og_stream] = Article::_get_image($enclosures, $line['content'], $feed_site_url, $line);

				$tpl->setVariable('ARTICLE_OG_IMAGE', $og_image, true);

				$tpl->addBlock('entry');
			}

			$tmp = "";

			$tpl->addBlock('feed');
			$tpl->generateOutputToString($tmp);

			if (empty($_REQUEST["noxml"])) {
				header("Content-Type: text/xml; charset=utf-8");
			} else {
				header("Content-Type: text/plain; charset=utf-8");
			}

			print $tmp;
		} else { // $format == 'json'

			$feed = [
				'title' => $feed_title,
				'feed_url' => $feed_self_url,
				'self_url' => Config::get_self_url(),
				'articles' => [],
			];

			while ($line = $result->fetch()) {

				$line["content_preview"] = Sanitizer::sanitize(truncate_string(strip_tags($line["content"]), 100, '...'));
				$line["tags"] = Article::_get_tags($line["id"], $owner_uid);

				PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_QUERY_HEADLINES,
					function ($result) use (&$line) {
						$line = $result;
					},
					$line);

				PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_ARTICLE_EXPORT_FEED,
					function ($result) use (&$line) {
						$line = $result;
					},
					$line, $feed, $is_cat, $owner_uid);

				$article = [
					'id' => $line['link'],
					'link' => $line['link'],
					'title' => $line['title'],
					'content' => Sanitizer::sanitize($line['content'], false, $owner_uid, $feed_site_url, null, $line['id']),
					'updated' => date('c', strtotime($line['updated'] ?? '')),
					'source' => [
						'link' => $line['site_url'] ?: Config::get_self_url(),
						'title' => $line['feed_title'] ?? $feed_title,
					],
				];

				if (!empty($line['note']))
					$article['note'] = $line['note'];

				if (!empty($line['author']))
					$article['author'] = $line['author'];

				if (count($line['tags']) > 0)
					$article['tags'] = $line['tags'];

				$enclosures = Article::_get_enclosures($line["id"]);

				if (count($enclosures) > 0) {
					$article['enclosures'] = [];

					foreach ($enclosures as $e) {
						$article['enclosures'][] = [
							'url' => $e['content_url'],
							'type' => $e['content_type'],
							'length' => $e['duration'],
						];
					}
				}

				$feed['articles'][] = $article;
			}

			header("Content-Type: application/json; charset=utf-8");
			print json_encode($feed);

		}
	}

	function getUnread(): void {
		$login = clean($_REQUEST["login"]);
		$fresh = clean($_REQUEST["fresh"] ?? "0") == "1";

		$uid = UserHelper::find_user_by_login($login);

		if ($uid) {
			print Feeds::_get_global_unread($uid);

			if ($fresh) {
				print ";";
				print Feeds::_get_counters(Feeds::FEED_FRESH, false, true, $uid);
			}
		} else {
			print "-1;User not found";
		}
	}

	function getProfiles(): void {
		$login = clean($_REQUEST["login"]);
		$rv = [];

		if ($login) {
			$profiles = ORM::for_table('ttrss_settings_profiles')
				->table_alias('p')
				->select_many('title' , 'p.id')
				->join('ttrss_users', ['owner_uid', '=', 'u.id'], 'u')
				->where_raw('LOWER(login) = LOWER(?)', [$login])
				->order_by_asc('title')
				->find_many();

			$rv = [
				['label' => __('Default profile'), 'value' => 0],
			];

			foreach ($profiles as $profile)
				$rv[] = ['label' => $profile->title, 'value' => $profile->id];
		}

		print json_encode($rv);
	}

	function logout(): void {
		if (validate_csrf($_POST["csrf_token"])) {

			$login = $_SESSION["name"];
			$user_id = $_SESSION["uid"];

			UserHelper::logout();

			$redirect_url = "";

			PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_POST_LOGOUT,
				function ($result) use (&$redirect_url) {
					if (!empty($result[0]))
						$redirect_url = UrlHelper::validate($result[0]);
				},
				$login, $user_id);

			if (!$redirect_url)
				$redirect_url = Config::get_self_url() . "/index.php";

			header("Location: " . $redirect_url);
		} else {
			header("Content-Type: application/json");
			print Errors::to_json(Errors::E_UNAUTHORIZED);
		}
	}

	function rss(): void {
		$feed = clean($_REQUEST["id"]);
		$key = clean($_REQUEST["key"]);
		$is_cat = self::_param_to_bool($_REQUEST["is_cat"] ?? false);
		$limit = (int)clean($_REQUEST["limit"] ?? 0);
		$offset = (int)clean($_REQUEST["offset"] ?? 0);

		$search = clean($_REQUEST["q"] ?? "");
		$view_mode = clean($_REQUEST["view-mode"] ?? "");
		$order = clean($_REQUEST["order"] ?? "");
		$start_ts = clean($_REQUEST["ts"] ?? "");

		$format = clean($_REQUEST['format'] ?? "atom");
		$orig_guid = clean($_REQUEST["orig_guid"] ?? "");

		if (Config::get(Config::SINGLE_USER_MODE)) {
			UserHelper::authenticate("admin", null);
		}

		if ($key) {
			$access_key = ORM::for_table('ttrss_access_keys')
				->select('owner_uid')
				->where(['access_key' => $key, 'feed_id' => $feed])
				->find_one();

			if ($access_key) {
				$this->generate_syndicated_feed($access_key->owner_uid, $feed, $is_cat, $limit,
					$offset, $search, $view_mode, $format, $order, $orig_guid, $start_ts);
				return;
			}
		}

		header('HTTP/1.1 403 Forbidden');
	}

	function login(): void {
		if (!Config::get(Config::SINGLE_USER_MODE)) {

			$login = clean($_POST["login"]);
			$password = clean($_POST["password"]);
			$remember_me = clean($_POST["remember_me"] ?? false);
			$safe_mode = checkbox_to_sql_bool($_POST["safe_mode"] ?? false);

			if (session_status() != PHP_SESSION_ACTIVE) {
				if ($remember_me) {
					session_set_cookie_params(Config::get(Config::SESSION_COOKIE_LIFETIME));
				} else {
					session_set_cookie_params(0);
				}
			}

			if (UserHelper::authenticate($login, $password)) {
				$_POST["password"] = "";

				$_SESSION["ref_schema_version"] = Config::get_schema_version();
				$_SESSION["bw_limit"] = !!clean($_POST["bw_limit"] ?? false);
				$_SESSION["safe_mode"] = $safe_mode;

				if (!empty($_POST["profile"])) {
					$profile = (int) clean($_POST["profile"]);

					$profile_obj = ORM::for_table('ttrss_settings_profiles')
						->where(['id' => $profile, 'owner_uid' => $_SESSION['uid']])
						->find_one();

					$_SESSION["profile"] = $profile_obj ? $profile : null;
				}
			} else {

				// start an empty session to deliver login error message
				if (session_status() != PHP_SESSION_ACTIVE)
					session_start();

				$_SESSION["login_error_msg"] ??= __("Incorrect username or password");
			}

			$return = clean($_REQUEST['return'] ?? '');

			if ($return && mb_strpos($return, Config::get_self_url()) === 0) {
				header("Location: $return");
			} else {
				header("Location: " . Config::get_self_url());
			}
		}
	}

	function index(): void {
		header("Content-Type: text/plain");
		print Errors::to_json(Errors::E_UNKNOWN_METHOD);
	}

	function forgotpass(): void {
		if (Config::get(Config::DISABLE_LOGIN_FORM) || !str_contains(Config::get(Config::PLUGINS), "auth_internal")) {
			header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
			echo "Forbidden.";

			return;
		}

		startup_gettext();
		session_start();

		$hash = clean($_REQUEST["hash"] ?? '');

		header('Content-Type: text/html; charset=utf-8');
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Tiny Tiny RSS</title>
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<?php
				echo javascript_tag("lib/dojo/dojo.js");
				echo javascript_tag("lib/dojo/tt-rss-layer.js");
				echo javascript_tag("js/common.js");
				echo javascript_tag("js/utility.js");
			?>
			<?= Config::get_override_links() ?>
		</head>
		<body class='flat ttrss_utility css_loading'>
		<div class='container'>

		<script type="text/javascript">
			const __csrf_token = "<?= $_SESSION["csrf_token"]; ?>";

			const __default_light_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_LIGHT_THEME), 'themes/light.css') ?>";
			const __default_dark_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_DARK_THEME), 'themes/night.css') ?>";
		</script>

		<script type="text/javascript">
		require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
    		'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
			ready(function() {
				parser.parse();
			});
		});
		</script>

		<style type="text/css">
			@media (prefers-color-scheme: dark) {
				body {
					background : #303030;
				}
			}

			body.css_loading * {
				display : none;
			}
		</style>

		<?php

		print "<h1>".__("Password recovery")."</h1>";
		print "<div class='content'>";

		$method = clean($_POST['method'] ?? '');

		if ($hash) {
			$login = clean($_REQUEST["login"]);

			if ($login) {
				$user = ORM::for_table('ttrss_users')
					->select_many('id', 'resetpass_token')
					->where_raw('LOWER(login) = LOWER(?)', [$login])
					->find_one();

				if ($user) {
					[$timestamp, $resetpass_token] = explode(":", $user->resetpass_token);

					if ($timestamp && $resetpass_token &&
						$timestamp >= time() - 15*60*60 &&
						$resetpass_token === $hash) {
							$user->resetpass_token = null;
							$user->save();

							UserHelper::reset_password($user->id, true);

							print "<p>"."Completed."."</p>";

					} else {
						print_error("Some of the information provided is missing or incorrect.");
					}
				} else {
					print_error("Some of the information provided is missing or incorrect.");
				}
			} else {
				print_error("Some of the information provided is missing or incorrect.");
			}

			print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";

		} else if (!$method) {
			print_notice(__("You will need to provide valid account name and email. Password reset link will be sent to your email address."));

			print "<form method='POST' action='public.php'>
				<input type='hidden' name='method' value='do'>
				<input type='hidden' name='op' value='forgotpass'>

				<fieldset>
				<label>".__("Login:")."</label>
				<input dojoType='dijit.form.TextBox' type='text' name='login' value='' required>
				</fieldset>

				<fieldset>
				<label>".__("Email:")."</label>
				<input dojoType='dijit.form.TextBox' type='email' name='email' value='' required>
				</fieldset>";

			$_SESSION["pwdreset:testvalue1"] = random_int(1,10);
			$_SESSION["pwdreset:testvalue2"] = random_int(1,10);

			print "<fieldset>
				<label>".T_sprintf("How much is %d + %d:", $_SESSION["pwdreset:testvalue1"], $_SESSION["pwdreset:testvalue2"])."</label>
				<input dojoType='dijit.form.TextBox' type='text' name='test' value='' required>
				</fieldset>

				<hr/>
				<fieldset>
				<button dojoType='dijit.form.Button' type='submit' class='alt-danger'>".__("Reset password")."</button>
				<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>
				</fieldset>

				</form>";
		} else if ($method == 'do') {
			$login = clean($_POST["login"]);
			$email = clean($_POST["email"]);
			$test = clean($_POST["test"]);

			if ($test != ($_SESSION["pwdreset:testvalue1"] + $_SESSION["pwdreset:testvalue2"]) || !$email || !$login) {
				print_error(__('Some of the required form parameters are missing or incorrect.'));

				print "<form method='GET' action='public.php'>
					<input type='hidden' name='op' value='forgotpass'>
					<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>".__("Go back")."</button>
					</form>";
			} else {
				// prevent submitting this form multiple times
				$_SESSION["pwdreset:testvalue1"] = random_int(1, 1000);
				$_SESSION["pwdreset:testvalue2"] = random_int(1, 1000);

				$user = ORM::for_table('ttrss_users')
					->select('id')
					->where_raw('LOWER(login) = LOWER(?)', [$login])
					->where('email', $email)
					->find_one();

				if ($user) {
					print_notice("Password reset instructions are being sent to your email address.");

					$resetpass_token = sha1(get_random_bytes(128));
					$resetpass_link = Config::get_self_url() . "/public.php?op=forgotpass&hash=" . $resetpass_token .
						"&login=" . urlencode($login);

					$tpl = new Templator();

					$tpl->readTemplateFromFile("resetpass_link_template.txt");

					$tpl->setVariable('LOGIN', $login);
					$tpl->setVariable('RESETPASS_LINK', $resetpass_link);
					$tpl->setVariable('TTRSS_HOST', Config::get_self_url());

					$tpl->addBlock('message');

					$message = "";

					$tpl->generateOutputToString($message);

					$mailer = new Mailer();

					$rc = $mailer->mail(["to_name" => $login,
						"to_address" => $email,
						"subject" => __("[tt-rss] Password reset request"),
						"message" => $message]);

					if (!$rc) print_error($mailer->error());

					$user->resetpass_token = time() . ":" . $resetpass_token;
					$user->save();

					print "<a href='index.php'>".__("Return to Tiny Tiny RSS")."</a>";
				} else {
					print_error(__("Sorry, login and email combination not found."));

					print "<form method='GET' action='public.php'>
						<input type='hidden' name='op' value='forgotpass'>
						<button dojoType='dijit.form.Button' type='submit'>".__("Go back")."</button>
						</form>";
				}
			}
		}

		print "</div>";
		print "</div>";
		print "</body>";
		print "</html>";
	}

	function dbupdate(): void {
		startup_gettext();

		if (!Config::get(Config::SINGLE_USER_MODE) && ($_SESSION["access_level"] ?? 0) < 10) {
			$_SESSION["login_error_msg"] = __("Your access level is insufficient to run this script.");
			static::_render_login_form();
			exit;
		}

		?>
		<!DOCTYPE html>
		<html>
			<head>
			<title>Tiny Tiny RSS: Database Updater</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<?php
			foreach (["lib/dojo/dojo.js",
						"lib/dojo/tt-rss-layer.js",
						"js/common.js",
						"js/utility.js"] as $jsfile) {

				echo javascript_tag($jsfile);

			} ?>

			<?= Config::get_override_links() ?>

			<style type="text/css">
				@media (prefers-color-scheme: dark) {
					body {
						background : #303030;
					}
				}

				body.css_loading * {
					display : none;
				}
			</style>

			<script type="text/javascript">
				require({cache:{}});
			</script>
		</head>
		<body class="flat ttrss_utility css_loading">

			<script type="text/javascript">
				const UtilityApp = {
					init: function() {
						require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
							'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
							ready(function() {
								parser.parse();
							});
						});
					}
				}

				function confirmDbUpdate() {
					return confirm(<?= json_encode(__('Proceed with update?')) ?>);
				}
			</script>

			<div class="container">
			<h1><?= __("Database Updater") ?></h1>

			<div class="content">

			<?php
				@$op = clean($_REQUEST["subop"] ?? "");

				$migrations = Config::get_migrations();

				if ($op == "performupdate") {
					if ($migrations->is_migration_needed()) {
						?>

						<h2><?= T_sprintf("Performing updates to version %d", Config::SCHEMA_VERSION) ?></h2>

						<code><pre class="small pre-wrap"><?php
							Debug::set_enabled(true);
							Debug::set_loglevel(Debug::LOG_VERBOSE);
							$result = $migrations->migrate();
							Debug::set_loglevel(Debug::LOG_NORMAL);
							Debug::set_enabled(false);
						?></pre></code>

						<?php if (!$result) { ?>
							<?= format_error("One of migrations failed. Either retry the process or perform updates manually.") ?>

							<form method="post">
								<?= \Controls\hidden_tag('subop', 'performupdate') ?>
								<?= \Controls\submit_tag(__("Update"), ["onclick" => "return confirmDbUpdate()"]) ?>
							</form>
						<?php } else { ?>
							<?= format_notice("Update successful.") ?>

							<a href="index.php"><?= __("Return to Tiny Tiny RSS") ?></a>
						<?php }

					} else { ?>

						<?= format_notice("Database is already up to date.") ?>

						<a href="index.php"><?= __("Return to Tiny Tiny RSS") ?></a>

						<?php
					}
				} else {
					if ($migrations->is_migration_needed()) {

						?>
						<h2><?= T_sprintf("Database schema needs update to the latest version (%d to %d).",
							Config::get_schema_version(), Config::SCHEMA_VERSION) ?></h2>

						<?= format_warning("Please backup your database before proceeding.") ?>

						<form method="post">
							<?= \Controls\hidden_tag('subop', 'performupdate') ?>
							<?= \Controls\submit_tag(__("Update"), ["onclick" => "return confirmDbUpdate()"]) ?>
						</form>

						<?php
					} else { ?>

						<?= format_notice("Database is already up to date.") ?>

						<a href="index.php"><?= __("Return to Tiny Tiny RSS") ?></a>

						<?php
					}
				}
			?>

			</div>
			</div>
			</body>
			</html>
		<?php
	}

	function cached(): void {
		[$cache_dir, $filename] = explode("/", $_GET["file"], 2);

		// we do not allow files with extensions at the moment
		$filename = str_replace(".", "", $filename);

		$cache = DiskCache::instance($cache_dir);

		if ($cache->exists($filename)) {
			$cache->send($filename);
		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			echo "File not found.";
		}
	}

	function feed_icon() : void {
		$id = (int)$_REQUEST['id'];
		$cache = DiskCache::instance('feed-icons');

		if ($cache->exists((string)$id)) {
			$cache->send((string)$id);
		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			echo "File not found.";
		}
	}

	private function _make_article_tag_uri(int $id, string $timestamp): string {

		$timestamp = date("Y-m-d", strtotime($timestamp));

		return "tag:" . parse_url(Config::get_self_url(), PHP_URL_HOST) . ",$timestamp:/$id";
	}

	// this should be used very carefully because this endpoint is exposed to unauthenticated users
	// plugin data is not loaded because there's no user context and owner_uid/session may or may not be available
	// in general, don't do anything user-related in here and do not modify $_SESSION
	public function pluginhandler(): void {
		$host = new PluginHost();

		$plugin_name = basename(clean($_REQUEST["plugin"]));
		$method = clean($_REQUEST["pmethod"]);

		$host->load($plugin_name, PluginHost::KIND_ALL, 0);
		//$host->load_data();

		$plugin = $host->get_plugin($plugin_name);

		if ($plugin) {
			if (method_exists($plugin, $method)) {
				if ($plugin->is_public_method($method)) {
					$plugin->$method();
				} else {
					user_error("PluginHandler[PUBLIC]: Requested private method '$method' of plugin '$plugin_name'.", E_USER_WARNING);
					header("Content-Type: application/json");
					print Errors::to_json(Errors::E_UNAUTHORIZED);
				}
			} else {
				user_error("PluginHandler[PUBLIC]: Requested unknown method '$method' of plugin '$plugin_name'.", E_USER_WARNING);
				header("Content-Type: application/json");
				print Errors::to_json(Errors::E_UNKNOWN_METHOD);
			}
		} else {
			user_error("PluginHandler[PUBLIC]: Requested method '$method' of unknown plugin '$plugin_name'.", E_USER_WARNING);
			header("Content-Type: application/json");
			print Errors::to_json(Errors::E_UNKNOWN_PLUGIN, ['plugin' => $plugin_name]);
		}
	}

	static function _render_login_form(string $return_to = ""): void {
		header('Cache-Control: public');

		if ($return_to)
			$_REQUEST['return'] = $return_to;

		require_once __DIR__ . '/../include/login_form.php';
		exit;
	}

	// implicit Config::sanity_check() does the actual checking */
	public function healthcheck() : void {
		header("Content-Type: text/plain");
		print "OK";
	}

}
?>

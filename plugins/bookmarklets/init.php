<?php
class Bookmarklets extends Plugin {

	/** @var PluginHost $host */
	private $host;

	public function about() {
		return [null,
			"Easy feed subscription and web page sharing using bookmarklets",
			"fox",
			false,
			"https://tt-rss.org/docs/Share-Anything.html"];
  }

	public function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	public function is_public_method($method) {
		return in_array($method, ["subscribe", "sharepopup"]);
	}

	public function subscribe() : void {
		if (Config::get(Config::SINGLE_USER_MODE)) {
			UserHelper::login_sequence();
		}

		if (!empty($_SESSION["uid"])) {

			$feed_url = clean($_REQUEST["feed_url"] ?? "");
			$csrf_token = clean($_POST["csrf_token"] ?? "");

			header('Content-Type: text/html; charset=utf-8');
			?>
			<!DOCTYPE html>
			<html>
			<head>
				<title><?= __("Subscribe to feed...") ?></title>
				<?= javascript_tag("lib/dojo/dojo.js") ?>
				<?= javascript_tag("js/utility.js") ?>
				<?= javascript_tag("js/common.js") ?>
				<?= javascript_tag("lib/dojo/tt-rss-layer.js") ?>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
				<link rel="shortcut icon" type="image/png" href="images/favicon.png">
				<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
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
				<?= Config::get_override_links() ?>
			</head>
			<body class='flat ttrss_utility css_loading'>
			<script type="text/javascript">
				const __default_light_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_LIGHT_THEME), 'themes/light.css') ?>";
				const __default_dark_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_DARK_THEME), 'themes/night.css') ?>";

				const UtilityApp = {
					init: function() {
                        require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
                            'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'], function(parser, ready){
                            ready(function() {
                                parser.parse();
                            });
                        });
					}
				};
			</script>
			<div class="container">
			<h1><?= __("Subscribe to feed...") ?></h1>
			<div class='content'>
			<?php

			if (!$feed_url || !validate_csrf($csrf_token)) {
				?>
				<form method="post" action='public.php'>
					<?= \Controls\public_method_tags($this, "subscribe") ?>
					<?= \Controls\hidden_tag("csrf_token", $_SESSION["csrf_token"]) ?>

					<fieldset>
						<label>Feed or site URL:</label>
						<input style="width: 300px" dojoType="dijit.form.ValidationTextBox" required="1" name="feed_url" value="<?= htmlspecialchars($feed_url) ?>">
					</fieldset>

					<button class="alt-primary" dojoType="dijit.form.Button" type="submit">
						<?= __("Subscribe") ?>
					</button>

					<a href="index.php"><?= __("Return to Tiny Tiny RSS") ?></a>
				</form>
				<?php
			} else {

				$rc = Feeds::_subscribe($feed_url);
				$feed_urls = false;

				switch ($rc['code']) {
					case 0:
						print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
						break;
					case 1:
						print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
						break;
					case 2:
						print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
						break;
					case 3:
						print_error(T_sprintf("No feeds found in <b>%s</b>.", $feed_url));
						break;
					case 4:
						$feed_urls = $rc["feeds"];
						break;
					case 5:
						print_error(T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url));
						break;
				}

				if ($feed_urls) {
					?>
					<form method='post' action='public.php'>
						<?= \Controls\public_method_tags($this, "subscribe") ?>
						<?= \Controls\hidden_tag("csrf_token", $_SESSION["csrf_token"]) ?>

						<fieldset>
							<label style='display : inline'><?= __("Multiple feed URLs found:") ?></label>
							<select name='feed_url' dojoType='dijit.form.Select'>
							<?php foreach ($feed_urls as $url => $name) { ?>
								<option value="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($name) ?></option>
							<?php } ?>
							</select>
						</fieldset>

						<button class='alt-primary' dojoType='dijit.form.Button' type='submit'><?= __("Subscribe to selected feed") ?></button>
						<a href='index.php'><?= __("Return to Tiny Tiny RSS") ?></a>
					</form>
					<?php
				}

				$feed_id = $rc['code'] <= 2 ? Feeds::_find_by_url($feed_url, $_SESSION['uid']) : 0;

				if ($feed_id) {
					?>
					<form method='GET' action="<?= htmlspecialchars(Config::get_self_url() . "/prefs.php") ?>">
						<input type='hidden' name='tab' value='feeds'>
						<input type='hidden' name='method' value='editfeed'>
						<input type='hidden' name='methodparam' value="<?= $feed_id ?>">
						<button dojoType='dijit.form.Button' class='alt-info' type='submit'><?= __("Edit subscription options") ?></button>
						<a href='index.php'><?= __("Return to Tiny Tiny RSS") ?></a>
					</form>
					<?php
				} else if (!$feed_urls) {
					?>
					<a href='index.php'><?= __("Return to Tiny Tiny RSS") ?></a>
					<?php
				}
			}
			?>
			</div>
		</div>
		</body>
		</html>
			<?php
		} else {
			Handler_Public::_render_login_form($this->host->get_public_method_url($this, "subscribe"));
		}
	}

	public function sharepopup() : void {
		if (Config::get(Config::SINGLE_USER_MODE)) {
			UserHelper::login_sequence();
		}

		header('Content-Type: text/html; charset=utf-8');
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title><?= __("Share with Tiny Tiny RSS") ?></title>
			<?= javascript_tag("lib/dojo/dojo.js") ?>
			<?= javascript_tag("js/utility.js") ?>
			<?= javascript_tag("js/common.js") ?>
			<?= javascript_tag("lib/dojo/tt-rss-layer.js") ?>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<link rel="shortcut icon" type="image/png" href="images/favicon.png">
			<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png">
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
			<?= Config::get_override_links() ?>
		</head>
		<body class='flat ttrss_utility share_popup css_loading'>
			<script type="text/javascript">
				const __default_light_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_LIGHT_THEME), 'themes/light.css') ?>";
				const __default_dark_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_DARK_THEME), 'themes/night.css') ?>";

				const UtilityApp = {
					init: function() {
						require(['dojo/parser', "dojo/ready", 'dijit/form/Button','dijit/form/CheckBox', 'dijit/form/Form',
							'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'], function(parser, ready){
							ready(function() {
								parser.parse();

								/* new Ajax.Autocompleter('labels_value', 'labels_choices',
									"backend.php?op=rpc&method=completeLabels",
									{ tokens: ',', paramName: "search" }); */
								});
						});
					}
				};
			</script>
		<div class="content">

		<?php
			if ($_SESSION["uid"]) {

				$action = clean($_REQUEST["action"] ?? "");

				if ($action == 'share') {

					$title = strip_tags(clean($_REQUEST["title"]));
					$url = strip_tags(clean($_REQUEST["url"]));
					$content = strip_tags(clean($_REQUEST["content"]));
					$labels = strip_tags(clean($_REQUEST["labels"]));

					Article::_create_published_article($title, $url, $content, $labels,
						$_SESSION["uid"]);

					?>
					<script type="text/javascript">
						window.close();
					</script>
					<?php

				} else {
					$title = htmlspecialchars(clean($_REQUEST["title"]));
					$url = htmlspecialchars(clean($_REQUEST["url"]));

					?>
					<form method='post' action='public.php'>

						<?= \Controls\public_method_tags($this, "sharepopup") ?>
						<?= \Controls\hidden_tag("csrf_token", $_SESSION["csrf_token"]) ?>
						<?= \Controls\hidden_tag("action", "share") ?>

						<fieldset>
							<label><?= __("Title:") ?></label>
							<input style='width : 270px' dojoType='dijit.form.TextBox' name='title' value="<?= $title ?>">
						</fieldset>

						<fieldset>
							<label><?= __("URL:") ?></label>
							<input style='width : 270px' name='url' dojoType='dijit.form.TextBox' value="<?= $url ?>">
						</fieldset>

						<fieldset>
							<label><?= __("Content:") ?></label>
							<input style='width : 270px' name='content' dojoType='dijit.form.TextBox' value="">
						</fieldset>

						<fieldset>
							<label><?= __("Labels:") ?></label>
							<input style='width : 270px' name='labels' dojoType='dijit.form.TextBox' id="labels_value"
								placeholder='Alpha, Beta, Gamma' value="">
							<!-- <div class="autocomplete" id="labels_choices"
								style="display : block"></div> -->
						</fieldset>

						<hr/>

						<fieldset>
							<?= \Controls\submit_tag(__("Share")) ?>
							<?= \Controls\button_tag(__("Cancel"), "", ["onclick" => "window.close()"]) ?>
							<span class="text-muted small"><?= __("Shared article will appear in the Published feed.") ?></span>
						</fieldset>

					</form>
					<?php

				}

			} else {
				$return_to = $this->host->get_public_method_url($this, "sharepopup");
			?>

			<?= format_error("Not logged in") ?>

			<form action="public.php?return=<?= urlencode($return_to) ?>" method="post">

				<input type="hidden" name="op" value="login">

				<fieldset>
					<label><?= __("Login:") ?></label>
					<input name="login" id="login" dojoType="dijit.form.TextBox" type="text"
							onchange="fetchProfiles()" onfocus="fetchProfiles()" onblur="fetchProfiles()"
							required="1" value="<?= $_SESSION["fake_login"] ?>" />
				</fieldset>

				<fieldset>
					<label><?= __("Password:") ?></label>

					<input type="password" name="password" required="1"
							dojoType="dijit.form.TextBox"
							class="input input-text"
							value="<?= $_SESSION["fake_password"] ?>"/>
				</fieldset>

				<hr/>

				<fieldset>
					<label> </label>

					<button dojoType="dijit.form.Button" type="submit" class="alt-primary"><?= __('Log in') ?></button>
				</fieldset>

			</form>
			<?php
			}
		?>
		</div>
		</body>
		</html>
		<?php
	}

	public function hook_prefs_tab($args) {
		if ($args != "prefFeeds")
			return;

			$bm_subscribe_url = $this->host->get_public_method_url($this, "subscribe");
			$bm_share_url = $this->host->get_public_method_url($this, "sharepopup");

			$confirm_str = str_replace("'", "\'", __('Subscribe to %s in Tiny Tiny RSS?'));

			$bm_subscribe_url = htmlspecialchars("javascript:{if(confirm('$confirm_str'.replace('%s',window.location.href)))window.location.href='$bm_subscribe_url&feed_url='+encodeURIComponent(window.location.href)}");
			$bm_share_url = htmlspecialchars("javascript:(function(){var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),f='$bm_share_url',l=d.location,e=encodeURIComponent,g=f+'&title='+((e(s))?e(s):e(document.title))+'&url='+e(l.href);function a(){if(!w.open(g,'t','toolbar=0,resizable=0,scrollbars=1,status=1,width=500,height=250')){l.href=g;}}a();})()");
		?>

		<div dojoType="dijit.layout.AccordionPane"
			title="<i class='material-icons'>bookmark</i> <?= __('Bookmarklets') ?>">

			<h3><?= __("Drag the link below to your browser toolbar, open the feed you're interested in in your browser and click on the link to subscribe to it.") ?></h3>

			<label class='dijitButton'>
				<a href="<?= $bm_subscribe_url ?>"><?= __('Subscribe in Tiny Tiny RSS') ?></a>
			</label>

			<h3><?= __("Use this bookmarklet to publish arbitrary pages using Tiny Tiny RSS") ?></h3>

			<label class='dijitButton'>
				<a href="<?= $bm_share_url ?>"><?= __('Share with Tiny Tiny RSS') ?></a>
			</label>

			<?= \Controls\button_tag(\Controls\icon("help") . " " . __("More info..."), "",
									["class" => 'alt-info', "onclick" => "window.open('https://tt-rss.org/docs/Share-Anything.html', '_blank', 'noreferrer')"]) ?>

		</div>

		<?php
	}

	public function api_version() {
		return 2;
	}

}

<?php
	// See index.php: PHP's default session cache limiter would send no-store,
	// which costs this page the back/forward cache for no benefit it needs.
	session_cache_limiter('');

	require_once __DIR__ . '/include/autoload.php';
	require_once __DIR__ . '/include/sessions.php';

	Config::sanity_check();

	if (!init_plugins()) return;

	UserHelper::login_sequence();

	header('Content-Type: text/html; charset=utf-8');

	header('Cache-Control: private, no-cache');

	ob_start();
?>
<!DOCTYPE html>
<html>
<head>
	<title>Tiny Tiny RSS : <?= __("Preferences") ?></title>
    <meta name="viewport" content="initial-scale=1,width=device-width" />

	<?php if ($_SESSION["uid"] && empty($_SESSION["safe_mode"])) {
		$theme = Prefs::get(Prefs::USER_CSS_THEME, $_SESSION['uid'], $_SESSION['profile'] ?? null);
		if ($theme && theme_exists("$theme")) {
			echo stylesheet_tag(get_theme_path($theme), ['id' => 'theme_css']);
		}
	} ?>

	<?= Config::get_override_links() ?>

	<script type="text/javascript">
		const __csrf_token = "<?= $_SESSION["csrf_token"]; ?>";

		const __default_light_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_LIGHT_THEME), 'themes/light.css') ?>";
		const __default_dark_theme = "<?= get_theme_path(Config::get(Config::DEFAULT_DARK_THEME), 'themes/night.css') ?>";
	</script>

	<?php UserHelper::print_user_stylesheet() ?>

	<style>
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $p) {
			$css = $p->get_prefs_css();
			if ($css)
				echo $css;
		}
	?>
	</style>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
	<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />

	<script>
		dojoConfig = {
			async: true,
			cacheBust: "<?= get_scripts_timestamp(); ?>",
			packages: [
				{ name: "lib", location: "../" },
				{ name: "fox", location: "../../js" },
			]
		};
	</script>

	<?php
	foreach (["lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"js/common.js",
				"js/prefs.js"] as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

    <script type="text/javascript">
		require({cache:{}});
    </script>

	<script type="text/javascript">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			$script = $p->get_prefs_js();

			if ($script) {
				echo "try {
					$script
				} catch (e) {
					console.warn('failed to initialize plugin JS: $n', e);
				}";
			}
		}
	?>
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

	<noscript>
		<style type="text/css">
			body.css_loading noscript {
				display : block;
				margin : 16px;
			}
		</style>

		<?= stylesheet_tag("themes/light.css") ?>
	</noscript>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>

<body class="flat ttrss_main ttrss_prefs css_loading">

<noscript class="alert alert-error"><?= __('Javascript is disabled. Please enable it.') ?></noscript>

<div id="notify" class="notify"></div>
<div id="cmdline" style="display : none"></div>

<div id="overlay">
	<div id="overlay_inner">
		<?= __("Loading, please wait...") ?>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
	</div>
</div>

<div id="header">
	<i class="material-icons net-alert" style="display: none"
		title="<?= __("Communication problem with server.") ?>">error_outline</i>
	<i class="material-icons log-alert" style="display: none"
		 title="<?= __("Recent entries found in event log.") ?>" onclick="App.openPreferences('system')">warning</i>
	<a id="updates-available" target="_blank" rel="noopener noreferrer" href="" style="display: none">
		<i class="material-icons icon-new-version" title="<?= __('Updates for Tiny Tiny RSS are available.') ?>">new_releases</i>
	</a>
	<a id="plugin-updates-available" href="prefs.php" onclick="dijit.byId('pref-tabs').selectChild(dijit.byId('prefsTab')); return false" style="display: none">
		<i class="material-icons icon-new-version" title="<?= __('Updates for some local plugins are available.') ?>">new_releases</i>
	</a>
	<a href="#" onclick="document.location.href = 'index.php'"><?= __('Exit preferences') ?></a>
</div>

<div id="main" dojoType="dijit.layout.BorderContainer">
    <div dojoType="dijit.layout.TabContainer" region="center" id="pref-tabs">
        <div id="prefsTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=Pref_Prefs"
            title="<i class='material-icons'>settings</i> <?= __('Preferences') ?>"></div>
        <div id="feedsTab" dojoType="dijit.layout.ContentPane"
            href="backend.php?op=Pref_Feeds"
            title="<i class='material-icons'>rss_feed</i>  <?= __('Feeds') ?>"></div>
        <div id="filtersTab" dojoType="dijit.layout.ContentPane"
            style="padding : 0px"
            href="backend.php?op=Pref_Filters"
            title="<i class='material-icons'>filter_list1</i> <?= __('Filters') ?>"></div>
        <div id="labelsTab" dojoType="dijit.layout.ContentPane"
            style="padding : 0px"
            href="backend.php?op=Pref_Labels"
            title="<i class='material-icons'>label_outline1</i> <?= __('Labels') ?>"></div>
        <?php if ($_SESSION["access_level"] >= UserHelper::ACCESS_LEVEL_ADMIN) { ?>
            <div id="usersTab" dojoType="dijit.layout.ContentPane"
                style="padding : 0px"
                href="backend.php?op=Pref_Users"
                title="<i class='material-icons'>person</i> <?= __('Users') ?>"></div>
            <div id="systemTab" dojoType="dijit.layout.ContentPane"
                href="backend.php?op=Pref_System"
                title="<i class='material-icons'>info_outline</i> <?= __('System') ?>"></div>
        <?php } ?>
        <?php
            PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TABS);
        ?>
        </div>
		<div id="footer" dojoType="dijit.layout.ContentPane" region="bottom">
			<a class="text-muted" target="_blank" href="https://github.com/tt-rss/tt-rss">Tiny Tiny RSS</a>
				<span>v<?= Config::get_version_html() ?></span>
    </div>
</div>

</body>
</html>
<?php send_conditional_html((string) ob_get_clean());

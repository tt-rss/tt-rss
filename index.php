<?php
	require_once __DIR__ . '/include/autoload.php';
	require_once __DIR__ . '/include/sessions.php';

	Config::sanity_check();

	if (!init_plugins()) return;

	UserHelper::login_sequence();

	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
	<title>Tiny Tiny RSS</title>
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

	<style type="text/css">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $p) {
			$css = $p->get_css();
			if ($css) {
				echo $css;
			}
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
				{ name: "fox", location: "../../js" },
			]
		};
	</script>

	<?php
	foreach (["lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"js/tt-rss.js",
				"js/common.js"] as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

	<script type="text/javascript">
		require({cache:{}});
	</script>

	<script type="text/javascript">
	<?php
		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			$script = $p->get_js();

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
		<?= stylesheet_tag("themes/light.css") ?>

		<style type="text/css">
			body.css_loading noscript {
				display : block;
				margin : 16px;
			}
		</style>
	</noscript>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<meta name="referrer" content="no-referrer"/>
</head>

<body class="flat ttrss_main ttrss_index css_loading">

<noscript class="alert alert-error"><?= ('Javascript is disabled. Please enable it.') ?></noscript>

<div id="overlay">
	<div id="overlay_inner">
		<?= __("Loading, please wait...") ?>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
	</div>
</div>

<div id="notify" class="notify"></div>
<div id="cmdline" style="display : none"></div>

<div id="main" dojoType="dijit.layout.BorderContainer">
    <div id="feeds-holder" dojoType="dijit.layout.ContentPane" region="leading" style="width : 20%" splitter="true">
        <div id="feedlistLoading" class="text-center text-muted text-small">
			  	<img class="icon-three-dots" src="images/three-dots.svg?2">
				<?= __("Loading, please wait..."); ?>
			</div>
        <?php
			 PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_FEED_TREE, function ($result) {
				 echo $result;
			 });
        ?>
        <div id="feedTree"></div>
    </div>

    <div dojoType="dijit.layout.BorderContainer" region="center" id="content-wrap">
        <div id="toolbar-frame" dojoType="dijit.layout.ContentPane" region="top">
            <div id="toolbar" dojoType="fox.Toolbar">

			 	<!-- order 0, default -->

				<?php
					PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_MAIN_TOOLBAR_BUTTON, function ($result) {
						echo $result;
					});
				?>

				<!-- order 5: alert icons -->

						<i class="material-icons net-alert" style="display : none; order : 5"
							title="<?= __("Communication problem with server.") ?>">error_outline</i>

						<i class="material-icons log-alert" style="display : none; order : 5" onclick="App.openPreferences('system')"
							title="<?= __("Recent entries found in event log.") ?>">warning</i>

						<a id="updates-available" target="_blank" rel="noopener noreferrer" href="" style="display: none; order: 5">
							<i class="material-icons icon-new-version" title="<?= __('Updates for Tiny Tiny RSS are available.') ?>">new_releases</i>
						</a>

						<a id="plugin-updates-available" href="prefs.php?tab=prefs" style="display: none; order: 5">
							<i class="material-icons icon-new-version" title="<?= __('Updates for some local plugins are available.') ?>">new_releases</i>
						</a>

				<!-- order 10: headlines toolbar -->
				<div id="toolbar-headlines" dojoType="fox.Toolbar" style="order : 10"> </div>

				<!-- order 20: main toolbar contents (dropdowns) -->
				<form id="toolbar-main" dojoType="dijit.form.Form" action="" style="order : 20" onsubmit="return false">
					<select name="view_mode" title="<?= __('Show articles') ?>"
						onchange="Feeds.onViewModeChanged()"
						dojoType="fox.form.Select">
						<option selected="selected" value="adaptive"><?= __('Adaptive') ?></option>
						<option value="all_articles"><?= __('All Articles') ?></option>
						<option value="marked"><?= __('Starred') ?></option>
						<option value="published"><?= __('Published') ?></option>
						<option value="unread"><?= __('Unread') ?></option>
						<option value="has_note"><?= __('With Note') ?></option>
					</select>

					<select title="<?= __('Sort articles') ?>"
							onchange="Feeds.onViewModeChanged()"
							dojoType="fox.form.Select" name="order_by">

						<option selected="selected" value="default"><?= __('Default') ?></option>
							<option value="feed_dates"><?= __('Newest first') ?></option>
							<option value="date_reverse"><?= __('Oldest first') ?></option>
							<option value="title"><?= __('Title') ?></option>

						<?php
							PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_HEADLINES_CUSTOM_SORT_MAP, function ($result) {
								foreach ($result as $sort_value => $sort_title) {
									print "<option value=\"" . htmlspecialchars($sort_value) . "\">$sort_title</option>";
								}
							});
						?>
					</select>

					<select class="catchup-button" id="main-catchup-dropdown" dojoType="fox.form.Select"
						data-prevent-value-change="true">
						<option value=""><?= __('Mark as read') ?></option>
						<option value="1day"><?= __('Older than one day') ?></option>
						<option value="1week"><?= __('Older than one week') ?></option>
						<option value="2week"><?= __('Older than two weeks') ?></option>
					</select>
				</form>

				<!-- toolbar actions dropdown: order 30 -->

				<div class="action-chooser" style="order : 30">
					<?php
						PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_TOOLBAR_BUTTON, function ($result) {
							echo $result;
						});

						// hacky workaround for xgettext having difficulty extracting strings from 'JS in PHP' and 'PHP in JS in PHP'
						$switch_to_three_panel = json_encode(__('Switch to three panel view'));
						$switch_to_combined = json_encode(__('Switch to combined view'));
						$disable_widescreen = json_encode(__('Disable widescreen mode'));
						$enable_widescreen = json_encode(__('Enable widescreen mode'));
						$expand_selected = json_encode(__('Expand selected article only'));
						$expand_all = json_encode(__('Expand all articles'));
					?>

					<div dojoType="fox.form.DropDownButton" class="action-button" title="<?= __('Actions...') ?>">
						<span><i class="material-icons">menu</i></span>
						<div dojoType="dijit.Menu" style="display: none">
								<script type='dojo/method' event='onOpen' args='evt,a,b,c'>
									const widescreen = this.getChildren().find((m) => m.id == 'qmcToggleWidescreen');
									const expanded = this.getChildren().find((m) => m.id == 'qmcToggleExpanded');
									const combined = this.getChildren().find((m) => m.id == 'qmcToggleCombined');

									if (combined)
										combined.attr('label',
											App.isCombinedMode() ? <?= $switch_to_three_panel ?> : <?= $switch_to_combined ?>);

									if (widescreen)
										widescreen
											.attr('hidden', !!App.isCombinedMode())
											.attr('label',
												App.isWideScreenMode() ? <?= $disable_widescreen ?> : <?= $enable_widescreen ?>);

									if (expanded)
										expanded
											.attr('hidden', !App.isCombinedMode())
											.attr('label',
												App.isExpandedMode() ? <?= $expand_selected ?> : <?= $expand_all ?>);
								</script>

								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcPrefs')"><?= __('Preferences...') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcSearch')"><?= __('Search...') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcFilterFeeds')"><?= __('Search feeds...') ?></div>
								<div dojoType="dijit.MenuItem" disabled="1"><?= __('Feed actions:') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcAddFeed')"><?= __('Subscribe to feed...') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcEditFeed')"><?= __('Edit this feed...') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcRemoveFeed')"><?= __('Unsubscribe') ?></div>
								<div dojoType="dijit.MenuItem" disabled="1"><?= __('All feeds:') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcCatchupAll')"><?= __('Mark as read') ?></div>
								<div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcShowOnlyUnread')"><?= __('(Un)hide read feeds') ?></div>
								<div dojoType="dijit.MenuItem" disabled="1"><?= __('UI layout:') ?></div>
								<div dojoType="dijit.MenuItem" id="qmcToggleCombined" onclick="App.onActionSelected('qmcToggleCombined')"><?= __('Toggle combined mode') ?></div>
								<div dojoType="dijit.MenuItem" id="qmcToggleWidescreen" onclick="App.onActionSelected('qmcToggleWidescreen')">
									<?= __('Toggle widescreen mode') ?></div>
								<div dojoType="dijit.MenuItem" id="qmcToggleExpanded" onclick="App.onActionSelected('qmcToggleExpanded')">
									<?= __('Toggle expand all articles') ?></div>
								<div dojoType="dijit.MenuItem" disabled="1"><?= __('Other actions:') ?></div>
                        <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcHKhelp')"><?= __('Keyboard shortcuts help') ?></div>

                        <?php
									PluginHost::getInstance()->run_hooks_callback(PluginHost::HOOK_ACTION_ITEM, function ($result) {
										echo $result;
									});
                        ?>

                        <?php if (empty($_SESSION["hide_logout"])) { ?>
                            <div dojoType="dijit.MenuItem" onclick="App.onActionSelected('qmcLogout')"><?= __('Logout') ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div> <!-- toolbar -->
        </div> <!-- toolbar pane -->
        <div id="headlines-wrap-inner" dojoType="dijit.layout.BorderContainer" region="center">
            <div id="headlines-frame" dojoType="dijit.layout.ContentPane" tabindex="0"
                    region="center">
                <div id="headlinesInnerContainer">
                    <div class="whiteBox"><?= __('Loading, please wait...') ?></div>
                </div>
            </div>
            <div id="content-insert" dojoType="dijit.layout.ContentPane" region="bottom"
                style="height : 50%" splitter="true"></div>
        </div>
    </div>
</div>

</body>
</html>

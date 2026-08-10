<?php
abstract class Plugin {
	const API_VERSION_COMPAT = 1;

	/** @var PDO $pdo */
	protected $pdo;

	/**
	 * @param PluginHost $host
	 *
	 * @return void
	 * */
	abstract public function init($host);

	/** @return array<null|float|string|bool> */
	abstract public function about();
	// return array(1.0, "plugin", "No description", "No author", false);

	public function __construct() {
		$this->pdo = Db::pdo();
	}

	/** @return array<string,bool> */
	public function flags() {
		/* associative array, possible keys:
			needs_curl = boolean
		*/
		return [];
	}

	/**
	 * @param string $method
	 *
	 * @return bool */
	public function is_public_method($method) {
		return false;
	}

	/**
	 * @param string $method
	 *
	 * @return bool */
	public function csrf_ignore($method) {
		return false;
	}

	/** @return string */
	public function get_js() {
		return "";
	}

	/** @return string */
	public function get_login_js() {
		return "";
	}

	/** @return string */
	public function get_css() {
		return "";
	}

	/** @return string */
	public function get_prefs_js() {
		return "";
	}

	/** @return string */
	public function get_prefs_css() {
		return "";
	}

	/** @return int */
	public function api_version() {
		return Plugin::API_VERSION_COMPAT;
	}

	/* gettext-related helpers */

	/**
	 * @param string $msgid
	 *
	 * @return string */
	public function __($msgid) {
		// this is a strictly template-related hack
		return _dgettext(PluginHost::object_to_domain($this), $msgid);
	}

	/**
	 * @param string $singular
	 * @param string $plural
	 * @param int $number
	 *
	 * @return string */
	public function _ngettext($singular, $plural, $number) {
		// this is a strictly template-related hack
		return _dngettext(PluginHost::object_to_domain($this), $singular, $plural, $number);
	}

	/** @return string */
	public function T_sprintf() {
		$args = func_get_args();
		$msgid = array_shift($args);

		return vsprintf($this->__($msgid), $args);
	}

	/* plugin hook methods */

	/* GLOBAL hooks are invoked in global context, only available to system plugins (loaded via .env for all users) */

	/** Adds buttons for article (on the right) - e.g. mail, share, add note. Generated markup must be valid XML.
	 * @param array<string,mixed> $line
	 * @return string
	 * @see PluginHost::HOOK_ARTICLE_BUTTON
	 * @see Plugin::hook_article_left_button()
	 */
	public function hook_article_button($line) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows plugins to alter article data as gathered from feed XML, i.e. embed images, get full text content, etc.
	 * @param array<string,mixed> $article
	 * @return array<string,mixed>
	 * @see PluginHost::HOOK_ARTICLE_FILTER
	 */
	public function hook_article_filter($article) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Allow adding new UI elements (e.g. accordion panes) to (top) tab contents in Preferences
	 * @param string $tab
	 * @return void
	 * @see PluginHost::HOOK_PREFS_TAB
	 */
	public function hook_prefs_tab($tab) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Allow adding new content to various sections of preferences UI (i.e. OPML import/export pane)
	 * @param string $section
	 * @return void
	 * @see PluginHost::HOOK_PREFS_TAB_SECTION
	 */
	public function hook_prefs_tab_section($section) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Allows adding new (top) tabs in preferences UI
	 * @return void
	 * @see PluginHost::HOOK_PREFS_TABS
	*/
	public function hook_prefs_tabs() {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Invoked when feed XML is processed by FeedParser class
	 * @param FeedParser $parser
	 * @param int $feed_id
	 * @return void
	 * @see PluginHost::HOOK_FEED_PARSED
	 */
	public function hook_feed_parsed($parser, $feed_id) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** GLOBAL: Invoked when a feed update task finishes
	 * @param array<string,string> $cli_options
	 * @return void
	 * @see PluginHost::HOOK_UPDATE_TASK
	 */
	public function hook_update_task($cli_options) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** This is a pluginhost compatibility wrapper that invokes $this->authenticate(...$args) (Auth_Base)
	 * @param string $login
	 * @param string $password
	 * @param string $service
	 * @return int|false user_id
	 * @see PluginHost::HOOK_AUTH_USER
	 */
	public function hook_auth_user($login, $password, $service = '') {
		user_error("Dummy method invoked.", E_USER_ERROR);
		return false;
	}

	/** IAuthModule only
	 * @param string $login
	 * @param string $password
	 * @param string $service
	 * @return int|false user_id
	 */
	public function authenticate($login, $password, $service = '') {
		user_error("Dummy method invoked.", E_USER_ERROR);
		return false;
	}

	/** Allows plugins to modify global hotkey map (hotkey sequence -> action)
	 * @param array<string, string> $hotkeys
	 * @return array<string, string>
	 * @see PluginHost::HOOK_HOTKEY_MAP
	 * @see Plugin::hook_hotkey_info()
	 */
	public function hook_hotkey_map($hotkeys) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Invoked when article is rendered by backend (before it gets passed to frontent JS code) - three panel mode
	 * @param array<string, mixed> $article
	 * @return array<string, mixed>
	 * @see PluginHost::HOOK_RENDER_ARTICLE
	 */
	public function hook_render_article($article) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Invoked when article is rendered by backend (before it gets passed to frontent JS code) - combined mode
	 * @param array<string, mixed> $article
	 * @return array<string, mixed>
	 * @see PluginHost::HOOK_RENDER_ARTICLE_CDM
	 */
	public function hook_render_article_cdm($article) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Invoked when raw feed XML data has been successfully downloaded (but not parsed yet)
	 * @param string $feed_data
	 * @param string $fetch_url
	 * @param int $owner_uid
	 * @param int $feed
	 * @return string
	 * @see PluginHost::HOOK_FEED_FETCHED
	 */
	public function hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked on article content when it is sanitized (i.e. potentially harmful tags removed)
	 * @param DOMDocument $doc
	 * @param string $site_url
	 * @param array<string> $allowed_elements
	 * @param array<string> $disallowed_attributes
	 * @param int $article_id
	 * @return DOMDocument|array<int,DOMDocument|array<string>>
	 * @see PluginHost::HOOK_SANITIZE
	 */
	public function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return $doc;
	}

	/** Invoked when article is rendered by backend (before it gets passed to frontent JS code) - exclusive to API clients
	 * @param array{'article': array<string,mixed>|null, 'headline': array<string,mixed>|null} $params
	 * @return array<string, string>
	 * @see PluginHost::HOOK_RENDER_ARTICLE_API
	 */
	public function hook_render_article_api($params) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Allows adding new UI elements to tt-rss main toolbar (to the right, before Actions... dropdown)
	 * @return string
	 * @see PluginHost::HOOK_TOOLBAR_BUTTON
	 */
	public function hook_toolbar_button() {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding new items to tt-rss main Actions... dropdown menu
	 * @return string
	 * @see PluginHost::HOOK_ACTION_ITEM
	 */
	public function hook_action_item() {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding new UI elements to the toolbar area related to currently loaded feed headlines
	 * @param int $feed_id
	 * @param bool $is_cat
	 * @return string
	 * @see PluginHost::HOOK_HEADLINE_TOOLBAR_BUTTON
	 */
	public function hook_headline_toolbar_button($feed_id, $is_cat) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding new hotkey action names and descriptions
	 * @param array<string, array<string, string>> $hotkeys
	 * @return array<string, array<string, string>>
	 * @see PluginHost::HOOK_HOTKEY_INFO
	 * @see Plugin::hook_hotkey_map()
	 */
	public function hook_hotkey_info($hotkeys) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Adds per-article buttons on the left side. Generated markup must be valid XML.
	 * @param array<string,mixed> $row
	 * @return string
	 * @see PluginHost::HOOK_ARTICLE_LEFT_BUTTON
	 * @see Plugin::hook_article_button()
	 */
	public function hook_article_left_button($row) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding new UI elements to the "Plugins" tab of the feed editor UI
	 * @param int $feed_id
	 * @return void
	 * @see PluginHost::HOOK_PREFS_EDIT_FEED
	 */
	public function hook_prefs_edit_feed($feed_id) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Invoked when data is saved in the feed editor
	 * @param int $feed_id
	 * @return void
	 * @see PluginHost::HOOK_PREFS_SAVE_FEED
	 */
	public function hook_prefs_save_feed($feed_id) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Allows overriding built-in fetching mechanism for feeds, substituting received data if necessary
	 * (i.e. origin site doesn't actually provide any RSS feeds), or XML is invalid
	 * @param string $feed_data
	 * @param string $fetch_url
	 * @param int $owner_uid
	 * @param int $feed
	 * @param int $last_article_timestamp
	 * @param string $auth_login
	 * @param string $auth_pass
	 * @return string (possibly mangled feed data)
	 * @see PluginHost::HOOK_FETCH_FEED
	 */
	public function hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked when headlines data ($row) has been retrieved from the database
	 * @param array<string,mixed> $row
	 * @param int $excerpt_length
	 * @return array<string,mixed>
	 * @see PluginHost::HOOK_QUERY_HEADLINES
	 */
	public function hook_query_headlines($row, $excerpt_length) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** This is run periodically by the update daemon when idle (available both to user and system plugins)
	 * @return void
	 * @see PluginHost::HOOK_HOUSE_KEEPING */
	public function hook_house_keeping() {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Allows overriding built-in article search
	 * @param string $query
	 * @return array<int, string|array<string>> - list(SQL search query, highlight keywords)
	 * @see PluginHost::HOOK_SEARCH
	 */
	public function hook_search($query) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Invoked when enclosures are rendered to HTML (when article itself is rendered)
	 * @param string $enclosures_formatted
	 * @param array<int, array<string, mixed>> $enclosures
	 * @param int $article_id
	 * @param bool $always_display_enclosures
	 * @param string $article_content
	 * @param bool $hide_images
	 * @return string|array<string,array<int, array<string, mixed>>> ($enclosures_formatted, $enclosures)
	 * @see PluginHost::HOOK_FORMAT_ENCLOSURES
	 */
	public function hook_format_enclosures($enclosures_formatted, $enclosures, $article_id, $always_display_enclosures, $article_content, $hide_images) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked during feed subscription (after data has been fetched)
	 * @param string $contents
	 * @param string $url
	 * @param string $auth_login
	 * @param string $auth_pass
	 * @return string (possibly mangled feed data)
	 * @see PluginHost::HOOK_SUBSCRIBE_FEED
	 */
	public function hook_subscribe_feed($contents, $url, $auth_login, $auth_pass) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/**
	 * @param int $feed
	 * @param bool $is_cat
	 * @param array<string,mixed> $qfh_ret (headlines object)
	 * @return string
	 * @see PluginHost::HOOK_HEADLINES_BEFORE
	 */
	public function hook_headlines_before($feed, $is_cat, $qfh_ret) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param int $article_id
	 * @param array<string,mixed> $rv
	 * @return string
	 * @see PluginHost::HOOK_RENDER_ENCLOSURE
	 */
	public function hook_render_enclosure($entry, $article_id, $rv) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/**
	 * @param array<string,mixed> $article
	 * @param string $action
	 * @return array<string,mixed> ($article)
	 * @see PluginHost::HOOK_ARTICLE_FILTER_ACTION
	 */
	public function hook_article_filter_action($article, $action) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/**
	 * @param array<string,mixed> $line
	 * @param int $feed
	 * @param bool $is_cat
	 * @param int $owner_uid
	 * @return array<string,mixed> ($line)
	 * @see PluginHost::HOOK_ARTICLE_EXPORT_FEED
	 */
	public function hook_article_export_feed($line, $feed, $is_cat, $owner_uid) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Allows adding custom buttons to tt-rss main toolbar (left side)
	 * @return void
	 * @see PluginHost::HOOK_MAIN_TOOLBAR_BUTTON
	*/
	public function hook_main_toolbar_button() {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Invoked for every enclosure entry as article is being rendered
	 * @param array<string,string> $entry
	 * @param int $id article id
	 * @param array{'formatted': string, 'entries': array<int, array<string, mixed>>} $rv
	 * @return array<string,string> ($entry)
	 * @see PluginHost::HOOK_ENCLOSURE_ENTRY
	 */
	public function hook_enclosure_entry($entry, $id, $rv) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [];
	}

	/** Share plugins run this when article is being rendered as HTML for sharing
	 * @param string $html
	 * @param array<string,mixed> $row
	 * @return string ($html)
	 * @see PluginHost::HOOK_FORMAT_ARTICLE
	 */
	public function hook_format_article($html, $row) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked when basic feed information (title, site_url) is being collected, useful to override default if feed doesn't provide anything (or feed itself is synthesized)
	 * @param array{"title": string, "site_url": string} $basic_info
	 * @param string $fetch_url
	 * @param int $owner_uid
	 * @param int $feed_id
	 * @param string $auth_login
	 * @param string $auth_pass
	 * @return array{"title": string, "site_url": string}
	 * @see PluginHost::HOOK_FEED_BASIC_INFO
	 */
	public function hook_feed_basic_info($basic_info, $fetch_url, $owner_uid, $feed_id, $auth_login, $auth_pass) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return $basic_info;
	}

	/** Invoked when file (e.g. cache entry, static data) is being sent to client, may override default mechanism
	 * using faster httpd-specific implementation (see nginx_xaccel)
	 * @param string $filename The full path of the file to send.
	 * @return bool
	 * @see PluginHost::HOOK_SEND_LOCAL_FILE
	 */
	public function hook_send_local_file($filename) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return false;
	}

	/** Invoked when user tries to unsubscribe from a feed, returning true would prevent any further default actions
	 * @param int $feed_id
	 * @param int $owner_uid
	 * @return bool
	 * @see PluginHost::HOOK_UNSUBSCRIBE_FEED
	 */
	public function hook_unsubscribe_feed($feed_id, $owner_uid) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return false;
	}

	/** Invoked when mail is being sent (if no hooks are registered, tt-rss uses PHP mail() as a fallback)
	 * @param Mailer $mailer
	 * @param array<string,mixed> $params
	 * @return int
	 * @see PluginHost::HOOK_SEND_MAIL
	 */
	public function hook_send_mail($mailer, $params) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return -1;
	}

	/**
	 * Invoked when filtering is triggered on an article. May be used to implement logging for filters, etc.
	 * @param int $feed_id
	 * @param int $owner_uid
	 * @param array<string,mixed> $article
	 * @param array<string,mixed> $matched_filters
	 * @param array<string,string|bool|int> $matched_rules
	 * @param array<int, array{'type': string, 'param': string}> $article_filter_actions An array of filter actions from matched filters
	 * @return void
	 * @see PluginHost::HOOK_FILTER_TRIGGERED
	 */
	public function hook_filter_triggered($feed_id, $owner_uid, $article, $matched_filters, $matched_rules, $article_filter_actions) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Plugins may provide this to allow getting full article text (af_readbility implements this)
	 * @param string $url
	 * @return string|false
	 * @see PluginHost::HOOK_GET_FULL_TEXT
	 */
	public function hook_get_full_text($url) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked when article flavor image is being determined, allows overriding default selection logic
	 * @param array<string,string> $enclosures
	 * @param string $content
	 * @param string $site_url
	 * @param array<string,mixed> $article
	 * @return string|array<int,string>
	 * @see PluginHost::HOOK_ARTICLE_IMAGE
	 */
	public function hook_article_image($enclosures, $content, $site_url, $article) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding arbitrary elements before feed tree
	 * @return string HTML
	 * @see PluginHost::HOOK_FEED_TREE
	 * */
	public function hook_feed_tree() {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked for every iframe to determine if it is allowed to be displayed
	 * @param string $url
	 * @return bool
	 * @see PluginHost::HOOK_IFRAME_WHITELISTED
	 */
	public function hook_iframe_whitelisted($url) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return false;
	}

	/**
	 * @param object $enclosure
	 * @param int $feed
	 * @return object ($enclosure)
	 * @see PluginHost::HOOK_ENCLOSURE_IMPORTED
	 */
	public function hook_enclosure_imported($enclosure, $feed) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return $enclosure;
	}

	/** Allows adding custom elements to headline sort dropdown (name -> caption)
	 * @return array<string,string>
	 * @see PluginHost::HOOK_HEADLINES_CUSTOM_SORT_MAP
	 */
	public function hook_headlines_custom_sort_map() {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return ["" => ""];
	}

	/** Allows overriding headline sorting (or provide custom sort methods)
	 * @param string $order
	 * @return array<int, string|bool> -- (query, skip_first_id)
	 * @see PluginHost::HOOK_HEADLINES_CUSTOM_SORT_OVERRIDE
	 */
	public function hook_headlines_custom_sort_override($order) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return ["", false];
	}

	/** Allows adding custom elements to headlines Select... dropdown
	 * @deprecated removed, see Plugin::hook_headline_toolbar_select_menu_item2()
	 * @param int $feed_id
	 * @param int $is_cat
	 * @return string
	 * @see PluginHost::HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM
	 */
	public function hook_headline_toolbar_select_menu_item($feed_id, $is_cat) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Allows adding custom elements to headlines Select... select dropdown (<option> format)
	 * @param int $feed_id
	 * @param int $is_cat
	 * @return string
	 * @see PluginHost::HOOK_HEADLINE_TOOLBAR_SELECT_MENU_ITEM2
	 */
	public function hook_headline_toolbar_select_menu_item2($feed_id, $is_cat) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return "";
	}

	/** Invoked when user tries to subscribe to feed, may override information (i.e. feed URL) used afterwards
	 * @param string $url
	 * @param string $auth_login
	 * @param string $auth_pass
	 * @return bool
	 * @see PluginHost::HOOK_PRE_SUBSCRIBE
	 */
	public function hook_pre_subscribe(&$url, $auth_login, $auth_pass) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return false;
	}

	/** Invoked after user logout, may override built-in behavior (redirect back to login page)
	 *  @param string $login
	 *  @param int $user_id
	 * @return array<mixed> - [0] - if set, url to redirect to
	*/
	public function hook_post_logout($login, $user_id) {
		user_error("Dummy method invoked.", E_USER_ERROR);

		return [""];
	}

	/** Adds buttons to the right of default Login button
	 * @return void
	 */
	public function hook_loginform_additional_buttons() {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Returns false if session is considered invalid, true cascades to next handler */
	public function hook_validate_session(): bool {
		user_error("Dummy method invoked.", E_USER_ERROR);
		return false;
	}

	/** Invoked after passed article IDs were either marked (i.e. starred) or unmarked.
	 *
	 * **Note** resulting state of the articles is not passed to this function (because
	 * tt-rss may do invert operation on ID range), you will need to get this from the database.
	 * @param array<int> $article_ids ref_ids
	 * @return void
	 */
	public function hook_articles_mark_toggled(array $article_ids) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}

	/** Invoked after passed article IDs were either published or unpublished.
	 *
	 * **Note** resulting state of the articles is not passed to this function (because
	 * tt-rss may do invert operation on ID range), you will need to get this from the database.
	 *
	 * @param array<int> $article_ids ref_ids
	 * @return void
	 */
	public function hook_articles_publish_toggled(array $article_ids) {
		user_error("Dummy method invoked.", E_USER_ERROR);
	}
}

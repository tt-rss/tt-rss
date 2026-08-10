<?php
class Share extends Plugin {
	/** @var PluginHost $host */
	private $host;

	public function about() {
		return [null,
			"Share article by unique URL",
			"fox"];
	}

	public function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
		$host->add_hook($host::HOOK_PREFS_TAB_SECTION, $this);
	}

	public function is_public_method($method) {
		return $method == "get";
	}

	public function get_js() {
		return file_get_contents(__DIR__ . "/share.js");
	}

	public function get_css() {
		return file_get_contents(__DIR__ . "/share.css");
	}

	public function get_prefs_js() {
		return file_get_contents(__DIR__ . "/share_prefs.js");
	}

	/** @return void */
	public function unshare() {
		$id = $_REQUEST['id'];

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = '' WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		print __("Article unshared");
	}

	/**
	 * @param string $id
	 * @return void */
	public function hook_prefs_tab_section($id) {
		if ($id == "prefFeedsPublishedGenerated") {
			?>
			<hr/>

			<?= format_notice("You can disable all articles shared by unique URLs here.") ?></h2>

			<button class='alt-danger' dojoType='dijit.form.Button' onclick="return Plugins.Share.clearKeys()">
				<?= \Controls\icon('delete') ?>
				<?= __('Unshare all articles') ?></button>
			<?php
		}
	}

	/** @return void */
	public function clearArticleKeys() {
		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = '' WHERE
			owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);

		print __("Shared URLs cleared.");
	}

	/** @return void */
	public function newkey() {
		$id = $_REQUEST['id'];
		$uuid = uniqid_short();

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = ? WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$uuid, $id, $_SESSION['uid']]);

		print json_encode(["link" => $uuid]);
	}

	/**
	 * @param array<string,mixed> $line
	 *
	 * @return string */
	public function hook_article_button($line) {
		$icon_class = !empty($line['uuid']) ? "is-shared" : "";

		return "<i class='material-icons icon-share share-icon-".$line['int_id']." $icon_class'
			style='cursor : pointer' onclick=\"Plugins.Share.shareArticle(".$line['int_id'].")\"
			title=\"".__('Share by URL')."\">link</i>";
	}

	/** @return void */
	public function get() {
		$uuid = clean($_REQUEST["key"] ?? "");

		if ($uuid) {
			$sth = $this->pdo->prepare("SELECT ref_id, owner_uid
						FROM ttrss_user_entries WHERE uuid = ?");
			$sth->execute([$uuid]);

			if ($row = $sth->fetch()) {
				header("Content-Type: text/html");

				$id = $row["ref_id"];
				$owner_uid = $row["owner_uid"];

				$this->format_article($id, $owner_uid);

				return;
			}
		}

		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		print "Article not found.";
	}

	private function format_article(int $id, int $owner_uid) : void {

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id,title,link,content,feed_id,comments,int_id,lang,
			SUBSTRING_FOR_DATE(updated,1,16) as updated,
			(SELECT site_url FROM ttrss_feeds WHERE id = feed_id) as site_url,
			(SELECT title FROM ttrss_feeds WHERE id = feed_id) as feed_title,
			(SELECT hide_images FROM ttrss_feeds WHERE id = feed_id) as hide_images,
			(SELECT always_display_enclosures FROM ttrss_feeds WHERE id = feed_id) as always_display_enclosures,
			num_comments,
			tag_cache,
			author,
			guid,
			note
			FROM ttrss_entries,ttrss_user_entries
			WHERE	id = ? AND ref_id = id AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($line = $sth->fetch()) {

			$line["tags"] = Article::_get_tags($id, $owner_uid, $line["tag_cache"]);
			unset($line["tag_cache"]);

			$line["content"] = Sanitizer::sanitize($line["content"],
				$line['hide_images'],
				$owner_uid, $line["site_url"], null, $line["id"]);

			PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_RENDER_ARTICLE,
				function ($result) use (&$line) {
					$line = $result;
				},
				$line);

			$enclosures = Article::_get_enclosures($line["id"]);
			[$og_image, $og_stream] = Article::_get_image($enclosures, $line['content'], $line["site_url"] ?? "", $line);

			$content_decoded = html_entity_decode($line["title"], ENT_NOQUOTES | ENT_HTML401);
			$parsed_updated = TimeHelper::make_local_datetime($line["updated"], true, $owner_uid, true);

			$line['content'] = DiskCache::rewrite_urls($line['content']);

			if (!$og_image)
				$og_image = Config::get_self_url() . "/images/favicon-512px.png";

			ob_start();

			?>
			<!DOCTYPE html>
			<html>
				<head>
					<meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
					<title><?= $line["title"] ?></title>
					<?= javascript_tag("js/common.js") ?>
					<?= javascript_tag("js/utility.js") ?>
					<style type='text/css'>
						@media (prefers-color-scheme: dark) {
						body {
							background : #222;
						}
					}
					body.css_loading * {
						display : none;
					}
					</style>
					<link rel='shortcut icon' type='image/png' href='images/favicon.png'>
					<link rel='icon' type='image/png' sizes='72x72' href='images/favicon-72px.png'>

					<meta property='og:title' content="<?= htmlspecialchars($content_decoded) ?>">
					<meta property='og:description' content="<?= htmlspecialchars(
						truncate_string(
							preg_replace("/[\r\n\t]/", "",
							preg_replace("/ {1,}/", " ",
								strip_tags($content_decoded)
							)
						), 500, "...")) ?>">
					<meta property='og:image' content="<?= htmlspecialchars($og_image) ?>">
					<?= Config::get_override_links() ?>
				</head>

				<body class='flat ttrss_utility ttrss_zoom css_loading'>
					<div class='container'>

					<div class='content post'>
						<div class='header'>
							<div class='row'>
								<?php if (!empty($line["link"])) { ?>
									<h1>
										<a rel='noopener noreferrer'
											href="<?= htmlspecialchars($line["link"]) ?>"><?= htmlspecialchars($line["title"]) ?></a>
									</h1>
								<?php } else { ?>
									<h1><?= $line["title"] ?></h1>
								<?php } ?>
							</div>
							<div class='row'>
								<div><?= $line['author'] ?></div>
								<div><?= $parsed_updated ?></div>
							</div>
						</div>

						<div class='content' lang="<?= $line['lang'] ?: "en" ?>">
							<?= $line["content"] ?>
						</div>
					</div>
				</body>
			</html>
			<?php

			$rv = ob_get_contents();
			ob_end_clean();

			PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_FORMAT_ARTICLE,
			function ($result) use (&$rv) {
				$rv = $result;
			},
			$rv, $line);

			print $rv;
		}
	}

	/** @return void */
	public function shareDialog() {
		$id = (int)clean($_REQUEST['id'] ?? 0);

		$sth = $this->pdo->prepare("SELECT uuid FROM ttrss_user_entries WHERE int_id = ?
			AND owner_uid = ?");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {
			$uuid = $row['uuid'];

			if (!$uuid) {
				$uuid = uniqid_short();

				$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET uuid = ? WHERE int_id = ?
					AND owner_uid = ?");
				$sth->execute([$uuid, $id, $_SESSION['uid']]);
			}

			$url_path = $this->host->get_public_method_url($this, "get", ["key" => $uuid]);
			?>

			<header><?= __("You can share this article by the following unique URL:") ?></header>

			<section>
				<div class='panel text-center'>
					<a class='target-url' href="<?= htmlspecialchars($url_path) ?>"
						target='_blank' rel='noopener noreferrer'><?= htmlspecialchars($url_path) ?></a>
				</div>
			</section>

			<?php

		} else {
			print format_error(__("Article not found."));
		}

		?>
		<footer class='text-center'>
			<?= \Controls\button_tag(__('Unshare article'), '', ['class' => 'alt-danger', 'onclick' => "App.dialogOf(this).unshare()"]) ?>
			<?= \Controls\button_tag(__('Generate new URL'), '', ['onclick' => "App.dialogOf(this).newurl()"]) ?>
			<?= \Controls\submit_tag(__("Close this window")) ?>
		</footer>
		<?php
	}

	/** @return int */
	public function api_version() {
		return 2;
	}

}

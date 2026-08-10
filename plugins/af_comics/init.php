<?php
class Af_Comics extends Plugin {

	/** @var array<object> $filters */
	private $filters = [];

	public function about() {
		return [null,
			"Fixes RSS feeds of assorted comic strips",
			"fox"];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_FETCH_FEED, $this);
		$host->add_hook($host::HOOK_FEED_BASIC_INFO, $this);
		$host->add_hook($host::HOOK_SUBSCRIBE_FEED, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);

		require_once __DIR__ . "/filter_base.php";

		$filters = [
			...(glob(__DIR__ . "/filters.local/*.php") ?: []),
			...(glob(__DIR__ . "/filters/*.php") ?: []),
		];
		$names = [];

		foreach ($filters as $file) {
			$filter_name = preg_replace("/\..*$/", "", basename($file));

			if (!in_array($filter_name, $names)) {
				if (!class_exists($filter_name)) {
					require_once $file;
				}

				$names[] = $filter_name;

				$filter = new $filter_name($host);

				if (is_subclass_of($filter, "Af_ComicFilter")) {
					$this->filters[] = $filter;
					$names[] = $filter_name;
				}
			}
		}
	}

	public function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		$comics = [];

		foreach ($this->filters as $f) {
			foreach ($f->supported() as $comic)
				$comics[] = $comic;
		}

		asort($comics);

		?>
		<div dojoType="dijit.layout.AccordionPane"
			title="<i class='material-icons'>photo</i> <?= __('Feeds supported by af_comics') ?>">

			<h3><?= __("The following comics are currently supported:") ?></h3>

			<ul class='panel panel-scrollable list list-unstyled'>
				<?php foreach ($comics as $comic) { ?>
					<li><?= htmlspecialchars($comic) ?></li>
				<?php } ?>
			</ul>

			<?= format_notice("To subscribe to GoComics use the comic's regular web page as the feed URL (e.g. for the <em>Garfield</em> comic use <code>http://www.gocomics.com/garfield</code>).") ?>
			<?= format_notice('Drop any updated filters into <code>filters.local</code> in plugin directory.') ?>
		</div>
		<?php
	}

	public function hook_article_filter($article) {
		foreach ($this->filters as $f) {
			if ($f->process($article))
				break;
		}

		return $article;
	}

	public function hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass) {
		foreach ($this->filters as $f) {
			$res = $f->on_fetch($fetch_url);

			if ($res)
				return $res;
		}

		return $feed_data;
	}

	public function hook_subscribe_feed($contents, $url, $auth_login, $auth_pass) {
		foreach ($this->filters as $f) {
			$res = $f->on_subscribe($url);

			if ($res)
				return $res;
		}

		return $contents;
	}

	public function hook_feed_basic_info($basic_info, $fetch_url, $owner_uid, $feed, $auth_login, $auth_pass) {
		foreach ($this->filters as $f) {
			$res = $f->on_basic_info($fetch_url);

			if ($res)
				return $res;
		}

		return $basic_info;
	}

	public function api_version() {
		return 2;
	}

}

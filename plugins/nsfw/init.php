<?php
class NSFW extends Plugin {

	/** @var PluginHost $host */
	private $host;

	public function about() {
		return [null,
			"Hide article content based on tags",
			"fox",
			false];
	}

	public function init($host) {
		$this->host = $host;

		$host->add_hook(PluginHost::HOOK_RENDER_ARTICLE, $this);
		$host->add_hook(PluginHost::HOOK_RENDER_ARTICLE_CDM, $this);
		$host->add_hook(PluginHost::HOOK_RENDER_ARTICLE_API, $this);
		$host->add_hook(PluginHost::HOOK_ARTICLE_IMAGE, $this);
		$host->add_hook(PluginHost::HOOK_PREFS_TAB, $this);

	}

	public function hook_article_image($enclosures, $content, $site_url, $article) {
		$tags = explode(",", $this->host->get($this, "tags"));
		$article_tags = $article["tags"];

		if (count(array_intersect($tags, $article_tags)) > 0) {
			return [Config::get_self_url() . "/plugins/nsfw/nsfw.png", "", "nsfw"];
		} else {
			return ["", "", $content];
		}
	}

	/**
	 * @param array<string, mixed> $article
	 * @return array<string,mixed>
	 * @throws PDOException
	 */
	private function rewrite_contents(array $article) : array {
		$tags = explode(",", $this->host->get($this, "tags"));
		$article_tags = $article["tags"];

		if (count(array_intersect($tags, $article_tags)) > 0) {
			$article["content"] = "<details class='nsfw'><summary>" . __("Not safe for work (click to toggle)") . "</summary>" . $article["content"] . "</details>";
		}

		return $article;
	}

	public function get_css() {
		return
			'details.nsfw {
				cursor : pointer;
				user-select : none;
			}';
	}

	public function hook_render_article_api($row) {
		$article = $row['headline'] ?? $row['article'];
		return $this->rewrite_contents($article);
	}

	public function hook_render_article($article) {
		return $this->rewrite_contents($article);
	}

	public function hook_render_article_cdm($article) {
		return $this->rewrite_contents($article);
	}

	public function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		$tags = $this->host->get($this, "tags");

		?>
		<div dojoType="dijit.layout.AccordionPane"
			title="<i class='material-icons'>extension</i> <?= __("NSFW Plugin") ?>">
			<form dojoType="dijit.form.Form">

				<?= \Controls\pluginhandler_tags($this, "save") ?>

				<script type="dojo/method" event="onSubmit" args="evt">
					evt.preventDefault();
					if (this.validate()) {
						Notify.progress('Saving data...', true);
						xhr.post("backend.php", this.getValues(), (reply) => {
							Notify.info(reply);
						})
					}
				</script>

				<header><?= __("Tags to consider NSFW (comma-separated):") ?></header>

				<fieldset>
					<textarea dojoType='dijit.form.SimpleTextarea' rows='4'
							style='width: 500px; font-size : 12px;'
							name='tags'><?= $tags ?></textarea>
				</fieldset>

				<hr/>

				<?= \Controls\submit_tag(__("Save")) ?>
			</form>
		</div>
		<?php
	}

	public function save() : void {
		$tags = implode(", ",
			FeedItem_Common::normalize_categories(explode(",", $_POST["tags"] ?? "")));

		$this->host->set($this, "tags", $tags);

		echo __("Configuration saved.");
	}

	public function api_version() {
		return 2;
	}
}


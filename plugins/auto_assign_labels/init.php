<?php
class Auto_Assign_Labels extends Plugin {

	public function about() {
		return [null,
			"Assign labels automatically based on article title, content, and tags",
			"fox"];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	/**
	 * @param int $owner_uid
	 * @return array<int, array{0: int, 1: string, 2: string, 3: string}> feed ID, caption, foreground color, background color
	 * @throws PDOException
	 */
	private function get_all_labels_filter_format(int $owner_uid) : array {
		$rv = [];

		// TODO: use Labels::get_all()
		$sth = $this->pdo->prepare('SELECT id, fg_color, bg_color, caption FROM ttrss_labels2 WHERE owner_uid = ?');
		$sth->execute([$owner_uid]);

		while ($line = $sth->fetch()) {
			$rv[] = [
				Labels::label_to_feed_id($line['id']),
				$line['caption'],
				$line['fg_color'],
				$line['bg_color'],
			];
		}

		return $rv;
	}


	public function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];
		$labels = $this->get_all_labels_filter_format($owner_uid);
		$tags_str = join(",", $article["tags"]);

		foreach ($labels as $label) {
			$caption = preg_quote($label[1], "/");

			if ($caption && preg_match("/\b$caption\b/i", "$tags_str " . strip_tags($article["content"]) . " " . $article["title"])) {
				if (!RSSUtils::labels_contains_caption($article['labels'], $caption))
					$article['labels'][] = $label;
			}
		}

		return $article;
	}

	public function api_version() {
		return 2;
	}
}

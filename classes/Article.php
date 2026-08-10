<?php
class Article extends Handler_Protected {
	const ARTICLE_KIND_ALBUM = 1;
	const ARTICLE_KIND_VIDEO = 2;
	const ARTICLE_KIND_YOUTUBE = 3;

	const CATCHUP_MODE_MARK_AS_READ = 0;
	const CATCHUP_MODE_MARK_AS_UNREAD = 1;
	const CATCHUP_MODE_TOGGLE = 2;

	public function redirect(): void {
		$article = ORM::for_table('ttrss_entries')
			->table_alias('e')
			->join('ttrss_user_entries', [ 'ref_id', '=', 'e.id'], 'ue')
			->where('ue.owner_uid', $_SESSION['uid'])
			->find_one((int)$_REQUEST['id']);

		if ($article) {
			$article_url = UrlHelper::validate($article->link);

			if ($article_url) {
				header("Location: $article_url");
				return;
			}

			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
			print 'Article URL did not pass validation.  No redirection performed.';
			return;
		}

		header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		print 'Article not found.';
	}

	public static function _create_published_article(string $title, string $url, string $content, string $labels_str, int $owner_uid): bool {

		$guid = 'SHA1:' . sha1("ttshared:" . $url . $owner_uid); // include owner_uid to prevent global GUID clash

		if (!$content) {
			$pluginhost = new PluginHost();
			$pluginhost->load_all(PluginHost::KIND_ALL, $owner_uid);
			//$pluginhost->load_data();

			$pluginhost->run_hooks_callback(PluginHost::HOOK_GET_FULL_TEXT,
				function ($result) use (&$content) {
					if ($result) {
						$content = $result;
						return true;
					}
				},
				$url);
		}

		$content_hash = sha1($content);

		$labels = $labels_str == '' ? [] : explode(',', $labels_str);

		$rc = false;

		if (!$title) {
			if ($url)
				$title = $url;
			else
				return false;
		}

		if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;

		$pdo = Db::pdo();

		$pdo->beginTransaction();

		// only check for our user data here, others might have shared this with different content etc
		$sth = $pdo->prepare("SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			guid = ? AND ref_id = id AND owner_uid = ? LIMIT 1");
		$sth->execute([$guid, $owner_uid]);

		if ($row = $sth->fetch()) {
			$ref_id = $row['id'];

			$sth = $pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = ? AND owner_uid = ? LIMIT 1");
            $sth->execute([$ref_id, $owner_uid]);

			if ($row = $sth->fetch()) {
				$int_id = $row['int_id'];

				$sth = $pdo->prepare("UPDATE ttrss_entries SET
					content = ?, content_hash = ? WHERE id = ?");
				$sth->execute([$content, $content_hash, $ref_id]);

				$sth = $pdo->prepare("UPDATE ttrss_entries
				SET tsvector_combined = to_tsvector( :ts_content)
				WHERE id = :id");
				$params = [
					":ts_content" => mb_substr(\Soundasleep\Html2Text::convert($content), 0, 900000),
					":id" => $ref_id];
				$sth->execute($params);

				$sth = $pdo->prepare("UPDATE ttrss_user_entries SET published = true,
						last_published = NOW() WHERE
						int_id = ? AND owner_uid = ?");
				$sth->execute([$int_id, $owner_uid]);

				PluginHost::getInstance()->run_hooks(PluginHost::HOOK_ARTICLES_PUBLISH_TOGGLED, [$ref_id]);

			} else {

				$sth = $pdo->prepare("INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					(?, '', NULL, NULL, ?, true, '', '', NOW(), '', false, NOW())");
				$sth->execute([$ref_id, $owner_uid]);

				PluginHost::getInstance()->run_hooks(PluginHost::HOOK_ARTICLES_PUBLISH_TOGGLED, [$ref_id]);
			}

			if (count($labels) != 0) {
				foreach ($labels as $label) {
					Labels::add_article($ref_id, trim($label), $owner_uid);
				}
			}

			$rc = true;

		} else {
			$sth = $pdo->prepare("INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated)
				VALUES
				(?, ?, ?, NOW(), ?, ?, NOW(), NOW()) RETURNING id");
			$sth->execute([$title, $guid, $url, $content, $content_hash]);

			if ($row = $sth->fetch()) {
				$ref_id = $row["id"];

				$sth = $pdo->prepare("UPDATE ttrss_entries
					SET tsvector_combined = to_tsvector( :ts_content)
					WHERE id = :id");
				$params = [
					":ts_content" => mb_substr(\Soundasleep\Html2Text::convert($content), 0, 900000),
					":id" => $ref_id];
				$sth->execute($params);

				$sth = $pdo->prepare("INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					(?, '', NULL, NULL, ?, true, '', '', NOW(), '', false, NOW())");
				$sth->execute([$ref_id, $owner_uid]);

				PluginHost::getInstance()->run_hooks(PluginHost::HOOK_ARTICLES_PUBLISH_TOGGLED, [$ref_id]);

				if (count($labels) != 0) {
					foreach ($labels as $label) {
						Labels::add_article($ref_id, trim($label), $owner_uid);
					}
				}

				$rc = true;
			}
		}

		$pdo->commit();

		return $rc;
	}

	public function printArticleTags(): void {
		$id = (int) clean($_REQUEST['id'] ?? 0);

		print json_encode(["id" => $id,
			"tags" => self::_get_tags($id)]);
	}

	public function setScore(): void {
		$ids = self::_param_to_int_array($_REQUEST['ids'] ?? '');

		if (!$ids)
			return;

		$score = (int) clean($_REQUEST['score']);

		$ids_qmarks = arr_qmarks($ids);

		$sth = $this->pdo->prepare("UPDATE ttrss_user_entries SET
			score = ? WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");

		$sth->execute([$score, ...$ids, $_SESSION['uid']]);

		print json_encode(["id" => $ids, "score" => $score]);
	}

	public function setArticleTags(): void {

		$id = clean($_REQUEST["id"]);

		// When user enters linefeeds, also process them as a separator.
		$tags = FeedItem_Common::normalize_categories(preg_split('/[,\r\n]+/', clean($_REQUEST['tags_str'] ?? ''), -1, PREG_SPLIT_NO_EMPTY));

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = ? AND owner_uid = ? LIMIT 1");
		$sth->execute([$id, $_SESSION['uid']]);

		if ($row = $sth->fetch()) {

			$tags_to_cache = [];

			$int_id = $row['int_id'];

			$dsth = $this->pdo->prepare("DELETE FROM ttrss_tags WHERE
				post_int_id = ? AND owner_uid = ?");
			$dsth->execute([$int_id, $_SESSION['uid']]);

			$csth = $this->pdo->prepare("SELECT post_int_id FROM ttrss_tags
				WHERE post_int_id = ? AND owner_uid = ? AND tag_name = ?");

			$usth = $this->pdo->prepare("INSERT INTO ttrss_tags
				(post_int_id, owner_uid, tag_name)
				VALUES (?, ?, ?)");

			foreach ($tags as $tag) {
				$csth->execute([$int_id, $_SESSION['uid'], $tag]);

				if (!$csth->fetch()) {
					$usth->execute([$int_id, $_SESSION['uid'], $tag]);
				}

				$tags_to_cache[] = $tag;
			}

			/* update tag cache */

			$tags_str = join(",", $tags_to_cache);

			$sth = $this->pdo->prepare("UPDATE ttrss_user_entries
				SET tag_cache = ? WHERE ref_id = ? AND owner_uid = ?");
			$sth->execute([$tags_str, $id, $_SESSION['uid']]);
		}

		$this->pdo->commit();

		// get latest tags from the database, original $tags is sometimes JSON-encoded as a hash ({}) - ???
		print json_encode(["id" => (int)$id, "tags" => static::_get_tags($id)]);
	}

	public function completeTags(): void {
		$search = clean($_REQUEST["search"]);

		$sth = $this->pdo->prepare("SELECT DISTINCT tag_name FROM ttrss_tags
				WHERE owner_uid = ? AND
				tag_name LIKE ? ORDER BY tag_name
				LIMIT 10");

		$sth->execute([$_SESSION['uid'], "$search%"]);

		$results = [];

		while ($line = $sth->fetch())
			$results[] = $line['tag_name'];

		print json_encode($results);
	}

	public function assigntolabel(): void {
		$this->_label_ops(true);
	}

	public function removefromlabel(): void {
		$this->_label_ops(false);
	}

	private function _label_ops(bool $assign): void {
		$reply = [];

		$ids = self::_param_to_int_array($_REQUEST['ids'] ?? '');

		$label_id = clean($_REQUEST['lid']);
		$label = Labels::find_caption($label_id, $_SESSION['uid']);

		$reply["labels-for"] = [];

		if ($label) {
			foreach ($ids as $id) {
				if ($assign)
					Labels::add_article($id, $label, $_SESSION["uid"]);
				else
					Labels::remove_article($id, $label, $_SESSION["uid"]);

				$reply['labels-for'][] = [
					'id' => (int) $id,
					'labels' => static::_get_labels($id),
				];
			}
		}

		$reply["message"] = "UPDATE_COUNTERS";

		print json_encode($reply);
	}

	/**
	 * @param int $id article id
	 * @return array{'formatted': string, 'entries': array<int, array<string, mixed>>}
	 */
	public static function _format_enclosures(int $id, bool $always_display_enclosures, string $article_content, bool $hide_images = false): array {
		$enclosures = self::_get_enclosures($id);
		$enclosures_formatted = "";

		/*foreach ($enclosures as &$enc) {
			array_push($enclosures, [
				"type" => $enc["content_type"],
				"filename" => basename($enc["content_url"]),
				"url" => $enc["content_url"],
				"title" => $enc["title"],
				"width" => (int) $enc["width"],
				"height" => (int) $enc["height"]
			]);
		}*/

		PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_FORMAT_ENCLOSURES,
			function ($result) use (&$enclosures_formatted, &$enclosures) {
				if (is_array($result)) {
					$enclosures_formatted = $result[0];
					$enclosures = $result[1];
				} else {
					$enclosures_formatted = $result;
				}
			},
			$enclosures_formatted, $enclosures, $id, $always_display_enclosures, $article_content, $hide_images);

		if (!empty($enclosures_formatted)) {
			return [
					'formatted' => $enclosures_formatted,
					'entries' => []
			];
		}

		$rv = [
			'formatted' => '',
			'entries' => []
		];

		$rv['can_inline'] = isset($_SESSION["uid"]) &&
									empty($_SESSION["bw_limit"]) &&
									!Prefs::get(Prefs::STRIP_IMAGES, $_SESSION["uid"], $_SESSION["profile"] ?? null) &&
									($always_display_enclosures || !preg_match("/<img/i", $article_content));

		$rv['inline_text_only'] = $hide_images && $rv['can_inline'];

		foreach ($enclosures as $enc) {

			// this is highly approximate
			$enc["filename"] = basename($enc["content_url"]);

			$rendered_enc = "";
			PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_RENDER_ENCLOSURE,
				function ($result) use (&$rendered_enc) {
					$rendered_enc = $result;
				},
				$enc, $id, $rv);

			if ($rendered_enc) {
				$rv['formatted'] .= $rendered_enc;
			} else {
				PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_ENCLOSURE_ENTRY,
					function ($result) use (&$enc) {
						$enc = $result;
					},
					$enc, $id, $rv);

				$rv['entries'][] = $enc;
			}
		}

		return $rv;
	}

	/**
	 * @return array<int, string>
	 */
	public static function _get_tags(int $id, int $owner_uid = 0, ?string $tag_cache = null): array {
		$a_id = $id;

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT DISTINCT tag_name,
			owner_uid as owner FROM	ttrss_tags
			WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE
			ref_id = ? AND owner_uid = ? LIMIT 1) ORDER BY tag_name");

		$tags = [];

		/* check cache first */

		if (!$tag_cache) {
			$csth = $pdo->prepare("SELECT tag_cache FROM ttrss_user_entries
				WHERE ref_id = ? AND owner_uid = ?");
			$csth->execute([$id, $owner_uid]);

			if ($row = $csth->fetch()) {
				$tag_cache = $row["tag_cache"];
			}
		}

		if ($tag_cache) {
			$tags = explode(",", $tag_cache);
		} else {

			/* do it the hard way */

			$sth->execute([$a_id, $owner_uid]);

			while ($tmp_line = $sth->fetch())
				$tags[] = $tmp_line['tag_name'];

			/* update the cache */

			$tags_str = join(",", $tags);

			$sth = $pdo->prepare("UPDATE ttrss_user_entries
				SET tag_cache = ? WHERE ref_id = ?
				AND owner_uid = ?");
			$sth->execute([$tags_str, $id, $owner_uid]);
		}

		return $tags;
	}

	public function getmetadatabyid(): void {
		$article = ORM::for_table('ttrss_entries')
			->join('ttrss_user_entries', ['ref_id', '=', 'id'], 'ue')
			->where('ue.owner_uid', $_SESSION['uid'])
			->find_one((int)$_REQUEST['id']);

		if ($article) {
			echo json_encode(["link" => $article->link, "title" => $article->title]);
		} else {
			echo json_encode([]);
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function _get_enclosures(int $id): array {
		$encs = ORM::for_table('ttrss_enclosures')
			->where('post_id', $id)
			->find_many();

		$rv = [];

		$cache = DiskCache::instance("images");

		foreach ($encs as $enc) {
			$cache_key = sha1($enc->content_url);

			if ($cache->exists($cache_key)) {
				$enc->content_url = $cache->get_url($cache_key);
			}

			$rv[] = $enc->as_array();
		}

		return $rv;

	}

	public static function _purge_orphans(): void {

        $pdo = Db::pdo();
        $res = $pdo->query("DELETE FROM ttrss_entries WHERE
			NOT EXISTS (SELECT ref_id FROM ttrss_user_entries WHERE ref_id = id)");

        if (Debug::enabled()) {
            $rows = $res->rowCount();
            Debug::log("Purged $rows orphaned posts.");
        }
    }

	/**
	 * @param array<int, int> $ids
	 * @param int $cmode Article::CATCHUP_MODE_*
	 */
	public static function _catchup_by_id($ids, int $cmode, ?int $owner_uid = null): void {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$ids_qmarks = arr_qmarks($ids);

		if ($cmode == Article::CATCHUP_MODE_MARK_AS_UNREAD) {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
				unread = true
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else if ($cmode == Article::CATCHUP_MODE_TOGGLE) {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
				unread = NOT unread,last_read = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		} else {
			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
				unread = false,last_read = NOW()
					WHERE ref_id IN ($ids_qmarks) AND owner_uid = ?");
		}

		$sth->execute([...$ids, $owner_uid]);
	}

	/**
	 * @return array{'no-labels': 1}|array<int, array{int, string, string, string}>
	 */
	public static function _get_labels(int $id, ?int $owner_uid = null): array {
		/** @var array{'no-labels': 1}|array<int, array{int, string, string, string}> */
		$rv = [];

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT label_cache FROM
			ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($row = $sth->fetch()) {
			$label_cache = $row["label_cache"];

			if ($label_cache) {
				$tmp = json_decode($label_cache, true);
				return (empty($tmp) || ($tmp['no-labels'] ?? 0) == 1) ? $rv : $tmp;
			}
		}

		$sth = $pdo->prepare("SELECT DISTINCT label_id,caption,fg_color,bg_color
				FROM ttrss_labels2, ttrss_user_labels2
			WHERE id = label_id
				AND article_id = ?
				AND owner_uid = ?
			ORDER BY caption");
		$sth->execute([$id, $owner_uid]);

		while ($line = $sth->fetch()) {
			$rv[] = [
				Labels::label_to_feed_id($line['label_id']),
				$line['caption'],
				$line['fg_color'],
				$line['bg_color'],
			];
		}

		Labels::update_cache($owner_uid, $id, count($rv) > 0 ? $rv : ['no-labels' => 1]);

		return $rv;
	}

	/**
	 * @param array<int, array<string, mixed>> $enclosures
	 * @param array<string, mixed> $headline
	 *
	 * @return array<int, Article::ARTICLE_KIND_*|string>
	 */
	public static function _get_image(array $enclosures, string $content, string $site_url, array $headline): array {
		$article_image = "";
		$article_stream = "";
		$article_kind = 0;

		PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_ARTICLE_IMAGE,
			function ($result, $plugin) use (&$article_image, &$article_stream, &$content) {
				[$article_image, $article_stream, $content] = $result;

				// run until first hard match
				return !empty($article_image);
			},
			$enclosures, $content, $site_url, $headline);

		if (!$article_image && !$article_stream) {
			$tmpdoc = new DOMDocument();

			if (@$tmpdoc->loadHTML('<?xml encoding="UTF-8">' . mb_substr($content, 0, 131070))) {
				$tmpxpath = new DOMXPath($tmpdoc);
				$elems = $tmpxpath->query('(//img[@src]|//video[@poster]|//iframe[contains(@src , "youtube.com/embed/")])');

				/** @var DOMElement $e */
				foreach ($elems as $e) {
					if ($e->nodeName == "iframe") {
						$matches = [];
						if (preg_match("/\/embed\/([\w-]+)/", $e->getAttribute("src"), $matches)) {
							$article_image = "https://img.youtube.com/vi/" . $matches[1] . "/hqdefault.jpg";
							$article_stream = "https://youtu.be/" . $matches[1];
							$article_kind = Article::ARTICLE_KIND_YOUTUBE;
							break;
						}
					} else if ($e->nodeName == "video") {
						$article_image = $e->getAttribute("poster");

						/** @var DOMElement|null $src */
						$src = $tmpxpath->query("//source[@src]", $e)->item(0);

						if ($src) {
							$article_stream = $src->getAttribute("src");
							$article_kind = Article::ARTICLE_KIND_VIDEO;
						}

						break;
					} else if ($e->nodeName == 'img') {
						if (mb_strpos($e->getAttribute("src"), "data:") !== 0) {
							$article_image = $e->getAttribute("src");
						}
						break;
					}
				}
			}

			if (!$article_image)
				foreach ($enclosures as $enc) {
					if (str_contains($enc["content_type"], "image/")) {
						$article_image = $enc["content_url"];
						break;
					}
				}

			if ($article_image) {
				$article_image = UrlHelper::rewrite_relative($site_url, $article_image);

				if (!$article_kind && (count($enclosures) > 1 || (isset($elems) && $elems->length > 1)))
					$article_kind = Article::ARTICLE_KIND_ALBUM;
			}

			if ($article_stream)
				$article_stream = UrlHelper::rewrite_relative($site_url, $article_stream);
		}

		$cache = DiskCache::instance("images");

		if ($article_image && $cache->exists(sha1($article_image)))
			$article_image = $cache->get_url(sha1($article_image));

		if ($article_stream && $cache->exists(sha1($article_stream)))
			$article_stream = $cache->get_url(sha1($article_stream));

		return [$article_image, $article_stream, $article_kind];
	}

	/**
	 * only cached, returns label ids (not label feed ids)
	 *
	 * @param array<int, int> $article_ids
	 * @return array<int, int>
	 */
	public static function _labels_of(array $article_ids) {
		if (count($article_ids) == 0)
			return [];

		$entries = ORM::for_table('ttrss_entries')
			->table_alias('e')
			->select('ue.label_cache')
			->join('ttrss_user_entries', ['ue.ref_id', '=', 'e.id'], 'ue')
			->where_in('e.id', $article_ids)
			->where('ue.owner_uid', $_SESSION['uid'])
			->find_many();

		$rv = [];

		foreach ($entries as $entry) {
			$labels = json_decode($entry->label_cache);

			if (isset($labels) && is_array($labels)) {
				foreach ($labels as $label) {
					if (empty($label['no-labels']))
						$rv[] = Labels::feed_to_label_id($label[0]);
				}
			}
		}

		return array_unique($rv);
	}

	/**
	 * @param array<int, int> $article_ids
	 * @return array<int, int>
	 */
	public static function _feeds_of(array $article_ids) {
		if (count($article_ids) == 0)
			return [];

		$entries = ORM::for_table('ttrss_entries')
			->table_alias('e')
			->select('ue.feed_id')
			->join('ttrss_user_entries', ['ue.ref_id', '=', 'e.id'], 'ue')
			->where_in('e.id', $article_ids)
			->where('ue.owner_uid', $_SESSION['uid'])
			->find_many();

		$rv = [];

		foreach ($entries as $entry)
			$rv[] = $entry->feed_id;

		return array_unique($rv);
	}
}

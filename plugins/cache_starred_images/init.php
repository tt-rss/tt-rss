<?php
class Cache_Starred_Images extends Plugin {

	const SCHEDULE_CACHE_STARRED_IMAGES = "SCHEDULE_CACHE_STARRED_IMAGES";
	const SCHEDULE_CACHE_STARRED_IMAGES_EXPIRE_CACHES = "SCHEDULE_CACHE_STARRED_IMAGES_EXPIRE_CACHES";

	/** @var PluginHost $host */
	private $host;

	/** @var DiskCache $cache */
	private $cache;

	/** @var DiskCache $cache_status */
	private $cache_status;

	/** @var int $max_cache_attempts (per article) */
	private $max_cache_attempts = 5;

	public function about() {
		return [null,
			"Automatically cache media files in Starred articles",
			"fox"];
	}

	public function init($host) {
		$this->host = $host;
		$this->cache = DiskCache::instance("starred-images");
		$this->cache_status = DiskCache::instance("starred-images.status-files");

		Config::add(self::SCHEDULE_CACHE_STARRED_IMAGES, "@hourly", Config::T_STRING);
		Config::add(self::SCHEDULE_CACHE_STARRED_IMAGES_EXPIRE_CACHES, "@daily", Config::T_STRING);

		if (!$this->cache->exists(".no-auto-expiry"))
			$this->cache->put(".no-auto-expiry", "");

		if (!$this->cache_status->exists(".no-auto-expiry"))
			$this->cache_status->put(".no-auto-expiry", "");

		if ($this->cache->is_writable() && $this->cache_status->is_writable()) {

			$host->add_scheduled_task($this, "cache_starred_images",
				Config::get(self::SCHEDULE_CACHE_STARRED_IMAGES), function() {

				Debug::log("caching media of starred articles for user " . $this->host->get_owner_uid() . "...");

				$sth = $this->pdo->prepare("SELECT content, ttrss_entries.title,
						ttrss_user_entries.owner_uid, link, site_url, ttrss_entries.id, plugin_data
					FROM ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON
						(ttrss_user_entries.feed_id = ttrss_feeds.id)
					WHERE ref_id = ttrss_entries.id AND
						marked = true AND
						site_url != '' AND
						ttrss_user_entries.owner_uid = ? AND
						plugin_data NOT LIKE '%starred_cache_images%'
					ORDER BY RANDOM() LIMIT 100");

				if ($sth->execute([$this->host->get_owner_uid()])) {

					$usth = $this->pdo->prepare("UPDATE ttrss_entries SET plugin_data = ? WHERE id = ?");

					while ($line = $sth->fetch()) {
						Debug::log("processing article " . $line["title"], Debug::LOG_VERBOSE);

						if ($line["site_url"]) {
							$success = $this->cache_article_images($line["content"], $line["site_url"], $line["owner_uid"], $line["id"]);

							if ($success) {
								$plugin_data = "starred_cache_images," . $line["owner_uid"] . ":" . $line["plugin_data"];

								$usth->execute([$plugin_data, $line['id']]);
							}
						}
					}
				}
			});

			$host->add_scheduled_task($this, "expire_caches",
				Config::get(self::SCHEDULE_CACHE_STARRED_IMAGES_EXPIRE_CACHES), function() {

				Debug::log("expiring {$this->cache->get_dir()} and {$this->cache_status->get_dir()}...");

				$files = [
					...(glob($this->cache->get_dir() . "/*-*") ?: []),
					...(glob($this->cache_status->get_dir() . "/*.status") ?: []),
				];

				asort($files);

				$last_article_id = 0;
				$article_exists = 1;

				foreach ($files as $file) {
					[$article_id, $hash] = explode("-", basename($file));

					if ($article_id != $last_article_id) {
						$last_article_id = $article_id;

						$sth = $this->pdo->prepare("SELECT id FROM ttrss_entries WHERE id = ?");
						$sth->execute([$article_id]);

						$article_exists = $sth->fetch();
					}

					if (!$article_exists) {
						unlink($file);
					}
				}

			});

			$host->add_hook($host::HOOK_ENCLOSURE_ENTRY, $this);
			$host->add_hook($host::HOOK_SANITIZE, $this);
		} else {
			user_error("Starred cache directory ".$this->cache->get_dir()." (or status cache subdir in status-files/) is not writable.", E_USER_WARNING);
		}
	}

	public function hook_enclosure_entry($enc, $article_id, $rv) {
		$local_filename = $article_id . "-" . sha1($enc["content_url"]);

		if ($this->cache->exists($local_filename)) {
			$enc["content_url"] = $this->cache->get_url($local_filename);
		}

		return $enc;
	}

	public function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {
		$xpath = new DOMXPath($doc);

		if ($article_id) {
			$entries = $xpath->query('(//img[@src]|//source[@src|@srcset]|//video[@poster|@src])');

			/** @var DOMElement $entry */
			foreach ($entries as $entry) {
				if ($entry->hasAttribute('src')) {
					$src = UrlHelper::rewrite_relative($site_url, $entry->getAttribute('src'));

					$local_filename = $article_id . "-" . sha1($src);

					if ($this->cache->exists($local_filename)) {
						$entry->setAttribute("src", $this->cache->get_url($local_filename));
						$entry->removeAttribute("srcset");
					}
				}
			}
		}

		return $doc;
	}

	private function cache_url(int $article_id, string $url) : bool {
		$local_filename = $article_id . "-" . sha1($url);

		if (!$this->cache->exists($local_filename)) {
			Debug::log("cache_images: downloading: $url to $local_filename", Debug::LOG_VERBOSE);

			$data = UrlHelper::fetch(["url" => $url, "max_size" => Config::get(Config::MAX_CACHE_FILE_SIZE)]);

			if ($data)
				return $this->cache->put($local_filename, $data);;

		} else {
			//Debug::log("cache_images: local file exists for $url", Debug::$LOG_VERBOSE);

			return true;
		}

		return false;
	}

	/**
	 * @todo retry on partial success
	 * @return bool true if any media was successfully cached or no valid media was found, otherwise false
	 */
	private function cache_article_images(string $content, string $site_url, int $owner_uid, int $article_id) : bool {
		$status_filename = $article_id . "-" . sha1($site_url) . ".status";

		/* housekeeping might run as a separate user, in this case status/media might not be writable */
		if (!$this->cache_status->is_writable($status_filename)) {
			Debug::log("status not writable: $status_filename", Debug::LOG_VERBOSE);
			return false;
		}

		Debug::log("status: $status_filename", Debug::LOG_VERBOSE);

		if ($this->cache_status->exists($status_filename))
			$status = json_decode($this->cache_status->get($status_filename), true);
		else
			$status = ["attempt" => 0];

		$status["attempt"] += 1;

		// only allow several download attempts for article
		if ($status["attempt"] > $this->max_cache_attempts) {
			Debug::log("too many attempts for $site_url", Debug::LOG_VERBOSE);
			return false;
		}

		if (!$this->cache_status->put($status_filename, json_encode($status))) {
			user_error("unable to write status file: $status_filename", E_USER_WARNING);
			return false;
		}

		$doc = new DOMDocument();

		$has_media = false;
		$success = false;

		if (@$doc->loadHTML('<?xml encoding="UTF-8">' . $content)) {
			$xpath = new DOMXPath($doc);
			$entries = $xpath->query('(//img[@src]|//source[@src|@srcset]|//video[@poster|@src])');

			/**
			 * @see RSSUtils::cache_media()
			 * @var DOMElement $entry
			 */
			foreach ($entries as $entry) {
				foreach (['src', 'poster'] as $attr) {
					if ($entry->hasAttribute($attr) && !str_starts_with($entry->getAttribute($attr), 'data:')) {
						$url = UrlHelper::rewrite_relative($site_url, $entry->getAttribute($attr));

						if ($url) {
							$has_media = true;

							if ($this->cache_url($article_id, $url))
								$success = true;
						}
					}
				}

				if ($entry->hasAttribute('srcset')) {
					$matches = RSSUtils::decode_srcset($entry->getAttribute('srcset'));

					for ($i = 0; $i < count($matches); $i++) {
						$url = UrlHelper::rewrite_relative($site_url, $matches[$i]['url']);

						if ($url) {
							$has_media = true;

							if ($this->cache_url($article_id, $url))
								$success = true;
						}
					}
				}
			}
		}

		$esth = $this->pdo->prepare("SELECT content_url FROM ttrss_enclosures WHERE post_id = ? AND
			(content_type LIKE '%image%' OR content_type LIKE '%video%')");

		if ($esth->execute([$article_id])) {
			while ($enc = $esth->fetch()) {

				$has_media = true;
				$url = UrlHelper::rewrite_relative($site_url, $enc["content_url"]);

				if ($this->cache_url($article_id, $url)) {
					$success = true;
				}
			}
		}

		return $success || !$has_media;
	}

	public function api_version() {
		return 2;
	}
}

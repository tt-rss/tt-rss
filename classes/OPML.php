<?php
class OPML extends Handler_Protected {

	function csrf_ignore(string $method): bool {
		return in_array($method, ['export']);
	}

	/**
	 * @return bool|int|null false if writing the file failed, true if printing succeeded, int if bytes were written to a file, or null if $owner_uid is missing
	 */
	function export(): bool|int|null {
		$output_name = sprintf('tt-rss_%s_%s.opml', $_SESSION['name'], date('Y-m-d'));
		$include_settings = ($_REQUEST['include_settings'] ?? null) == '1';
		$owner_uid = $_SESSION['uid'];

		$rc = $this->opml_export($output_name, $owner_uid, false, $include_settings);

		return $rc;
	}

	// Export

	private function opml_export_category(int $owner_uid, int $cat_id, bool $hide_private_feeds = false, bool $include_settings = true): string {
		$hide_qpart = $hide_private_feeds ? "(private IS false AND auth_login = '' AND auth_pass = '')" : 'true';

		$out = "";

		$ttrss_specific_qpart = "";

		if ($cat_id) {
			$sth = $this->pdo->prepare("SELECT title,order_id
				FROM ttrss_feed_categories WHERE id = ?
					AND owner_uid = ?");
			$sth->execute([$cat_id, $owner_uid]);
			$row = $sth->fetch();
			$cat_title = htmlspecialchars($row['title']);

			if ($include_settings) {
				$order_id = (int)$row["order_id"];
				$ttrss_specific_qpart = "ttrssSortOrder=\"$order_id\"";
			}
		} else {
			$cat_title = "";
		}

		if ($cat_title) $out .= "<outline text=\"$cat_title\" $ttrss_specific_qpart>\n";

		$sth = $this->pdo->prepare("SELECT id,title
			FROM ttrss_feed_categories WHERE
				(parent_cat = :cat OR (:cat = 0 AND parent_cat IS NULL)) AND
				owner_uid = :uid ORDER BY order_id, title");

		$sth->execute([':cat' => $cat_id, ':uid' => $owner_uid]);

		while ($line = $sth->fetch()) {
			$out .= $this->opml_export_category($owner_uid, $line["id"], $hide_private_feeds, $include_settings);
		}

		$fsth = $this->pdo->prepare("select title, feed_url, site_url, update_interval, order_id, purge_interval
				FROM ttrss_feeds WHERE
					(cat_id = :cat OR (:cat = 0 AND cat_id IS NULL)) AND owner_uid = :uid AND $hide_qpart
				ORDER BY order_id, title");

		$fsth->execute([':cat' => $cat_id, ':uid' => $owner_uid]);

		while ($fline = $fsth->fetch()) {
			$title = htmlspecialchars($fline["title"]);
			$url = htmlspecialchars($fline["feed_url"]);
			$site_url = htmlspecialchars($fline["site_url"]);

			if ($include_settings) {
				$update_interval = (int)$fline["update_interval"];
				$order_id = (int)$fline["order_id"];
				$purge_interval = (int)$fline["purge_interval"];

				$ttrss_specific_qpart = "ttrssSortOrder=\"$order_id\" ttrssPurgeInterval=\"$purge_interval\" ttrssUpdateInterval=\"$update_interval\"";
			} else {
				$ttrss_specific_qpart = "";
			}

			$html_url_qpart = $site_url ? "htmlUrl=\"$site_url\"" : '';

			$out .= "<outline type=\"rss\" text=\"$title\" xmlUrl=\"$url\" $ttrss_specific_qpart $html_url_qpart/>\n";
		}

		if ($cat_title) $out .= "</outline>\n";

		return $out;
	}

	/**
	 * @return bool|int|null false if writing the file failed, true if printing succeeded, int if bytes were written to a file, or null if $owner_uid is missing
	 */
	function opml_export(string $filename, int $owner_uid, bool $hide_private_feeds = false, bool $include_settings = true, bool $file_output = false): bool|int|null {
		if (!$owner_uid) return null;

		if (!$file_output)
			if (!isset($_REQUEST["debug"])) {
				header("Content-type: application/xml+opml");
				header("Content-Disposition: attachment; filename=$filename");
			} else {
				header("Content-type: text/xml");
			}

		$out = "<?xml version=\"1.0\" encoding=\"utf-8\"?".">";

		$out .= "<opml version=\"1.0\">";
		$out .= "<head>
			<dateCreated>" . date("r", time()) . "</dateCreated>
			<title>Tiny Tiny RSS Feed Export</title>
		</head>";
		$out .= "<body>";

		$out .= $this->opml_export_category($owner_uid, 0, $hide_private_feeds, $include_settings);

		# export tt-rss settings

		if ($include_settings) {
			$out .= "<outline text=\"tt-rss-prefs\" schema-version=\"".Config::SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT pref_name, value FROM ttrss_user_prefs2 WHERE
			   profile IS NULL AND owner_uid = ? ORDER BY pref_name");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				$name = $line["pref_name"];
				$value = htmlspecialchars($line["value"]);

				$out .= "<outline pref-name=\"$name\" value=\"$value\"/>";
			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-labels\" schema-version=\"".Config::SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_labels2 WHERE
				owner_uid = ?");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch()) {
				$name = htmlspecialchars($line['caption']);
				$fg_color = htmlspecialchars($line['fg_color']);
				$bg_color = htmlspecialchars($line['bg_color']);

				$out .= "<outline label-name=\"$name\" label-fg-color=\"$fg_color\" label-bg-color=\"$bg_color\"/>";

			}

			$out .= "</outline>";

			$out .= "<outline text=\"tt-rss-filters\" schema-version=\"".Config::SCHEMA_VERSION."\">";

			$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2
				WHERE owner_uid = ? ORDER BY id");
			$sth->execute([$owner_uid]);

			while ($line = $sth->fetch(PDO::FETCH_ASSOC)) {
				$line["rules"] = [];
				$line["actions"] = [];

				$tmph = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
					WHERE filter_id = ?");
				$tmph->execute([$line['id']]);

				while ($tmp_line = $tmph->fetch(PDO::FETCH_ASSOC)) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					$cat_filter = $tmp_line["cat_filter"];

					if (!$tmp_line["match_on"]) {
						if ($cat_filter && $tmp_line["cat_id"] || $tmp_line["feed_id"]) {
							$tmp_line["feed"] = Feeds::_get_title(
								$cat_filter ? $tmp_line["cat_id"] : $tmp_line["feed_id"],
								$owner_uid,
								$cat_filter);
						} else {
							$tmp_line["feed"] = "";
						}
					} else {
						$match = [];
						foreach (json_decode($tmp_line["match_on"], true) as $feed_id) {
							if (str_starts_with($feed_id, 'CAT:')) {
								$feed_id = (int) substr($feed_id, 4);
								$match[] = $feed_id ? [Feeds::_get_cat_title($feed_id, $owner_uid), true, false] : [0, true, true];
							} else {
								$match[] = $feed_id ? [Feeds::_get_title((int)$feed_id, $owner_uid), false, false] : [0, false, true];
							}
						}

						$tmp_line["match"] = $match;
						unset($tmp_line["match_on"]);
					}

					unset($tmp_line["feed_id"]);
					unset($tmp_line["cat_id"]);

					$line['rules'][] = $tmp_line;
				}

				$tmph = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
					WHERE filter_id = ?");
				$tmph->execute([$line['id']]);

				while ($tmp_line = $tmph->fetch(PDO::FETCH_ASSOC)) {
					unset($tmp_line["id"]);
					unset($tmp_line["filter_id"]);

					$line['actions'][] = $tmp_line;
				}

				unset($line["id"]);
				unset($line["owner_uid"]);
				$filter = json_encode($line);

				$out .= "<outline filter-type=\"2\"><![CDATA[$filter]]></outline>";

			}


			$out .= "</outline>";
		}

		$out .= "</body></opml>";

		// Format output.
		$doc = new DOMDocument();
		$doc->formatOutput = true;
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($out);

		$xpath = new DOMXPath($doc);
		$outlines = $xpath->query("//outline[@title]");

		// cleanup empty categories
		/** @var DOMElement $node */
		foreach ($outlines as $node) {
			if ($node->getElementsByTagName('outline')->length == 0)
				$node->parentNode->removeChild($node);
		}

		$res = $doc->saveXML();

/*		// saveXML uses a two-space indent.  Change to tabs.
		$res = preg_replace_callback('/^(?:  )+/mu',
			create_function(
				'$matches',
				'return str_repeat("\t", intval(strlen($matches[0])/2));'),
			$res); */

		if ($file_output)
			return file_put_contents($filename, $res) > 0;

		print $res;
		return true;
	}

	// Import

	private function opml_import_feed(DOMNode $node, int $cat_id, int $owner_uid, int $nest): void {
		$attrs = $node->attributes;

		$feed_title = mb_substr($attrs->getNamedItem('text')->nodeValue ?? $attrs->getNamedItem('title')->nodeValue ?? '', 0, 250);
		$feed_url = $attrs->getNamedItem('xmlUrl')->nodeValue ?? $attrs->getNamedItem('xmlURL')?->nodeValue;
		$site_url = mb_substr($attrs->getNamedItem('htmlUrl')->nodeValue ?? '', 0, 250);

		if ($feed_url) {
			$sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE
				feed_url = ? AND owner_uid = ?");
			$sth->execute([$feed_url, $owner_uid]);

			if (!$feed_title) $feed_title = '[Unknown]';

			if (!$sth->fetch()) {
				#$this->opml_notice("[FEED] [$feed_title/$feed_url] dst_CAT=$cat_id");
				$this->opml_notice(T_sprintf("Adding feed: %s", $feed_title == '[Unknown]' ? $feed_url : $feed_title), $nest);

				if (!$cat_id) $cat_id = null;

				$update_interval = (int) $attrs->getNamedItem('ttrssUpdateInterval')?->nodeValue;
				if (!$update_interval) $update_interval = 0;

				$order_id = (int) $attrs->getNamedItem('ttrssSortOrder')?->nodeValue;
				if (!$order_id) $order_id = 0;

				$purge_interval = (int) $attrs->getNamedItem('ttrssPurgeInterval')?->nodeValue;
				if (!$purge_interval) $purge_interval = 0;

				$sth = $this->pdo->prepare("INSERT INTO ttrss_feeds
					(title, feed_url, owner_uid, cat_id, site_url, order_id, update_interval, purge_interval) VALUES
					(?, ?, ?, ?, ?, ?, ?, ?)");

				$sth->execute([$feed_title, $feed_url, $owner_uid, $cat_id, $site_url, $order_id, $update_interval, $purge_interval]);

			} else {
				$this->opml_notice(T_sprintf("Duplicate feed: %s", $feed_title == '[Unknown]' ? $feed_url : $feed_title), $nest);
			}
		}
	}

	private function opml_import_label(DOMNode $node, int $owner_uid, int $nest): void {
		$attrs = $node->attributes;
		$label_name = $attrs->getNamedItem('label-name')->nodeValue;

		if ($label_name) {
			$fg_color = $attrs->getNamedItem('label-fg-color')->nodeValue;
			$bg_color = $attrs->getNamedItem('label-bg-color')->nodeValue;

			if (!Labels::find_id($label_name, $owner_uid)) {
				$this->opml_notice(T_sprintf("Adding label %s", htmlspecialchars($label_name)), $nest);
				Labels::create($label_name, $fg_color, $bg_color, $owner_uid);
			} else {
				$this->opml_notice(T_sprintf("Duplicate label: %s", htmlspecialchars($label_name)), $nest);
			}
		}
	}

	/**
	 * @todo support passing in $profile so 'update.php --opml-import' can import prefs to a user profile
	 */
	private function opml_import_preference(DOMNode $node, int $owner_uid, int $nest): void {
		$attrs = $node->attributes;
		$pref_name = $attrs->getNamedItem('pref-name')->nodeValue;

		if ($pref_name) {
			$pref_value = $attrs->getNamedItem('value')->nodeValue;

			$this->opml_notice(T_sprintf("Setting preference key %s to %s",
				$pref_name, $pref_value), $nest);

			Prefs::set($pref_name, $pref_value, $owner_uid, $_SESSION['profile'] ?? null);
		}
	}

	private function opml_import_filter(DOMNode $node, int $owner_uid, int $nest): void {
		$attrs = $node->attributes;

		$filter_type = $attrs->getNamedItem('filter-type')->nodeValue;

		if ($filter_type == '2') {
			$filter = json_decode($node->nodeValue, true);

			if ($filter) {
				$match_any_rule = bool_to_sql_bool($filter["match_any_rule"]);
				$enabled = bool_to_sql_bool($filter["enabled"]);
				$inverse = bool_to_sql_bool($filter["inverse"]);
				$title = $filter["title"];

				//print "F: $title, $inverse, $enabled, $match_any_rule";

				$sth = $this->pdo->prepare("INSERT INTO ttrss_filters2 (match_any_rule,enabled,inverse,title,owner_uid)
					VALUES (?, ?, ?, ?, ?) RETURNING id");

				$sth->execute([$match_any_rule, $enabled, $inverse, $title, $owner_uid]);

				$row = $sth->fetch();
				$filter_id = $row['id'];

				if ($filter_id) {
					$this->opml_notice(T_sprintf("Adding filter %s...", $title), $nest);
					//$this->opml_notice(json_encode($filter));

					foreach ($filter["rules"] as $rule) {
						$feed_id = null;
						$cat_id = null;

						if ($rule["match"] ?? false) {

							$match_on = [];

							foreach ($rule["match"] as $match) {
								[$name, $is_cat, $is_id] = $match;

								if ($is_id) {
									$match_on[] = ($is_cat ? 'CAT:' : '') . $name;
								} else {
									$match_id = Feeds::_find_by_title($name, $is_cat, $owner_uid);

									if ($match_id)
											$match_on[] = $is_cat ? "CAT:$match_id" : $match_id;

									/*if (!$is_cat) {
										$tsth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
											WHERE title = ? AND owner_uid = ?");

										$tsth->execute([$name, $_SESSION['uid']]);

										if ($row = $tsth->fetch()) {
											$match_id = $row['id'];

											array_push($match_on, $match_id);
										}
									} else {
										$tsth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
											WHERE title = ? AND owner_uid = ?");
										$tsth->execute([$name, $_SESSION['uid']]);

										if ($row = $tsth->fetch()) {
											$match_id = $row['id'];

											array_push($match_on, "CAT:$match_id");
										}
									} */
								}
							}

							$reg_exp = $rule["reg_exp"];
							$filter_type = (int)$rule["filter_type"];
							$inverse = bool_to_sql_bool($rule["inverse"]);
							$match_on = json_encode($match_on);

							$usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
								(feed_id,cat_id,match_on,filter_id,filter_type,reg_exp,cat_filter,inverse)
								VALUES
								(NULL, NULL, ?, ?, ?, ?, false, ?)");
							$usth->execute([$match_on, $filter_id, $filter_type, $reg_exp, $inverse]);

						} else {

							$match_id = Feeds::_find_by_title($rule['feed'] ?? "", $rule['cat_filter'], $owner_uid);

							if ($match_id) {
								if ($rule['cat_filter']) {
									$cat_id = $match_id;
								} else {
									$feed_id = $match_id;
								}
							}

							/*if (!$rule["cat_filter"]) {
								$tsth = $this->pdo->prepare("SELECT id FROM ttrss_feeds
									WHERE title = ? AND owner_uid = ?");

								$tsth->execute([$rule['feed'], $_SESSION['uid']]);

								if ($row = $tsth->fetch()) {
									$feed_id = $row['id'];
								}
							} else {
								$tsth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
									WHERE title = ? AND owner_uid = ?");

								$tsth->execute([$rule['feed'], $_SESSION['uid']]);

								if ($row = $tsth->fetch()) {
									$feed_id = $row['id'];
								}
							} */

							$cat_filter = bool_to_sql_bool($rule["cat_filter"]);
							$reg_exp = $rule["reg_exp"];
							$filter_type = (int)$rule["filter_type"];
							$inverse = bool_to_sql_bool($rule["inverse"]);

							$usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
								(feed_id,cat_id,filter_id,filter_type,reg_exp,cat_filter,inverse)
								VALUES
								(?, ?, ?, ?, ?, ?, ?)");
							$usth->execute([$feed_id, $cat_id, $filter_id, $filter_type, $reg_exp, $cat_filter, $inverse]);
						}
					}

					foreach ($filter["actions"] as $action) {

						$action_id = (int)$action["action_id"];
						$action_param = $action["action_param"];

						$usth = $this->pdo->prepare("INSERT INTO ttrss_filters2_actions
							(filter_id,action_id,action_param)
							VALUES
							(?, ?, ?)");
						$usth->execute([$filter_id, $action_id, $action_param]);
					}
				}
			}
		}
	}

	private function opml_import_category(DOMDocument $doc, ?DOMNode $root_node, int $owner_uid, int $parent_id, int $nest): void {
		$default_cat_id = (int) $this->get_feed_category('Imported feeds', $owner_uid, 0);

		if ($root_node) {
			$cat_title = mb_substr($root_node->attributes->getNamedItem('text')->nodeValue, 0, 250);

			if (!$cat_title)
				$cat_title = mb_substr($root_node->attributes->getNamedItem('title')->nodeValue, 0, 250);

			if (!in_array($cat_title, ['tt-rss-filters', 'tt-rss-labels', 'tt-rss-prefs'])) {
				$cat_id = $this->get_feed_category($cat_title, $owner_uid, $parent_id);

				if ($cat_id === 0) {
					$order_id = (int) $root_node->attributes->getNamedItem('ttrssSortOrder')?->nodeValue;

					Feeds::_add_cat($cat_title, $owner_uid, $parent_id ?: null, $order_id);
					$cat_id = $this->get_feed_category($cat_title, $owner_uid, $parent_id);
				}
			} else {
				$cat_id = 0;
			}

			$outlines = $root_node->childNodes;
		} else {
			$xpath = new DOMXPath($doc);
			$outlines = $xpath->query("//opml/body/outline");

			$cat_id = 0;
			$cat_title = false;
		}

		//$this->opml_notice("[CAT] $cat_title id: $cat_id P_id: $parent_id");
		$this->opml_notice(T_sprintf("Processing category: %s", $cat_title ?: __("Uncategorized")), $nest);

		/** @var DOMElement $node */
		foreach ($outlines as $node) {
			if ($node->hasAttributes() && strtolower($node->tagName) == 'outline') {
				$attrs = $node->attributes;
				$node_cat_title = $attrs->getNamedItem('text')->nodeValue ?? $attrs->getNamedItem('title')?->nodeValue;
				$node_feed_url = $attrs->getNamedItem('xmlUrl')?->nodeValue;

				if ($node_cat_title && !$node_feed_url) {
					$this->opml_import_category($doc, $node, $owner_uid, $cat_id, $nest+1);
				} else {
					$dst_cat_id = $cat_id ?: $default_cat_id;

					match ($cat_title) {
						'tt-rss-prefs' => $this->opml_import_preference($node, $owner_uid, $nest+1),
						'tt-rss-labels' => $this->opml_import_label($node, $owner_uid, $nest+1),
						'tt-rss-filters' => $this->opml_import_filter($node, $owner_uid, $nest+1),
						default => $this->opml_import_feed($node, $dst_cat_id, $owner_uid, $nest+1),
					};
				}
			}
		}
	}

	/** $filename is optional; assumes HTTP upload with $_FILES otherwise */
	/**
	 * @return bool|null false on failure, true if successful, null if $owner_uid is missing
	 */
	function opml_import(int $owner_uid, string $filename = ""): ?bool {
		if (!$owner_uid) return null;

		if (!$filename) {
			if ($_FILES['opml_file']['error'] != 0) {
				print_error(T_sprintf("Upload failed with error code %d",
					$_FILES['opml_file']['error']));
				return false;
			}

			if (is_uploaded_file($_FILES['opml_file']['tmp_name'])) {
				$tmp_file = (string)tempnam(Config::get(Config::CACHE_DIR) . '/upload', 'opml');

				$result = move_uploaded_file($_FILES['opml_file']['tmp_name'],
					$tmp_file);

				if (!$result) {
					print_error(__("Unable to move uploaded file."));
					return false;
				}
			} else {
				print_error(__('Error: please upload OPML file.'));
				return false;
			}
		} else {
			$tmp_file = $filename;
		}

		if (!is_readable($tmp_file)) {
			$this->opml_notice(T_sprintf("Error: file is not readable: %s", $filename));
			return false;
		}

		$doc = new DOMDocument();

		$loaded = $doc->load($tmp_file);

		// only remove temporary i.e. HTTP uploaded files
		if (!$filename)
			unlink($tmp_file);

		if ($loaded) {
			// we're using ORM while importing so we can't transaction-lock things anymore
			//$this->pdo->beginTransaction();
			$this->opml_import_category($doc, null, $owner_uid, 0, 0);
			//$this->pdo->commit();
		} else {
			$this->opml_notice(__('Error while parsing document.'));
			return false;
		}

		return true;
	}

	private function opml_notice(string $msg, int $prefix_length = 0): void {
		if (php_sapi_name() == "cli") {
			Debug::log(str_repeat("   ", $prefix_length) . $msg);
		} else {
			// TODO: use better separator i.e. CSS-defined span of certain width or something
			print str_repeat("&nbsp;&nbsp;&nbsp;", $prefix_length) . $msg . "<br/>";
		}
	}

	function get_feed_category(string $feed_cat, int $owner_uid, int $parent_cat_id) : int {

		$sth = $this->pdo->prepare("SELECT id FROM ttrss_feed_categories
			WHERE title = :title
			AND (parent_cat = :parent OR (:parent = 0 AND parent_cat IS NULL))
			AND owner_uid = :uid");

		$sth->execute([':title' => $feed_cat, ':parent' => $parent_cat_id, ':uid' => $owner_uid]);

		if ($row = $sth->fetch()) {
			return $row['id'];
		} else {
			return 0;
		}
	}

}

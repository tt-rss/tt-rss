<?php
class Pref_Filters extends Handler_Protected {

	const ACTION_TAG = 4;
	const ACTION_SCORE = 6;
	const ACTION_LABEL = 7;
	const ACTION_PLUGIN = 9;
	const ACTION_REMOVE_TAG = 10;

	const PARAM_ACTIONS = [self::ACTION_TAG, self::ACTION_SCORE,
		self::ACTION_LABEL, self::ACTION_PLUGIN, self::ACTION_REMOVE_TAG];

	const MAX_ACTIONS_TO_DISPLAY = 3;

	/** @var array<int,array<mixed>> */
	private array $filter_actions;

	/** @var array<int,array<mixed>> */
	private array $filter_types;

	function before(string $method) : bool {
		// ttrss_filters2_actions, but here to support translations
		$this->filter_actions = [
			1 => ['name' => 'filter', 'description' => __('Delete article')],
			2 => ['name' => 'catchup', 'description' => __('Mark as read')],
			3 => ['name' => 'mark', 'description' => __('Set starred')],
			4 => ['name' => 'tag', 'description' => __('Assign tags')],
			5 => ['name' => 'publish', 'description' => __('Publish article')],
			6 => ['name' => 'score', 'description' => __('Modify score')],
			7 => ['name' => 'label', 'description' => __('Assign label')],
			8 => ['name' => 'stop', 'description' => __('Stop / Do nothing')],
			9 => ['name' => 'plugin', 'description' => __('Invoke plugin')],
			10 => ['name' => 'ignore-tag', 'description' => __('Ignore tags')],
		];

		// // ttrss_filter_types, but here to support translations
		$this->filter_types = [
			1 => ['name' => 'title', 'description' => __('Title')],
			2 => ['name' => 'content', 'description' => __('Content')],
			3 => ['name' => 'both', 'description' => __('Title or Content')],
			4 => ['name' => 'link', 'description' => __('Link')],
			// preserving the original behavior of this type not being supported
			// 5 => ['name' => 'date', 'description' => __('Article Date')],
			6 => ['name' => 'author', 'description' => __('Author')],
			7 => ['name' => 'tag', 'description' => __('Article Tags')],
		];

		return parent::before($method);
	}

	function csrf_ignore(string $method): bool {
		return in_array($method, ['index', 'getfiltertree']);
	}

	function filtersortreset(): void {
		$sth = $this->pdo->prepare("UPDATE ttrss_filters2
				SET order_id = 0 WHERE owner_uid = ?");
		$sth->execute([$_SESSION['uid']]);
	}

	function savefilterorder(): void {
		$data = json_decode($_POST['payload'], true);

		#file_put_contents("/tmp/saveorder.json", clean($_POST['payload']));
		#$data = json_decode(file_get_contents("/tmp/saveorder.json"), true);

		if (!is_array($data['items']))
			$data['items'] = json_decode($data['items'], true);

		$index = 0;

		if (is_array($data) && is_array($data['items'])) {

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET
						order_id = ? WHERE id = ? AND
						owner_uid = ?");

			foreach ($data['items'][0]['items'] as $item) {
				$filter_id = (int) str_replace("FILTER:", "", $item['_reference']);

				if ($filter_id > 0) {
					$sth->execute([$index, $filter_id, $_SESSION['uid']]);
					++$index;
				}
			}
		}
	}

	function testFilterDo(): void {
		$offset = (int) clean($_REQUEST["offset"]);
		$limit = (int) clean($_REQUEST["limit"]);

		// catchall fake filter which includes all rules
		$filter = [
			'enabled' => true,
			'match_any_rule' => checkbox_to_sql_bool($_REQUEST['match_any_rule'] ?? false),
			'inverse' => checkbox_to_sql_bool($_REQUEST['inverse'] ?? false),
			'rules' => [],
			'actions' => ['dummy-action'],
		];

		$scope_qparts = [];

		/** @var string $rule_json */
		foreach ($_REQUEST['rule'] as $rule_json) {
			try {
				/** @var array{reg_exp: string, filter_type: int, feed_id: array<int, int|string>, name: string, inverse?: bool}|null */
				$rule = json_decode($rule_json, true, flags: JSON_THROW_ON_ERROR);
			} catch (Exception) {
				continue;
			}

			if (is_array($rule)) {
				$rule['type'] = $this->filter_types[$rule['filter_type']]['name'];
				$rule['inverse'] ??= false;
				$filter['rules'][] = $rule;

				$scope_inner_qparts = [];

				/** @var int|string $feed_id may be a category string (e.g. 'CAT:7') or feed ID int */
				foreach ($rule['feed_id'] as $feed_id) {
					if (str_starts_with("$feed_id", 'CAT:')) {
						$cat_id = (int) substr("$feed_id", 4);
						$scope_inner_qparts[] = $cat_id > 0 ? "cat_id = $cat_id" : 'cat_id IS NULL';
					} elseif (is_numeric($feed_id) && $feed_id > 0) {
						$scope_inner_qparts[] = 'feed_id = ' . (int)$feed_id;
					}
				}

				if (count($scope_inner_qparts) > 0)
					$scope_qparts[] = '(' . implode(' OR ', $scope_inner_qparts) . ')';
			}
		}

		$query = ORM::for_table('ttrss_entries')
			->table_alias('e')
			->select_many('e.title', 'e.content', 'e.date_entered', 'e.link', 'e.author', 'ue.tag_cache',
				['feed_id' => 'f.id', 'feed_title' => 'f.title', 'cat_id' => 'fc.id'])
			->join('ttrss_user_entries', [ 'ue.ref_id', '=', 'e.id'], 'ue')
			->left_outer_join('ttrss_feeds', ['f.id', '=', 'ue.feed_id'], 'f')
			->left_outer_join('ttrss_feed_categories', ['fc.id', '=', 'f.cat_id'], 'fc')
			->where('ue.owner_uid', $_SESSION['uid'])
			->order_by_desc('e.date_entered')
			->limit($limit)
			->offset($offset);

		if (count($scope_qparts) > 0)
			$query->where_raw(join($filter['match_any_rule'] ? ' OR ' : ' AND ', $scope_qparts));

		$entries = $query->find_array();

		$rv = [
			'pre_filtering_count' => count($entries),
			'items' => [],
		];

		foreach ($entries as $entry) {

			// temporary filter which will be used to compare against returned article
			$feed_filter = $filter;
			$feed_filter['rules'] = [];

			// only add rules which match result from specific feed or category ID or rules matching all feeds
			foreach ($filter['rules'] as $rule) {
				foreach ($rule['feed_id'] as $rule_feed) {
					if (($rule_feed === 'CAT:0' && $entry['cat_id'] === null) || 			// rule matches Uncategorized
							$rule_feed === 'CAT:' . $entry['cat_id'] ||                    // rule matches category
							(int)$rule_feed === $entry['feed_id'] ||                            // rule matches feed
							$rule_feed === '0') {                                          // rule matches all feeds

						$feed_filter['rules'][] = $rule;
					}
				}
			}

			$matched_rules = [];

			$entry_tags = explode(",", $entry['tag_cache']);

			$article_filter_actions = RSSUtils::eval_article_filters([$feed_filter], $entry['title'], $entry['content'], $entry['link'],
				$entry['author'], $entry_tags, $matched_rules);

			if (count($article_filter_actions) > 0) {
				$content_preview = "";

				$matches = [];
				$rules = [];

				$entry_title = $entry["title"];

				// technically only one rule may match *here* because we're testing a single (fake) filter defined above
				// let's keep this forward-compatible in case we'll want to return multiple rules for whatever reason
				foreach ($matched_rules as $rule) {
					$can_highlight_content = false;
					$can_highlight_title = false;

					$rule_regexp_match = mb_substr(strip_tags($rule['regexp_matches'][0]), 0, 200);

					$matches[] = $rule_regexp_match;

					$rules[] = self::_get_rule_name($rule, false);

					if (in_array($rule['type'], ['content', 'both'])) {
						// also stripping [\r\n\t] to match what's done for content in RSSUtils#eval_article_filters()
						$entry_content_text = strip_tags(preg_replace("/[\r\n\t]/", "", $entry["content"]));

						$match_index = mb_strpos($entry_content_text, $rule_regexp_match);
						$content_preview = truncate_string(mb_substr($entry_content_text, $match_index), 200);

						if ($match_index > 0)
							$content_preview = '&hellip;' . $content_preview;
					} else {
						$content_preview = match ($rule['type']) {
							'link' => $entry['link'],
							'author' => $entry['author'],
							'tag' => '<i class="material-icons">label_outline</i> ' . implode(', ', $entry_tags),
							default => '&mdash;',
						};
					}

					switch ($rule['type']) {
						case "both":
							$can_highlight_title = true;
							$can_highlight_content = true;
							break;
						case "title":
							$can_highlight_title = true;
							break;
						case "content":
						case "link":
						case "author":
						case "tag":
							$can_highlight_content = true;
							break;
					}

					if ($can_highlight_content)
						$content_preview = Sanitizer::highlight_words_str($content_preview, $matches);

					if ($can_highlight_title)
						$entry_title = Sanitizer::highlight_words_str($entry_title, $matches);
				}

				$rv['items'][] = [
					'title' => $entry_title,
					'feed_title' => $entry['feed_title'],
					'date' => mb_substr($entry['date_entered'], 0, 16),
					'content_preview' => $content_preview,
					'rules' => $rules
				];
			}
		}

		print json_encode($rv);
	}

	private function _get_rules_list(int $filter_id): string {
		// keep sort order in sync with Pref_Filters#edit()
		$rules = ORM::for_table('ttrss_filters2_rules')
			->where('filter_id', $filter_id)
			->order_by_asc('reg_exp')
			->order_by_asc('id')
			->find_many();

		$rv = "";

		foreach ($rules as $rule) {
			if ($rule->match_on) {
					$feeds = json_decode($rule->match_on, true);
					$feeds_fmt = [];

					foreach ($feeds as $feed_id) {
						if (str_starts_with($feed_id, "CAT:")) {
							$feed_id = (int) substr($feed_id, 4);
							$feeds_fmt[] = Feeds::_get_cat_title($feed_id, $_SESSION['uid']);
						} else {
							$feeds_fmt[] = $feed_id ? Feeds::_get_title((int) $feed_id, $_SESSION['uid']) : __('All feeds');
						}
					}

					$where = implode(', ', $feeds_fmt);

			} else {
				$where = $rule->cat_filter ?
						Feeds::_get_cat_title($rule->cat_id ?? 0, $_SESSION['uid']) :
					($rule->feed_id ?
						Feeds::_get_title($rule->feed_id, $_SESSION['uid']) : __("All feeds"));
			}

			$inverse_class = $rule->inverse ? "inverse" : "";

			$rv .= "<li class='$inverse_class'>" . T_sprintf("%s on %s in %s %s",
				htmlspecialchars($rule->reg_exp),
				$this->filter_types[$rule->filter_type]['description'],
				htmlspecialchars($where),
				$rule->inverse ? __("(inverse)") : "") . "</li>";
		}

		return $rv;
	}

	function getfiltertree(): void {
		$root = [
			'id' => 'root',
			'name' =>  __('Filters'),
			'enabled' => true,
			'items' => []
		];

		$filter_search = ($_SESSION["prefs_filter_search"] ?? "");

		$filters = ORM::for_table('ttrss_filters2')
			->where('owner_uid', $_SESSION['uid'])
			->order_by_asc(['order_id', 'title'])
			->find_many();

		$folder = [
			'items' => []
		];

		foreach ($filters as $filter) {
			if ($filter_search &&
				mb_stripos($filter->title, $filter_search) === false &&
					!ORM::for_table('ttrss_filters2_rules')
						->where('filter_id', $filter->id)
						->where_raw('LOWER(reg_exp) LIKE LOWER(?)', ["%$filter_search%"])
						->find_one()) {

					continue;
			}

			$details = $this->_get_details($filter->id);

			$folder['items'][] = [
				'id' => 'FILTER:' . $filter->id,
				'bare_id' => $filter->id,
				'bare_name' => $details['title'],
				'name' => $details['title_summary'],
				'param' => $details['actions_summary'],
				'checkbox' => false,
				'last_triggered' => $filter->last_triggered ? TimeHelper::make_local_datetime($filter->last_triggered) : null,
				'enabled' => sql_bool_to_bool($filter->enabled),
				'rules' => $this->_get_rules_list($filter->id),
			];
		}

		$root['items'] = $folder['items'];

		$fl = [
			'identifier' => 'id',
			'label' => 'name',
			'items' => [$root]
		];

		print json_encode($fl);
	}

	function edit(): void {

		$filter_id = (int) clean($_REQUEST["id"] ?? 0);

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2
			WHERE id = ? AND owner_uid = ?");
		$sth->execute([$filter_id, $_SESSION['uid']]);

		if (empty($filter_id) || $row = $sth->fetch()) {
			$rv = [
				"id" => $filter_id,
				"enabled" => $row["enabled"] ?? true,
				"match_any_rule" => $row["match_any_rule"] ?? false,
				"inverse" => $row["inverse"] ?? false,
				"title" => $row["title"] ?? "",
				"rules" => [],
				"actions" => [],
				"filter_types" => [],
				"action_types" => [],
				"plugin_actions" => [],
				"labels" => Labels::get_all($_SESSION["uid"])
			];

			foreach ($this->filter_types as $id => $details)
				$rv['filter_types'][$id] = $details['description'];

			foreach ($this->filter_actions as $id => $details)
				$rv['action_types'][$id] = $details['description'];

			foreach (PluginHost::getInstance()->get_filter_actions() as $fclass => $factions) {
				foreach ($factions as $faction) {
					$rv["plugin_actions"][$fclass . ":" . $faction["action"]] =
						$fclass . ": " . $faction["description"];
				}
			}

			if ($filter_id) {
				// keep sort order in sync with Pref_Filters#_get_rules_list()
				$rules_sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
					WHERE filter_id = ? ORDER BY reg_exp, id");
				$rules_sth->execute([$filter_id]);

				while ($rrow = $rules_sth->fetch(PDO::FETCH_ASSOC)) {
					if ($rrow["match_on"]) {
						$rrow["feed_id"] = json_decode($rrow["match_on"], true);
					} else {
						if ($rrow["cat_filter"]) {
							$feed_id = "CAT:" . (int)$rrow["cat_id"];
						} else {
							$feed_id = (int)$rrow["feed_id"];
						}

						$rrow["feed_id"] = ["" . $feed_id]; // set item type to string for in_array()
					}

                    // The function _get_rule_name() expects $rrow['inverse'] to be unset
                    // to mean "false", in order to have a behavior similar to the HTML
                    // checkbox which is unset when not checked on the web page.
					if (!$rrow['inverse'])
						unset($rrow['inverse']);

					// NOTE: '_get_rule_name()' depends upon the 'match_on'/'feed_id' massaging that happens above.
					$rrow['name'] = $this->_get_rule_name($rrow);

					unset($rrow['cat_filter'], $rrow['cat_id'], $rrow['filter_id'], $rrow['id'], $rrow['match_on']);

					$rv['rules'][] = $rrow;
				}

				$actions_sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
					WHERE filter_id = ? ORDER BY id");
				$actions_sth->execute([$filter_id]);

				while ($arow = $actions_sth->fetch(PDO::FETCH_ASSOC)) {
					$arow["action_param_label"] = $arow["action_param"];

					unset($arow["filter_id"]);
					unset($arow["id"]);

					$arow["name"] = $this->_get_action_name($arow);

					$rv['actions'][] = $arow;
				}
			}
			print json_encode($rv);
		}
	}

	/**
	 * @param array<string, mixed> $rule
	 * @param bool|null $html_format Whether to return a HTML-formatted string or plain text.
	 */
	private function _get_rule_name(array $rule, ?bool $html_format = true): string {
		$feeds = $rule['feed_id'];
		$feeds_fmt = [];

		if (!is_array($feeds))
			$feeds = [$feeds];

		foreach ($feeds as $feed_id) {
			if (str_starts_with($feed_id, 'CAT:')) {
				$feed_id = (int) substr($feed_id, 4);
				$feeds_fmt[] = Feeds::_get_cat_title($feed_id, $_SESSION['uid']);
			} else {
				$feeds_fmt[] = $feed_id ? Feeds::_get_title((int) $feed_id, $_SESSION['uid']) : __('All feeds');
			}
		}

		$feed = implode(', ', $feeds_fmt);
		$filter_type = $this->filter_types[(int) $rule['filter_type']]['description'] ?? 'unknown filter type';
		$inverse = isset($rule['inverse']) ? 'inverse' : '';

		if ($html_format) {
			return "<span class='filterRule $inverse'>" .
				T_sprintf('%s on %s in %s %s',
					htmlspecialchars($rule['reg_exp']),
					"<span class='field'>" . htmlspecialchars($filter_type) . '</span>',
					"<span class='feed'>" . htmlspecialchars($feed) . '</span>',
					$inverse ? __('(inverse)') : '') .
				'</span>';
		}

		return T_sprintf('%s on %s in %s %s', $rule['reg_exp'],
			$filter_type, $feed, $inverse ? __('(inverse)') : '');
	}

	function printRuleName(): void {
		try {
			$rule = json_decode($_REQUEST['rule'], true, flags: JSON_THROW_ON_ERROR);
		} catch (Exception) {
			print 'malformed rule JSON';
			return;
		}

		print is_array($rule) ? $this->_get_rule_name($rule) : 'invalid rule JSON';
	}

	/**
	 * @param array<string,mixed>|ArrayAccess<string, mixed>|null $action
	 */
	private function _get_action_name(array|ArrayAccess|null $action = null): string {
		if (!$action)
			return '';

		$action_id = (int) $action['action_id'];
		$title = $this->filter_actions[$action_id]['description'] ?? T_sprintf('Unknown action: %d', $action_id);

		if ($action_id == self::ACTION_PLUGIN) {
			[$pfclass, $pfaction] = explode(":", $action["action_param"]);

			$filter_actions = PluginHost::getInstance()->get_filter_actions();

			foreach ($filter_actions as $fclass => $factions) {
				foreach ($factions as $faction) {
					if ($pfaction == $faction["action"] && $pfclass == $fclass) {
						$title .= ": " . $fclass . ": " . $faction["description"];
						break;
					}
				}
			}
		} else if (in_array($action_id, self::PARAM_ACTIONS)) {
			$title .= ": " . $action["action_param"];
		}

		return $title;
	}

	function printActionName(): void {
		try {
			$action = json_decode($_REQUEST['action'], true, flags: JSON_THROW_ON_ERROR);
		} catch (Exception) {
			print 'malformed action JSON';
			return;
		}

		print is_array($action) ? $this->_get_action_name($action) : 'invalid action JSON';
	}

	function editSave(): void {
		$filter_id = (int) clean($_REQUEST["id"]);
		$enabled = checkbox_to_sql_bool($_REQUEST["enabled"] ?? false);
		$match_any_rule = checkbox_to_sql_bool($_REQUEST["match_any_rule"] ?? false);
		$inverse = checkbox_to_sql_bool($_REQUEST["inverse"] ?? false);
		// intentionally not doing clean() here to allow for '<', etc. in titles
		$title = trim($_REQUEST['title'] ?? '');

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET enabled = ?,
			match_any_rule = ?,
			inverse = ?,
			title = ?
			WHERE id = ? AND owner_uid = ?");

		$sth->execute([$enabled, $match_any_rule, $inverse, $title, $filter_id, $_SESSION['uid']]);

		$this->_save_rules_and_actions($filter_id);

		$this->pdo->commit();
	}

	function remove(): void {
		$ids = self::_param_to_int_array($_REQUEST['ids'] ?? '');

		if (!$ids)
			return;

		ORM::for_table('ttrss_filters2')
			->where_in('id', $ids)
			->where('owner_uid', $_SESSION['uid'])
			->delete_many();
	}

	private function _clone_rules_and_actions(int $filter_id, ?int $src_filter_id = null): bool {
		$sth = $this->pdo->prepare('INSERT INTO ttrss_filters2_rules
					(filter_id, reg_exp, inverse, filter_type, feed_id, cat_id, match_on, cat_filter)
					SELECT :filter_id, reg_exp, inverse, filter_type, feed_id, cat_id, match_on, cat_filter
					FROM ttrss_filters2_rules
					WHERE filter_id = :src_filter_id');

		if (!$sth->execute(['filter_id' => $filter_id, 'src_filter_id' => $src_filter_id]))
			return false;

		$sth = $this->pdo->prepare('INSERT INTO ttrss_filters2_actions
			(filter_id, action_id, action_param)
			SELECT :filter_id, action_id, action_param
			FROM ttrss_filters2_actions
			WHERE filter_id = :src_filter_id');

		return $sth->execute(['filter_id' => $filter_id, 'src_filter_id' => $src_filter_id]);
	}

	private function _save_rules_and_actions(int $filter_id): void {

		$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2_rules WHERE filter_id = ?");
		$sth->execute([$filter_id]);

		$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2_actions WHERE filter_id = ?");
		$sth->execute([$filter_id]);

		if (!is_array(clean($_REQUEST["rule"] ?? ""))) $_REQUEST["rule"] = [];
		if (!is_array(clean($_REQUEST["action"] ?? ""))) $_REQUEST["action"] = [];

		if ($filter_id) {
			/* create rules */

			$rules = [];
			$actions = [];

			foreach ($_REQUEST['rule'] as $rule) {
				try {
					$rule = json_decode($rule, true, flags: JSON_THROW_ON_ERROR);
				} catch (Exception) {
					continue;
				}

				if (!is_array($rule))
					continue;

				unset($rule['id']);

				if (!in_array($rule, $rules))
					$rules[] = $rule;
			}

			foreach ($_REQUEST['action'] as $action) {
				try {
					$action = json_decode($action, true, flags: JSON_THROW_ON_ERROR);
				} catch (Exception) {
					continue;
				}

				if (!is_array($action))
					continue;

				unset($action['id']);

				if (!in_array($action, $actions))
					$actions[] = $action;
			}

			$rsth = $this->pdo->prepare("INSERT INTO ttrss_filters2_rules
						(filter_id, reg_exp,filter_type,feed_id,cat_id,match_on,inverse) VALUES
						(?, ?, ?, NULL, NULL, ?, ?)");

			foreach ($rules as $rule) {
				if ($rule) {

					$reg_exp = trim($rule["reg_exp"]);
					$inverse = isset($rule["inverse"]) ? 1 : 0;

					$filter_type = (int)trim($rule["filter_type"]);
					$match_on = json_encode($rule["feed_id"]);

					$rsth->execute([$filter_id, $reg_exp, $filter_type, $match_on, $inverse]);
				}
			}

			$asth = $this->pdo->prepare("INSERT INTO ttrss_filters2_actions
						(filter_id, action_id, action_param) VALUES
						(?, ?, ?)");

			foreach ($actions as $action) {
				if ($action) {

					$action_id = (int)$action["action_id"];
					$action_param = $action["action_param"];
					$action_param_label = $action["action_param_label"];

					$action_param = match ($action_id) {
						self::ACTION_LABEL => $action_param_label,
						self::ACTION_SCORE => (int) str_replace('+', '', $action_param),
						self::ACTION_TAG, self::ACTION_REMOVE_TAG => implode(', ', FeedItem_Common::normalize_categories(explode(',', $action_param))),
						default => $action_param,
					};

					$asth->execute([$filter_id, $action_id, $action_param]);
				}
			}
		}
	}

	/**
	 * @param null|array{'src_filter_id': int, 'title': string, 'enabled': 0|1, 'match_any_rule': 0|1, 'inverse': 0|1} $props
	 */
	function add(?array $props = null): void {
		if ($props === null) {
			$src_filter_id = null;
			// intentionally not doing clean() here to allow for '<', etc. in titles
			$title = trim($_REQUEST['title']);
			$enabled = checkbox_to_sql_bool($_REQUEST['enabled'] ?? false);
			$match_any_rule = checkbox_to_sql_bool($_REQUEST['match_any_rule'] ?? false);
			$inverse = checkbox_to_sql_bool($_REQUEST['inverse'] ?? false);
		} else {
			// see checkbox_to_sql_bool() for 0 vs false justification
			$src_filter_id = $props['src_filter_id'];
			// intentionally not doing clean() here to allow for '<', etc. in titles
			$title = trim($props['title']);
			$enabled = $props['enabled'];
			$match_any_rule = $props['match_any_rule'];
			$inverse = $props['inverse'];
		}

		$this->pdo->beginTransaction();

		/* create base filter */

		$sth = $this->pdo->prepare("INSERT INTO ttrss_filters2
			(owner_uid, match_any_rule, enabled, title, inverse) VALUES
			(?, ?, ?, ?, ?) RETURNING id");

		$sth->execute([$_SESSION['uid'], $match_any_rule, $enabled, $title, $inverse]);

		if ($row = $sth->fetch()) {
			$filter_id = $row['id'];

			if ($src_filter_id === null)
				$this->_save_rules_and_actions($filter_id);
			else
				$this->_clone_rules_and_actions($filter_id, $src_filter_id);
		}

		$this->pdo->commit();
	}

	function clone(): void {
		$src_filter_ids = self::_param_to_int_array($_REQUEST['ids'] ?? '');

		if (!$src_filter_ids)
			return;

		// intentionally not doing clean() here to allow for '<', etc. in titles
		$new_filter_title = count($src_filter_ids) === 1 ? trim($_REQUEST['new_filter_title'] ?? null) : null;

		$src_filters = ORM::for_table('ttrss_filters2')
			->where('owner_uid', $_SESSION['uid'])
			->where_id_in($src_filter_ids)
			->find_many();

		foreach ($src_filters as $src_filter) {
			// see checkbox_to_sql_bool() for 0+1 justification
			$this->add([
				'src_filter_id' => $src_filter->id,
				'title' => $new_filter_title ?? sprintf(__('Clone of %s'), $src_filter->title),
				'enabled' => 0,
				'match_any_rule' => $src_filter->match_any_rule ? 1 : 0,
				'inverse' => $src_filter->inverse ? 1 : 0,
			]);
		}
	}

	function index(): void {
		if (array_key_exists("search", $_REQUEST)) {
			$filter_search = clean($_REQUEST["search"]);
			$_SESSION["prefs_filter_search"] = $filter_search;
		} else {
			$filter_search = ($_SESSION["prefs_filter_search"] ?? "");
		}

		?>
		<div dojoType='dijit.layout.BorderContainer' gutters='false'>
			<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='top'>
				<div dojoType='fox.Toolbar'>

					<div style='float : right; padding-right : 4px;'>
						<form dojoType="dijit.form.Form" onsubmit="dijit.byId('filterTree').reload(); return false;">
							<input dojoType="dijit.form.TextBox" id="filter_search" size="20" type="search"
								value="<?= htmlspecialchars($filter_search) ?>">
							<button dojoType="dijit.form.Button" type="submit">
								<?= __('Search') ?></button>
						</form>
					</div>

					<div dojoType="fox.form.DropDownButton">
						<span><?= __('Select') ?></span>
						<div dojoType="dijit.Menu" style="display: none;">
							<div onclick="dijit.byId('filterTree').model.setAllChecked(true)"
								dojoType="dijit.MenuItem"><?= __('All') ?></div>
							<div onclick="dijit.byId('filterTree').model.setAllChecked(false)"
								dojoType="dijit.MenuItem"><?= __('None') ?></div>
						</div>
					</div>

					<button dojoType="dijit.form.Button" onclick="return Filters.edit()">
						<?= __('Create filter') ?></button>
					<button dojoType="dijit.form.Button" onclick="return dijit.byId('filterTree').cloneSelectedFilters()">
						<?= __('Clone') ?></button>
					<button dojoType="dijit.form.Button" onclick="return dijit.byId('filterTree').joinSelectedFilters()">
						<?= __('Combine') ?></button>
					<button dojoType="dijit.form.Button" onclick="return dijit.byId('filterTree').removeSelectedFilters()">
						<?= __('Remove') ?></button>
					<button dojoType="dijit.form.Button" onclick="return dijit.byId('filterTree').resetFilterOrder()">
						<?= __('Reset sort order') ?></button>
					<button dojoType="dijit.form.Button" onclick="return dijit.byId('filterTree').toggleRules()">
						<?= __('Toggle rule display') ?></button>

				</div>
			</div>
			<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='center'>
				<div dojoType="fox.PrefFilterStore" jsId="filterStore"
					url="backend.php?op=Pref_Filters&method=getfiltertree">
				</div>
				<div dojoType="lib.CheckBoxStoreModel" jsId="filterModel" store="filterStore"
					query="{id:'root'}" rootId="root" rootLabel="Filters"
					childrenAttrs="items" checkboxStrict="false" checkboxAll="false">
				</div>
				<div dojoType="fox.PrefFilterTree" id="filterTree" dndController="dijit.tree.dndSource"
					betweenThreshold="5" model="filterModel" openOnClick="true">
				</div>
			</div>
			<?php PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB, "prefFilters") ?>
		</div>
		<?php
	}

	function editrule(): void {
		// may contain category strings, so don't use self::_param_to_int_array() here
		$feed_ids = explode(",", clean($_REQUEST["ids"]));

		print json_encode([
			"multiselect" => $this->_feed_multi_select("feed_id", $feed_ids, 'required="1" style="width : 100%; height : 300px" dojoType="fox.form.ValidationMultiSelect"')
		]);
	}

	/**
	 * @return array{'title': string, 'title_summary': string, 'actions_summary': string}
	 */
	private function _get_details(int $id): array {

		$filter = ORM::for_table("ttrss_filters2")
			->table_alias('f')
			->select('f.title')
			->select('f.match_any_rule')
			->select('f.inverse')
			->select_expr('COUNT(DISTINCT r.id)', 'num_rules')
			->select_expr('COUNT(DISTINCT a.id)', 'num_actions')
			->left_outer_join('ttrss_filters2_rules', ['r.filter_id', '=', 'f.id'], 'r')
			->left_outer_join('ttrss_filters2_actions', ['a.filter_id', '=', 'f.id'], 'a')
			->where('f.id', $id)
			->group_by_expr('f.title, f.match_any_rule, f.inverse')
			->find_one();

		$title = $filter->title ?: __('[No caption]');

		$title_summary = [
			sprintf(
			_ngettext("%s (%d rule)", "%s (%d rules)", (int) $filter->num_rules),
			$title,
			$filter->num_rules)];

		if ($filter->match_any_rule)
			$title_summary[] = __('matches any rule');

		if ($filter->inverse)
			$title_summary[] = __('inverse');

		$actions = ORM::for_table("ttrss_filters2_actions")
			->where("filter_id", $id)
			->order_by_asc('id')
			->find_many();

		/** @var array<string> $actions_summary */
		$actions_summary = [];
		$cumulative_score = 0;

		// we're going to show a summary adjustment so we skip individual score action descriptions here
		foreach ($actions as $action) {
			if ($action->action_id == self::ACTION_SCORE) {
				$cumulative_score += (int) $action->action_param;
				continue;
			}

			$actions_summary[] = '<li>' . htmlspecialchars(self::_get_action_name($action)) . '</li>';
		}

		// inject a fake action description using cumulative filter score
		if ($cumulative_score != 0) {
			array_unshift($actions_summary,
				"<li>" . htmlspecialchars(self::_get_action_name(["action_id" => self::ACTION_SCORE, "action_param" => $cumulative_score])) . "</li>");
		}

		if (count($actions_summary) > self::MAX_ACTIONS_TO_DISPLAY) {
			$actions_not_shown = count($actions_summary) - self::MAX_ACTIONS_TO_DISPLAY;
			$actions_summary = array_slice($actions_summary, 0, self::MAX_ACTIONS_TO_DISPLAY);

			$actions_summary[] =
				'<li class="text-muted"><em>' . sprintf(_ngettext('(+%d action)', '(+%d actions)', $actions_not_shown), $actions_not_shown) . '</em></li>';
		}

		return [
			'title' => $title,
			'title_summary' => implode(', ', $title_summary),
			'actions_summary' => implode('', $actions_summary),
		];
	}

	function join(): void {
		$ids = self::_param_to_int_array($_REQUEST['ids'] ?? '');

		if (!$ids)
			return;

		// fail early if any provided filter IDs aren't owned by the current user
		$unowned_filter_count = ORM::for_table('ttrss_filters2')
			->where_in('id', $ids)
			->where_not_equal('owner_uid', $_SESSION['uid'])
			->count();

		if ($unowned_filter_count)
			return;

		if (count($ids) > 1) {
			$base_id = array_shift($ids);
			$ids_qmarks = arr_qmarks($ids);

			$this->pdo->beginTransaction();

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2_rules
				SET filter_id = ? WHERE filter_id IN ($ids_qmarks)");
			$sth->execute([$base_id, ...$ids]);

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2_actions
				SET filter_id = ? WHERE filter_id IN ($ids_qmarks)");
			$sth->execute([$base_id, ...$ids]);

			$sth = $this->pdo->prepare("DELETE FROM ttrss_filters2 WHERE id IN ($ids_qmarks)");
			$sth->execute($ids);

			$sth = $this->pdo->prepare("UPDATE ttrss_filters2 SET match_any_rule = true WHERE id = ?");
			$sth->execute([$base_id]);

			$this->pdo->commit();

			$this->_optimize($base_id);

		}
	}

	private function _optimize(int $id): void {

		$this->pdo->beginTransaction();

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_actions
			WHERE filter_id = ?");
		$sth->execute([$id]);

		$tmp = [];
		$dupe_ids = [];

		while ($line = $sth->fetch()) {
			$id = $line["id"];
			unset($line["id"]);

			if (in_array($line, $tmp))
				$dupe_ids[] = $id;
			else
				$tmp[] = $line;
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);

			$this->pdo->query("DELETE FROM ttrss_filters2_actions WHERE id IN ($ids_str)");
		}

		$sth = $this->pdo->prepare("SELECT * FROM ttrss_filters2_rules
			WHERE filter_id = ?");
		$sth->execute([$id]);

		$tmp = [];
		$dupe_ids = [];

		while ($line = $sth->fetch()) {
			$id = $line["id"];
			unset($line["id"]);

			if (in_array($line, $tmp))
				$dupe_ids[] = $id;
			else
				$tmp[] = $line;
		}

		if (count($dupe_ids) > 0) {
			$ids_str = join(",", $dupe_ids);

			$this->pdo->query("DELETE FROM ttrss_filters2_rules WHERE id IN ($ids_str)");
		}

		$this->pdo->commit();
	}

	/**
	 * @param array<int, int|string> $default_ids
	 */
	private function _feed_multi_select(string $id, array $default_ids = [], string $attributes = "",
		bool $include_all_feeds = true, ?int $root_id = null, int $nest_level = 0): string {

		$pdo = Db::pdo();

		$rv = "";

		//	print_r(in_array("CAT:6",$default_ids));

		if (!$root_id) {
			$rv .= "<select multiple=\true\" id=\"$id\" name=\"$id\" $attributes>";
			if ($include_all_feeds) {
				$is_selected = (in_array("0", $default_ids)) ? "selected=\"1\"" : "";
				$rv .= "<option $is_selected value=\"0\">".__('All feeds')."</option>";
			}
		}

		if (Prefs::get(Prefs::ENABLE_FEED_CATS, $_SESSION['uid'], $_SESSION['profile'] ?? null)) {

			if (!$root_id) $root_id = null;

			$sth = $pdo->prepare("SELECT id,title,
					(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
						c2.parent_cat = ttrss_feed_categories.id) AS num_children
					FROM ttrss_feed_categories
					WHERE owner_uid = :uid AND
					(parent_cat = :root_id OR (:root_id IS NULL AND parent_cat IS NULL)) ORDER BY title");

			$sth->execute([":uid" => $_SESSION['uid'], ":root_id" => $root_id]);

			while ($line = $sth->fetch()) {

				for ($i = 0; $i < $nest_level; $i++)
					$line["title"] = " " . $line["title"];

				$is_selected = in_array("CAT:".$line["id"], $default_ids) ? "selected=\"1\"" : "";

				$rv .= sprintf("<option $is_selected value='CAT:%d'>%s</option>",
					$line["id"], htmlspecialchars($line["title"]));

				if ($line["num_children"] > 0)
					$rv .= $this->_feed_multi_select($id, $default_ids, $attributes,
						$include_all_feeds, $line["id"], $nest_level+1);

				$f_sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
						WHERE cat_id = ? AND owner_uid = ? ORDER BY title");

				$f_sth->execute([$line['id'], $_SESSION['uid']]);

				while ($fline = $f_sth->fetch()) {
					$is_selected = (in_array($fline["id"], $default_ids)) ? "selected=\"1\"" : "";

					$fline["title"] = " " . $fline["title"];

					for ($i = 0; $i < $nest_level; $i++)
						$fline["title"] = " " . $fline["title"];

					$rv .= sprintf("<option $is_selected value='%d'>%s</option>",
						$fline["id"], htmlspecialchars($fline["title"]));
				}
			}

			if (!$root_id) {
				$is_selected = in_array("CAT:0", $default_ids) ? "selected=\"1\"" : "";

				$rv .= sprintf("<option $is_selected value='CAT:0'>%s</option>",
					__("Uncategorized"));

				$f_sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
						WHERE cat_id IS NULL AND owner_uid = ? ORDER BY title");
				$f_sth->execute([$_SESSION['uid']]);

				while ($fline = $f_sth->fetch()) {
					$is_selected = in_array($fline["id"], $default_ids) ? "selected=\"1\"" : "";

					$fline["title"] = " " . $fline["title"];

					for ($i = 0; $i < $nest_level; $i++)
						$fline["title"] = " " . $fline["title"];

					$rv .= sprintf("<option $is_selected value='%d'>%s</option>",
						$fline["id"], htmlspecialchars($fline["title"]));
				}
			}

		} else {
			$sth = $pdo->prepare("SELECT id,title FROM ttrss_feeds
					WHERE owner_uid = ? ORDER BY title");
			$sth->execute([$_SESSION['uid']]);

			while ($line = $sth->fetch()) {

				$is_selected = (in_array($line["id"], $default_ids)) ? "selected=\"1\"" : "";

				$rv .= sprintf("<option $is_selected value='%d'>%s</option>",
					$line["id"], htmlspecialchars($line["title"]));
			}
		}

		if (!$root_id) {
			$rv .= "</select>";
		}

		return $rv;
	}
}

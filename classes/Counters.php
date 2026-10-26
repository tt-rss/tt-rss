<?php
class Counters {

	/**
	 * @return array<int, array<string, int|string>>
	 */
	static function get_all(): array {
		return [
			...self::get_global(),
			...self::get_virt(),
			...self::get_labels(),
			...self::get_feeds(),
			...self::get_cats(),
		];
	}

	/**
	 * @param array<int>|null $feed_ids
	 * @param array<int>|null $label_ids
	 * @return array<int, array<string, int|string>>
	 */
	static function get_conditional(?array $feed_ids = null, ?array $label_ids = null): array {
		return [
			...self::get_global(),
			...self::get_virt(),
			...self::get_labels($label_ids),
			...self::get_feeds($feed_ids),
			...self::get_cats(is_array($feed_ids) ? Feeds::_cats_of($feed_ids, $_SESSION["uid"], true) : null)
		];
	}

	/**
	 * Sums a category's own counters with those of all its descendants.
	 *
	 * @param array<int, array<int>> $children parent category id => child category ids
	 * @param array<int, array{int, int, int}> $own category id => [unread, marked, published] of its own feeds
	 * @param array<int, array{int, int, int}> $totals memoised results, keyed by category id
	 * @param array<int, bool> $visiting categories on the current path, guards against cycles
	 * @return array{int, int, int}
	 */
	private static function sum_cat_tree(int $cat_id, array $children, array $own, array &$totals, array &$visiting): array {
		if (isset($totals[$cat_id]))
			return $totals[$cat_id];

		[$unread, $marked, $published] = $own[$cat_id] ?? [0, 0, 0];

		$visiting[$cat_id] = true;

		foreach ($children[$cat_id] ?? [] as $child_id) {
			if (isset($visiting[$child_id]))
				continue;

			[$child_unread, $child_marked, $child_published] = self::sum_cat_tree($child_id, $children, $own, $totals, $visiting);

			$unread += $child_unread;
			$marked += $child_marked;
			$published += $child_published;
		}

		unset($visiting[$cat_id]);

		return $totals[$cat_id] = [$unread, $marked, $published];
	}

	/**
	 * Loads the category tree and per-category counters once and sums them in PHP,
	 * so the number of queries doesn't grow with category nesting depth.
	 *
	 * @param array<int>|null $cat_ids
	 * @return array<int, array{id: int, kind: 'cat', counter: int, markedcounter?: int, publishedcounter?: int}>
	 */
	private static function get_cats(?array $cat_ids = null): array {
		if (is_array($cat_ids) && count($cat_ids) == 0)
			return [];

		$pdo = Db::pdo();
		$owner_uid = $_SESSION['uid'];

		/* Labels category */

		$ret = [
			[
				'id' => Feeds::CATEGORY_LABELS,
				'kind' => 'cat',
				'counter' => Feeds::_get_cat_unread(Feeds::CATEGORY_LABELS),
			],
		];

		$cats = ORM::for_table('ttrss_feed_categories')
			->select_many('id', 'parent_cat')
			->where('owner_uid', $owner_uid)
			->find_array();

		$all_cat_ids = [];
		$children = [];

		foreach ($cats as $cat) {
			$all_cat_ids[] = (int) $cat['id'];

			if ($cat['parent_cat'])
				$children[(int) $cat['parent_cat']][] = (int) $cat['id'];
		}

		$wanted_cat_ids = is_array($cat_ids) ?
			array_values(array_intersect($all_cat_ids, array_map(intval(...), $cat_ids))) : $all_cat_ids;

		/* conditional counters: only count feeds in the requested categories and their descendants */

		$cat_filter_qpart = "";
		$counted_cat_ids = [];

		if (is_array($cat_ids)) {
			$pending = $wanted_cat_ids;

			while (($id = array_pop($pending)) !== null) {
				if (isset($counted_cat_ids[$id]))
					continue;

				$counted_cat_ids[$id] = true;
				array_push($pending, ...($children[$id] ?? []));
			}

			$cat_filter_qpart = count($counted_cat_ids) > 0 ?
				"AND (f.cat_id IS NULL OR f.cat_id IN (" . arr_qmarks(array_keys($counted_cat_ids)) . "))" :
				"AND f.cat_id IS NULL";
		}

		$sth = $pdo->prepare("SELECT f.cat_id,
				SUM(CASE WHEN unread THEN 1 ELSE 0 END) AS count,
				SUM(CASE WHEN marked THEN 1 ELSE 0 END) AS count_marked,
				SUM(CASE WHEN published THEN 1 ELSE 0 END) AS count_published
			FROM ttrss_feeds f
				JOIN ttrss_user_entries ue ON (ue.feed_id = f.id)
			WHERE ue.owner_uid = ? $cat_filter_qpart
			GROUP BY f.cat_id");

		$sth->execute([$owner_uid, ...array_keys($counted_cat_ids)]);

		/* uncategorized (cat_id IS NULL) ends up under 0 */

		$own = [];

		while ($line = $sth->fetch()) {
			$own[(int) $line['cat_id']] = [(int) $line['count'], (int) $line['count_marked'], (int) $line['count_published']];
		}

		$totals = [];
		$visiting = [];

		foreach ([0, ...$wanted_cat_ids] as $id) {
			[$unread, $marked, $published] = $id == 0 ?
				($own[0] ?? [0, 0, 0]) : self::sum_cat_tree($id, $children, $own, $totals, $visiting);

			$ret[] = [
				'id' => $id,
				'kind' => 'cat',
				'markedcounter' => $marked,
				'publishedcounter' => $published,
				'counter' => $unread,
			];
		}

		return $ret;
	}

	/**
	 * @param array<int>|null $feed_ids
	 * @return array<int, array{id: int, title: string, error: string, updated: string, counter: int, markedcounter: int, publishedcounter: int, ts: int}>
	 */
	private static function get_feeds(?array $feed_ids = null): array {
		$ret = [];

		if (is_array($feed_ids) && count($feed_ids) === 0)
			return $ret;

		$feeds = ORM::for_table('ttrss_feeds')
			->table_alias('f')
			->select_many('f.id', 'f.title', 'f.last_error')
			->select_many_expr([
				'count' => 'SUM(CASE WHEN ue.unread THEN 1 ELSE 0 END)',
				'count_marked' => 'SUM(CASE WHEN ue.marked THEN 1 ELSE 0 END)',
				'count_published' => 'SUM(CASE WHEN ue.published THEN 1 ELSE 0 END)',
				'last_updated' => 'SUBSTRING_FOR_DATE(f.last_updated,1,19)',
			])
			->join('ttrss_user_entries', [ 'ue.feed_id', '=', 'f.id'], 'ue')
			->where('ue.owner_uid', $_SESSION['uid'])
			->group_by('f.id');

		if (is_array($feed_ids))
			$feeds->where_in('f.id', $feed_ids);

		foreach ($feeds->find_many() as $feed) {
			$ret[] = [
				'id' => $feed->id,
				'title' => truncate_string($feed->title, 30),
				'error' => $feed->last_error,
				'updated' => TimeHelper::make_local_datetime($feed->last_updated),
				'counter' => (int) $feed->count,
				'markedcounter' => (int) $feed->count_marked,
				'publishedcounter' => (int) $feed->count_published,
				'ts' => Feeds::_has_icon($feed->id) ? (int) filemtime(Feeds::_get_icon_file($feed->id)) : 0,
			];
		}

		return $ret;
	}

	/**
	 * @return array<int, array{id: string, counter: int}>
	 */
	private static function get_global(): array {
		return [
			['id' => 'global-unread', 'counter' => (int) Feeds::_get_global_unread()],
			['id' => 'subscribed-feeds', 'counter' => ORM::for_table('ttrss_feeds')->where('owner_uid', $_SESSION['uid'])->count()],
		];
	}

	/**
	 * @return array<int, array{id: int, counter: int, auxcounter: int, markedcounter?: int, publishedcounter?: int}>
	 */
	private static function get_virt(): array {
		$ret = [];

		foreach ([Feeds::FEED_ARCHIVED, Feeds::FEED_STARRED, Feeds::FEED_PUBLISHED,
			Feeds::FEED_FRESH, Feeds::FEED_ALL] as $feed_id) {

			$count = Feeds::_get_counters($feed_id, false, true);

			if (in_array($feed_id, [Feeds::FEED_ARCHIVED, Feeds::FEED_STARRED, Feeds::FEED_PUBLISHED]))
				$auxctr = Feeds::_get_counters($feed_id, false);
			else
				$auxctr = 0;

			$cv = [
				"id" => $feed_id,
				"counter" => (int) $count,
				"auxcounter" => (int) $auxctr
			];

			if ($feed_id == Feeds::FEED_STARRED)
				$cv['markedcounter'] = $auxctr;
			elseif ($feed_id == Feeds::FEED_PUBLISHED)
				$cv['publishedcounter'] = $auxctr;

			$ret[] = $cv;
		}

		foreach (PluginHost::getInstance()->get_feeds(Feeds::CATEGORY_SPECIAL) as $feed) {
			if (!implements_interface($feed['sender'], 'IVirtualFeed'))
				continue;

			/** @var Plugin&IVirtualFeed $feed['sender'] */

			$cv = [
				"id" => PluginHost::pfeed_to_feed_id($feed['id']),
				"counter" => $feed['sender']->get_unread($feed['id'])
			];

			if (method_exists($feed['sender'], 'get_total'))
				$cv["auxcounter"] = $feed['sender']->get_total($feed['id']);

			$ret[] = $cv;
		}

		return $ret;
	}

	/**
	 * @param array<int>|null $label_ids
	 * @return array<int, array{id: int, counter: int, auxcounter: int, markedcounter: int, publishedcounter: int, description: string}>
	 */
	static function get_labels(?array $label_ids = null): array {
		$ret = [];

		$pdo = Db::pdo();

		if (is_array($label_ids)) {
			if (count($label_ids) == 0)
				return [];

			$label_ids_qmarks = arr_qmarks($label_ids);

			$sth = $pdo->prepare("SELECT id,
						caption,
						SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS count_unread,
						SUM(CASE WHEN u1.marked = true THEN 1 ELSE 0 END) AS count_marked,
						SUM(CASE WHEN u1.published = true THEN 1 ELSE 0 END) AS count_published,
						COUNT(u1.unread) AS total
				FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
					(ttrss_labels2.id = label_id)
						LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id AND u1.owner_uid = ?
							WHERE ttrss_labels2.owner_uid = ? AND ttrss_labels2.id IN ($label_ids_qmarks)
								GROUP BY ttrss_labels2.id, ttrss_labels2.caption");
			$sth->execute([$_SESSION["uid"], $_SESSION["uid"], ...$label_ids]);
		} else {
			$sth = $pdo->prepare("SELECT id,
						caption,
						SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS count_unread,
						SUM(CASE WHEN u1.marked = true THEN 1 ELSE 0 END) AS count_marked,
						SUM(CASE WHEN u1.published = true THEN 1 ELSE 0 END) AS count_published,
						COUNT(u1.unread) AS total
				FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
					(ttrss_labels2.id = label_id)
						LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id AND u1.owner_uid = :uid
							WHERE ttrss_labels2.owner_uid = :uid
								GROUP BY ttrss_labels2.id, ttrss_labels2.caption");
			$sth->execute([":uid" => $_SESSION['uid']]);
		}

		while ($line = $sth->fetch()) {

			$id = Labels::label_to_feed_id($line["id"]);

			$cv = [
				"id" => $id,
				"counter" => (int) $line["count_unread"],
				"auxcounter" => (int) $line["total"],
				"markedcounter" => (int) $line["count_marked"],
				"publishedcounter" => (int) $line["count_published"],
				"description" => $line["caption"]
			];

			$ret[] = $cv;
		}

		return $ret;
	}
}

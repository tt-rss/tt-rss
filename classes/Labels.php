<?php
class Labels
{
	public static function label_to_feed_id(int $label): int {
		return LABEL_BASE_INDEX - 1 - abs($label);
	}

	public static function feed_to_label_id(int $feed): int {
		return LABEL_BASE_INDEX - 1 + abs($feed);
	}

	public static function find_id(string $label, int $owner_uid): int {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id FROM ttrss_labels2 WHERE LOWER(caption) = LOWER(?)
				AND owner_uid = ? LIMIT 1");
		$sth->execute([$label, $owner_uid]);

		if ($row = $sth->fetch()) {
			return $row['id'];
		} else {
			return 0;
		}
	}

	public static function find_caption(int $label, int $owner_uid): string {
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2 WHERE id = ?
				AND owner_uid = ? LIMIT 1");
		$sth->execute([$label, $owner_uid]);

		if ($row = $sth->fetch()) {
			return $row['caption'];
		} else {
			return "";
		}
	}

	/**
	 * @return array<int, array{id: int, fg_color: string, bg_color: string, caption: string}>
	 */
	public static function get_as_hash(int $owner_uid): array {
		$rv = [];
		$labels = Labels::get_all($owner_uid);

		foreach ($labels as $i => $label) {
			$rv[(int)$label["id"]] = $labels[$i];
		}

		return $rv;
	}

	/**
	 * @return array<int, array{id: int, fg_color: string, bg_color: string, caption: string}> An array of label detail arrays
	 */
	public static function get_all(int $owner_uid)	{
		$rv = [];

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT id, fg_color, bg_color, caption FROM ttrss_labels2
			WHERE owner_uid = ? ORDER BY caption");
		$sth->execute([$owner_uid]);

		while ($line = $sth->fetch(PDO::FETCH_ASSOC))
			$rv[] = $line;

		return $rv;
	}

	/**
	 * @param array{'no-labels': 1}|array<int, array{int, string, string, string}> $labels
	 * [label_id, caption, fg_color, bg_color]
	 *
	 * @see Article::_get_labels()
	 */
	public static function update_cache(int $owner_uid, int $id, array $labels, bool $force = false): void {
		$pdo = Db::pdo();

		if ($force)
			self::clear_cache($id);

		if (!$labels)
			$labels = Article::_get_labels($id);

		$labels = json_encode($labels);

		$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			label_cache = ? WHERE ref_id = ? AND owner_uid = ?");
		$sth->execute([$labels, $id, $owner_uid]);

	}

	public static function clear_cache(int $id): void	{

		$pdo = Db::pdo();

		$sth = $pdo->prepare("UPDATE ttrss_user_entries SET
			label_cache = '' WHERE ref_id = ?");
		$sth->execute([$id]);

	}

	public static function remove_article(int $id, string $label, int $owner_uid): void {

		$label_id = self::find_id($label, $owner_uid);

		if (!$label_id) return;

		$pdo = Db::pdo();

		$sth = $pdo->prepare("DELETE FROM ttrss_user_labels2
			WHERE
				label_id = ? AND
				article_id = ?");

		$sth->execute([$label_id, $id]);

		self::clear_cache($id);
	}

	public static function add_article(int $id, string $label, int $owner_uid): void {

		$label_id = self::find_id($label, $owner_uid);

		if (!$label_id) return;

		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT
				article_id FROM ttrss_labels2, ttrss_user_labels2
			WHERE
				label_id = id AND
				label_id = ? AND
				article_id = ? AND owner_uid = ?
			LIMIT 1");

		$sth->execute([$label_id, $id, $owner_uid]);

		if (!$sth->fetch()) {
			$sth = $pdo->prepare("INSERT INTO ttrss_user_labels2
				(label_id, article_id) VALUES (?, ?)");

			$sth->execute([$label_id, $id]);
		}

		self::clear_cache($id);

	}

	public static function remove(int $id, int $owner_uid): void {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$pdo = Db::pdo();
		$tr_in_progress = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception) {
			$tr_in_progress = true;
		}

		$sth = $pdo->prepare("SELECT caption FROM ttrss_labels2
			WHERE id = ?");
		$sth->execute([$id]);

		$row = $sth->fetch();
		$caption = $row['caption'];

		$sth = $pdo->prepare("DELETE FROM ttrss_labels2 WHERE id = ?
			AND owner_uid = ?");
		$sth->execute([$id, $owner_uid]);

		if ($sth->rowCount() != 0 && $caption) {

			/* Remove access key for the label */

			$ext_id = LABEL_BASE_INDEX - 1 - $id;

			$sth = $pdo->prepare("DELETE FROM ttrss_access_keys WHERE
				feed_id = ? AND owner_uid = ?");
			$sth->execute([$ext_id, $owner_uid]);

			/* Remove cached data */

			$sth = $pdo->prepare("UPDATE ttrss_user_entries SET label_cache = ''
				WHERE owner_uid = ?");
			$sth->execute([$owner_uid]);

		}

		if (!$tr_in_progress) $pdo->commit();
	}

	/**
	 * @return false|int false if the check for an existing label failed, otherwise the number of rows inserted (1 on success)
	 */
	public static function create(string $caption, ?string $fg_color = '', ?string $bg_color = '', ?int $owner_uid = null): false|int	{

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		$pdo = Db::pdo();

		$tr_in_progress = false;

		try {
			$pdo->beginTransaction();
		} catch (Exception) {
			$tr_in_progress = true;
		}

		$sth = $pdo->prepare("SELECT id FROM ttrss_labels2
			WHERE LOWER(caption) = LOWER(?) AND owner_uid = ?");
		$sth->execute([$caption, $owner_uid]);

		if (!$sth->fetch()) {
			$sth = $pdo->prepare("INSERT INTO ttrss_labels2
				(caption,owner_uid,fg_color,bg_color) VALUES (?, ?, ?, ?)");

			$sth->execute([$caption, $owner_uid, $fg_color, $bg_color]);

			$result = $sth->rowCount();
		} else {
			$result = false;
		}

		if (!$tr_in_progress) $pdo->commit();

		return $result;
	}
}

<?php
class Digest
{
	public static function send_headlines_digests(): void {
		$user_limit = 15; // amount of users to process (e.g. emails to send out)
		$limit = 1000; // maximum amount of headlines to include

		Debug::log("Sending digests, batch of max $user_limit users, headline limit = $limit");

		$pdo = Db::pdo();

		$res = $pdo->query("SELECT id, login, email FROM ttrss_users
				WHERE email != '' AND (last_digest_sent IS NULL OR last_digest_sent < NOW() - INTERVAL '1 day')");

		while ($line = $res->fetch()) {

			if (Prefs::get(Prefs::DIGEST_ENABLE, $line['id'])) {
				$preferred_ts = strtotime(Prefs::get(Prefs::DIGEST_PREFERRED_TIME, $line['id']) ?? '');

				// try to send digests within 2 hours of preferred time
				if ($preferred_ts && time() >= $preferred_ts &&
					time() - $preferred_ts <= 7200
				) {

					Debug::log("Sending digest for UID:" . $line['id'] . " - " . $line["email"]);

					$do_catchup = Prefs::get(Prefs::DIGEST_CATCHUP, $line['id']);

					global $tz_offset;

					// reset tz_offset global to prevent tz cache clash between users
					$tz_offset = -1;

					$tuple = Digest::prepare_headlines_digest($line["id"], 1, $limit);
					$digest = $tuple[0];
					$headlines_count = $tuple[1];
					$affected_ids = $tuple[2];
					$digest_text = $tuple[3];

					if ($headlines_count > 0) {

						$mailer = new Mailer();

						//$rc = $mail->quickMail($line["email"], $line["login"], Config::get(Config::DIGEST_SUBJECT), $digest, $digest_text);

						$rc = $mailer->mail(["to_name" => $line["login"],
							"to_address" => $line["email"],
							"subject" => Config::get(Config::DIGEST_SUBJECT),
							"message" => $digest_text,
							"message_html" => $digest]);

						//if (!$rc && $debug) Debug::log("ERROR: " . $mailer->lastError());

						Debug::log("RC=$rc");

						if ($rc && $do_catchup) {
							Debug::log("Marking affected articles as read...");
							Article::_catchup_by_id($affected_ids, Article::CATCHUP_MODE_MARK_AS_READ, $line["id"]);
						}
					} else {
						Debug::log("No headlines");
					}

					$sth = $pdo->prepare("UPDATE ttrss_users SET last_digest_sent = NOW()
						WHERE id = ?");
					$sth->execute([$line["id"]]);

				}
			}
		}

		Debug::log("All done.");
	}

	/**
	 * @return array{0: string, 1: int, 2: array<int>, 3: string}
	 */
	public static function prepare_headlines_digest(int $user_id, int $days = 1, int $limit = 1000) {

		$tpl = new Templator();
		$tpl_t = new Templator();

		$tpl->readTemplateFromFile("digest_template_html.txt");
		$tpl_t->readTemplateFromFile("digest_template.txt");

		$user_tz_string = Prefs::get(Prefs::USER_TIMEZONE, $user_id);
		$min_score = Prefs::get(Prefs::DIGEST_MIN_SCORE, $user_id);

		if ($user_tz_string == 'Automatic')
			$user_tz_string = 'GMT';

		$local_ts = TimeHelper::convert_timestamp(time(), 'UTC', $user_tz_string);

		$tpl->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
		$tpl->setVariable('CUR_TIME', date('G:i', $local_ts));
		$tpl->setVariable('TTRSS_HOST', Config::get_self_url());

		$tpl_t->setVariable('CUR_DATE', date('Y/m/d', $local_ts));
		$tpl_t->setVariable('CUR_TIME', date('G:i', $local_ts));
		$tpl_t->setVariable('TTRSS_HOST', Config::get_self_url());

		$affected_ids = [];
		$pdo = Db::pdo();

		$sth = $pdo->prepare("SELECT ttrss_entries.title,
				ttrss_feeds.title AS feed_title,
				COALESCE(ttrss_feed_categories.title, '" . __('Uncategorized') . "') AS cat_title,
				date_updated,
				ttrss_user_entries.ref_id,
				link,
				score,
				content,
				SUBSTRING_FOR_DATE(last_updated,1,19) AS last_updated
			FROM
				ttrss_user_entries,ttrss_entries,ttrss_feeds
			LEFT JOIN
				ttrss_feed_categories ON (cat_id = ttrss_feed_categories.id)
			WHERE
				ref_id = ttrss_entries.id AND feed_id = ttrss_feeds.id
				AND include_in_digest = true
				AND ttrss_entries.date_updated > NOW() - INTERVAL '$days day'
				AND ttrss_user_entries.owner_uid = :user_id
				AND unread = true
				AND score >= :min_score
			ORDER BY ttrss_feed_categories.title, ttrss_feeds.title, score DESC, date_updated DESC
			LIMIT " . (int)$limit);
		$sth->execute([':user_id' => $user_id, ':min_score' => $min_score]);

		$headlines_count = 0;
		$headlines = [];

		while ($line = $sth->fetch()) {
			$headlines[] = $line;
			$headlines_count++;
		}

		for ($i = 0; $i < count($headlines); $i++) {

			$line = $headlines[$i];

			$affected_ids[] = $line['ref_id'];

			$updated = TimeHelper::make_local_datetime($line['last_updated'], owner_uid: $user_id);

			if (Prefs::get(Prefs::ENABLE_FEED_CATS, $user_id)) {
				$line['feed_title'] = $line['cat_title'] . " / " . $line['feed_title'];
			}

			$article_labels = Article::_get_labels($line["ref_id"], $user_id);
			$article_labels_formatted = "";

			if (count($article_labels) > 0) {
				$article_labels_formatted = implode(", ", array_map(fn($a) => $a[1], $article_labels));
			}

			$tpl->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl->setVariable('ARTICLE_UPDATED', $updated);
			$tpl->setVariable('ARTICLE_EXCERPT',
				truncate_string(strip_tags($line["content"]), 300));
//			$tpl->setVariable('ARTICLE_CONTENT',
//				strip_tags($article_content));
			$tpl->setVariable('ARTICLE_LABELS', $article_labels_formatted, true);

			$tpl->addBlock('article');

			$tpl_t->setVariable('FEED_TITLE', $line["feed_title"]);
			$tpl_t->setVariable('ARTICLE_TITLE', $line["title"]);
			$tpl_t->setVariable('ARTICLE_LINK', $line["link"]);
			$tpl_t->setVariable('ARTICLE_UPDATED', $updated);
			$tpl_t->setVariable('ARTICLE_LABELS', $article_labels_formatted, true);
			$tpl_t->setVariable('ARTICLE_EXCERPT',
				truncate_string(strip_tags($line["content"]), 300, "..."), true);

			$tpl_t->addBlock('article');

			if (!isset($headlines[$i + 1]) || $headlines[$i]['feed_title'] != $headlines[$i + 1]['feed_title']) {
				$tpl->addBlock('feed');
				$tpl_t->addBlock('feed');
			}

		}

		$tpl->addBlock('digest');
		$tpl->generateOutputToString($tmp);

		$tpl_t->addBlock('digest');
		$tpl_t->generateOutputToString($tmp_t);

		return [$tmp, $headlines_count, $affected_ids, $tmp_t];
	}
}

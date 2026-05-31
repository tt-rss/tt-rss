<?php
/** @group integration */
final class FeedsCountersIntegrationTest extends DbTestCase {

    protected function setUp(): void {
        parent::setUp();

        // Ensure ALL articles are considered "fresh" regardless of when tests run.
        // Default FRESH_ARTICLE_MAX_AGE is 24 hours; seed data articles may be old.
        Prefs::set(Prefs::FRESH_ARTICLE_MAX_AGE, 999999, 1, null);
    }

    // ── Return type ──

    public function test_get_counters_returns_int(): void {
        $result = Feeds::_get_counters(Feeds::FEED_ALL);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // ── Virtual feeds ──

    public function test_get_counters_feed_all(): void {
        // All 11 seeded articles belong to owner_uid=1
        $this->assertEquals(11, Feeds::_get_counters(Feeds::FEED_ALL));
    }

    public function test_get_counters_feed_starred(): void {
        // seed.sql: articles 3, 8, 11 are marked=true
        $this->assertEquals(3, Feeds::_get_counters(Feeds::FEED_STARRED));
    }

    public function test_get_counters_feed_starred_unread_only(): void {
        // Starred articles: 3, 8, 11 — none are unread (all marked=true means read)
        $this->assertEquals(0, Feeds::_get_counters(Feeds::FEED_STARRED, false, true));
    }

    public function test_get_counters_feed_published(): void {
        // No articles are published in seed data
        $this->assertEquals(0, Feeds::_get_counters(Feeds::FEED_PUBLISHED));
    }

    public function test_get_counters_feed_fresh(): void {
        // Fresh = unread + score >= 0 + within FRESH_ARTICLE_MAX_AGE.
        // setUp() sets FRESH_ARTICLE_MAX_AGE=999999 so all articles are fresh.
        // Unread articles in seed: 1,2,4,5,6,7,9,10 = 8
        $this->assertEquals(8, Feeds::_get_counters(Feeds::FEED_FRESH));
    }

    public function test_get_counters_feed_recently_read(): void {
        // FEED_RECENTLY_READ always returns 0
        $this->assertEquals(0, Feeds::_get_counters(Feeds::FEED_RECENTLY_READ));
    }

    public function test_get_counters_feed_archived(): void {
        // Archived = feed_id IS NULL — no articles have null feed_id
        $this->assertEquals(0, Feeds::_get_counters(Feeds::FEED_ARCHIVED));
    }

    // ── Regular feed IDs ──

    public function test_get_counters_by_feed_id(): void {
        // Default (unread_only=false) returns TOTAL count.
        // Feed 1 (Hacker News): articles 1,2,3 → total = 3
        $this->assertEquals(3, Feeds::_get_counters(1));

        // Feed 2 (The Register): articles 4,5,6 → total = 3
        $this->assertEquals(3, Feeds::_get_counters(2));

        // Feed 3 (Ars Technica): articles 7,8 → total = 2
        $this->assertEquals(2, Feeds::_get_counters(3));

        // Feed 4 (MIT Tech Review): articles 9,10,11 → total = 3
        $this->assertEquals(3, Feeds::_get_counters(4));
    }

    public function test_get_counters_by_feed_id_all(): void {
        // Feed 1 has 3 articles (1,2,3)
        $this->assertEquals(3, Feeds::_get_counters(1, false, false));

        // Feed 2 has 3 articles (4,5,6)
        $this->assertEquals(3, Feeds::_get_counters(2, false, false));

        // Feed 3 has 2 articles (7,8)
        $this->assertEquals(2, Feeds::_get_counters(3, false, false));

        // Feed 4 has 3 articles (9,10,11)
        $this->assertEquals(3, Feeds::_get_counters(4, false, false));
    }

    public function test_get_counters_by_feed_id_unread_only(): void {
        $this->assertEquals(2, Feeds::_get_counters(1, false, true));
        $this->assertEquals(3, Feeds::_get_counters(2, false, true));
        $this->assertEquals(1, Feeds::_get_counters(3, false, true));
        $this->assertEquals(2, Feeds::_get_counters(4, false, true));
    }

    public function test_get_counters_nonexistent_feed(): void {
        // Feed 999 doesn't exist and has no user entries
        $this->assertEquals(0, Feeds::_get_counters(999));
    }

    // ── Category view ──

    public function test_get_counters_category(): void {
        // Category 1 (Technology): feeds 1,2,3 → articles 1-8
        // Unread: 1,2,4,5,6,7 = 6
        $this->assertEquals(6, Feeds::_get_counters(1, true));
    }

    public function test_get_counters_category_all(): void {
        // Category queries always call _get_cat_unread() → returns unread count
        // Category 1 (Technology) unread: 1,2,4,5,6,7 = 6
        $this->assertEquals(6, Feeds::_get_counters(1, true));
    }

    public function test_get_counters_category_unread_only(): void {
        // is_cat=true always returns unread via _get_cat_unread()
        $this->assertEquals(6, Feeds::_get_counters(1, true, true));
    }

    public function test_get_counters_category_science(): void {
        // Category 2 (Science & Nature): feed 4 → articles 9,10,11
        // Unread: 9,10 = 2
        $this->assertEquals(2, Feeds::_get_counters(2, true));

        // Total: 3 articles
        $this->assertEquals(3, Feeds::_get_counters(2, false, false));
    }

    public function test_get_counters_category_uncategorized(): void {
        // Category 0 = uncategorized — all seeded feeds have a category
        $this->assertEquals(0, Feeds::_get_counters(0, true));
    }

    public function test_get_counters_category_special(): void {
        // CATEGORY_SPECIAL (-1) returns 0
        $this->assertEquals(0, Feeds::_get_counters(Feeds::CATEGORY_SPECIAL, true));
    }

    // ── Tag feed ──

    public function test_get_counters_tag(): void {
        // Tags are stored in ttrss_tags table (not tag_cache).
        // Seed data only populates tag_cache strings, not ttrss_tags rows.
        // So tag queries return 0 unless tags are explicitly inserted.
        $this->assertEquals(0, Feeds::_get_counters("rust"));
    }

    public function test_get_counters_tag_nonexistent(): void {
        $this->assertEquals(0, Feeds::_get_counters("nonexistent-tag"));
    }

    public function test_get_counters_tag_all(): void {
        // No tags in ttrss_tags table from seed data
        $this->assertEquals(0, Feeds::_get_counters("ai", false, false));
    }

    // ── Label IDs ──

    public function test_get_counters_label_id(): void {
        // Create labels and assign them to articles
        $pdo = Db::pdo();

        // Insert label for uid=1
        $pdo->exec("INSERT INTO ttrss_labels2 (owner_uid, caption) VALUES (1, 'test-counter-label')");

        // Get the label id
        $label_id = (int) $pdo->query("SELECT id FROM ttrss_labels2 WHERE owner_uid = 1 AND caption = 'test-counter-label'")->fetchColumn();

        // Assign label to article 1 (which is unread)
        $pdo->exec("INSERT INTO ttrss_user_labels2 (label_id, article_id) VALUES ($label_id, 1)");

        // Get the negative label reference ID
        $label_ref = Labels::label_to_feed_id($label_id);

        // Count unread articles with this label
        $this->assertEquals(1, Feeds::_get_counters($label_ref));

        // Count all articles with this label
        $this->assertEquals(1, Feeds::_get_counters($label_ref, false, false));

        // Cleanup
        $pdo->exec("DELETE FROM ttrss_user_labels2 WHERE label_id = $label_id");
        $pdo->exec("DELETE FROM ttrss_labels2 WHERE id = $label_id");
    }

    // ── Owner UID ──

    public function test_get_counters_different_owner(): void {
        // Feed 1 exists but owner_uid=2 has no user entries
        $this->assertEquals(0, Feeds::_get_counters(1, false, false, 2));
        $this->assertEquals(0, Feeds::_get_counters(Feeds::FEED_ALL, false, false, 2));
    }

    public function test_get_counters_category_different_owner(): void {
        // Category 1 has no entries for owner_uid=2
        $this->assertEquals(0, Feeds::_get_counters(1, true, false, 2));
    }

    // ── Cross-feed totals ──

    public function test_get_counters_all_total(): void {
        // FEED_ALL for uid=1 should include all 11 articles
        $this->assertEquals(11, Feeds::_get_counters(Feeds::FEED_ALL));

        // Unread-only should be 8
        $this->assertEquals(8, Feeds::_get_counters(Feeds::FEED_ALL, false, true));
    }

    public function test_get_counters_sum_per_feed(): void {
        // Sum of individual feed counts should equal FEED_ALL total
        $total = 0;
        for ($i = 1; $i <= 4; $i++) {
            $total += Feeds::_get_counters($i, false, false);
        }
        $this->assertEquals(11, $total);

        // Unread sum
        $unread_total = 0;
        for ($i = 1; $i <= 4; $i++) {
            $unread_total += Feeds::_get_counters($i, false, true);
        }
        $this->assertEquals(8, $unread_total);
    }

    // ── Counters::get_feeds (via get_conditional) ──

    public function test_counters_get_feeds_returns_array(): void {
        $result = Counters::get_conditional([1, 2]);

        // @phpstan-ignore-next-line
        $this->assertIsArray($result);
    }

    public function test_counters_get_feeds_contains_requested_feeds(): void {
        $result = Counters::get_conditional([1, 2]);

        // Filter to only feed entries: has numeric id and 'title' key (not cats which have 'kind')
        $feed_entries = array_filter($result, fn ($entry) =>
            isset($entry["id"]) && is_int($entry["id"]) && $entry["id"] > 0
            && isset($entry["title"]) && isset($entry["ts"])
        );

        $this->assertCount(2, $feed_entries, "Should contain exactly feeds 1 and 2");

        // Build lookup by id
        $by_id = array_column($feed_entries, null, "id");

        // Feed 1 (Hacker News): 2 unread, 1 marked
        $this->assertEquals(1, $by_id[1]["id"]);
        $this->assertEquals(2, $by_id[1]["counter"], "Feed 1 should have 2 unread");
        $this->assertEquals(1, $by_id[1]["markedcounter"], "Feed 1 should have 1 starred");
        $this->assertEquals(0, $by_id[1]["publishedcounter"]);
        $this->assertArrayHasKey("error", $by_id[1]);
        $this->assertArrayHasKey("updated", $by_id[1]);

        // Feed 2 (The Register): 3 unread, 0 marked
        $this->assertEquals(3, $by_id[2]["counter"], "Feed 2 should have 3 unread");
        $this->assertEquals(0, $by_id[2]["markedcounter"]);
    }

    public function test_counters_get_feeds_empty_array(): void {
        // Passing an empty array should return no feed entries
        $result = Counters::get_conditional([]);

        $feed_entries = array_filter($result, fn ($entry) =>
            isset($entry["id"]) && is_int($entry["id"]) && $entry["id"] > 0
        );

        $this->assertEmpty($feed_entries, "Empty feed_ids should yield no feed entries");
    }

    // ── Counters::get_all() ──

    public function test_counters_get_all_returns_array(): void {
        $result = Counters::get_all();

        $this->assertNotEmpty($result);
    }

    public function test_counters_get_all_contains_global_unread(): void {
        $result = Counters::get_all();

        $global = array_values(array_filter($result, fn ($entry) =>
            $entry["id"] === "global-unread"
        ));

        $this->assertCount(1, $global);
        $this->assertArrayHasKey("counter", $global[0]);
        $this->assertIsInt($global[0]["counter"]);
        $this->assertGreaterThanOrEqual(0, $global[0]["counter"]);
    }

    public function test_counters_get_all_contains_virtual_feeds(): void {
        $result = Counters::get_all();

        $virtual_ids = [Feeds::FEED_STARRED, Feeds::FEED_PUBLISHED, Feeds::FEED_FRESH, Feeds::FEED_ALL, Feeds::FEED_ARCHIVED];
        $virtual_entries = array_values(array_filter($result, fn ($entry) =>
            isset($entry["id"]) && in_array($entry["id"], $virtual_ids, true)
        ));

        $this->assertGreaterThanOrEqual(5, count($virtual_entries), "Should contain at least 5 virtual feed entries");

        // Verify each expected virtual feed has required keys
        foreach ($virtual_entries as $entry) {
            $this->assertArrayHasKey("counter", $entry);
        }
    }

    public function test_counters_get_all_contains_category_entries(): void {
        $result = Counters::get_all();

        $cat_entries = array_values(array_filter($result, fn ($entry) =>
            isset($entry["kind"]) && $entry["kind"] === "cat"
        ));

        // Should have at least the Labels category + our 2 seed categories
        $this->assertNotEmpty($cat_entries);
    }

    public function test_counters_get_all_contains_label_entries(): void {
        $result = Counters::get_all();

        // Label entries have negative ids (Labels::label_to_feed_id()) and a description key
        $label_entries = array_values(array_filter($result, fn ($entry) =>
            isset($entry["id"]) && $entry["id"] < 0 && isset($entry["description"])
        ));

        // Seed data has 2 labels (test-label-1, test-label-2)
        $this->assertCount(2, $label_entries, "Should contain seed label entries");
    }

    // ── Counters::get_labels() ──

    public function test_counters_get_labels_returns_array(): void {
        $result = Counters::get_labels();

        $this->assertNotEmpty($result);
    }

    public function test_counters_get_labels_with_ids(): void {
        $pdo = Db::pdo();

        // Get existing label ids from seed data
        $label_ids = $pdo->query("SELECT id FROM ttrss_labels2 WHERE owner_uid = 1")->fetchAll(
            PDO::FETCH_COLUMN
        );

        $this->assertNotEmpty($label_ids);

        $result = Counters::get_labels($label_ids);

        // Should return entries for the requested labels
        $this->assertCount(count($label_ids), $result);

        // Each entry should have expected keys
        foreach ($result as $entry) {
            $this->assertArrayHasKey("id", $entry);
            $this->assertArrayHasKey("counter", $entry);
            $this->assertArrayHasKey("auxcounter", $entry);
            $this->assertArrayHasKey("description", $entry);
        }
    }

    public function test_counters_get_labels_empty_array(): void {
        $result = Counters::get_labels([]);

        $this->assertEmpty($result);
    }

    public function test_counters_get_labels_nonexistent_ids(): void {
        // Nonexistent label ids should return empty array
        $result = Counters::get_labels([99999, 99998]);

        $this->assertEmpty($result);
    }

    // ── Counters::get_conditional with label_ids ──

    public function test_counters_get_conditional_with_label_ids(): void {
        $pdo = Db::pdo();

        // Get existing label ids
        $label_ids = $pdo->query("SELECT id FROM ttrss_labels2 WHERE owner_uid = 1")->fetchAll(
            PDO::FETCH_COLUMN
        );

        $this->assertNotEmpty($label_ids);

        $result = Counters::get_conditional(null, $label_ids);

        // Should contain label entries for the requested labels
        $label_entries = array_values(array_filter($result, fn ($entry) =>
            isset($entry["id"]) && $entry["id"] < 0 && isset($entry["description"])
        ));

        $this->assertCount(count($label_ids), $label_entries);
    }

    public function test_counters_get_conditional_with_both_feed_and_label_ids(): void {
        $pdo = Db::pdo();

        $feed_ids = [1, 2];
        $label_ids = $pdo->query("SELECT id FROM ttrss_labels2 WHERE owner_uid = 1")->fetchAll(
            PDO::FETCH_COLUMN
        );

        $result = Counters::get_conditional($feed_ids, $label_ids);

        // Should contain both feed and label entries
        // Note: may also include feed 0 (uncategorized) if any feeds lack a category
        $feed_entries = array_filter($result, fn ($entry) =>
            isset($entry["id"]) && is_int($entry["id"]) && $entry["id"] > 0
        );
        $label_entries = array_filter($result, fn ($entry) =>
            isset($entry["id"]) && $entry["id"] < 0 && isset($entry["description"])
        );

        $this->assertGreaterThanOrEqual(2, count($feed_entries), "Should contain at least 2 requested feed entries");
        $this->assertCount(count($label_ids), $label_entries, "Should contain label entries");
    }
}

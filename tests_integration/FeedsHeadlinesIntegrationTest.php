<?php
/** @group integration */
final class FeedsHeadlinesIntegrationTest extends DbTestCase {
    protected function setUp(): void {
        parent::setUp();

        // Ensure ALL articles are considered "fresh" regardless of when tests run.
        // Default FRESH_ARTICLE_MAX_AGE is 24 hours; seed data articles may be old.
        Prefs::set(Prefs::FRESH_ARTICLE_MAX_AGE, 999999, 1, null);
    }

    /**
     * _get_headlines returns a 9-element array:
     * [0] PDOResult — headline rows
     * [1] string — feed title
     * [2] string — feed site URL
     * [3] string — last error
     * [4] string — last updated
     * [5] array — search words
     * [6] int — always 1
     * [7] bool — whether virtual feed
     * [8] string — query error override (empty string on success)
     */
    public function test_get_headlines_return_structure(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title, $feed_site_url, $last_error, $last_updated,
         $search_words, $flag, $is_vfeed, $query_error] = Feeds::_get_headlines($params);

        $this->assertIsObject($res, "Element 0 should be a PDO result set");
        $this->assertIsString($feed_title);
        $this->assertIsString($feed_site_url);
        $this->assertIsString($last_error);
        $this->assertIsString($last_updated);
        $this->assertIsArray($search_words);
        $this->assertIsBool($is_vfeed);
        $this->assertIsString($query_error);

        // FEED_ALL is a virtual feed
        $this->assertTrue($is_vfeed);
        // No query error
        $this->assertEmpty($query_error);
    }

    // ── Feed ID queries ──

    public function test_get_headlines_by_feed_id(): void {
        $params = [
            "feed" => 1, // Hacker News
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals("Hacker News", $feed_title);
        $this->assertGreaterThanOrEqual(3, count($headlines), "Feed 1 should have at least 3 articles");

        // All headlines should have required columns
        foreach ($headlines as $hl) {
            $this->assertArrayHasKey("id", $hl);
            $this->assertArrayHasKey("title", $hl);
            $this->assertArrayHasKey("guid", $hl);
            $this->assertArrayHasKey("feed_id", $hl);
            $this->assertArrayHasKey("unread", $hl);
            $this->assertArrayHasKey("marked", $hl);
        }
    }

    public function test_get_headlines_by_feed_id_empty(): void {
        // Feed id 999 does not exist
        $params = [
            "feed" => 999,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEmpty($headlines);
    }

    public function test_get_headlines_by_feed_id_different_owner(): void {
        // Feed 1 exists but owner_uid=2 has no subscriptions
        $params = [
            "feed" => 1,
            "view_mode" => "all",
            "owner_uid" => 2,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEmpty($headlines, "Owner 2 should see no headlines for feed 1");
    }

    // ── Virtual feeds ──

    public function test_get_headlines_feed_all(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title, , , , , , $is_vfeed] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertTrue($is_vfeed);
        // FEED_ALL returns all articles for the owner
        $this->assertGreaterThanOrEqual(11, count($headlines), "FEED_ALL should return all 11 seeded articles");
    }

    public function test_get_headlines_feed_starred(): void {
        $params = [
            "feed" => Feeds::FEED_STARRED,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, , , , , , , $is_vfeed] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertTrue($is_vfeed);
        // seed.sql marks articles 3, 8, 11 as marked=true
        $this->assertEquals(3, count($headlines), "Should return exactly 3 starred articles");

        foreach ($headlines as $hl) {
            $this->assertTrue($hl["marked"], "Each starred headline should be marked");
        }
    }

    public function test_get_headlines_feed_fresh(): void {
        // Fresh articles: unread, score >= 0, entered within FRESH_ARTICLE_MAX_AGE hours
        // Default max_age is 24 hours. All seeded articles are within 24h of "now".
        // Unread articles in seed: 1,2,4,5,6,7,9,10 = 8 articles
        $params = [
            "feed" => Feeds::FEED_FRESH,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertGreaterThan(0, count($headlines), "Should return some fresh articles");

        foreach ($headlines as $hl) {
            $this->assertTrue($hl["unread"], "Each fresh headline should be unread");
        }
    }

    public function test_get_headlines_feed_recently_read(): void {
        // Articles 3, 8, 11 are marked=true in seed but have last_read=NULL.
        // Use _catchup_by_id to set last_read = NOW() so they qualify as
        // "recently read" (unread=false, last_read IS NOT NULL,
        // last_read > NOW() - INTERVAL '1 day').
        Article::_catchup_by_id([3, 8, 11], Article::CATCHUP_MODE_MARK_AS_READ, 1);

        $params = [
            "feed" => Feeds::FEED_RECENTLY_READ,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title, , , , , , $is_vfeed] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        // Virtual feed
        $this->assertTrue($is_vfeed);

        // Feed title should be "Recently read"
        $this->assertEquals("Recently read", $feed_title);

        // Exactly 3 articles should appear — the ones we just caught up
        $this->assertEquals(3, count($headlines));

        // Verify the correct article IDs are present
        $ids = array_column($headlines, "id");
        $this->assertEquals([3, 8, 11], $ids);

        // All should be read
        foreach ($headlines as $hl) {
            $this->assertFalse($hl["unread"], "Recently read articles should not be unread");
        }
    }

    // ── View modes ──

    public function test_get_headlines_view_mode_unread(): void {
        $params = [
            "feed" => 1, // Hacker News (has 3 articles: 2 unread, 1 read)
            "view_mode" => "unread",
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals(2, count($headlines), "Feed 1 should have 2 unread articles");

        foreach ($headlines as $hl) {
            $this->assertTrue($hl["unread"], "Each headline should be unread");
        }
    }

    public function test_get_headlines_view_mode_marked(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "marked",
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        // seed.sql has 3 marked articles (ids 3, 8, 11)
        $this->assertEquals(3, count($headlines), "Should return 3 marked articles");

        foreach ($headlines as $hl) {
            $this->assertTrue($hl["marked"], "Each headline should be marked");
        }
    }

    public function test_get_headlines_view_mode_published(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "published",
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        // None of the seeded articles are published
        $this->assertEmpty($headlines, "No published articles in seed data");
    }

    // ── Pagination ──

    public function test_get_headlines_limit(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "all",
            "limit" => 5,
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals(5, count($headlines), "Limit of 5 should return exactly 5 headlines");
    }

    public function test_get_headlines_offset(): void {
        // First call: limit=5, offset=0 (page 1)
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "all",
            "limit" => 5,
            "offset" => 0,
            "owner_uid" => 1,
        ];

        [$res1] = Feeds::_get_headlines($params);
        $headlines_page1 = $res1->fetchAll(PDO::FETCH_ASSOC);

        // Second call: limit=5, offset=5 (page 2) — must use same limit to share matview
        $params["offset"] = 5;
        [$res2] = Feeds::_get_headlines($params);
        $headlines_page2 = $res2->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(5, $headlines_page1);
        $this->assertCount(5, $headlines_page2);

        // Page 1 and page 2 should not overlap
        $page1_ids = array_column($headlines_page1, "id");
        $page2_ids = array_column($headlines_page2, "id");
        $overlap = array_intersect($page1_ids, $page2_ids);
        $this->assertEmpty($overlap, "Page 1 and page 2 should not overlap");

        // Together they should be 10 distinct articles
        $all_ids = array_merge($page1_ids, $page2_ids);
        $this->assertCount(10, array_unique($all_ids));
    }

    // ── Since ID ──

    public function test_get_headlines_since_id(): void {
        $params = [
            "feed" => Feeds::FEED_ALL,
            "view_mode" => "all",
            "since_id" => 5,
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        // Should only return articles with id > 5
        $ids = array_column($headlines, "id");
        foreach ($ids as $id) {
            $this->assertGreaterThan(5, $id, "All returned articles should have id > 5");
        }
    }

    // ── Category view ──

    public function test_get_headlines_cat_view(): void {
        // Category 1 = "Technology" (feeds 1, 2, 3)
        $params = [
            "feed" => 1, // category id
            "view_mode" => "all",
            "cat_view" => true,
            "owner_uid" => 1,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals("Technology", $feed_title);
        // Category 1 has 3 feeds with 3+3+2 = 8 articles
        $this->assertEquals(8, count($headlines), "Category Technology should have 8 articles");
    }

    public function test_get_headlines_cat_view_uncategorized(): void {
        $params = [
            "feed" => Feeds::CATEGORY_UNCATEGORIZED,
            "view_mode" => "all",
            "cat_view" => true,
            "owner_uid" => 1,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals("Uncategorized", $feed_title);
        // All seeded feeds have a category, so uncategorized should be empty
        $this->assertEmpty($headlines);
    }

    // ── Metadata ──

    public function test_get_headlines_metadata_feed(): void {
        $params = [
            "feed" => 1,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res, $feed_title, $feed_site_url, $last_error, $last_updated] = Feeds::_get_headlines($params);

        $this->assertEquals("Hacker News", $feed_title);
        $this->assertEquals("https://news.ycombinator.com", $feed_site_url);
        $this->assertEmpty($last_error);
    }

    public function test_get_headlines_metadata_category(): void {
        $params = [
            "feed" => 1,
            "view_mode" => "all",
            "cat_view" => true,
            "owner_uid" => 1,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);

        $this->assertEquals("Technology", $feed_title);
    }

    // ── Headline columns ──

    public function test_get_headlines_columns(): void {
        $params = [
            "feed" => 1,
            "view_mode" => "all",
            "owner_uid" => 1,
        ];

        [$res] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($headlines);

        $expected_columns = [
            "id", "date_entered", "guid", "title", "updated",
            "label_cache", "tag_cache", "always_display_enclosures",
            "site_url", "note", "num_comments", "comments",
            "int_id", "uuid", "lang", "hide_images",
            "unread", "feed_id", "marked", "published", "link",
            "last_read", "last_marked", "last_published",
            "content", "author", "score",
            "num_labels", "num_enclosures",
        ];

        foreach ($headlines as $hl) {
            foreach ($expected_columns as $col) {
                $this->assertArrayHasKey($col, $hl, "Missing column: $col");
            }
        }
    }

    // ── Feed with no articles for owner ──

    public function test_get_headlines_feed_not_subscribed(): void {
        // Feed 1 exists in the DB but owner_uid=2 has no ttrss_user_entries for it
        $params = [
            "feed" => 1,
            "view_mode" => "all",
            "owner_uid" => 2,
        ];

        [$res, $feed_title] = Feeds::_get_headlines($params);
        $headlines = $res->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEmpty($headlines);
        // Feed doesn't exist for owner_uid=2, so _get_title returns "Unknown feed"
        $this->assertEquals("Unknown feed (1)", $feed_title);
    }
}

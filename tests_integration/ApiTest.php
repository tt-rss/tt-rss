<?php
/** @group integration */
final class ApiTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();

        // Ensure ALL articles are considered "fresh" regardless of when tests run.
        // Default FRESH_ARTICLE_MAX_AGE is 24 hours; seed data articles may be old.
        Prefs::set(Prefs::FRESH_ARTICLE_MAX_AGE, 999999, 1, null);
    }

    public function test_getVersion() : void {
        $resp = $this->api(["op" => "getVersion"]);

        $this->common_assertions($resp);
        $this->assertArrayHasKey("version", $resp['content']);
    }

    public function test_getUnread() : void {
        $resp = $this->api(["op" => "getUnread"]);
        $this->common_assertions($resp);

        $this->assertArrayHasKey("unread", $resp['content']);
    }

    public function test_subscribeToFeed() : void {

        $feed_url = $this->app_url . "/tests_integration/feed.xml";

        $resp = $this->api(["op" => "subscribeToFeed", "feed_url" => $feed_url]);
        $this->common_assertions($resp);

        $this->assertArrayHasKey("feed_id", $resp['content']['status']);
    }

    public function test_getCounters() : void {
        $resp = $this->api(["op" => "getCounters"]);
        $this->common_assertions($resp);

        foreach ($resp['content'] as $ctr) {
            $this->assertIsArray($ctr);

            foreach (["id", "counter"] as $k) {
                $this->assertArrayHasKey($k, $ctr);
                $this->assertNotNull($ctr[$k]);
            }
        }
    }

    public function test_getFeedTree() : void {
        $resp = $this->api(["op" => "getFeedTree"]);

        $this->assertArrayHasKey('categories', $resp['content']);
        $this->assertArrayHasKey('items', $resp['content']['categories']);

        foreach ($resp['content']['categories']['items'] as $cat) {

            foreach (["id", "bare_id", "name", "items"] as $k) {
                $this->assertArrayHasKey($k, $cat);
            }

            foreach ($cat['items'] as $feed) {
                $this->assertIsArray($feed);

                foreach (["id", "name", "unread", "bare_id"] as $k) {
                    $this->assertArrayHasKey($k, $feed);
                    $this->assertNotNull($feed[$k]);
                }
            }
        }
    }

    public function test_getHeadlines() : void {
        // ── Basic structure ──

        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-4"]); // FEED_ALL
        $this->common_assertions($resp);

        // Without include_header, content is a numerically indexed array of headlines
        $this->assertIsArray($resp['content']);
        $this->assertIsInt($resp['content'][0]['id']);

        // ── Virtual feeds ──

        // FEED_ALL ("-4") — all 11 articles
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-4"]);
        $this->assertEquals(11, count($resp['content']));

        // FEED_STARRED ("-1") — 3 marked articles
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-1"]);
        $this->assertEquals(3, count($resp['content']));
        foreach ($resp['content'] as $hl) {
            $this->assertTrue($hl['marked'], "Starred headline should be marked");
        }

        // FEED_FRESH ("-3") — unread recent articles
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-3"]);
        $this->assertGreaterThan(0, count($resp['content']));
        foreach ($resp['content'] as $hl) {
            $this->assertTrue($hl['unread'], "Fresh headline should be unread");
        }

        // FEED_RECENTLY_READ ("-6") — always empty in this API path
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-6"]);
        $this->assertIsArray($resp['content']);

        // FEED_PUBLISHED ("-2") — 0 published articles in seed data
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "-2"]);
        $this->assertEquals(0, count($resp['content']));

        // ── Regular feed IDs ──

        // Feed 1 (Hacker News) — 3 articles, 2 unread
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "1"]);
        $this->assertEquals(3, count($resp['content']));
        $feed_ids = array_column($resp['content'], 'feed_id');
        $this->assertEquals(array_fill(0, 3, 1), $feed_ids);

        // Feed 2 (The Register) — 3 articles, all unread
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "2"]);
        $this->assertEquals(3, count($resp['content']));
        foreach ($resp['content'] as $hl) {
            $this->assertTrue($hl['unread']);
        }

        // Nonexistent feed
        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "999"]);
        $this->assertEquals(0, count($resp['content']));

        // ── View modes ──

        // Unread mode on FEED_ALL
        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "view_mode" => "unread",
        ]);
        $this->assertEquals(8, count($resp['content']));
        foreach ($resp['content'] as $hl) {
            $this->assertTrue($hl['unread']);
        }

        // Marked mode on FEED_ALL
        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "view_mode" => "marked",
        ]);
        $this->assertEquals(3, count($resp['content']));
        foreach ($resp['content'] as $hl) {
            $this->assertTrue($hl['marked']);
        }

        // ── Pagination ──

        // Default limit is 200, but we test explicit limit
        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "limit" => 5,
        ]);
        $this->assertEquals(5, count($resp['content']));

        // Offset (skip)
        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "limit" => 5,
            "skip" => 5,
        ]);
        $this->assertEquals(5, count($resp['content']));

        // ── Article structure ──

        $resp = $this->api(["op" => "getHeadlines", "feed_id" => "1"]);
        $hl = $resp['content'][0];

        $this->assertArrayHasKey("id", $hl);
        $this->assertArrayHasKey("guid", $hl);
        $this->assertArrayHasKey("unread", $hl);
        $this->assertArrayHasKey("marked", $hl);
        $this->assertArrayHasKey("published", $hl);
        $this->assertArrayHasKey("updated", $hl);
        $this->assertArrayHasKey("title", $hl);
        $this->assertArrayHasKey("link", $hl);
        $this->assertArrayHasKey("feed_id", $hl);
        $this->assertArrayHasKey("tags", $hl);
        $this->assertArrayHasKey("labels", $hl);
        $this->assertArrayHasKey("feed_title", $hl);
        $this->assertArrayHasKey("comments_count", $hl);
        $this->assertArrayHasKey("score", $hl);
        $this->assertArrayHasKey("author", $hl);
        $this->assertArrayHasKey("note", $hl);
        $this->assertArrayHasKey("lang", $hl);
        $this->assertArrayHasKey("site_url", $hl);

        $this->assertIsInt($hl['id']);
        $this->assertIsString($hl['guid']);
        $this->assertIsBool($hl['unread']);
        $this->assertIsBool($hl['marked']);
        $this->assertIsInt($hl['updated']);
        $this->assertIsString($hl['title']);
        $this->assertIsInt($hl['feed_id']);
        $this->assertIsArray($hl['tags']);
        $this->assertIsArray($hl['labels']);
        $this->assertIsInt($hl['comments_count']);
        $this->assertIsInt($hl['score']);

        // ── With excerpt and content ──

        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "1",
            "show_excerpt" => true,
            "show_content" => true,
        ]);
        $hl = $resp['content'][0];

        $this->assertArrayHasKey("excerpt", $hl);
        $this->assertIsString($hl['excerpt']);
        $this->assertNotEmpty($hl['excerpt']);

        $this->assertArrayHasKey("content", $hl);
        $this->assertIsString($hl['content']);

        // ── Header ──
        // With include_header=true, content is [headlines_header, headlines]

        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "include_header" => true,
        ]);
        $this->assertIsArray($resp['content']);
        $this->assertCount(2, $resp['content']);
        $header = $resp['content'][0];
        $headlines = $resp['content'][1];

        $this->assertArrayHasKey("id", $header);
        $this->assertArrayHasKey("first_id", $header);
        $this->assertArrayHasKey("is_cat", $header);
        $this->assertEquals("-4", $header['id']);
        $this->assertFalse($header['is_cat']);
        $this->assertIsArray($headlines);
        $this->assertGreaterThan(0, count($headlines));

        // ── Category view ──

        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "1",
            "is_cat" => true,
            "include_header" => true,
        ]);
        $this->assertGreaterThan(0, count($resp['content'][1]));
        $this->assertTrue($resp['content'][0]['is_cat']);

        // ── Since ID ──

        $resp = $this->api([
            "op" => "getHeadlines",
            "feed_id" => "-4",
            "since_id" => 5,
        ]);
        foreach ($resp['content'] as $hl) {
            $this->assertGreaterThan(5, $hl['id']);
        }
    }

    public function test_getArticle_markAsRead_verify() : void {
        // Article 1 (uuid-hn-001) starts as unread (from seed.sql)
        $article_id = 1;

        // 1. Retrieve the article and verify initial state
        $resp = $this->api(["op" => "getArticle", "article_id" => $article_id]);
        $this->common_assertions($resp);

        $articles = $resp['content'];
        $this->assertIsArray($articles);
        $this->assertNotEmpty($articles);

        $article = $articles[0];
        $this->assertEquals($article_id, $article['id']);
        $this->assertTrue($article['unread'], "Article should start as unread");
        $this->assertFalse($article['marked'], "Article should not be marked initially");
        $this->assertArrayHasKey('title', $article);
        $this->assertArrayHasKey('guid', $article);
        $this->assertArrayHasKey('content', $article);
        $this->assertArrayHasKey('feed_id', $article);

        // 2. Mark the article as read using updateArticle
        // field=2 (unread), mode=0 (false)
        $resp = $this->api([
            "op" => "updateArticle",
            "article_ids" => $article_id,
            "field" => 2,
            "mode" => 0,
        ]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);
        $this->assertEquals(1, $resp['content']['updated']);

        // 3. Retrieve the article again and verify the change
        $resp = $this->api(["op" => "getArticle", "article_id" => $article_id]);
        $this->common_assertions($resp);

        $article = $resp['content'][0];
        $this->assertFalse($article['unread'], "Article should now be read");

        // 4. Toggle unread back to true (mode=2 toggles)
        $resp = $this->api([
            "op" => "updateArticle",
            "article_ids" => $article_id,
            "field" => 2,
            "mode" => 2,
        ]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // 5. Verify it is unread again after toggle
        $resp = $this->api(["op" => "getArticle", "article_id" => $article_id]);
        $this->common_assertions($resp);

        $article = $resp['content'][0];
        $this->assertTrue($article['unread'], "Article should be unread after toggle");
    }

    public function test_markArticleAsMarked() : void {
        // Article 3 (uuid-hn-003) starts as marked=true (from seed.sql)
        $article_id = 3;

        // 1. Retrieve the article and verify initial state
        $resp = $this->api(["op" => "getArticle", "article_id" => $article_id]);
        $this->common_assertions($resp);

        $article = $resp['content'][0];
        $this->assertTrue($article['marked'], "Article should start as marked");

        // 2. Unmark the article (field=0=marked, mode=0=false)
        $resp = $this->api([
            "op" => "updateArticle",
            "article_ids" => $article_id,
            "field" => 0,
            "mode" => 0,
        ]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // 3. Verify it is now unmarked
        $resp = $this->api(["op" => "getArticle", "article_id" => $article_id]);
        $this->common_assertions($resp);

        $article = $resp['content'][0];
        $this->assertFalse($article['marked'], "Article should now be unmarked");
    }

    public function test_subscribeUnsubscribeLifecycle() : void {
        $feed_url = $this->app_url . "/tests_integration/feed.xml";

        // 1. Subscribe to a feed
        $resp = $this->api(["op" => "subscribeToFeed", "feed_url" => $feed_url]);
        $this->common_assertions($resp);
        $this->assertArrayHasKey("feed_id", $resp['content']['status']);

        $feed_id = $resp['content']['status']['feed_id'];
        $this->assertIsInt($feed_id);

        // 2. Verify the feed appears in getFeedTree
        $resp = $this->api(["op" => "getFeedTree"]);
        $this->common_assertions($resp);

        $found = false;
        foreach ($resp['content']['categories']['items'] as $cat) {
            foreach ($cat['items'] as $feed) {
                if (($feed['bare_id'] ?? -1) === $feed_id) {
                    $found = true;
                    $this->assertIsString($feed['name']);
                    $this->assertArrayHasKey("unread", $feed);
                    break 2;
                }
            }
        }
        $this->assertTrue($found, "Subscribed feed should appear in getFeedTree");

        // 3. Unsubscribe from the feed
        $resp = $this->api(["op" => "unsubscribeFeed", "feed_id" => $feed_id]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // 4. Verify the feed no longer appears in getFeedTree
        $resp = $this->api(["op" => "getFeedTree"]);
        $this->common_assertions($resp);

        $found = false;
        foreach ($resp['content']['categories']['items'] as $cat) {
            foreach ($cat['items'] as $feed) {
                if (($feed['bare_id'] ?? -1) === $feed_id) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($found, "Unsubscribed feed should not appear in getFeedTree");
    }

    public function test_apiResponseHasContentLength() : void {
        $ch = curl_init($this->api_url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "op"   => "getVersion",
            "sid"  => $this->sid,
        ]));

        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertEquals(200, $status);

        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $this->assertGreaterThan(0, $contentLength, "Content-Length header should be present and nonzero");

        // Verify the reported Content-Length matches the actual body length
        $this->assertEquals(strlen($resp), (int) $contentLength,
            "Content-Length should match actual response body size");

        curl_close($ch);
    }

    public function test_catchupFeed() : void {
        $feed_url = $this->app_url . "/tests_integration/feed.xml";

        // Subscribe to a feed to get a valid feed_id
        $resp = $this->api(["op" => "subscribeToFeed", "feed_url" => $feed_url]);
        $this->common_assertions($resp);

        $feed_id = $resp['content']['status']['feed_id'];
        $this->assertIsInt($feed_id);

        // Test catchupFeed with default mode ("all")
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id]);
        $this->common_assertions($resp);
        $this->assertArrayHasKey("status", $resp['content']);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with explicit mode "all"
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "mode" => "all"]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with mode "1day"
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "mode" => "1day"]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with mode "1week"
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "mode" => "1week"]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with mode "2week"
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "mode" => "2week"]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with is_cat=false (feed mode)
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "is_cat" => false]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);

        // Test catchupFeed with is_cat=true (category mode)
        $resp = $this->api(["op" => "catchupFeed", "feed_id" => $feed_id, "is_cat" => true]);
        $this->common_assertions($resp);
        $this->assertEquals("OK", $resp['content']['status']);
    }

}

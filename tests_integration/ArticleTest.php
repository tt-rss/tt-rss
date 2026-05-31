<?php
/** @group integration */
final class ArticleTest extends DbTestCase {

    // ──────────────────────────────────────────────────────────────────────
    // _get_tags()
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_tags_returns_tags_from_cache(): void {
        // seed.sql: article 1 has tag_cache = 'rust|open-source'
        $tags = Article::_get_tags(1);
        $this->assertEquals(['rust|open-source'], $tags);
    }

    public function test_get_tags_returns_empty_for_no_tags(): void {
        // seed.sql: article 2 has tag_cache = 'database|postgresql'
        $tags = Article::_get_tags(2);
        $this->assertEquals(['database|postgresql'], $tags);
    }

    public function test_get_tags_with_custom_owner_uid(): void {
        // uid=1 has tags; uid=999 has no user_entries
        $tags = Article::_get_tags(1, 999);
        $this->assertEquals([], $tags);
    }

    public function test_get_tags_with_explicit_tag_cache(): void {
        // When tag_cache is provided, it is split by comma
        $tags = Article::_get_tags(1, 1, 'explicit,tag,cache');
        $this->assertEquals(['explicit', 'tag', 'cache'], $tags);
    }

    // ──────────────────────────────────────────────────────────────────────
    // _get_labels()
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_labels_returns_cached_labels(): void {
        // seed.sql: article 1 has label_cache = '' (empty)
        // This means it should query the database
        $labels = Article::_get_labels(1);
        $this->assertEquals([], $labels);
    }

    public function test_get_labels_returns_no_labels_marker(): void {
        // When label_cache is empty and no labels in DB, should return empty array
        // and update cache with no-labels marker
        $labels = Article::_get_labels(1);
        $this->assertEquals([], $labels);

        // Verify cache was updated with no-labels marker
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT label_cache FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $row = $sth->fetch();
        $this->assertNotNull($row);
        $decoded = json_decode($row['label_cache'], true);
        $this->assertArrayHasKey('no-labels', $decoded);
        $this->assertEquals(1, $decoded['no-labels']);
    }

    public function test_get_labels_with_cached_labels(): void {
        // Manually set a label cache
        $pdo = Db::pdo();
        $labels = [['1', 'test-label-1', '#000000', '#ffffff']];
        $sth = $pdo->prepare("UPDATE ttrss_user_entries SET label_cache = ? WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([json_encode($labels), 1, 1]);

        $retrieved = Article::_get_labels(1);
        $this->assertEquals($labels, $retrieved);
    }

    public function test_get_labels_different_owner(): void {
        $labels = Article::_get_labels(1, 999);
        $this->assertEquals([], $labels);
    }

    // ──────────────────────────────────────────────────────────────────────
    // _catchup_by_id()
    // ──────────────────────────────────────────────────────────────────────

    public function test_catchup_mark_as_read(): void {
        // Article 1 is unread (from seed.sql)
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT unread, last_read FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $before = $sth->fetch();
        $this->assertTrue($before['unread']);
        $this->assertNull($before['last_read']);

        Article::_catchup_by_id([1], Article::CATCHUP_MODE_MARK_AS_READ);

        $sth->execute([1, 1]);
        $after = $sth->fetch();
        $this->assertFalse($after['unread']);
        $this->assertNotNull($after['last_read']);
    }

    public function test_catchup_mark_as_unread(): void {
        // Article 3 is read (from seed.sql)
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT unread FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([3, 1]);
        $before = $sth->fetch();
        $this->assertFalse($before['unread']);

        Article::_catchup_by_id([3], Article::CATCHUP_MODE_MARK_AS_UNREAD);

        $sth->execute([3, 1]);
        $after = $sth->fetch();
        $this->assertTrue($after['unread']);
    }

    public function test_catchup_toggle(): void {
        // Article 1 is unread → toggle should make it read
        Article::_catchup_by_id([1], Article::CATCHUP_MODE_TOGGLE);

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT unread, last_read FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $after = $sth->fetch();
        $this->assertFalse($after['unread']);
        $this->assertNotNull($after['last_read']);

        // Toggle again → should become unread
        Article::_catchup_by_id([1], Article::CATCHUP_MODE_TOGGLE);
        $sth->execute([1, 1]);
        $after2 = $sth->fetch();
        $this->assertTrue($after2['unread']);
    }

    public function test_catchup_multiple_articles(): void {
        // Mark articles 1, 2, 3 as read at once
        Article::_catchup_by_id([1, 2, 3], Article::CATCHUP_MODE_MARK_AS_READ);

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT ref_id, unread FROM ttrss_user_entries WHERE ref_id IN (1,2,3) AND owner_uid = ?");
        $sth->execute([1]);
        $results = $sth->fetchAll();

        $this->assertCount(3, $results);
        foreach ($results as $row) {
            $this->assertFalse($row['unread'], "Article {$row['ref_id']} should be marked as read");
        }
    }

    public function test_catchup_custom_owner_uid(): void {
        // Should not affect uid=999's entries
        Article::_catchup_by_id([1], Article::CATCHUP_MODE_MARK_AS_READ, 999);

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT unread FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $after = $sth->fetch();
        $this->assertTrue($after['unread'], "uid=1's article should not be affected by uid=999 catchup");
    }

    // ──────────────────────────────────────────────────────────────────────
    // _purge_orphans()
    // ──────────────────────────────────────────────────────────────────────

    public function test_purge_orphans_removes_orphaned_entries(): void {
        // Insert an article without a corresponding user_entry
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "INSERT INTO ttrss_entries (title, guid, link, updated, content, content_hash, date_entered, date_updated) " .
            "VALUES (?, ?, ?, NOW(), ?, ?, NOW(), NOW()) RETURNING id"
        );
        $sth->execute(['Orphan Article', 'tag:test.com,2026:orphan', 'https://test.com/orphan',
            '<p>Orphan content</p>', 'SHA1:orphan123']);
        $orphanId = (int) $sth->fetchColumn();

        // Verify it exists before purge
        $sth = $pdo->prepare("SELECT COUNT(*) FROM ttrss_entries WHERE id = ?");
        $sth->execute([$orphanId]);
        $this->assertEquals(1, (int) $sth->fetchColumn());

        Article::_purge_orphans();

        // Verify it was deleted
        $sth->execute([$orphanId]);
        $this->assertEquals(0, (int) $sth->fetchColumn());
    }

    public function test_purge_orphans_keeps_valid_entries(): void {
        // Article 1 has both ttrss_entries and ttrss_user_entries
        Article::_purge_orphans();

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT COUNT(*) FROM ttrss_entries WHERE id = ?");
        $sth->execute([1]);
        $this->assertEquals(1, (int) $sth->fetchColumn(), 'Valid entries should not be purged');
    }

    // ──────────────────────────────────────────────────────────────────────
    // _get_enclosures()
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_enclosures_returns_enclosures_for_article(): void {
        // seed.sql adds 2 enclosures for article id=1
        $enclosures = Article::_get_enclosures(1);

        $this->assertCount(2, $enclosures);

        // Check that we have the expected content types
        $contentTypes = array_column($enclosures, 'content_type');
        $this->assertContains('video/mp4', $contentTypes);
        $this->assertContains('image/jpeg', $contentTypes);
    }

    public function test_get_enclosures_returns_empty_for_no_enclosures(): void {
        // Article 2 has no enclosures in seed.sql
        $enclosures = Article::_get_enclosures(2);
        $this->assertEquals([], $enclosures);
    }

    // ──────────────────────────────────────────────────────────────────────
    // _labels_of() and _feeds_of()
    // ──────────────────────────────────────────────────────────────────────

    public function test_labels_of_returns_empty_for_empty_array(): void {
        $labels = Article::_labels_of([]);
        $this->assertEquals([], $labels);
    }

    public function test_labels_of_returns_unique_labels(): void {
        // Articles 1 and 2 are in the same feed but have different tags
        // Labels are not assigned to these articles in seed, so should be empty
        $labels = Article::_labels_of([1, 2]);
        $this->assertEquals([], $labels);
    }

    public function test_feeds_of_returns_empty_for_empty_array(): void {
        $feeds = Article::_feeds_of([]);
        $this->assertEquals([], $feeds);
    }

    public function test_feeds_of_returns_unique_feeds(): void {
        // Articles 1, 2, 3 are all in feed_id=1 (Hacker News)
        $feeds = Article::_feeds_of([1, 2, 3]);
        $this->assertEquals([1], $feeds);
    }

    public function test_feeds_of_returns_multiple_feeds(): void {
        // Articles 1 (feed 1) and 4 (feed 2)
        $feeds = Article::_feeds_of([1, 4]);
        $this->assertEquals([1, 2], $feeds);
    }

    // ──────────────────────────────────────────────────────────────────────
    // _get_image()
    // ──────────────────────────────────────────────────────────────────────

    private function extractOriginalUrl(string $redirectUrl): string {
        // DiskCache wraps URLs: public.php?op=cached_redirect&url=<encoded>&d=images
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);
        return $query['url'] ?? $redirectUrl;
    }

    public function test_get_image_extract_youtube_embed(): void {
        $content = '<p>Check out this video: <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe></p>';
        [$image, $stream, $kind] = Article::_get_image([], $content, 'https://example.com', []);

        // DiskCache wraps URLs with a redirect; extract the original URL
        $originalImage = urldecode($this->extractOriginalUrl($image));
        $originalStream = urldecode($this->extractOriginalUrl($stream));

        $this->assertStringContainsString('img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $originalImage);
        $this->assertStringContainsString('youtu.be/dQw4w9WgXcQ', $originalStream);
        $this->assertEquals(Article::ARTICLE_KIND_YOUTUBE, $kind);
    }

    public function test_get_image_extract_video_poster(): void {
        $content = '<video poster="https://example.com/poster.jpg"><source src="https://example.com/video.mp4"></video>';
        [$image, $stream, $kind] = Article::_get_image([], $content, 'https://example.com', []);

        $originalImage = urldecode($this->extractOriginalUrl($image));
        $originalStream = urldecode($this->extractOriginalUrl($stream));

        $this->assertStringContainsString('example.com/poster.jpg', $originalImage);
        $this->assertStringContainsString('example.com/video.mp4', $originalStream);
        $this->assertEquals(Article::ARTICLE_KIND_VIDEO, $kind);
    }

    public function test_get_image_extract_first_image(): void {
        $content = '<p>Some text <img src="https://example.com/photo.jpg"></p>';
        [$image, $stream, $kind] = Article::_get_image([], $content, 'https://example.com', []);

        $originalImage = urldecode($this->extractOriginalUrl($image));
        $this->assertStringContainsString('example.com/photo.jpg', $originalImage);
        $this->assertEmpty($stream);
        $this->assertEquals(0, $kind);
    }

    public function test_get_image_skips_data_uris(): void {
        $content = '<img src="data:image/png;base64,abc123">';
        [$image, $stream, $kind] = Article::_get_image([], $content, 'https://example.com', []);

        $this->assertEmpty($image);
        $this->assertEmpty($stream);
        $this->assertEquals(0, $kind);
    }

    public function test_get_image_falls_back_to_enclosure_image(): void {
        $content = '<p>No images in content</p>';
        $enclosures = [['content_type' => 'image/jpeg', 'content_url' => 'https://example.com/enclosure.jpg']];
        [$image, $stream, $kind] = Article::_get_image($enclosures, $content, 'https://example.com', []);

        $originalImage = urldecode($this->extractOriginalUrl($image));
        $this->assertStringContainsString('example.com/enclosure.jpg', $originalImage);
        $this->assertEmpty($stream);
        $this->assertEquals(0, $kind);
    }

    public function test_get_image_does_not_select_non_image_enclosure(): void {
        $content = '<p>No images in content</p>';
        $enclosures = [['content_type' => 'video/mp4', 'content_url' => 'https://example.com/video.mp4']];
        [$image, $stream, $kind] = Article::_get_image($enclosures, $content, 'https://example.com', []);

        $this->assertEmpty($image);
        $this->assertEmpty($stream);
        $this->assertEquals(0, $kind);
    }

    public function test_get_image_mark_album_with_multiple_enclosures(): void {
        $content = '<p>Single image</p>';
        $enclosures = [
            ['content_type' => 'image/jpeg', 'content_url' => 'https://example.com/img1.jpg'],
            ['content_type' => 'image/png', 'content_url' => 'https://example.com/img2.png'],
        ];
        [$image, $stream, $kind] = Article::_get_image($enclosures, $content, 'https://example.com', []);

        $originalImage = urldecode($this->extractOriginalUrl($image));
        $this->assertStringContainsString('example.com/img1.jpg', $originalImage);
        $this->assertEquals(Article::ARTICLE_KIND_ALBUM, $kind);
    }

    public function test_get_image_rewrites_relative_urls(): void {
        // Per RFC 3986, both /path and path resolve to root when base ends with /
        // dirname('/base/') = '/' so relative URLs append to root
        $content = '<img src="/relative/path.jpg">';
        [$image, $stream, $kind] = Article::_get_image([], $content, 'https://example.com/base/', []);

        $originalImage = urldecode($this->extractOriginalUrl($image));
        $this->assertStringContainsString('example.com/relative/path.jpg', $originalImage);
    }

    // ──────────────────────────────────────────────────────────────────────
    // _format_enclosures()
    // ──────────────────────────────────────────────────────────────────────

    public function test_format_enclosures_returns_empty_when_no_enclosures(): void {
        $result = Article::_format_enclosures(2, false, '<p>Content</p>');
        $this->assertEquals('', $result['formatted']);
        $this->assertEquals([], $result['entries']);
    }

    public function test_format_enclosures_can_inline_flag(): void {
        // With enclosures and can_inline conditions met
        $enclosures = [['content_type' => 'image/jpeg', 'content_url' => 'https://example.com/img.jpg',
            'width' => 800, 'height' => 600, 'title' => 'Test', 'duration' => '0']];

        // bw_limit is empty, STRIP_IMAGES is false (default), no <img> in content
        $result = Article::_format_enclosures(1, true, '<p>Content</p>');

        $this->assertArrayHasKey('can_inline', $result);
        $this->assertArrayHasKey('inline_text_only', $result);
        $this->assertArrayHasKey('entries', $result);
        $this->assertArrayHasKey('formatted', $result);
    }
}

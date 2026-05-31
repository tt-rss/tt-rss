<?php
/** @group integration */
final class ArticleHandlerTest extends HandlerTestCase {

    // ──────────────────────────────────────────────────────────────────────
    // setScore()
    // ──────────────────────────────────────────────────────────────────────

    public function test_set_score_single_article(): void {
        $resp = $this->invokeHandler('Article', 'setScore', [
            'ids' => [1],
            'score' => 5,
        ]);

        $this->assertResponseOk($resp['response']);

        // Verify score in DB
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT score FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $row = $sth->fetch();
        $this->assertEquals(5, (int) $row['score']);
    }

    public function test_set_score_multiple_articles(): void {
        $resp = $this->invokeHandler('Article', 'setScore', [
            'ids' => [1, 2, 3],
            'score' => 10,
        ]);

        $this->assertResponseOk($resp['response']);

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT ref_id, score FROM ttrss_user_entries WHERE ref_id IN (1,2,3) AND owner_uid = ?");
        $sth->execute([1]);
        $results = $sth->fetchAll();

        $this->assertCount(3, $results);
        foreach ($results as $row) {
            $this->assertEquals(10, (int) $row['score']);
        }
    }

    public function test_set_score_zero(): void {
        $resp = $this->invokeHandler('Article', 'setScore', [
            'ids' => [1],
            'score' => 0,
        ]);

        $this->assertResponseOk($resp['response']);

        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT score FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([1, 1]);
        $row = $sth->fetch();
        $this->assertEquals(0, (int) $row['score']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // setArticleTags()
    // ──────────────────────────────────────────────────────────────────────

    public function test_set_article_tags_new_tags(): void {
        $resp = $this->invokeHandler('Article', 'setArticleTags', [
            'id' => 1,
            'tags_str' => 'new-tag-1,new-tag-2',
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('tags', $resp['response']);
        $this->assertEquals(['new-tag-1', 'new-tag-2'], $resp['response']['tags']);
    }

    public function test_set_article_tags_replaces_existing(): void {
        // Article 1 starts with tag_cache 'rust|open-source'
        $resp = $this->invokeHandler('Article', 'setArticleTags', [
            'id' => 1,
            'tags_str' => 'replaced-tag',
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertEquals(['replaced-tag'], $resp['response']['tags']);

        // Verify old tags are gone
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT COUNT(*) FROM ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE ref_id = 1)");
        $sth->execute([]);
        $this->assertEquals(1, (int) $sth->fetchColumn(), 'Should have exactly 1 tag');
    }

    public function test_set_article_tags_clears_all(): void {
        $resp = $this->invokeHandler('Article', 'setArticleTags', [
            'id' => 1,
            'tags_str' => '',
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertEquals([], $resp['response']['tags']);

        // Verify tags table is empty for this article
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT COUNT(*) FROM ttrss_tags WHERE post_int_id = (SELECT int_id FROM ttrss_user_entries WHERE ref_id = 1)");
        $sth->execute([]);
        $this->assertEquals(0, (int) $sth->fetchColumn());
    }

    public function test_set_article_tags_trims_whitespace(): void {
        $resp = $this->invokeHandler('Article', 'setArticleTags', [
            'id' => 1,
            'tags_str' => '  tag-a  ,  tag-b  ',
        ]);

        $this->assertResponseOk($resp['response']);
        // FeedItem_Common::normalize_categories handles trimming
        $this->assertContains('tag-a', $resp['response']['tags']);
        $this->assertContains('tag-b', $resp['response']['tags']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // printArticleTags()
    // ──────────────────────────────────────────────────────────────────────

    public function test_print_article_tags_existing(): void {
        $resp = $this->invokeHandler('Article', 'printArticleTags', [
            'id' => 1,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('tags', $resp['response']);
        $this->assertArrayHasKey('id', $resp['response']);
        $this->assertEquals(1, $resp['response']['id']);
    }

    public function test_print_article_tags_empty(): void {
        // Article 11 has tag_cache 'quantum|computing' from seed
        $resp = $this->invokeHandler('Article', 'printArticleTags', [
            'id' => 11,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('tags', $resp['response']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // completeTags()
    // ──────────────────────────────────────────────────────────────────────

    public function test_complete_tags_returns_matching_tags(): void {
        // seed.sql has tags 'rust|open-source' for article 1 (stored as comma-separated in tag_cache)
        // The actual tags table may not have entries yet since we only set tag_cache
        // Let's insert a tag to test completion
        $pdo = Db::pdo();
        $intId = $this->getIntIdForRef(1);
        $sth = $pdo->prepare("INSERT INTO ttrss_tags (tag_name, owner_uid, post_int_id) VALUES (?, 1, ?)");
        $sth->execute(['testing-complete', $intId]);

        $resp = $this->invokeHandler('Article', 'completeTags', [
            'search' => 'test',
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertContains('testing-complete', $resp['response']);
    }

    public function test_complete_tags_no_match(): void {
        $resp = $this->invokeHandler('Article', 'completeTags', [
            'search' => 'zzzzzzz-nonexistent',
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertEquals([], $resp['response']);
    }

    public function test_complete_tags_case_sensitive(): void {
        // Insert a tag starting with 'T' to test case-sensitive completion
        $pdo = Db::pdo();
        $intId = $this->getIntIdForRef(1);
        $sth = $pdo->prepare("INSERT INTO ttrss_tags (tag_name, owner_uid, post_int_id) VALUES (?, 1, ?)");
        $sth->execute(['TagUppercase', $intId]);

        $resp = $this->invokeHandler('Article', 'completeTags', [
            'search' => 'T',
        ]);

        $this->assertResponseOk($resp['response']);
        // Should return tags starting with 'T' (case-sensitive LIKE)
        $this->assertContains('TagUppercase', $resp['response']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // assigntolabel()
    // ──────────────────────────────────────────────────────────────────────

    public function test_assign_to_label(): void {
        $resp = $this->invokeHandler('Article', 'assigntolabel', [
            'ids' => [1],
            'lid' => 1,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('labels-for', $resp['response']);
        $this->assertArrayHasKey('message', $resp['response']);
        $this->assertEquals('UPDATE_COUNTERS', $resp['response']['message']);
    }

    public function test_remove_from_label(): void {
        // First assign a label (article_id references ttrss_entries.id, not int_id)
        $pdo = Db::pdo();
        $sth = $pdo->prepare("INSERT INTO ttrss_user_labels2 (label_id, article_id) VALUES (1, 1)");
        $sth->execute([]);

        // Then remove it
        $resp = $this->invokeHandler('Article', 'removefromlabel', [
            'ids' => [1],
            'lid' => 1,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('labels-for', $resp['response']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // getmetadatabyid()
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_metadata_by_id(): void {
        $resp = $this->invokeHandler('Article', 'getmetadatabyid', [
            'id' => 1,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertArrayHasKey('link', $resp['response']);
        $this->assertArrayHasKey('title', $resp['response']);
        $this->assertEquals('https://news.ycombinator.com/item?id=40001', $resp['response']['link']);
        $this->assertEquals('Show HN: I built a distributed task queue in Rust', $resp['response']['title']);
    }

    public function test_get_metadata_by_id_nonexistent(): void {
        $resp = $this->invokeHandler('Article', 'getmetadatabyid', [
            'id' => 99999,
        ]);

        $this->assertResponseOk($resp['response']);
        $this->assertEquals([], $resp['response']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // redirect()
    // ──────────────────────────────────────────────────────────────────────

    public function test_redirect_valid_article(): void {
        $resp = $this->invokeHandler('Article', 'redirect', [
            'id' => 1,
        ]);

        // Handler redirects with Response::redirect()->send() which sends a 302
        // The response body is empty for redirects
        $this->assertEmpty($resp['body']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helper methods
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get the int_id (primary key of ttrss_user_entries) for a given ref_id.
     */
    private function getIntIdForRef(int $refId): int {
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT int_id FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $sth->execute([$refId, 1]);
        $row = $sth->fetch();
        return (int) $row['int_id'];
    }
}

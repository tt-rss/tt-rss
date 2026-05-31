<?php
/** @group integration */
final class LabelsIntegrationTest extends DbTestCase {

    /**
     * Helper: get the count of rows in ttrss_user_labels2.
     */
    private function countUserLabels(int $labelId, int $articleId): int {
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            'SELECT COUNT(*) FROM ttrss_user_labels2 WHERE label_id = ? AND article_id = ?');
        $sth->execute([$labelId, $articleId]);
        return (int) $sth->fetchColumn();
    }

    /**
     * Helper: get the label_cache for a user entry.
     */
    private function getLabelCache(int $refId, int $ownerUid = 1): string {
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            'SELECT label_cache FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?');
        $sth->execute([$refId, $ownerUid]);
        $row = $sth->fetch();
        return $row ? $row['label_cache'] : '';
    }

    // ── find_id() ─────────────────────────────────────────────────────────────

    public function test_find_id_existing_label(): void {
        // seed.sql inserts label id=1 with caption 'test-label-1'
        $id = Labels::find_id('test-label-1', 1);
        $this->assertEquals(1, $id, 'Should find the label by exact caption');
    }

    public function test_find_id_non_existing_label(): void {
        $id = Labels::find_id('nonexistent-label', 1);
        $this->assertEquals(0, $id, 'Should return 0 for a label that does not exist');
    }

    public function test_find_id_case_insensitive(): void {
        // seed has 'test-label-1'
        $idUpper = Labels::find_id('TEST-LABEL-1', 1);
        $idMixed = Labels::find_id('Test-Label-1', 1);
        $idLower = Labels::find_id('test-label-1', 1);

        $this->assertEquals(1, $idUpper, 'Uppercase should find the label');
        $this->assertEquals(1, $idMixed, 'Mixed case should find the label');
        $this->assertEquals(1, $idLower, 'Lowercase should find the label');
    }

    public function test_find_id_different_owner(): void {
        // seed data is for uid=1; uid=999 has no labels
        $id = Labels::find_id('test-label-1', 999);
        $this->assertEquals(0, $id, 'Should not find label for a different owner');
    }

    // ── find_caption() ────────────────────────────────────────────────────────

    public function test_find_caption_existing(): void {
        $caption = Labels::find_caption(1, 1);
        $this->assertEquals('test-label-1', $caption);
    }

    public function test_find_caption_non_existing(): void {
        $caption = Labels::find_caption(9999, 1);
        $this->assertEquals('', $caption);
    }

    public function test_find_caption_different_owner(): void {
        $caption = Labels::find_caption(1, 999);
        $this->assertEquals('', $caption);
    }

    // ── get_all() ─────────────────────────────────────────────────────────────

    public function test_get_all_returns_seed_labels(): void {
        $labels = Labels::get_all(1);
        $this->assertCount(2, $labels);
    }

    public function test_get_all_ordered_by_caption(): void {
        $labels = Labels::get_all(1);
        // Seed: 'test-label-1' (id=1), 'test-label-2' (id=2)
        // Alphabetically: 'test-label-1' < 'test-label-2'
        $this->assertEquals('test-label-1', $labels[0]['caption']);
        $this->assertEquals('test-label-2', $labels[1]['caption']);
    }

    public function test_get_all_empty_for_nonexistent_owner(): void {
        $labels = Labels::get_all(9999);
        $this->assertEmpty($labels);
    }

    public function test_get_all_includes_color_fields(): void {
        $labels = Labels::get_all(1);
        $this->assertArrayHasKey('id', $labels[0]);
        $this->assertArrayHasKey('caption', $labels[0]);
        $this->assertArrayHasKey('fg_color', $labels[0]);
        $this->assertArrayHasKey('bg_color', $labels[0]);
    }

    // ── get_as_hash() ─────────────────────────────────────────────────────────

    public function test_get_as_hash_returns_associative_array(): void {
        $hash = Labels::get_as_hash(1);
        $this->assertCount(2, $hash);
    }

    public function test_get_as_hash_keys_are_ids(): void {
        $hash = Labels::get_as_hash(1);
        $this->assertArrayHasKey(1, $hash);
        $this->assertArrayHasKey(2, $hash);
    }

    public function test_get_as_hash_values_match_get_all(): void {
        $hash = Labels::get_as_hash(1);
        $all = Labels::get_all(1);

        foreach ($all as $label) {
            $id = (int) $label['id'];
            $this->assertArrayHasKey($id, $hash);
            $this->assertEquals($label, $hash[$id]);
        }
    }

    // ── create() ──────────────────────────────────────────────────────────────

    public function test_create_new_label(): void {
        $result = Labels::create('integration-test-label', '#ff0000', '#000000');
        $this->assertEquals(1, $result, 'Should return 1 row inserted');

        // Verify it exists in the database
        $id = Labels::find_id('integration-test-label', 1);
        $this->assertGreaterThan(0, $id, 'Label should be findable after creation');

        // Clean up
        Labels::remove($id, 1);
    }

    public function test_create_label_with_default_colors(): void {
        $result = Labels::create('minimal-label');
        $this->assertEquals(1, $result);

        $id = Labels::find_id('minimal-label', 1);
        $caption = Labels::find_caption($id, 1);
        $this->assertEquals('minimal-label', $caption);

        // Clean up
        Labels::remove($id, 1);
    }

    public function test_create_duplicate_label_returns_false(): void {
        // seed has 'test-label-1'
        $result = Labels::create('test-label-1');
        $this->assertFalse($result, 'Creating a duplicate label should return false');
    }

    public function test_create_duplicate_label_case_insensitive(): void {
        // seed has 'test-label-1'
        $result = Labels::create('TEST-LABEL-1');
        $this->assertFalse($result, 'Duplicate check should be case-insensitive');
    }

    public function test_create_different_owner(): void {
        // The test database only has uid=1 (admin). Creating for other UIDs
        // would require additional seed data, so we verify that uid=1 works
        // and that the owner_uid parameter is correctly passed through.
        $result = Labels::create('owner-1-label', '#00ff00', '#ffffff', 1);
        $this->assertEquals(1, $result, 'Should create label for uid=1');

        $id = Labels::find_id('owner-1-label', 1);
        $this->assertGreaterThan(0, $id);

        // Verify uid=999 cannot see it
        $idForUid999 = Labels::find_id('owner-1-label', 999);
        $this->assertEquals(0, $idForUid999);

        // Clean up
        Labels::remove($id, 1);
    }

    // ── remove() ──────────────────────────────────────────────────────────────

    public function test_remove_existing_label(): void {
        // Create a label to remove
        $result = Labels::create('to-be-removed', '#ff0000', '#ffffff');
        $this->assertEquals(1, $result);
        $id = Labels::find_id('to-be-removed', 1);
        $this->assertGreaterThan(0, $id);

        // Remove it
        Labels::remove($id, 1);

        // Verify it's gone
        $caption = Labels::find_caption($id, 1);
        $this->assertEquals('', $caption, 'Label should be removed from DB');
    }

    public function test_remove_cleans_label_cache(): void {
        // Create a label
        $result = Labels::create('cache-test-label', '#ff0000', '#ffffff');
        $this->assertEquals(1, $result);
        $id = Labels::find_id('cache-test-label', 1);
        $this->assertGreaterThan(0, $id);

        // Set a label_cache on an article
        $pdo = Db::pdo();
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '{\"test\":true}' WHERE ref_id = 1 AND owner_uid = 1");

        // Verify cache was set
        $cache = $this->getLabelCache(1);
        $this->assertNotEmpty($cache);

        // Remove the label — should clear all caches for uid=1
        Labels::remove($id, 1);

        // Verify cache was cleared
        $cacheAfter = $this->getLabelCache(1);
        $this->assertEquals('', $cacheAfter, 'Removing a label should clear label_cache');

        // Clean up: recreate the label and clear cache again
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '' WHERE ref_id = 1 AND owner_uid = 1");
    }

    public function test_remove_nonexistent_label(): void {
        // Should not throw — removing a non-existent label is a no-op
        $this->expectNotToPerformAssertions();
        Labels::remove(99999, 1);
    }

    // ── add_article() ─────────────────────────────────────────────────────────

    public function test_add_article_to_existing_label(): void {
        // seed has label id=1, caption='test-label-1'
        // seed has article ref_id=1

        Labels::add_article(1, 'test-label-1', 1);

        $count = $this->countUserLabels(1, 1);
        $this->assertEquals(1, $count, 'Should have one entry in ttrss_user_labels2');

        // Verify label_cache was cleared
        $cache = $this->getLabelCache(1);
        $this->assertEquals('', $cache, 'add_article should clear label_cache');
    }

    public function test_add_article_to_nonexistent_label(): void {
        // Label 'nonexistent' does not exist, so find_id returns 0
        // and add_article returns early without modifying anything
        $initialCount = $this->countUserLabels(0, 1);

        Labels::add_article(1, 'nonexistent-label', 1);

        $count = $this->countUserLabels(0, 1);
        $this->assertEquals($initialCount, $count, 'Should not add anything for nonexistent label');
    }

    public function test_add_article_is_idempotent(): void {
        Labels::add_article(1, 'test-label-1', 1);
        $count1 = $this->countUserLabels(1, 1);

        Labels::add_article(1, 'test-label-1', 1);
        $count2 = $this->countUserLabels(1, 1);

        $this->assertEquals($count1, $count2, 'Adding same label twice should not create duplicates');
    }

    public function test_add_article_different_article(): void {
        Labels::add_article(1, 'test-label-1', 1);
        $count1 = $this->countUserLabels(1, 1);

        Labels::add_article(2, 'test-label-1', 1);
        $count2 = $this->countUserLabels(1, 2);

        $this->assertEquals(1, $count1, 'Article 1 should have the label');
        $this->assertEquals(1, $count2, 'Article 2 should have the label');
    }

    // ── remove_article() ──────────────────────────────────────────────────────

    public function test_remove_article_from_existing_label(): void {
        // First add the label relation
        Labels::add_article(1, 'test-label-1', 1);
        $countBefore = $this->countUserLabels(1, 1);
        $this->assertEquals(1, $countBefore);

        // Now remove it
        Labels::remove_article(1, 'test-label-1', 1);

        $countAfter = $this->countUserLabels(1, 1);
        $this->assertEquals(0, $countAfter, 'Article should be removed from label');
    }

    public function test_remove_article_nonexistent_relation(): void {
        // Removing a label relation that doesn't exist should be a no-op
        $initialCount = $this->countUserLabels(1, 9999);
        Labels::remove_article(9999, 'test-label-1', 1);
        $finalCount = $this->countUserLabels(1, 9999);
        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_remove_article_clears_cache(): void {
        // Add a label to article 1
        Labels::add_article(1, 'test-label-1', 1);

        // Manually set a label_cache
        $pdo = Db::pdo();
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '{\"test\":true}' WHERE ref_id = 1 AND owner_uid = 1");

        // Remove the label
        Labels::remove_article(1, 'test-label-1', 1);

        // Cache should be cleared
        $cache = $this->getLabelCache(1);
        $this->assertEquals('', $cache, 'remove_article should clear label_cache');
    }

    // ── clear_cache() ─────────────────────────────────────────────────────────

    public function test_clear_cache_clears_label_cache(): void {
        // Set a label_cache
        $pdo = Db::pdo();
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '{\"test\":true}' WHERE ref_id = 1 AND owner_uid = 1");

        $cacheBefore = $this->getLabelCache(1);
        $this->assertNotEmpty($cacheBefore);

        // Clear it
        Labels::clear_cache(1);

        $cacheAfter = $this->getLabelCache(1);
        $this->assertEquals('', $cacheAfter);
    }

    public function test_clear_cache_nonexistent_article(): void {
        // Should not throw even if article doesn't exist
        $this->expectNotToPerformAssertions();
        Labels::clear_cache(99999);
    }

    // ── update_cache() ────────────────────────────────────────────────────────

    public function test_update_cache_force_mode(): void {
        // Force mode first clears the cache, then repopulates from the
        // article's actual labels (via Article::_get_labels). Since article
        // 1 has no labels in ttrss_user_labels2, the result is an empty array.
        $pdo = Db::pdo();
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '{\"existing\":true}' WHERE ref_id = 1 AND owner_uid = 1");

        $cacheBefore = $this->getLabelCache(1);
        $this->assertNotEmpty($cacheBefore);

        // Force clear — should clear and then repopulate from DB (empty)
        Labels::update_cache(1, 1, [], true);

        $cacheAfter = $this->getLabelCache(1);
        // The cache is repopulated from the article's actual labels.
        // Since article 1 has no labels, it becomes an empty array '[]'.
        $this->assertEquals('[]', $cacheAfter, 'Force mode should clear stale cache and repopulate from DB');
    }

    public function test_update_cache_with_labels(): void {
        // Labels format: [[label_id, caption, fg_color, bg_color], ...]
        // phpcs:ignore PhanTypeMismatchArgument -- Labels::update_cache() accepts this format
        $labels = [[1, 'test-label-1', '#ff0000', '#ffffff']];
        // @phpstan-ignore-next-line
        Labels::update_cache(1, 1, $labels, false);

        $cache = $this->getLabelCache(1);
        $this->assertNotEmpty($cache, 'Cache should be set');

        $decoded = json_decode($cache, true);
        $this->assertIsArray($decoded);
        // Format is [[label_id, caption, fg_color, bg_color], ...]
        $this->assertCount(1, $decoded);
        $this->assertEquals(1, $decoded[0][0], 'First element should be label_id');
        $this->assertEquals('test-label-1', $decoded[0][1], 'Second element should be caption');
    }

    public function test_update_cache_empty_labels(): void {
        // Set a label_cache first
        $pdo = Db::pdo();
        $pdo->exec("UPDATE ttrss_user_entries SET label_cache = '{\"existing\":true}' WHERE ref_id = 1 AND owner_uid = 1");

        // Update with empty labels (no force) — should not change anything
        Labels::update_cache(1, 1, [], false);

        $cache = $this->getLabelCache(1);
        $this->assertNotEmpty($cache, 'Empty labels without force should not modify cache');
    }

    // ── Cross-cutting: label and article relationship ─────────────────────────

    public function test_full_label_lifecycle(): void {
        // 1. Create a new label
        $result = Labels::create('lifecycle-test', '#0000ff', '#ffffff');
        $this->assertEquals(1, $result);

        // 2. Find it
        $id = Labels::find_id('lifecycle-test', 1);
        $this->assertGreaterThan(0, $id);

        // 3. Get its caption
        $caption = Labels::find_caption($id, 1);
        $this->assertEquals('lifecycle-test', $caption);

        // 4. It appears in get_all
        $all = Labels::get_all(1);
        $found = false;
        foreach ($all as $label) {
            if ((int) $label['id'] === $id && $label['caption'] === 'lifecycle-test') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'New label should appear in get_all()');

        // 5. Add it to an article
        Labels::add_article(1, 'lifecycle-test', 1);
        $this->assertEquals(1, $this->countUserLabels($id, 1));

        // 6. Remove it from the article
        Labels::remove_article(1, 'lifecycle-test', 1);
        $this->assertEquals(0, $this->countUserLabels($id, 1));

        // 7. Remove the label entirely
        Labels::remove($id, 1);
        $this->assertEquals('', Labels::find_caption($id, 1));
    }
}

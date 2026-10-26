<?php
/** @group integration */
final class FeedsNestedCategoriesIntegrationTest extends DbTestCase {

    /**
     * Builds a nested tree on top of seed.sql:
     *
     *   1 Technology          feeds 1, 2  (unread 1,2,4,5,6; starred 3)
     *   └─ 10 Hardware        feed 3      (unread 7; starred 8; published 7)
     *      └─ 11 Research     feed 4      (unread 9,10; starred 11)
     *         └─ 12 Empty     no feeds
     *   2 Science & Nature    no feeds
     */
    protected function setUp(): void {
        parent::setUp();

        $pdo = Db::pdo();

        $pdo->exec("INSERT INTO ttrss_feed_categories (id, owner_uid, title, parent_cat) VALUES
            (10, 1, 'Hardware', 1),
            (11, 1, 'Research', 10),
            (12, 1, 'Empty', 11)");

        $pdo->exec("UPDATE ttrss_feeds SET cat_id = 10 WHERE id = 3");
        $pdo->exec("UPDATE ttrss_feeds SET cat_id = 11 WHERE id = 4");
        $pdo->exec("UPDATE ttrss_user_entries SET published = true WHERE ref_id = 7");
    }

    /**
     * @param array<int, array<string, int|string>> $counters
     * @return array<int, array<string, int|string>> category id => counter entry
     */
    private function cat_counters(array $counters): array {
        $rv = [];

        foreach ($counters as $entry) {
            if (($entry['kind'] ?? null) === 'cat')
                $rv[(int) $entry['id']] = $entry;
        }

        return $rv;
    }

    public function test_get_child_cats_returns_all_descendants(): void {
        $child_cats = Feeds::_get_child_cats(1, 1);
        sort($child_cats);

        $this->assertSame([10, 11, 12], $child_cats);
        $this->assertSame([12], Feeds::_get_child_cats(11, 1));
        $this->assertSame([], Feeds::_get_child_cats(12, 1));
    }

    public function test_get_child_cats_other_owner(): void {
        $this->assertSame([], Feeds::_get_child_cats(1, 2));
    }

    public function test_get_cat_children_unread(): void {
        // descendants of 1: feed 3 (7) + feed 4 (9, 10); the category's own feeds are excluded
        $this->assertEquals(3, Feeds::_get_cat_children_unread(1, 1));
        $this->assertEquals(2, Feeds::_get_cat_children_unread(10, 1));
        $this->assertEquals(0, Feeds::_get_cat_children_unread(11, 1));
        $this->assertEquals(0, Feeds::_get_cat_children_unread(2, 1));
    }

    public function test_counters_get_all_sums_descendants(): void {
        $cats = $this->cat_counters(Counters::get_all());

        $expected = [
            //     unread, marked, published
            0  => [0, 0, 0],
            1  => [8, 3, 1],
            2  => [0, 0, 0],
            10 => [3, 2, 1],
            11 => [2, 1, 0],
            12 => [0, 0, 0],
        ];

        foreach ($expected as $id => [$unread, $marked, $published]) {
            $this->assertArrayHasKey($id, $cats, "category $id");
            $this->assertSame($unread, $cats[$id]['counter'], "unread of category $id");
            $this->assertSame($marked, $cats[$id]['markedcounter'], "marked of category $id");
            $this->assertSame($published, $cats[$id]['publishedcounter'], "published of category $id");
        }

        $this->assertArrayHasKey(Feeds::CATEGORY_LABELS, $cats);
    }

    public function test_counters_get_conditional_includes_ancestors_with_totals(): void {
        // feed 4 lives in 11, so its category and every ancestor get updated
        $cats = $this->cat_counters(Counters::get_conditional([4]));

        $this->assertSame(8, $cats[1]['counter']);
        $this->assertSame(3, $cats[10]['counter']);
        $this->assertSame(2, $cats[11]['counter']);

        $this->assertArrayNotHasKey(2, $cats);
        $this->assertArrayNotHasKey(12, $cats);
    }

    public function test_counters_uncategorized(): void {
        Db::pdo()->exec("UPDATE ttrss_feeds SET cat_id = NULL WHERE id = 1");

        $cats = $this->cat_counters(Counters::get_all());

        $this->assertSame(2, $cats[0]['counter']);
        $this->assertSame(1, $cats[0]['markedcounter']);
        $this->assertSame(6, $cats[1]['counter']);
    }

    public function test_category_cycle_terminates(): void {
        // 1 -> 10 -> 11 -> 1
        Db::pdo()->exec("UPDATE ttrss_feed_categories SET parent_cat = 11 WHERE id = 1");

        $child_cats = Feeds::_get_child_cats(10, 1);
        sort($child_cats);
        $this->assertSame([1, 10, 11, 12], $child_cats);

        // totals inside a cycle aren't well defined, it just has to finish
        $cats = $this->cat_counters(Counters::get_all());

        foreach ([1, 10, 11, 12] as $id)
            $this->assertArrayHasKey($id, $cats, "category $id");

        $this->assertGreaterThan(0, Feeds::_get_cat_children_unread(10, 1));
    }
}

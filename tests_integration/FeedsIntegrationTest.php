<?php
/** @group integration */
final class FeedsIntegrationTest extends DbTestCase {

    // ── Special feeds (hardcoded titles, no DB needed for the lookup) ──

    public function test_get_title_special_feeds(): void {
        $this->assertEquals(
            __("Starred articles"),
            Feeds::_get_title(Feeds::FEED_STARRED, $_SESSION['uid'])
        );
        $this->assertEquals(
            __("Published articles"),
            Feeds::_get_title(Feeds::FEED_PUBLISHED, $_SESSION['uid'])
        );
        $this->assertEquals(
            __("Fresh articles"),
            Feeds::_get_title(Feeds::FEED_FRESH, $_SESSION['uid'])
        );
        $this->assertEquals(
            __("All articles"),
            Feeds::_get_title(Feeds::FEED_ALL, $_SESSION['uid'])
        );
        $this->assertEquals(
            __("Archived articles"),
            Feeds::_get_title(Feeds::FEED_ARCHIVED, $_SESSION['uid'])
        );
        $this->assertEquals(
            __("Recently read"),
            Feeds::_get_title(Feeds::FEED_RECENTLY_READ, $_SESSION['uid'])
        );
    }

    public function test_get_title_error_feed(): void {
        // FEED_ERROR (-7) is < 0 and >= LABEL_BASE_INDEX, falls to else branch
        $this->assertEquals("-7", Feeds::_get_title(Feeds::FEED_ERROR, $_SESSION['uid']));
    }

    public function test_get_title_non_numeric_id(): void {
        $this->assertEquals("random-id", Feeds::_get_title("random-id", $_SESSION['uid']));
    }

    // ── Numeric feed IDs (requires DB lookup in ttrss_feeds) ──

    public function test_get_title_numeric_feed_id_existing(): void {
        // seed.sql inserts feeds with ids 1-4
        $this->assertEquals("Hacker News", Feeds::_get_title(1, $_SESSION['uid']));
        $this->assertEquals("The Register", Feeds::_get_title(2, $_SESSION['uid']));
        $this->assertEquals("Ars Technica", Feeds::_get_title(3, $_SESSION['uid']));
        $this->assertEquals("MIT Tech Review", Feeds::_get_title(4, $_SESSION['uid']));
    }

    public function test_get_title_numeric_feed_id_missing(): void {
        // Feed id 999 does not exist in seed.sql
        $this->assertEquals("Unknown feed (999)", Feeds::_get_title(999, $_SESSION['uid']));
    }



    // ── Label IDs (requires DB lookup in ttrss_labels2) ──

    public function test_get_title_label_id_existing(): void {
        // Label reference IDs must be < LABEL_BASE_INDEX (-1024).
        // Labels::label_to_feed_id() converts a positive DB label id to
        // the negative reference used by _get_title().
        $ref_id_1 = Labels::label_to_feed_id(1);
        $this->assertEquals("test-label-1", Feeds::_get_title($ref_id_1, $_SESSION['uid']));

        $ref_id_2 = Labels::label_to_feed_id(2);
        $this->assertEquals("test-label-2", Feeds::_get_title($ref_id_2, $_SESSION['uid']));
    }

    public function test_get_title_label_id_missing(): void {
        // Label id 9999 does not exist; its reference is < LABEL_BASE_INDEX
        $ref_id = Labels::label_to_feed_id(9999);
        $this->assertEquals("Unknown label (9999)", Feeds::_get_title($ref_id, $_SESSION['uid']));
    }

    // ── Category IDs (cat=true, requires DB lookup in ttrss_feed_categories) ──

    public function test_get_title_category_special(): void {
        $this->assertEquals(
            __("Special"),
            Feeds::_get_title(Feeds::CATEGORY_SPECIAL, 1, true)
        );
    }

    public function test_get_title_category_uncategorized(): void {
        $this->assertEquals(
            __("Uncategorized"),
            Feeds::_get_title(Feeds::CATEGORY_UNCATEGORIZED, 1, true)
        );
    }

    public function test_get_title_category_labels(): void {
        $this->assertEquals(
            __("Labels"),
            Feeds::_get_title(Feeds::CATEGORY_LABELS, 1, true)
        );
    }

    public function test_get_title_category_existing(): void {
        // seed.sql inserts categories with ids 1 and 2
        $this->assertEquals("Technology", Feeds::_get_title(1, $_SESSION['uid'], 1, true));
        $this->assertEquals("Science & Nature", Feeds::_get_title(2, $_SESSION['uid'], 1, true));
    }

    public function test_get_title_category_missing(): void {
        // Category id 999 does not exist in seed.sql
        $this->assertEquals("UNKNOWN", Feeds::_get_title(999, $_SESSION['uid'], 1, true));
    }

    // ── Owner UID parameter ──

    public function test_get_title_different_owner_uid(): void {
        // seed data is for uid=1; uid=2 has no feeds
        $this->assertEquals("UNKNOWN", Feeds::_get_title(1, 2, 1));
    }
}

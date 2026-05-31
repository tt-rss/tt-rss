<?php
use PHPUnit\Framework\TestCase;

final class FeedsTest extends TestCase {

    protected function setUp(): void {
        $_SESSION['uid'] = 1;
    }

    protected function tearDown(): void {
        unset($_SESSION['uid']);
    }

    /**
     * Test _get_title for all special feed IDs that return hardcoded strings.
     * These do not require a database connection.
     */
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

    /**
     * Test _get_title for the error feed ID.
     * FEED_ERROR (-7) is < 0 and >= LABEL_BASE_INDEX, so it falls through
     * to the final else branch which returns the id as a string.
     */
    public function test_get_title_error_feed(): void {
        $this->assertEquals(
            "-7",
            Feeds::_get_title(Feeds::FEED_ERROR, $_SESSION['uid'])
        );
    }

    /**
     * Test _get_title with a non-numeric string id falls through to the
     * final else branch.
     */
    public function test_get_title_non_numeric_id(): void {
        $this->assertEquals(
            "some-string-id",
            Feeds::_get_title("some-string-id", $_SESSION['uid'])
        );
    }
}

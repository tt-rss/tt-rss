<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Labels class pure functions.
 *
 * Tests label_to_feed_id() and feed_to_label_id() which are
 * pure functions that convert between label IDs and feed IDs
 * using the LABEL_BASE_INDEX constant (-1024) to create
 * non-overlapping ID ranges.
 */
final class LabelsTest extends TestCase {

    /**
     * label_to_feed_id() converts a label ID to a feed ID.
     * Formula: LABEL_BASE_INDEX - 1 - abs($label)
     * LABEL_BASE_INDEX = -1024
     *
     * label=1 → -1024 - 1 - 1 = -1026
     */
    public function test_label_to_feed_id_basic(): void {
        $this->assertEquals(-1026, Labels::label_to_feed_id(1));
    }

    /**
     * label_to_feed_id() with a larger label ID.
     *
     * label=5 → -1024 - 1 - 5 = -1030
     */
    public function test_label_to_feed_id_larger_label(): void {
        $this->assertEquals(-1030, Labels::label_to_feed_id(5));
    }

    /**
     * label_to_feed_id() with label=0.
     *
     * label=0 → -1024 - 1 - 0 = -1025
     */
    public function test_label_to_feed_id_zero(): void {
        $this->assertEquals(-1025, Labels::label_to_feed_id(0));
    }

    /**
     * label_to_feed_id() uses abs(), so negative labels produce the
     * same result as their positive counterparts.
     *
     * label=-3 → -1024 - 1 - 3 = -1028
     * label=3  → -1024 - 1 - 3 = -1028
     */
    public function test_label_to_feed_id_negative_input(): void {
        $this->assertEquals(-1028, Labels::label_to_feed_id(-3));
        $this->assertEquals(Labels::label_to_feed_id(3), Labels::label_to_feed_id(-3));
    }

    /**
     * feed_to_label_id() converts a feed ID to a label ID.
     * Formula: LABEL_BASE_INDEX - 1 + abs($feed)
     * LABEL_BASE_INDEX = -1024
     *
     * feed=1 → -1024 - 1 + 1 = -1024
     */
    public function test_feed_to_label_id_basic(): void {
        $this->assertEquals(-1024, Labels::feed_to_label_id(1));
    }

    /**
     * feed_to_label_id() with a larger feed ID.
     *
     * feed=5 → -1024 - 1 + 5 = -1020
     */
    public function test_feed_to_label_id_larger_feed(): void {
        $this->assertEquals(-1020, Labels::feed_to_label_id(5));
    }

    /**
     * feed_to_label_id() with feed=0.
     *
     * feed=0 → -1024 - 1 + 0 = -1025
     */
    public function test_feed_to_label_id_zero(): void {
        $this->assertEquals(-1025, Labels::feed_to_label_id(0));
    }

    /**
     * feed_to_label_id() uses abs(), so negative feeds produce the
     * same result as their positive counterparts.
     *
     * feed=-2 → -1024 - 1 + 2 = -1023
     * feed=2  → -1024 - 1 + 2 = -1023
     */
    public function test_feed_to_label_id_negative_input(): void {
        $this->assertEquals(-1023, Labels::feed_to_label_id(-2));
        $this->assertEquals(Labels::feed_to_label_id(2), Labels::feed_to_label_id(-2));
    }

    /**
     * Verify that label_to_feed_id and feed_to_label_id are inverses
     * for positive label IDs (round-trip).
     *
     * feed_to_label_id(label_to_feed_id(1)) should equal 1.
     * label_to_feed_id(1) = -1026
     * feed_to_label_id(-1026) = -1024 - 1 + 1026 = 1
     */
    public function test_round_trip_label_to_feed(): void {
        $label = 1;
        $feed_id = Labels::label_to_feed_id($label);
        $round_trip = Labels::feed_to_label_id($feed_id);
        $this->assertEquals($label, $round_trip);
    }

    /**
     * Verify round-trip for multiple label IDs.
     */
    public function test_round_trip_label_to_feed_multiple(): void {
        foreach ([1, 5, 10, 100, 500] as $label) {
            $feed_id = Labels::label_to_feed_id($label);
            $round_trip = Labels::feed_to_label_id($feed_id);
            $this->assertEquals($label, $round_trip, "Round-trip failed for label=$label");
        }
    }

    /**
     * Verify that feed_to_label_id and label_to_feed_id are inverses
     * for positive feed IDs (reverse round-trip).
     *
     * label_to_feed_id(feed_to_label_id(1)) should equal 1.
     * feed_to_label_id(1) = -1024
     * label_to_feed_id(-1024) = -1024 - 1 - 1024 = -2049...
     *
     * Wait — this is NOT an inverse! feed_to_label_id(1) = -1024,
     * and label_to_feed_id(-1024) = -1024 - 1 - 1024 = -2049, not 1.
     *
     * The functions are only inverses in the label→feed→label direction,
     * not feed→label→feed. This is by design: feed IDs are always positive,
     * so the feed→label conversion produces negative values that only
     * make sense as feed IDs, not as label IDs.
     */
    public function test_feed_to_label_is_not_invertible(): void {
        $feed = 1;
        $label_id = Labels::feed_to_label_id($feed);
        $round_trip = Labels::label_to_feed_id($label_id);
        // The reverse direction is NOT an inverse — this is expected behavior
        $this->assertNotEquals($feed, $round_trip);
    }

    /**
     * Verify that label IDs and feed IDs produce non-overlapping ranges
     * for positive inputs (the normal case).
     *
     * For label >= 1: label_to_feed_id(label) <= LABEL_BASE_INDEX - 2 = -1026
     * For feed >= 1:  feed_to_label_id(feed)  >= LABEL_BASE_INDEX - 1 = -1024
     *
     * Note: label=0 and feed=0 both produce -1025 (LABEL_BASE_INDEX - 1),
     * so they share a single boundary value. Non-overlapping applies to
     * the normal positive-input range.
     */
    public function test_label_and_feed_ranges_non_overlapping(): void {
        $label_threshold = LABEL_BASE_INDEX - 2; // -1026
        $feed_threshold  = LABEL_BASE_INDEX - 1; // -1025

        // All positive label conversions produce values at or below the label threshold
        foreach ([1, 5, 100, 500] as $label) {
            $this->assertLessThanOrEqual($label_threshold, Labels::label_to_feed_id($label),
                "label_to_feed_id($label) should be <= $label_threshold");
        }

        // All positive feed conversions produce values at or above the feed threshold
        foreach ([1, 5, 100, 500] as $feed) {
            $this->assertGreaterThanOrEqual($feed_threshold, Labels::feed_to_label_id($feed),
                "feed_to_label_id($feed) should be >= $feed_threshold");
        }

        // Verify the gap: label range max < feed range min
        $this->assertLessThan(
            Labels::feed_to_label_id(1),
            Labels::label_to_feed_id(1)
        );
    }

    /**
     * Verify that increasing label IDs produce decreasing feed IDs
     * (monotonic decreasing).
     */
    public function test_label_to_feed_id_monotonic(): void {
        $this->assertGreaterThan(
            Labels::label_to_feed_id(2),
            Labels::label_to_feed_id(1)
        );
        $this->assertGreaterThan(
            Labels::label_to_feed_id(10),
            Labels::label_to_feed_id(5)
        );
    }

    /**
     * Verify that increasing feed IDs produce increasing label IDs
     * (monotonic increasing).
     */
    public function test_feed_to_label_id_monotonic(): void {
        $this->assertLessThan(
            Labels::feed_to_label_id(2),
            Labels::feed_to_label_id(1)
        );
        $this->assertLessThan(
            Labels::feed_to_label_id(10),
            Labels::feed_to_label_id(5)
        );
    }

    /**
     * Verify that label=0 and feed=0 produce the same result,
     * since both formulas reduce to LABEL_BASE_INDEX - 1 when the
     * input is 0.
     */
    public function test_zero_label_and_feed_produce_same_value(): void {
        $this->assertEquals(
            Labels::label_to_feed_id(0),
            Labels::feed_to_label_id(0)
        );
    }
}

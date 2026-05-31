<?php
use PHPUnit\Framework\TestCase;

final class RSSUtilsTest extends TestCase {

    // ------------------------------------------------------------------------
    // make_guid_from_title() — GUID slug generation from article title
    // ------------------------------------------------------------------------

    public function test_make_guid_from_title_simple(): void {
        $this->assertEquals("test-article", RSSUtils::make_guid_from_title("Test Article"));
    }

    public function test_make_guid_from_title_lowercase(): void {
        $this->assertEquals("already-lowercase", RSSUtils::make_guid_from_title("Already Lowercase"));
    }

    public function test_make_guid_from_title_strips_html_tags(): void {
        // strip_tags removes both tags and their content, then chars are replaced
        $this->assertEquals("bold-article", RSSUtils::make_guid_from_title("<b>bold</b> article"));
    }

    public function test_make_guid_from_title_replaces_special_chars(): void {
        // 5 special chars each replaced by '-'
        $this->assertEquals("-----", RSSUtils::make_guid_from_title(" ,':;"));
    }

    public function test_make_guid_from_title_replaces_spaces(): void {
        $this->assertEquals("hello-world", RSSUtils::make_guid_from_title("Hello World"));
    }

    public function test_make_guid_from_title_handles_unicode(): void {
        $result = RSSUtils::make_guid_from_title("Café Résumé");
        $this->assertStringContainsString("café", $result);
        $this->assertStringContainsString("résumé", $result);
    }

    public function test_make_guid_from_title_empty_string(): void {
        $this->assertEquals("", RSSUtils::make_guid_from_title(""));
    }

    public function test_make_guid_from_title_only_spaces(): void {
        $this->assertEquals("---", RSSUtils::make_guid_from_title("   "));
    }

    public function test_make_guid_from_title_nested_tags(): void {
        $this->assertEquals("hello-world", RSSUtils::make_guid_from_title("<div><p><b>hello</b> <i>world</i></p></div>"));
    }

    public function test_make_guid_from_title_preserves_alphanumerics_and_hyphens(): void {
        $this->assertEquals("abc123-x-y", RSSUtils::make_guid_from_title("abc123 x y"));
    }

    // ------------------------------------------------------------------------
    // decode_srcset() — HTML srcset attribute parsing
    // ------------------------------------------------------------------------

    public function test_decode_srcset_single_entry(): void {
        $result = RSSUtils::decode_srcset("image.jpg 1x");
        $this->assertCount(1, $result);
        $this->assertEquals("image.jpg", $result[0]["url"]);
        $this->assertEquals("1x", $result[0]["size"]);
    }

    public function test_decode_srcset_multiple_entries(): void {
        $result = RSSUtils::decode_srcset("small.jpg 1x, medium.jpg 2x, large.jpg 3x");
        $this->assertCount(3, $result);
        $this->assertEquals("small.jpg", $result[0]["url"]);
        $this->assertEquals("1x", $result[0]["size"]);
        $this->assertEquals("medium.jpg", $result[1]["url"]);
        $this->assertEquals("2x", $result[1]["size"]);
        $this->assertEquals("large.jpg", $result[2]["url"]);
        $this->assertEquals("3x", $result[2]["size"]);
    }

    public function test_decode_srcset_width_descriptors(): void {
        $result = RSSUtils::decode_srcset("small.jpg 480w, medium.jpg 800w, large.jpg 1200w");
        $this->assertCount(3, $result);
        $this->assertEquals("480w", $result[0]["size"]);
        $this->assertEquals("800w", $result[1]["size"]);
        $this->assertEquals("1200w", $result[2]["size"]);
    }

    public function test_decode_srcset_leading_comma(): void {
        $result = RSSUtils::decode_srcset(", image.jpg 1x");
        $this->assertCount(1, $result);
        $this->assertEquals("image.jpg", $result[0]["url"]);
    }

    public function test_decode_srcset_trailing_comma(): void {
        $result = RSSUtils::decode_srcset("image.jpg 1x,");
        $this->assertCount(1, $result);
        $this->assertEquals("image.jpg", $result[0]["url"]);
    }

    public function test_decode_srcset_empty_string(): void {
        $result = RSSUtils::decode_srcset("");
        $this->assertCount(0, $result);
    }

    public function test_decode_srcset_no_size_descriptor(): void {
        $result = RSSUtils::decode_srcset("image.jpg");
        $this->assertCount(1, $result);
        $this->assertEquals("image.jpg", $result[0]["url"]);
        $this->assertEquals("", $result[0]["size"]);
    }

    public function test_decode_srcset_decimal_descriptor(): void {
        $result = RSSUtils::decode_srcset("image.jpg 1.5x");
        $this->assertCount(1, $result);
        $this->assertEquals("1.5x", $result[0]["size"]);
    }

    public function test_decode_srcset_whitespace_handling(): void {
        $result = RSSUtils::decode_srcset("  small.jpg   1x  ,   large.jpg   2x  ");
        $this->assertCount(2, $result);
        $this->assertEquals("small.jpg", $result[0]["url"]);
        $this->assertEquals("1x", $result[0]["size"]);
        $this->assertEquals("large.jpg", $result[1]["url"]);
        $this->assertEquals("2x", $result[1]["size"]);
    }

    // ------------------------------------------------------------------------
    // encode_srcset() — srcset reconstruction from parsed data
    // ------------------------------------------------------------------------

    public function test_encode_srcset_single_entry(): void {
        $input = [["url" => "image.jpg", "size" => "1x"]];
        $this->assertEquals("image.jpg 1x", RSSUtils::encode_srcset($input));
    }

    public function test_encode_srcset_multiple_entries(): void {
        $input = [
            ["url" => "small.jpg", "size" => "1x"],
            ["url" => "large.jpg", "size" => "2x"],
        ];
        $this->assertEquals("small.jpg 1x,large.jpg 2x", RSSUtils::encode_srcset($input));
    }

    public function test_encode_srcset_empty_array(): void {
        $this->assertEquals("", RSSUtils::encode_srcset([]));
    }

    public function test_encode_srcset_no_size(): void {
        $input = [["url" => "image.jpg", "size" => ""]];
        $this->assertEquals("image.jpg ", RSSUtils::encode_srcset($input));
    }

    public function test_encode_srcset_roundtrip(): void {
        $original = "small.jpg 1x, medium.jpg 2x, large.jpg 3x";
        $decoded = RSSUtils::decode_srcset($original);
        $encoded = RSSUtils::encode_srcset($decoded);
        // Round-trip should produce equivalent output (whitespace may differ)
        $decoded_again = RSSUtils::decode_srcset($encoded);
        $this->assertCount(3, $decoded_again);
        $this->assertEquals($decoded[0]["url"], $decoded_again[0]["url"]);
        $this->assertEquals($decoded[1]["url"], $decoded_again[1]["url"]);
        $this->assertEquals($decoded[2]["url"], $decoded_again[2]["url"]);
    }

    // ------------------------------------------------------------------------
    // is_gzipped() — gzip magic byte detection
    // ------------------------------------------------------------------------

    public function test_is_gzipped_valid_gzip(): void {
        // Gzip magic bytes: 0x1f 0x8b 0x08
        $gzip_data = "\x1f\x8b\x08" . "\x00\x00\x00\x00\x00\x03";
        $this->assertTrue(RSSUtils::is_gzipped($gzip_data));
    }

    public function test_is_gzipped_plaintext(): void {
        $this->assertFalse(RSSUtils::is_gzipped("<xml>hello</xml>"));
    }

    public function test_is_gzipped_empty_string(): void {
        $this->assertFalse(RSSUtils::is_gzipped(""));
    }

    public function test_is_gzipped_short_string(): void {
        $this->assertFalse(RSSUtils::is_gzipped("\x1f"));
        $this->assertFalse(RSSUtils::is_gzipped("\x1f\x8b"));
    }

    public function test_is_gzipped_json_content(): void {
        $this->assertFalse(RSSUtils::is_gzipped('{"key": "value"}'));
    }

    public function test_is_gzipped_xml_content(): void {
        $this->assertFalse(RSSUtils::is_gzipped('<?xml version="1.0"?><rss version="2.0"/>'));
    }

    public function test_is_gzipped_png_header(): void {
        // PNG magic bytes: 0x89 0x50 0x4e 0x47
        $png_data = "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";
        $this->assertFalse(RSSUtils::is_gzipped($png_data));
    }

    public function test_is_gzipped_jpeg_header(): void {
        // JPEG magic bytes: 0xff 0xd8 0xff
        $jpeg_data = "\xff\xd8\xff\xe0\x00\x10JFIF";
        $this->assertFalse(RSSUtils::is_gzipped($jpeg_data));
    }

    // ------------------------------------------------------------------------
    // function_enabled() — PHP disable_functions check
    // ------------------------------------------------------------------------

    public function test_function_enabled_common_functions(): void {
        // These functions should normally be enabled
        $this->assertTrue(RSSUtils::function_enabled("strlen"));
        $this->assertTrue(RSSUtils::function_enabled("phpinfo"));
    }

    public function test_function_enabled_nonexistent(): void {
        // Non-existent functions are still "enabled" (not in disable_functions list)
        $this->assertTrue(RSSUtils::function_enabled("this_function_does_not_exist_xyz"));
    }

    // ------------------------------------------------------------------------
    // labels_contains_caption() — label caption lookup
    // ------------------------------------------------------------------------

    public function test_labels_contains_caption_found(): void {
        $labels = [
            [1, "Important", "#ff0000", "#ffffff"],
            [2, "News", "#0000ff", "#ffffff"],
        ];
        $this->assertTrue(RSSUtils::labels_contains_caption($labels, "Important"));
    }

    public function test_labels_contains_caption_not_found(): void {
        $labels = [
            [1, "Important", "#ff0000", "#ffffff"],
            [2, "News", "#0000ff", "#ffffff"],
        ];
        $this->assertFalse(RSSUtils::labels_contains_caption($labels, "Archive"));
    }

    public function test_labels_contains_caption_empty_array(): void {
        $this->assertFalse(RSSUtils::labels_contains_caption([], "Any"));
    }

    public function test_labels_contains_caption_case_sensitive(): void {
        $labels = [[1, "Important", "#ff0000", "#ffffff"]];
        // Caption matching is case-sensitive (exact string comparison)
        $this->assertFalse(RSSUtils::labels_contains_caption($labels, "important"));
    }

    public function test_labels_contains_caption_special_chars(): void {
        $labels = [[1, "Tag: News (2024)", "#ff0000", "#ffffff"]];
        $this->assertTrue(RSSUtils::labels_contains_caption($labels, "Tag: News (2024)"));
    }

    public function test_labels_contains_caption_empty_caption(): void {
        $labels = [[1, "", "#ff0000", "#ffffff"]];
        $this->assertTrue(RSSUtils::labels_contains_caption($labels, ""));
    }

    // ------------------------------------------------------------------------
    // has_article_filter_action() — filter action type check
    // ------------------------------------------------------------------------

    public function test_has_article_filter_action_found(): void {
        $actions = [
            ["type" => "label", "param" => "Important"],
            ["type" => "score", "param" => "10"],
        ];
        $this->assertTrue(RSSUtils::has_article_filter_action($actions, "label"));
    }

    public function test_has_article_filter_action_not_found(): void {
        $actions = [
            ["type" => "label", "param" => "Important"],
            ["type" => "score", "param" => "10"],
        ];
        $this->assertFalse(RSSUtils::has_article_filter_action($actions, "publish"));
    }

    public function test_has_article_filter_action_empty_array(): void {
        $this->assertFalse(RSSUtils::has_article_filter_action([], "label"));
    }

    public function test_has_article_filter_action_type_case_sensitive(): void {
        $actions = [["type" => "label", "param" => "Important"]];
        $this->assertFalse(RSSUtils::has_article_filter_action($actions, "Label"));
    }

    public function test_has_article_filter_action_multiple_types(): void {
        $actions = [
            ["type" => "label", "param" => "A"],
            ["type" => "tag", "param" => "B"],
            ["type" => "catchup", "param" => ""],
        ];
        $this->assertTrue(RSSUtils::has_article_filter_action($actions, "catchup"));
        $this->assertFalse(RSSUtils::has_article_filter_action($actions, "mark"));
    }

    // ------------------------------------------------------------------------
    // find_article_filter_actions() — filter action type filtering
    // ------------------------------------------------------------------------

    public function test_find_article_filter_actions_single_match(): void {
        $actions = [
            ["type" => "label", "param" => "Important"],
            ["type" => "score", "param" => "10"],
        ];
        $result = RSSUtils::find_article_filter_actions($actions, "label");
        $this->assertCount(1, $result);
        $this->assertEquals("label", $result[0]["type"]);
        $this->assertEquals("Important", $result[0]["param"]);
    }

    public function test_find_article_filter_actions_multiple_matches(): void {
        $actions = [
            ["type" => "score", "param" => "5"],
            ["type" => "label", "param" => "A"],
            ["type" => "score", "param" => "10"],
        ];
        $result = RSSUtils::find_article_filter_actions($actions, "score");

        $this->assertCount(2, $result);
        $this->assertEquals("5", $result[0]["param"]);
        $this->assertEquals("10", $result[1]["param"]);
    }

    public function test_find_article_filter_actions_no_match(): void {
        $actions = [
            ["type" => "label", "param" => "Important"],
            ["type" => "score", "param" => "10"],
        ];
        $result = RSSUtils::find_article_filter_actions($actions, "publish");
        $this->assertCount(0, $result);
    }

    public function test_find_article_filter_actions_empty_input(): void {
        $result = RSSUtils::find_article_filter_actions([], "label");
        $this->assertCount(0, $result);
    }

    // ------------------------------------------------------------------------
    // calculate_article_score() — score modifier summation
    // ------------------------------------------------------------------------

    public function test_calculate_article_score_single_modifier(): void {
        $actions = [["type" => "score", "param" => "10"]];
        $this->assertEquals(10, RSSUtils::calculate_article_score($actions));
    }

    public function test_calculate_article_score_multiple_modifiers(): void {
        $actions = [
            ["type" => "score", "param" => "5"],
            ["type" => "label", "param" => "Important"],
            ["type" => "score", "param" => "15"],
        ];
        $this->assertEquals(20, RSSUtils::calculate_article_score($actions));
    }

    public function test_calculate_article_score_negative_modifier(): void {
        $actions = [
            ["type" => "score", "param" => "10"],
            ["type" => "score", "param" => "-5"],
        ];
        $this->assertEquals(5, RSSUtils::calculate_article_score($actions));
    }

    public function test_calculate_article_score_empty_array(): void {
        $this->assertEquals(0, RSSUtils::calculate_article_score([]));
    }

    public function test_calculate_article_score_no_score_actions(): void {
        $actions = [
            ["type" => "label", "param" => "Important"],
            ["type" => "tag", "param" => "news"],
        ];
        $this->assertEquals(0, RSSUtils::calculate_article_score($actions));
    }

    public function test_calculate_article_score_zero_modifier(): void {
        $actions = [["type" => "score", "param" => "0"]];
        $this->assertEquals(0, RSSUtils::calculate_article_score($actions));
    }

    public function test_calculate_article_score_large_values(): void {
        $actions = [
            ["type" => "score", "param" => "1000"],
            ["type" => "score", "param" => "-500"],
        ];
        $this->assertEquals(500, RSSUtils::calculate_article_score($actions));
    }

    // ------------------------------------------------------------------------
    // eval_article_filters() — filter rule evaluation (pure function)
    //
    // Note: The reg_exp values in filter rules come from the DB without
    // regex delimiters. The code adds "/pattern/iu" automatically.
    // ------------------------------------------------------------------------

    public function test_eval_article_filters_title_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "TestLabel"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "This is a test article", "", "", "", []);
        $this->assertCount(1, $result);
        $this->assertEquals("label", $result[0]["type"]);
    }

    public function test_eval_article_filters_title_no_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "xyzxyz", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "TestLabel"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "This is a test article", "", "", "", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_content_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "content", "reg_exp" => "secret", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Secret"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Title", "This contains a secret message", "", "", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_author_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "author", "reg_exp" => "john", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "John"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Title", "", "", "John Doe", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_link_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "link", "reg_exp" => "example\\.com", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Example"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Title", "", "https://example.com/post", "", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_tag_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "tag", "reg_exp" => "news", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "News"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Title", "", "", "", ["news", "world"]);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_tag_no_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "tag", "reg_exp" => "sports", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Sports"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Title", "", "", "", ["news", "world"]);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_both_title_and_content(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "both", "reg_exp" => "urgent", "inverse" => false]],
                "actions" => [["type" => "score", "param" => "100"]],
            ],
        ];
        // Match in content
        $result = RSSUtils::eval_article_filters($filters, "Title", "This is urgent", "", "", []);
        $this->assertCount(1, $result);
        $this->assertEquals("score", $result[0]["type"]);
    }

    public function test_eval_article_filters_inverse_rule(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => true]],
                "actions" => [["type" => "label", "param" => "NotTest"]],
            ],
        ];
        // Inverse: should match when title does NOT contain "test"
        $result = RSSUtils::eval_article_filters($filters, "This is normal", "", "", "", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_inverse_rule_no_match(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => true]],
                "actions" => [["type" => "label", "param" => "NotTest"]],
            ],
        ];
        // Inverse: should NOT match when title DOES contain "test"
        $result = RSSUtils::eval_article_filters($filters, "This is a test", "", "", "", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_inverse_filter(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => true,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "NotTest"]],
            ],
        ];
        // Inverse filter: should match when rules do NOT match
        $result = RSSUtils::eval_article_filters($filters, "Normal article", "", "", "", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_match_any_rule(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => true,
                "inverse" => false,
                "rules" => [
                    ["type" => "title", "reg_exp" => "xyz", "inverse" => false],
                    ["type" => "author", "reg_exp" => "john", "inverse" => false],
                ],
                "actions" => [["type" => "label", "param" => "MatchAny"]],
            ],
        ];
        // match_any_rule: author matches, title doesn't — filter should still match
        $result = RSSUtils::eval_article_filters($filters, "No xyz here", "", "", "John Doe", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_match_all_rule(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [
                    ["type" => "title", "reg_exp" => "hello", "inverse" => false],
                    ["type" => "author", "reg_exp" => "john", "inverse" => false],
                ],
                "actions" => [["type" => "label", "param" => "AllMatch"]],
            ],
        ];
        // match_all (default): both title AND author must match
        $result = RSSUtils::eval_article_filters($filters, "Hello from John", "", "", "John Doe", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_match_all_rule_partial(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [
                    ["type" => "title", "reg_exp" => "hello", "inverse" => false],
                    ["type" => "author", "reg_exp" => "john", "inverse" => false],
                ],
                "actions" => [["type" => "label", "param" => "AllMatch"]],
            ],
        ];
        // match_all: title matches but author doesn't — filter should NOT match
        $result = RSSUtils::eval_article_filters($filters, "Hello World", "", "", "Jane Doe", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_stop_action(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "urgent", "inverse" => false]],
                "actions" => [
                    ["type" => "label", "param" => "Urgent"],
                    ["type" => "stop", "param" => ""],
                ],
            ],
            [
                "id" => 2,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "urgent", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "AlsoUrgent"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Urgent news", "", "", "", []);
        // First filter matches and has stop action, so second filter should not be evaluated
        $this->assertCount(2, $result);
        $this->assertEquals("label", $result[0]["type"]);
        $this->assertEquals("stop", $result[1]["type"]);
    }

    public function test_eval_article_filters_multiple_filters(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Label1"]],
            ],
            [
                "id" => 2,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "content", "reg_exp" => "secret", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Label2"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Test article", "Contains secret info", "", "", []);
        $this->assertCount(2, $result);
    }

    public function test_eval_article_filters_matched_rules_and_filters(): void {
        $matched_rules = [];
        $matched_filters = [];
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "TestLabel"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Test article", "", "", "", [], $matched_rules, $matched_filters);
        $this->assertCount(1, $result);
        $this->assertCount(1, $matched_rules);
        $this->assertCount(1, $matched_filters);
        $this->assertEquals(1, $matched_filters[0]["id"]);
    }

    public function test_eval_article_filters_score_action(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "important", "inverse" => false]],
                "actions" => [["type" => "score", "param" => "50"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Important news", "", "", "", []);
        $this->assertCount(1, $result);
        $this->assertEquals("score", $result[0]["type"]);
        $this->assertEquals("50", $result[0]["param"]);
    }

    public function test_eval_article_filters_complex_regexp(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "^[A-Z][a-z]+\\s[A-Z][a-z]+$", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "ProperNames"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "John Doe", "", "", "", []);
        $this->assertCount(1, $result);
    }

    public function test_eval_article_filters_filter_with_multiple_actions(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "critical", "inverse" => false]],
                "actions" => [
                    ["type" => "label", "param" => "Critical"],
                    ["type" => "score", "param" => "100"],
                    ["type" => "mark", "param" => ""],
                ],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Critical update", "", "", "", []);
        $this->assertCount(3, $result);
        $this->assertEquals("label", $result[0]["type"]);
        $this->assertEquals("score", $result[1]["type"]);
        $this->assertEquals("mark", $result[2]["type"]);
    }

    public function test_eval_article_filters_empty_filters(): void {
        $result = RSSUtils::eval_article_filters([], "Title", "Content", "Link", "Author", ["tag1"]);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_empty_regexp_skipped(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "ShouldNotMatch"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Any title", "", "", "", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_tag_with_no_tags(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "tag", "reg_exp" => "empty", "inverse" => false]],
                "actions" => [["type" => "label", "param" => "Empty"]],
            ],
        ];
        // With no tags, the filter should not match (empty tag array gets a dummy '' tag)
        $result = RSSUtils::eval_article_filters($filters, "Title", "", "", "", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_filter_with_no_actions(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [["type" => "title", "reg_exp" => "test", "inverse" => false]],
                "actions" => [],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Test article", "", "", "", []);
        $this->assertCount(0, $result);
    }

    public function test_eval_article_filters_filter_with_no_rules(): void {
        $filters = [
            [
                "id" => 1,
                "match_any_rule" => false,
                "inverse" => false,
                "rules" => [],
                "actions" => [["type" => "label", "param" => "NoRules"]],
            ],
        ];
        $result = RSSUtils::eval_article_filters($filters, "Test article", "", "", "", []);
        $this->assertCount(0, $result);
    }
}

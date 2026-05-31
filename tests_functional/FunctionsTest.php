<?php
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase {

    // ------------------------------------------------------------------------
    // clean() — XSS prevention
    // ------------------------------------------------------------------------

    public function test_clean_string_simple(): void {
        $this->assertEquals("hello world", clean("hello world"));
    }

    public function test_clean_string_trimmed(): void {
        $this->assertEquals("hello", clean("  hello  "));
    }

    public function test_clean_string_strips_tags(): void {
        $this->assertEquals("hello world", clean("<b>hello</b> <i>world</i>"));
    }

    public function test_clean_string_strips_nested_tags(): void {
        // strip_tags with no allowed tags removes everything
        $this->assertEquals("hello", clean("<div><p><b>hello</b></p></div>"));
    }

    public function test_clean_string_strips_attributes(): void {
        $this->assertEquals("click me", clean('<a href="javascript:alert(1)" onclick="evil()">click me</a>'));
    }

    public function test_clean_string_strips_script(): void {
        // strip_tags removes <script>...</script> tags but keeps content
        $this->assertEquals("alert('xss')", clean("<script>alert('xss')</script>"));
    }

    public function test_clean_string_strips_img_onerror(): void {
        $this->assertEquals("", clean('<img src=x onerror=alert(1)>'));
    }

    public function test_clean_string_array(): void {
        $input = ["<b>bold</b>", "  plain  ", '<script>bad</script>'];
        $result = clean($input);
        $this->assertEquals(["bold", "plain", "bad"], $result);
    }

    public function test_clean_string_array_preserves_keys(): void {
        $input = ["a" => "<b>bold</b>", "b" => " plain "];
        $result = clean($input);
        $this->assertArrayHasKey("a", $result);
        $this->assertArrayHasKey("b", $result);
        $this->assertEquals(["a" => "bold", "b" => "plain"], $result);
    }

    public function test_clean_non_string_passthrough(): void {
        // integers pass through unchanged
        $this->assertSame(42, clean(42));
        $this->assertSame(0, clean(0));
        $this->assertSame(-7, clean(-7));

        // floats pass through unchanged
        $this->assertSame(3.14, clean(3.14));

        // null passes through
        $this->assertNull(clean(null));

        // booleans pass through
        $this->assertTrue(clean(true));
        $this->assertFalse(clean(false));

        // arrays are processed
        $this->assertEquals(["clean"], clean(["<b>clean</b>"]));
    }

    // ------------------------------------------------------------------------
    // truncate_string() — UTF-8 string truncation
    // ------------------------------------------------------------------------

    public function test_truncate_string_no_truncation(): void {
        $this->assertEquals("hello", truncate_string("hello", 10));
    }

    public function test_truncate_string_exact_length(): void {
        $this->assertEquals("hello", truncate_string("hello", 5));
    }

    public function test_truncate_string_shortens(): void {
        // Default suffix is &hellip; (HTML entity), not …
        $this->assertEquals("hello&hellip;", truncate_string("hello world", 5));
    }

    public function test_truncate_string_with_custom_suffix(): void {
        $this->assertEquals("hello---", truncate_string("hello world", 5, "---"));
    }

    public function test_truncate_string_empty_string(): void {
        $this->assertEquals("", truncate_string("", 10));
    }

    public function test_truncate_string_max_len_zero(): void {
        $this->assertEquals("&hellip;", truncate_string("hello", 0));
    }

    public function test_truncate_string_multibyte_ascii(): void {
        // 5 bytes = 5 chars in ASCII
        $this->assertEquals("hell&hellip;", truncate_string("hello", 4));
    }

    public function test_truncate_string_multibyte_unicode(): void {
        // "café" is 4 characters but 5 bytes in UTF-8
        // mb_strlen counts bytes, not characters
        $this->assertEquals("café", truncate_string("café", 4));
    }

    public function test_truncate_string_multibyte_unicode_boundary(): void {
        // "café" is 4 chars/bytes; truncating at 2 bytes
        $this->assertEquals("ca&hellip;", truncate_string("café", 2));
    }

    public function test_truncate_string_emoji(): void {
        // "👋🌍" — 2 emoji characters; mb_strlen counts characters
        // max_len=2 means no truncation (2 chars <= 2)
        $this->assertEquals("👋🌍", truncate_string("👋🌍", 2));
        // max_len=1 should truncate
        $result = truncate_string("👋🌍", 1);
        $this->assertStringContainsString("👋", $result);
        $this->assertStringContainsString("&hellip;", $result);
    }

    public function test_truncate_string_cjk(): void {
        // "你好世界" — 4 CJK characters, 3 bytes each in UTF-8
        // 3 bytes keeps 1 full character
        $result = truncate_string("你好世界", 3);
        $this->assertStringContainsString("你", $result);
        $this->assertStringContainsString("&hellip;", $result);
    }

    // ------------------------------------------------------------------------
    // truncate_middle() — middle truncation
    // ------------------------------------------------------------------------

    public function test_truncate_middle_no_truncation(): void {
        $this->assertEquals("hello", truncate_middle("hello", 10));
    }

    public function test_truncate_middle_exact_length(): void {
        $this->assertEquals("hello", truncate_middle("hello", 5));
    }

    public function test_truncate_middle_shortens(): void {
        $result = truncate_middle("hello world", 8);
        $this->assertStringContainsString("&hellip;", $result);
        // truncate_middle replaces middle chars with suffix, doesn't limit total length
        // "hell" + "&hellip;" + "rld" = "hell&hellip;rld"
        $this->assertStringContainsString("hell", $result);
        $this->assertStringContainsString("rld", $result);
    }

    public function test_truncate_middle_with_custom_suffix(): void {
        $result = truncate_middle("hello world", 8, "...");
        $this->assertStringContainsString("...", $result);
    }

    public function test_truncate_middle_empty_string(): void {
        $this->assertEquals("", truncate_middle("", 10));
    }

    public function test_truncate_middle_max_len_smaller_than_suffix(): void {
        // max_len < suffix length — function replaces chars with suffix
        $result = truncate_middle("hello", 2);
        // Replaces 3 chars starting at pos 1 with &hellip;
        $this->assertStringContainsString("&hellip;", $result);
    }

    public function test_truncate_middle_multibyte_unicode(): void {
        // "café world" — 10 chars
        $result = truncate_middle("café world", 8);
        $this->assertStringContainsString("&hellip;", $result);
        // Should keep parts from start and end
        $this->assertStringContainsString("café", $result);
        $this->assertStringContainsString("rld", $result);
    }

    // ------------------------------------------------------------------------
    // mb_substr_replace() — multibyte string replacement
    // ------------------------------------------------------------------------

    public function test_mb_substr_replace_basic(): void {
        // position 3 = 4th character (0-indexed), replace 2 chars
        $this->assertEquals("helXX world", mb_substr_replace("hello world", "XX", 3, 2));
    }

    public function test_mb_substr_replace_at_start(): void {
        $this->assertEquals("XXlo world", mb_substr_replace("hello world", "XX", 0, 3));
    }

    public function test_mb_substr_replace_at_end(): void {
        $this->assertEquals("hello XX", mb_substr_replace("hello world", "XX", 6, 5));
    }

    public function test_mb_substr_replace_replaces_all(): void {
        $this->assertEquals("hello ", mb_substr_replace("hello world", "", 6, 5));
    }

    public function test_mb_substr_replace_multibyte(): void {
        // "café world" — replace "café" (4 chars) with "XX"
        $this->assertEquals("XX world", mb_substr_replace("café world", "XX", 0, 4));
    }

    public function test_mb_substr_replace_multibyte_middle(): void {
        // "café world" — replace " world" (6 chars) with "!"
        $this->assertEquals("café!", mb_substr_replace("café world", "!", 4, 6));
    }

    // ------------------------------------------------------------------------
    // sql_bool_to_bool() — SQL boolean to PHP boolean
    // ------------------------------------------------------------------------

    public function test_sql_bool_to_bool_true_values(): void {
        $this->assertTrue(sql_bool_to_bool("t"));
        $this->assertTrue(sql_bool_to_bool("true"));
        $this->assertTrue(sql_bool_to_bool("1"));
        $this->assertTrue(sql_bool_to_bool("y"));
        $this->assertTrue(sql_bool_to_bool("anything"));
    }

    public function test_sql_bool_to_bool_false_values(): void {
        $this->assertFalse(sql_bool_to_bool("f"));
        $this->assertFalse(sql_bool_to_bool("false"));
        $this->assertFalse(sql_bool_to_bool("0"));
        $this->assertFalse(sql_bool_to_bool(""));
        $this->assertFalse(sql_bool_to_bool(null));
    }

    public function test_sql_bool_to_bool_uppercase_truthy(): void {
        // Uppercase values are NOT "f" or "false", so they're truthy
        $this->assertTrue(sql_bool_to_bool("F"));
        $this->assertTrue(sql_bool_to_bool("False"));
    }

    // ------------------------------------------------------------------------
    // bool_to_sql_bool() — PHP boolean to SQL boolean
    // ------------------------------------------------------------------------

    public function test_bool_to_sql_bool_true(): void {
        $this->assertSame(1, bool_to_sql_bool(true));
    }

    public function test_bool_to_sql_bool_false(): void {
        $this->assertSame(0, bool_to_sql_bool(false));
    }

    // ------------------------------------------------------------------------
    // arr_qmarks() — SQL IN clause placeholder generation
    // ------------------------------------------------------------------------

    public function test_arr_qmarks_single_element(): void {
        $this->assertEquals("?", arr_qmarks([1]));
    }

    public function test_arr_qmarks_two_elements(): void {
        $this->assertEquals("?,?", arr_qmarks([1, 2]));
    }

    public function test_arr_qmarks_three_elements(): void {
        $this->assertEquals("?,?,?", arr_qmarks([1, 2, 3]));
    }

    public function test_arr_qmarks_five_elements(): void {
        $this->assertEquals("?,?,?,?,?", arr_qmarks([1, 2, 3, 4, 5]));
    }

    public function test_arr_qmarks_string_values(): void {
        $this->assertEquals("?,?", arr_qmarks(["a", "b"]));
    }

    public function test_arr_qmarks_mixed_values(): void {
        $this->assertEquals("?,?,?", arr_qmarks([1, "text", 3.14]));
    }

    // ------------------------------------------------------------------------
    // with_trailing_slash() — URL path normalization
    // ------------------------------------------------------------------------

    public function test_with_trailing_slash_already_has_slash(): void {
        $this->assertEquals("/path/", with_trailing_slash("/path/"));
    }

    public function test_with_trailing_slash_no_slash(): void {
        $this->assertEquals("/path/", with_trailing_slash("/path"));
    }

    public function test_with_trailing_slash_root(): void {
        $this->assertEquals("/", with_trailing_slash("/"));
    }

    public function test_with_trailing_slash_empty(): void {
        $this->assertEquals("/", with_trailing_slash(""));
    }

    public function test_with_trailing_slash_double_slash(): void {
        $this->assertEquals("/path//", with_trailing_slash("/path//"));
    }

    // ------------------------------------------------------------------------
    // checkbox_to_sql_bool() — form checkbox to SQL boolean
    // ------------------------------------------------------------------------

    public function test_checkbox_to_sql_bool_on(): void {
        $this->assertSame(1, checkbox_to_sql_bool("on"));
    }

    public function test_checkbox_to_sql_bool_other(): void {
        $this->assertSame(0, checkbox_to_sql_bool("off"));
        $this->assertSame(0, checkbox_to_sql_bool("1"));
        $this->assertSame(0, checkbox_to_sql_bool("yes"));
        $this->assertSame(0, checkbox_to_sql_bool(""));
        $this->assertSame(0, checkbox_to_sql_bool(null));
    }

    // ------------------------------------------------------------------------
    // implements_interface() — type checking
    // ------------------------------------------------------------------------

    public function test_implements_interface_true(): void {
        // ArrayObject implements Traversable
        $this->assertTrue(implements_interface("ArrayObject", "Traversable"));
    }

    public function test_implements_interface_false(): void {
        $this->assertFalse(implements_interface("stdClass", "NonExistentInterface"));
    }

    public function test_implements_interface_class_string(): void {
        // stdClass doesn't implement any interfaces, so this returns false
        $this->assertFalse(implements_interface("stdClass", "stdClass"));
        // ArrayObject implements Countable, Traversable, IteratorAggregate
        $this->assertTrue(implements_interface("ArrayObject", "Countable"));
        $this->assertTrue(implements_interface("ArrayObject", "Traversable"));
    }

    public function test_implements_interface_instance(): void {
        $test = new self("foo");
        // PHPUnit\Framework\TestCase implements Test, Reorderable, SelfDescribing
        $this->assertTrue(implements_interface($test, "PHPUnit\Framework\Test"));
        $this->assertFalse(implements_interface($test, "NonExistentInterface"));
    }

    // ------------------------------------------------------------------------
    // get_theme_path() — theme path resolution
    // ------------------------------------------------------------------------

    public function test_get_theme_path_nonexistent(): void {
        $path = get_theme_path("nonexistent-theme-xyz");
        $this->assertEquals("", $path);
    }

    public function test_get_theme_path_with_default(): void {
        $path = get_theme_path("nonexistent-theme-xyz", "themes/classic");
        $this->assertEquals("themes/classic", $path);
    }

    public function test_get_theme_path_empty_default(): void {
        $path = get_theme_path("nonexistent");
        $this->assertEquals("", $path);
    }

    // ------------------------------------------------------------------------
    // theme_exists() — theme existence check
    // ------------------------------------------------------------------------

    public function test_theme_exists_fake(): void {
        $this->assertFalse(theme_exists("nonexistent-theme-xyz"));
    }

    // ------------------------------------------------------------------------
    // uniqid_short() — short unique ID generation
    // ------------------------------------------------------------------------

    public function test_uniqid_short_returns_string(): void {
        $id = uniqid_short();
        $this->assertNotEmpty($id);
    }

    public function test_uniqid_short_non_empty(): void {
        $this->assertNotEmpty(uniqid_short());
    }

    public function test_uniqid_short_alphanumeric(): void {
        $id = uniqid_short();
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $id);
    }

    public function test_uniqid_short_uniqueness(): void {
        // Generate multiple IDs and verify they're different
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = uniqid_short();
        }
        $unique = array_unique($ids);
        $this->assertCount(100, $unique, "All generated IDs should be unique");
    }

    // ------------------------------------------------------------------------
    // get_random_bytes() — cryptographic randomness
    // ------------------------------------------------------------------------

    public function test_get_random_bytes_returns_string(): void {
        $result = get_random_bytes(16);
        $this->assertEquals(16, strlen($result));
    }

    public function test_get_random_bytes_variety(): void {
        // Generate many bytes and check they're not all the same
        $bytes = get_random_bytes(256);
        $unique = count(array_unique(str_split($bytes)));
        // With 256 random bytes, we should see significant variety
        $this->assertGreaterThan(50, $unique, "Random bytes should show significant variety");
    }

    public function test_get_random_bytes_large(): void {
        $this->assertEquals(1024, strlen(get_random_bytes(1024)));
    }

    // ------------------------------------------------------------------------
    // T_sprintf() — translated sprintf (wrapper)
    // ------------------------------------------------------------------------

    public function test_t_sprintf_format(): void {
        // T_sprintf uses __() which returns the message key unchanged in test env
        $result = T_sprintf("Hello %s", "World");
        $this->assertEquals("Hello World", $result);
    }

    public function test_t_sprintf_multiple_args(): void {
        $result = T_sprintf("Hello %s, you have %d messages", "User", 5);
        $this->assertEquals("Hello User, you have 5 messages", $result);
    }

    // ------------------------------------------------------------------------
    // T_nsprintf() — translated plural sprintf (wrapper)
    // Note: This function has a design quirk — it consumes the count arg
    // via _ngettext, so format strings with placeholders don't work well.
    // ------------------------------------------------------------------------

    public function test_t_nsprintf_no_placeholders(): void {
        // Without format placeholders, the function works
        $result = T_nsprintf("one file", "multiple files", 1);
        $this->assertNotEmpty($result);
    }

    // ------------------------------------------------------------------------
    // gzdecode() — gzip decode fallback
    // Note: Uses compress.zlib wrapper; error handling is tricky in tests
    // ------------------------------------------------------------------------

    public function test_gzdecode_basic(): void {
        // Create a simple gzip-compressed string using gzencode
        $original = "Hello, World!";
        $compressed = gzencode($original, 9);
        $result = gzdecode($compressed);
        $this->assertEquals($original, $result);
    }

    // ------------------------------------------------------------------------
    // get_scripts_timestamp() — JS file timestamp aggregation
    // ------------------------------------------------------------------------

    public function test_get_scripts_timestamp_returns_int(): void {
        $ts = get_scripts_timestamp();
        $this->assertGreaterThanOrEqual(0, $ts);
    }

    public function test_get_scripts_timestamp_nonzero_when_js_exists(): void {
        // If there are JS files in js/, should return a non-zero timestamp
        $ts = get_scripts_timestamp();
        $has_js_files = glob("js/*.js");
        if (!empty($has_js_files)) {
            $this->assertGreaterThan(0, $ts);
        }
    }
}

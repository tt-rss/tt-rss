<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for FeedItem_Atom-specific methods (get_id, get_date, get_link,
 * get_content, get_description, get_categories, get_enclosures, get_language).
 *
 * FeedItem_Atom extends FeedItem_Common; tests here focus on Atom-specific
 * parsing behavior not covered by FeedParserTest (which tests feed-level
 * methods) or FeedItemCommonTest (which tests shared methods).
 */
final class FeedItemAtomTest extends TestCase {

    // =========================================================================
    // Fixtures — get_id()
    // =========================================================================

    private const ATOM1_ITEM_WITH_ID = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>With ID</title>
    <link href="https://example.com/1" rel="alternate"/>
    <id>urn:uuid:abc123</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_ID = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No ID</title>
    <link href="https://example.com/2" rel="alternate"/>
  </entry>
</feed>';

    private const ATOM1_ITEM_ID_WITH_WHITESPACE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Whitespace ID</title>
    <link href="https://example.com/3" rel="alternate"/>
    <id>  urn:uuid:whitespace  </id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_date()
    // =========================================================================

    private const ATOM1_ITEM_UPDATED = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Updated Date</title>
    <link href="https://example.com/4" rel="alternate"/>
    <id>id-4</id>
    <updated>2024-01-15T10:30:00Z</updated>
  </entry>
</feed>';

    private const ATOM1_ITEM_PUBLISHED = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Published Date</title>
    <link href="https://example.com/5" rel="alternate"/>
    <id>id-5</id>
    <published>2023-06-20T08:00:00Z</published>
  </entry>
</feed>';

    private const ATOM1_ITEM_BOTH_UPDATED_AND_PUBLISHED = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Both Dates</title>
    <link href="https://example.com/6" rel="alternate"/>
    <id>id-6</id>
    <updated>2024-03-01T12:00:00Z</updated>
    <published>2023-01-01T00:00:00Z</published>
  </entry>
</feed>';

    private const ATOM1_ITEM_DC_DATE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:dc="http://purl.org/dc/elements/1.1/">
  <title>Test</title>
  <entry>
    <title>DC Date</title>
    <link href="https://example.com/7" rel="alternate"/>
    <id>id-7</id>
    <dc:date>2022-11-11T00:00:00Z</dc:date>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_DATE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Date</title>
    <link href="https://example.com/8" rel="alternate"/>
    <id>id-8</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_link()
    // =========================================================================

    private const ATOM1_ITEM_LINK_ALTERNATE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Link Alternate</title>
    <link href="https://example.com/9/alt" rel="alternate"/>
    <id>id-9</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LINK_STANDOUT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Link Standout</title>
    <link href="https://example.com/10/standout" rel="standout"/>
    <id>id-10</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LINK_NO_REL = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Link No Rel</title>
    <link href="https://example.com/11"/>
    <id>id-11</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LINK_SELF_IGNORED = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Link Self Ignored</title>
    <link href="https://example.com/12/self" rel="self"/>
    <link href="https://example.com/12/alternate" rel="alternate"/>
    <id>id-12</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_LINK = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Link</title>
    <id>id-13</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_XML_BASE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:base="https://example.com/base/">
  <title>Test</title>
  <entry>
    <title>XML Base</title>
    <link href="relative/path" rel="alternate"/>
    <id>id-14</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LINK_WHITESPACE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Link Whitespace</title>
    <link href="  https://example.com/15/trimmed  " rel="alternate"/>
    <id>id-15</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_title()
    // =========================================================================

    private const ATOM1_ITEM_TITLE_WITH_WHITESPACE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>  Title With Spaces  </title>
    <link href="https://example.com/16" rel="alternate"/>
    <id>id-16</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_TITLE_EMPTY = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title></title>
    <link href="https://example.com/17" rel="alternate"/>
    <id>id-17</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_TITLE_ENTITY = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Entity &amp;amp; &lt;test&gt;</title>
    <link href="https://example.com/18" rel="alternate"/>
    <id>id-18</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_content()
    // =========================================================================

    private const ATOM1_ITEM_CONTENT_TEXT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Content Text</title>
    <link href="https://example.com/19" rel="alternate"/>
    <id>id-19</id>
    <content type="text">Plain text content</content>
  </entry>
</feed>';

    private const ATOM1_ITEM_CONTENT_HTML = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Content HTML</title>
    <link href="https://example.com/20" rel="alternate"/>
    <id>id-20</id>
    <content type="html">&lt;p&gt;HTML &lt;strong&gt;content&lt;/strong&gt;&lt;/p&gt;</content>
  </entry>
</feed>';

    private const ATOM1_ITEM_CONTENT_XHTML = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Content XHTML</title>
    <link href="https://example.com/21" rel="alternate"/>
    <id>id-21</id>
    <content type="xhtml" xmlns="http://www.w3.org/1999/xhtml">
      <div xmlns="http://www.w3.org/1999/xhtml">
        <p>XHTML content</p>
      </div>
    </content>
  </entry>
</feed>';

    private const ATOM1_ITEM_CONTENT_WITH_BASE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:base="https://cdn.example.com/">
  <title>Test</title>
  <entry>
    <title>Content With Base</title>
    <link href="https://example.com/22" rel="alternate"/>
    <id>id-22</id>
    <content type="text">&lt;img src="images/photo.jpg"/&gt;</content>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_CONTENT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Content</title>
    <link href="https://example.com/23" rel="alternate"/>
    <id>id-23</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_description()
    // =========================================================================

    private const ATOM1_ITEM_SUMMARY_TEXT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Summary Text</title>
    <link href="https://example.com/24" rel="alternate"/>
    <id>id-24</id>
    <summary>Summary description text</summary>
  </entry>
</feed>';

    private const ATOM1_ITEM_SUMMARY_XHTML = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Summary XHTML</title>
    <link href="https://example.com/25" rel="alternate"/>
    <id>id-25</id>
    <summary type="xhtml" xmlns="http://www.w3.org/1999/xhtml">
      <div xmlns="http://www.w3.org/1999/xhtml">
        <p>XHTML summary</p>
      </div>
    </summary>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_SUMMARY = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Summary</title>
    <link href="https://example.com/26" rel="alternate"/>
    <id>id-26</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_categories()
    // =========================================================================

    private const ATOM1_ITEM_CATEGORIES_TERM = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Categories Term</title>
    <link href="https://example.com/27" rel="alternate"/>
    <id>id-27</id>
    <category term="Technology"/>
    <category term="Programming" scheme="http://example.com/scheme"/>
  </entry>
</feed>';

    private const ATOM1_ITEM_CATEGORIES_DC_SUBJECT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:dc="http://purl.org/dc/elements/1.1/">
  <title>Test</title>
  <entry>
    <title>DC Subject</title>
    <link href="https://example.com/28" rel="alternate"/>
    <id>id-28</id>
    <dc:subject>Science</dc:subject>
    <dc:subject>Research</dc:subject>
  </entry>
</feed>';

    private const ATOM1_ITEM_CATEGORIES_BOTH = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:dc="http://purl.org/dc/elements/1.1/">
  <title>Test</title>
  <entry>
    <title>Both Categories</title>
    <link href="https://example.com/29" rel="alternate"/>
    <id>id-29</id>
    <category term="News"/>
    <dc:subject>World</dc:subject>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_CATEGORIES = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Categories</title>
    <link href="https://example.com/30" rel="alternate"/>
    <id>id-30</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_enclosures()
    // =========================================================================

    private const ATOM1_ITEM_ENCLOSURE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Enclosure</title>
    <link href="https://example.com/31" rel="alternate"/>
    <link href="https://example.com/podcast.mp3"
          rel="enclosure"
          type="audio/mpeg"
          length="1234567"/>
    <id>id-31</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_ENCLOSURE_WITH_BASE = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:base="https://cdn.example.com/">
  <title>Test</title>
  <entry>
    <title>Enclosure With Base</title>
    <link href="https://example.com/32" rel="alternate"/>
    <link href="relative/audio.mp3"
          rel="enclosure"
          type="audio/mpeg"
          length="999"/>
    <id>id-32</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_ENCLOSURE_NO_REL = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Rel Enclosure</title>
    <link href="https://example.com/33" rel="alternate"/>
    <link href="https://example.com/file.bin" type="application/octet-stream"/>
    <id>id-33</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_ENCLOSURE_MEDIA = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:media="http://search.yahoo.com/mrss/">
  <title>Test</title>
  <entry>
    <title>Media Enclosure</title>
    <link href="https://example.com/34" rel="alternate"/>
    <link href="https://example.com/podcast.mp3"
          rel="enclosure"
          type="audio/mpeg"
          length="555"/>
    <media:content url="https://example.com/video.mp4"
                   type="video/mp4"/>
    <id>id-34</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — get_language()
    // =========================================================================

    private const ATOM1_ITEM_LANG_ENTRY = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry xml:lang="en-US">
    <title>Entry Lang</title>
    <link href="https://example.com/35" rel="alternate"/>
    <id>id-35</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LANG_FEED_FALLBACK = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="fr-FR">
  <title>Test</title>
  <entry>
    <title>Feed Lang Fallback</title>
    <link href="https://example.com/36" rel="alternate"/>
    <id>id-36</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_LANG_ENTRY_OVERRIDES_FEED = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="ja">
  <title>Test</title>
  <entry xml:lang="de-AT">
    <title>Entry Overrides Feed</title>
    <link href="https://example.com/37" rel="alternate"/>
    <id>id-37</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_LANG = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Lang</title>
    <link href="https://example.com/38" rel="alternate"/>
    <id>id-38</id>
  </entry>
</feed>';

    // =========================================================================
    // Fixtures — multiple entries
    // =========================================================================

    private const ATOM1_MULTIPLE_ENTRIES = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>First Entry</title>
    <link href="https://example.com/a" rel="alternate"/>
    <id>id-a</id>
    <updated>2024-01-01T00:00:00Z</updated>
  </entry>
  <entry>
    <title>Second Entry</title>
    <link href="https://example.com/b" rel="alternate"/>
    <id>id-b</id>
    <published>2023-06-15T12:00:00Z</published>
  </entry>
  <entry>
    <title>Third Entry</title>
    <link href="https://example.com/c" rel="alternate"/>
    <id>id-c</id>
  </entry>
</feed>';

    // =========================================================================
    // get_id()
    // =========================================================================

    public function test_get_id_with_id_element(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_WITH_ID);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("urn:uuid:abc123", $item->get_id());
    }

    public function test_get_id_fallback_to_link(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_ID);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/2", $item->get_id());
    }

    public function test_get_id_does_not_trim_id(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ID_WITH_WHITESPACE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        // get_id returns raw nodeValue, not cleaned
        $this->assertEquals("  urn:uuid:whitespace  ", $item->get_id());
    }

    // =========================================================================
    // get_date()
    // =========================================================================

    public function test_get_date_updated(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_UPDATED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $date = $item->get_date();
        $this->assertIsInt($date);
        $this->assertEquals(strtotime("2024-01-15T10:30:00Z"), $date);
    }

    public function test_get_date_published(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_PUBLISHED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $date = $item->get_date();
        $this->assertIsInt($date);
        $this->assertEquals(strtotime("2023-06-20T08:00:00Z"), $date);
    }

    public function test_get_date_prefers_updated_over_published(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_BOTH_UPDATED_AND_PUBLISHED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $date = $item->get_date();
        $this->assertIsInt($date);
        // Should use updated, not published
        $this->assertEquals(strtotime("2024-03-01T12:00:00Z"), $date);
        $this->assertNotEquals(strtotime("2023-01-01T00:00:00Z"), $date);
    }

    public function test_get_date_dc_date(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_DC_DATE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $date = $item->get_date();
        $this->assertIsInt($date);
        $this->assertEquals(strtotime("2022-11-11T00:00:00Z"), $date);
    }

    public function test_get_date_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_DATE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertFalse($item->get_date());
    }

    // =========================================================================
    // get_link()
    // =========================================================================

    public function test_get_link_rel_alternate(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LINK_ALTERNATE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/9/alt", $item->get_link());
    }

    public function test_get_link_rel_standout(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LINK_STANDOUT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/10/standout", $item->get_link());
    }

    public function test_get_link_no_rel(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LINK_NO_REL);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/11", $item->get_link());
    }

    public function test_get_link_ignores_self_rel(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LINK_SELF_IGNORED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/12/alternate", $item->get_link());
    }

    public function test_get_link_no_link(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_LINK);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("", $item->get_link());
    }

    public function test_get_link_xml_base(): void {
        // xml:base on feed root is inherited by entries and used to resolve
        // relative link hrefs. PHP's DOMXPath * wildcard matches elements in
        // any namespace, so ancestor-or-self::*[@xml:base] works for Atom feeds.
        $parser = new FeedParser(self::ATOM1_ITEM_XML_BASE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/relative/path", $item->get_link());
    }

    public function test_get_link_trims_whitespace(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LINK_WHITESPACE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("https://example.com/15/trimmed", $item->get_link());
    }

    // =========================================================================
    // get_title()
    // =========================================================================

    public function test_get_title_trims_whitespace(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_TITLE_WITH_WHITESPACE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("Title With Spaces", $item->get_title());
    }

    public function test_get_title_empty(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_TITLE_EMPTY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("", $item->get_title());
    }

    public function test_get_title_decodes_entities(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_TITLE_ENTITY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        // XML parser decodes &amp;amp; to &amp; and &lt; to <, &gt; to >
        // clean() then applies htmlspecialchars, encoding & and < and >
        $this->assertStringContainsString("Entity", $item->get_title());
        $this->assertStringContainsString("amp", $item->get_title());
    }

    // =========================================================================
    // get_content()
    // =========================================================================

    public function test_get_content_text(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CONTENT_TEXT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("Plain text content", $item->get_content());
    }

    public function test_get_content_html(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CONTENT_HTML);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $content = $item->get_content();
        $this->assertStringContainsString("HTML", $content);
        $this->assertStringContainsString("strong", $content);
    }

    public function test_get_content_xhtml(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CONTENT_XHTML);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $content = $item->get_content();
        $this->assertStringContainsString("XHTML content", $content);
    }

    public function test_get_content_no_content(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_CONTENT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("", $item->get_content());
    }

    public function test_get_content_rewrites_relative_urls(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CONTENT_WITH_BASE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $content = $item->get_content();
        $this->assertStringContainsString("https://cdn.example.com/images/photo.jpg", $content);
    }

    // =========================================================================
    // get_description()
    // =========================================================================

    public function test_get_description_text(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_SUMMARY_TEXT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("Summary description text", $item->get_description());
    }

    public function test_get_description_xhtml(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_SUMMARY_XHTML);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $desc = $item->get_description();
        $this->assertStringContainsString("XHTML summary", $desc);
    }

    public function test_get_description_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_SUMMARY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("", $item->get_description());
    }

    // =========================================================================
    // get_categories()
    // =========================================================================

    public function test_get_categories_term(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CATEGORIES_TERM);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $cats = $item->get_categories();
        $this->assertCount(2, $cats);
        $this->assertContains("technology", $cats);
        $this->assertContains("programming", $cats);
    }

    public function test_get_categories_dc_subject(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CATEGORIES_DC_SUBJECT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $cats = $item->get_categories();
        $this->assertCount(2, $cats);
        $this->assertContains("science", $cats);
        $this->assertContains("research", $cats);
    }

    public function test_get_categories_both(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CATEGORIES_BOTH);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $cats = $item->get_categories();
        $this->assertEquals(["news", "world"], $cats);
    }

    public function test_get_categories_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_CATEGORIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEmpty($item->get_categories());
    }

    // =========================================================================
    // get_enclosures()
    // =========================================================================

    public function test_get_enclosure_rel_enclosure(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ENCLOSURE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $encs = $item->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/podcast.mp3", $encs[0]->link);
        $this->assertEquals("audio/mpeg", $encs[0]->type);
        $this->assertEquals("1234567", $encs[0]->length);
    }

    public function test_get_enclosure_rewrites_relative_with_base(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ENCLOSURE_WITH_BASE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $encs = $item->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://cdn.example.com/relative/audio.mp3", $encs[0]->link);
    }

    public function test_get_enclosure_ignores_non_enclosure_links(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ENCLOSURE_NO_REL);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $encs = $item->get_enclosures();
        $this->assertEmpty($encs);
    }

    public function test_get_enclosure_combined_with_media(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ENCLOSURE_MEDIA);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $encs = $item->get_enclosures();
        // rel=enclosure link + media:content = 2 enclosures
        $this->assertCount(2, $encs);
    }

    // =========================================================================
    // get_language()
    // =========================================================================

    public function test_get_language_entry_lang(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LANG_ENTRY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("en-US", $item->get_language());
    }

    public function test_get_language_feed_fallback(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LANG_FEED_FALLBACK);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("fr-FR", $item->get_language());
    }

    public function test_get_language_entry_overrides_feed(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_LANG_ENTRY_OVERRIDES_FEED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("de-AT", $item->get_language());
    }

    public function test_get_language_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_LANG);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        /** @var \FeedItem_Atom $item */
        $item = $items[0];
        $this->assertEquals("", $item->get_language());
    }

    // =========================================================================
    // Multiple entries — ensure each entry is parsed independently
    // =========================================================================

    public function test_multiple_entries_have_independent_ids(): void {
        $parser = new FeedParser(self::ATOM1_MULTIPLE_ENTRIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertCount(3, $items);
        $this->assertEquals("id-a", $items[0]->get_id());
        $this->assertEquals("id-b", $items[1]->get_id());
        $this->assertEquals("id-c", $items[2]->get_id());
    }

    public function test_multiple_entries_have_independent_dates(): void {
        $parser = new FeedParser(self::ATOM1_MULTIPLE_ENTRIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertIsInt($items[0]->get_date());
        $this->assertEquals(strtotime("2024-01-01T00:00:00Z"), $items[0]->get_date());

        $this->assertIsInt($items[1]->get_date());
        $this->assertEquals(strtotime("2023-06-15T12:00:00Z"), $items[1]->get_date());

        $this->assertFalse($items[2]->get_date());
    }

    public function test_multiple_entries_have_independent_links(): void {
        $parser = new FeedParser(self::ATOM1_MULTIPLE_ENTRIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("https://example.com/a", $items[0]->get_link());
        $this->assertEquals("https://example.com/b", $items[1]->get_link());
        $this->assertEquals("https://example.com/c", $items[2]->get_link());
    }

    public function test_multiple_entries_have_independent_titles(): void {
        $parser = new FeedParser(self::ATOM1_MULTIPLE_ENTRIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("First Entry", $items[0]->get_title());
        $this->assertEquals("Second Entry", $items[1]->get_title());
        $this->assertEquals("Third Entry", $items[2]->get_title());
    }
}

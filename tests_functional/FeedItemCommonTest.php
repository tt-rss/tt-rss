<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for FeedItem_Common shared methods (get_author, get_enclosures,
 * get_comments_url, get_comments_count, normalize_categories, etc.)
 *
 * FeedItem_Common is abstract; we test through its concrete subclasses
 * FeedItem_RSS and FeedItem_Atom via FeedParser.
 */
final class FeedItemCommonTest extends TestCase {

    // =========================================================================
    // Fixtures — RSS 2.0
    // =========================================================================

    private const RSS2_ITEM_AUTHOR = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Author Test</title>
      <author><name>Alice Smith</name><email>alice@example.com</email></author>
      <link>https://example.com/1</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_AUTHOR_NAME_ONLY = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Author Name Only</title>
      <author><name>Bob Jones</name></author>
      <link>https://example.com/2</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_AUTHOR_EMAIL_ONLY = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Author Email Only</title>
      <author><email>bob@example.com</email></author>
      <link>https://example.com/3</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_AUTHOR_TEXT_ONLY = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Author Text Only</title>
      <author>Charlie Brown</author>
      <link>https://example.com/4</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_DC_CREATOR = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Test</title>
    <item>
      <title>DC Creator</title>
      <dc:creator>Dave Author</dc:creator>
      <dc:creator>Eve Writer</dc:creator>
      <link>https://example.com/5</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_NO_AUTHOR = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>No Author</title>
      <link>https://example.com/6</link>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_COMMENTS = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Comments Test</title>
      <link>https://example.com/7</link>
      <comments>https://example.com/7/comments</comments>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_COMMENTS_COUNT = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:slash="http://purl.org/rss/1.0/modules/slash/">
  <channel>
    <title>Test</title>
    <item>
      <title>Comments Count</title>
      <link>https://example.com/8</link>
      <slash:comments>42</slash:comments>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_MEDIA_CONTENT = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>Media Content</title>
      <link>https://example.com/9</link>
      <media:content url="https://example.com/video.mp4"
                     type="video/mp4"
                     length="123456"
                     height="720"
                     width="1280">
        <media:description>Video description</media:description>
      </media:content>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_MEDIA_THUMBNAIL = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>Media Thumbnail</title>
      <link>https://example.com/10</link>
      <media:thumbnail url="https://example.com/thumb.jpg"
                       height="360"
                       width="640"/>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_ENCLOSURE = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Enclosure</title>
      <link>https://example.com/11</link>
      <enclosure url="https://example.com/audio.mp3"
                 type="audio/mpeg"
                 length="987654"/>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_CATEGORIES = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Test</title>
    <item>
      <title>Categories</title>
      <link>https://example.com/12</link>
      <category>Technology</category>
      <category>Programming, Web</category>
      <dc:subject>Design</dc:subject>
    </item>
  </channel>
</rss>';

    private const RSS2_ITEM_SOURCE_REMOVED = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Source Removal</title>
      <link>https://example.com/13</link>
      <source url="https://example.com/source.rss">Source Feed</source>
    </item>
  </channel>
</rss>';

    // =========================================================================
    // Fixtures — Atom 1.0
    // =========================================================================

    private const ATOM1_ITEM_AUTHOR = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Atom Author</title>
    <author><name>Atom Author</name><email>atom@example.com</email></author>
    <link href="https://example.com/atom/1" rel="alternate"/>
    <id>atom-id-1</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_REPLIES = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Atom Replies</title>
    <link href="https://example.com/atom/2" rel="alternate"/>
    <link href="https://example.com/atom/2/replies"
          rel="replies"
          type="text/html"/>
    <id>atom-id-2</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_THREADED_REPLIES = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:thread="http://purl.org/syndication/thread/1.0">
  <title>Test</title>
  <entry>
    <title>Threaded Replies</title>
    <link href="https://example.com/atom/3" rel="alternate"/>
    <link href="https://example.com/atom/3/replies"
          rel="replies"
          thread:count="7"/>
    <id>atom-id-3</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_MEDIA_CONTENT = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:media="http://search.yahoo.com/mrss/">
  <title>Test</title>
  <entry>
    <title>Atom Media</title>
    <link href="https://example.com/atom/4" rel="alternate"/>
    <id>atom-id-4</id>
    <media:content url="https://example.com/atom-video.mp4"
                   type="video/mp4"
                   length="555555"/>
  </entry>
</feed>';

    private const ATOM1_ITEM_ENCLOSURE_LINK = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>Atom Enclosure Link</title>
    <link href="https://example.com/atom/5" rel="alternate"/>
    <link href="https://example.com/atom-podcast.mp3"
          rel="enclosure"
          type="audio/mpeg"
          length="1234567"/>
    <id>atom-id-5</id>
  </entry>
</feed>';

    private const ATOM1_ITEM_CATEGORIES = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:dc="http://purl.org/dc/elements/1.1/">
  <title>Test</title>
  <entry>
    <title>Atom Categories</title>
    <link href="https://example.com/atom/6" rel="alternate"/>
    <id>atom-id-6</id>
    <category term="News" scheme="http://example.com/scheme"/>
    <category term="Politics"/>
    <dc:subject>World</dc:subject>
  </entry>
</feed>';

    private const ATOM1_ITEM_NO_AUTHOR = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test</title>
  <entry>
    <title>No Author Atom</title>
    <link href="https://example.com/atom/7" rel="alternate"/>
    <id>atom-id-7</id>
  </entry>
</feed>';

    // =========================================================================
    // get_author() — RSS
    // =========================================================================

    public function test_rss_get_author_with_name_and_email(): void {
        $parser = new FeedParser(self::RSS2_ITEM_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertCount(1, $items);
        $this->assertEquals("Alice Smith", $items[0]->get_author());
    }

    public function test_rss_get_author_name_only(): void {
        $parser = new FeedParser(self::RSS2_ITEM_AUTHOR_NAME_ONLY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("Bob Jones", $items[0]->get_author());
    }

    public function test_rss_get_author_email_only(): void {
        $parser = new FeedParser(self::RSS2_ITEM_AUTHOR_EMAIL_ONLY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("bob@example.com", $items[0]->get_author());
    }

    public function test_rss_get_author_text_only(): void {
        $parser = new FeedParser(self::RSS2_ITEM_AUTHOR_TEXT_ONLY);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("Charlie Brown", $items[0]->get_author());
    }

    public function test_rss_get_author_dc_creator_single(): void {
        $parser = new FeedParser(self::RSS2_ITEM_DC_CREATOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        // dc:creator should be used when no <author> element exists
        $this->assertEquals("Dave Author, Eve Writer", $items[0]->get_author());
    }

    public function test_rss_get_author_none(): void {
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("", $items[0]->get_author());
    }

    // =========================================================================
    // get_author() — Atom
    // =========================================================================

    public function test_atom_get_author(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("Atom Author", $items[0]->get_author());
    }

    public function test_atom_get_author_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("", $items[0]->get_author());
    }

    // =========================================================================
    // get_author() — HTML entity / special character handling
    // =========================================================================

    public function test_rss_get_author_with_html_entities(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Entity Test</title>
      <author><name>John &amp; Jane Doe</name></author>
      <link>https://example.com/entities</link>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("John & Jane Doe", $items[0]->get_author());
    }

    // =========================================================================
    // get_comments_url()
    // =========================================================================

    public function test_rss_get_comments_url(): void {
        $parser = new FeedParser(self::RSS2_ITEM_COMMENTS);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("https://example.com/7/comments", $items[0]->get_comments_url());
    }

    public function test_rss_get_comments_url_none(): void {
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("", $items[0]->get_comments_url());
    }

    public function test_atom_get_comments_url_replies(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_REPLIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("https://example.com/atom/2/replies", $items[0]->get_comments_url());
    }

    // =========================================================================
    // get_comments_count()
    // =========================================================================

    public function test_rss_get_comments_count_slash(): void {
        $parser = new FeedParser(self::RSS2_ITEM_COMMENTS_COUNT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals(42, $items[0]->get_comments_count());
    }

    public function test_atom_get_comments_count_thread(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_THREADED_REPLIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals(7, $items[0]->get_comments_count());
    }

    public function test_rss_get_comments_count_none(): void {
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals(0, $items[0]->get_comments_count());
    }

    public function test_atom_get_comments_count_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals(0, $items[0]->get_comments_count());
    }

    // =========================================================================
    // get_enclosures() — Media RSS
    // =========================================================================

    public function test_rss_get_enclosures_media_content(): void {
        $parser = new FeedParser(self::RSS2_ITEM_MEDIA_CONTENT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/video.mp4", $encs[0]->link);
        $this->assertEquals("video/mp4", $encs[0]->type);
        $this->assertEquals("123456", $encs[0]->length);
        $this->assertEquals("720", $encs[0]->height);
        $this->assertEquals("1280", $encs[0]->width);
        $this->assertEquals("Video description", $encs[0]->title);
    }

    public function test_rss_get_enclosures_media_thumbnail(): void {
        $parser = new FeedParser(self::RSS2_ITEM_MEDIA_THUMBNAIL);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/thumb.jpg", $encs[0]->link);
        $this->assertEquals("image/generic", $encs[0]->type);
        $this->assertEquals("360", $encs[0]->height);
        $this->assertEquals("640", $encs[0]->width);
    }

    public function test_rss_get_enclosures_rss_enclosure(): void {
        $parser = new FeedParser(self::RSS2_ITEM_ENCLOSURE);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/audio.mp3", $encs[0]->link);
        $this->assertEquals("audio/mpeg", $encs[0]->type);
        $this->assertEquals("987654", $encs[0]->length);
    }

    public function test_rss_get_enclosures_media_and_rss_combined(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>Combined Enclosures</title>
      <link>https://example.com/combined</link>
      <enclosure url="https://example.com/audio.mp3"
                 type="audio/mpeg"
                 length="1000"/>
      <media:content url="https://example.com/video.mp4"
                     type="video/mp4"/>
      <media:thumbnail url="https://example.com/thumb.jpg"/>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        // RSS enclosure + media:content + media:thumbnail = 3
        $this->assertCount(3, $encs);
    }

    public function test_atom_get_enclosures_media_content(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_MEDIA_CONTENT);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/atom-video.mp4", $encs[0]->link);
        $this->assertEquals("video/mp4", $encs[0]->type);
    }

    public function test_atom_get_enclosures_enclosure_link(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_ENCLOSURE_LINK);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/atom-podcast.mp3", $encs[0]->link);
        $this->assertEquals("audio/mpeg", $encs[0]->type);
        $this->assertEquals("1234567", $encs[0]->length);
    }

    public function test_rss_get_enclosures_none(): void {
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertEmpty($encs);
    }

    public function test_atom_get_enclosures_none(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertEmpty($encs);
    }

    // =========================================================================
    // get_enclosures() — media:group
    // =========================================================================

    public function test_rss_get_enclosures_media_group(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>Media Group</title>
      <link>https://example.com/group</link>
      <media:group>
        <media:content url="https://example.com/highres.jpg"
                       type="image/jpeg"
                       height="1080"
                       width="1920"/>
        <media:description>High resolution image</media:description>
      </media:group>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("https://example.com/highres.jpg", $encs[0]->link);
        $this->assertEquals("image/jpeg", $encs[0]->type);
        $this->assertEquals("1080", $encs[0]->height);
        $this->assertEquals("1920", $encs[0]->width);
        $this->assertEquals("High resolution image", $encs[0]->title);
    }

    // =========================================================================
    // get_enclosures() — medium attribute fallback
    // =========================================================================

    public function test_rss_get_enclosures_medium_attribute(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>Medium Attribute</title>
      <link>https://example.com/medium</link>
      <media:content medium="video" url="https://example.com/video.webm"/>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("video/generic", $encs[0]->type);
    }

    // =========================================================================
    // normalize_categories() — static method
    // =========================================================================

    /**
     * Helper: check that two arrays have the same values (order-independent),
     * since normalize_categories uses asort() which may behave differently
     * depending on locale and preserves keys from array_filter.
     *
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function assertNormalizeCategoriesEquals(array $expected, array $actual): void {
        $expectedSorted = $expected;
        $actualSorted = $actual;
        sort($expectedSorted);
        sort($actualSorted);
        $this->assertEquals($expectedSorted, $actualSorted);
    }

    public function test_normalize_categories_simple(): void {
        $result = FeedItem_Common::normalize_categories(["Technology", "Programming"]);
        $this->assertNormalizeCategoriesEquals(["programming", "technology"], $result);
    }

    public function test_normalize_categories_csv_split(): void {
        $result = FeedItem_Common::normalize_categories(["Tech, Programming"]);
        $this->assertNormalizeCategoriesEquals(["programming", "tech"], $result);
    }

    public function test_normalize_categories_csv_multiple(): void {
        $result = FeedItem_Common::normalize_categories(["A, B", "C, D"]);
        $this->assertNormalizeCategoriesEquals(["a", "b", "c", "d"], $result);
    }

    public function test_normalize_categories_numeric_prefix(): void {
        $result = FeedItem_Common::normalize_categories(["123", "456"]);
        $this->assertNormalizeCategoriesEquals(["t:123", "t:456"], $result);
    }

    public function test_normalize_categories_lowercase(): void {
        $result = FeedItem_Common::normalize_categories(["Technology", "PROGRAMMING"]);
        $this->assertNormalizeCategoriesEquals(["programming", "technology"], $result);
    }

    public function test_normalize_categories_strip_quotes(): void {
        $result = FeedItem_Common::normalize_categories(["'Tech'", '"Web"']);
        $this->assertNormalizeCategoriesEquals(["tech", "web"], $result);
    }

    public function test_normalize_categories_trim_whitespace(): void {
        $result = FeedItem_Common::normalize_categories(["  Technology  "]);
        $this->assertEquals(["technology"], $result);
    }

    public function test_normalize_categories_truncate_long(): void {
        $long = str_repeat("a", 300);
        $result = FeedItem_Common::normalize_categories([$long]);
        $this->assertEquals(250, mb_strlen($result[0]));
    }

    public function test_normalize_categories_remove_empty(): void {
        $result = FeedItem_Common::normalize_categories(["", "  ", "Valid"]);
        $this->assertCount(1, $result);
        $this->assertEquals("valid", array_values($result)[0]);
    }

    public function test_normalize_categories_empty_input(): void {
        $result = FeedItem_Common::normalize_categories([]);
        $this->assertEmpty($result);
    }

    public function test_normalize_categories_dedup(): void {
        $result = FeedItem_Common::normalize_categories(["Tech", "tech", "TECH"]);
        $this->assertEquals(["tech"], $result);
    }

    public function test_normalize_categories_mixed(): void {
        $result = FeedItem_Common::normalize_categories(["News, Politics", "world", "123"]);
        $this->assertNormalizeCategoriesEquals(["news", "politics", "t:123", "world"], $result);
    }

    // =========================================================================
    // count_children()
    // =========================================================================

    public function test_count_children_empty(): void {
        $doc = new DOMDocument();
        $doc->appendChild($doc->createElement("root"));
        $root = $doc->documentElement;
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $parser->init();
        $items = $parser->get_items();
        // FeedItem_Common is abstract, use FeedItem_RSS
        $common = $items[0];
        // Use reflection to access protected count_children
        $ref = new ReflectionMethod($common, "count_children");
        $ref->setAccessible(true);
        $this->assertEquals(0, $ref->invoke($common, $root));
    }

    public function test_count_children_with_siblings(): void {
        $doc = new DOMDocument();
        $parent = $doc->createElement("parent");
        $parent->appendChild($doc->createElement("child1"));
        $parent->appendChild($doc->createElement("child2"));
        $parent->appendChild($doc->createElement("child3"));
        $doc->appendChild($parent);

        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $parser->init();
        $items = $parser->get_items();
        $common = $items[0];
        $ref = new ReflectionMethod($common, "count_children");
        $ref->setAccessible(true);
        $this->assertEquals(3, $ref->invoke($common, $parent));
    }

    // =========================================================================
    // subtree_or_text()
    // =========================================================================

    public function test_subtree_or_text_leaf_node(): void {
        $doc = new DOMDocument();
        $node = $doc->createElement("title", "Simple Title");
        $doc->appendChild($node);

        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $parser->init();
        $items = $parser->get_items();
        $common = $items[0];
        $ref = new ReflectionMethod($common, "subtree_or_text");
        $ref->setAccessible(true);
        $result = $ref->invoke($common, $node);
        $this->assertEquals("Simple Title", $result);
    }

    public function test_subtree_or_text_element_with_children(): void {
        $doc = new DOMDocument();
        $node = $doc->createElement("content");
        $text = $doc->createTextNode("Hello ");
        $bold = $doc->createElement("b");
        $bold->appendChild($doc->createTextNode("world"));
        $node->appendChild($text);
        $node->appendChild($bold);
        $doc->appendChild($node);

        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $parser->init();
        $items = $parser->get_items();
        $common = $items[0];
        $ref = new ReflectionMethod($common, "subtree_or_text");
        $ref->setAccessible(true);
        $result = $ref->invoke($common, $node);
        $this->assertNotEquals("Hello world", $result);
        // Should return c14n serialized XML, not plain text
        $this->assertStringContainsString("<b>", $result);
    }

    // =========================================================================
    // get_element()
    // =========================================================================

    public function test_get_element_returns_dom_element(): void {
        $parser = new FeedParser(self::RSS2_ITEM_NO_AUTHOR);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        // get_element is defined in FeedItem_Common, not abstract FeedItem
        /** @var \FeedItem_Common $item */
        $item = $items[0];
        $elem = $item->get_element();
        $this->assertInstanceOf(DOMElement::class, $elem);
        $this->assertEquals("item", $elem->tagName);
    }

    // =========================================================================
    // Source element removal in constructor
    // =========================================================================

    public function test_constructor_removes_source_element(): void {
        $parser = new FeedParser(self::RSS2_ITEM_SOURCE_REMOVED);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        // get_element is defined in FeedItem_Common, not abstract FeedItem
        /** @var \FeedItem_Common $item */
        $item = $items[0];
        $elem = $item->get_element();
        $sources = $elem->getElementsByTagName("source");
        $this->assertEquals(0, $sources->length);
    }

    // =========================================================================
    // get_categories() — via normalize_categories (common logic)
    // =========================================================================

    public function test_rss_get_categories(): void {
        $parser = new FeedParser(self::RSS2_ITEM_CATEGORIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $cats = $items[0]->get_categories();
        $this->assertNormalizeCategoriesEquals(["design", "programming", "technology", "web"], $cats);
    }

    public function test_atom_get_categories(): void {
        $parser = new FeedParser(self::ATOM1_ITEM_CATEGORIES);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $cats = $items[0]->get_categories();
        $this->assertEquals(["news", "politics", "world"], $cats);
    }

    // =========================================================================
    // get_author() — dc:creator fallback when <author> has no name/email
    // =========================================================================

    public function test_rss_get_author_author_element_no_name_or_email(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <item>
      <title>Author Fallback</title>
      <author><someOtherElement>value</someOtherElement></author>
      <link>https://example.com/fallback</link>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        // <author> exists but has no name/email child; nodeValue returns
        // the concatenated text of child nodes ("value"), so that is returned
        $this->assertEquals("value", $items[0]->get_author());
    }

    // =========================================================================
    // get_comments_count() — non-numeric value
    // =========================================================================

    public function test_get_comments_count_non_numeric(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:slash="http://purl.org/rss/1.0/modules/slash/">
  <channel>
    <title>Test</title>
    <item>
      <title>Non-numeric Comments</title>
      <link>https://example.com/nonnumeric</link>
      <slash:comments>many</slash:comments>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals(0, $items[0]->get_comments_count());
    }

    // =========================================================================
    // get_enclosures() — media:content with medium fallback
    // =========================================================================

    public function test_get_enclosures_media_content_no_type(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Test</title>
    <item>
      <title>No Type</title>
      <link>https://example.com/no-type</link>
      <media:content url="https://example.com/file.bin" medium="application"/>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $encs = $items[0]->get_enclosures();
        $this->assertCount(1, $encs);
        $this->assertEquals("application/generic", $encs[0]->type);
    }

    // =========================================================================
    // get_comments_url() — atom:link rel=replies in RSS
    // =========================================================================

    public function test_rss_get_comments_url_atom_replies(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Test</title>
    <item>
      <title>Atom Replies in RSS</title>
      <link>https://example.com/atom-replies</link>
      <atom:link href="https://example.com/atom-replies/comments"
                 rel="replies"
                 type="text/html"/>
    </item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $this->assertTrue($parser->init());
        $items = $parser->get_items();
        $this->assertEquals("https://example.com/atom-replies/comments", $items[0]->get_comments_url());
    }
}

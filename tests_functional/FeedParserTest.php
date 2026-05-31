<?php
use PHPUnit\Framework\TestCase;

final class FeedParserTest extends TestCase {

    // ------------------------------------------------------------------------
    // XML fixtures
    // ------------------------------------------------------------------------

    private const RSS2_XML = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test RSS Feed</title>
    <link>https://example.com/rss</link>
    <item>
      <title>Article One</title>
      <link>https://example.com/article/1</link>
      <guid>guid-1</guid>
    </item>
    <item>
      <title>Article Two</title>
      <link>https://example.com/article/2</link>
      <guid>guid-2</guid>
    </item>
  </channel>
</rss>';

    private const RSS2_WITH_LINK_ATTR = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Channel With Attr Link</title>
    <link href="https://example.com/attr-link"/>
    <item>
      <title>Item</title>
      <link>https://example.com/item</link>
    </item>
  </channel>
</rss>';

    private const ATOM1_XML = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Test Atom Feed</title>
  <link href="https://example.com/atom"/>
  <entry>
    <title>Atom Entry One</title>
    <link href="https://example.com/entry/1" rel="alternate"/>
    <id>atom-entry-1</id>
  </entry>
  <entry>
    <title>Atom Entry Two</title>
    <link href="https://example.com/entry/2" rel="alternate"/>
    <id>atom-entry-2</id>
  </entry>
</feed>';

    private const ATOM03_XML = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://purl.org/atom/ns#" version="0.3">
  <title>Test Atom 0.3 Feed</title>
  <link href="https://example.com/atom03" rel="alternate"/>
  <entry>
    <title>Atom 0.3 Entry</title>
    <link href="https://example.com/atom03-entry" rel="alternate"/>
  </entry>
</feed>';

    private const RDF_XML = '<?xml version="1.0" encoding="UTF-8"?>
<RDF:RDF xmlns:RDF="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns="http://purl.org/rss/1.0/">
  <channel RDF:about="https://example.com/rdf">
    <title>RDF Feed Title</title>
    <link>https://example.com/rdf</link>
  </channel>
  <item RDF:about="https://example.com/rdf-item">
    <title>RDF Item</title>
    <link>https://example.com/rdf-item-link</link>
  </item>
</RDF:RDF>';

    private const MALFORMED_XML = '<?xml version="1.0"?>
<rss><channel><title>Unclosed';

    private const UNKNOWN_FEED = '<?xml version="1.0"?>
<unknown>
  <data>Not a feed</data>
</unknown>';

    private const ATOM_WITH_MULTIPLE_LINKS = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Multi Link Feed</title>
  <link href="https://example.com/atom" rel="alternate"/>
  <link href="https://example.com/atom/feed.xml" rel="self"/>
  <link href="https://example.com/atom/prev" rel="prev"/>
  <entry>
    <title>Entry</title>
  </entry>
</feed>';

    // ------------------------------------------------------------------------
    // Constructor — error handling
    // ------------------------------------------------------------------------

    public function test_constructor_malformed_xml_sets_error(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $this->assertFalse($parser->init());
        $this->assertNotEmpty($parser->error());
    }

    public function test_constructor_malformed_xml_returns_errors_array(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $errors = $parser->errors();
        $this->assertNotEmpty($errors);
    }

    public function test_constructor_unknown_feed_type_no_parse_error(): void {
        $parser = new FeedParser(self::UNKNOWN_FEED);
        // No XML parse error, but type will be FEED_UNKNOWN
        $this->assertEquals(FeedParser::FEED_UNKNOWN, $parser->get_type());
        $this->assertNotEmpty($parser->error());
    }

    // ------------------------------------------------------------------------
    // Constructor — valid feeds produce no parse error
    // ------------------------------------------------------------------------

    public function test_constructor_rss2_no_error(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $this->assertEmpty($parser->error());
    }

    public function test_constructor_atom1_no_error(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $this->assertEmpty($parser->error());
    }

    public function test_constructor_atom03_no_error(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $this->assertEmpty($parser->error());
    }

    public function test_constructor_rdf_no_error(): void {
        $parser = new FeedParser(self::RDF_XML);
        $this->assertEmpty($parser->error());
    }

    // ------------------------------------------------------------------------
    // get_type() — feed detection
    // ------------------------------------------------------------------------

    public function test_get_type_rss2(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $parser->init();
        $this->assertEquals(FeedParser::FEED_RSS, $parser->get_type());
    }

    public function test_get_type_atom1(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $parser->init();
        $this->assertEquals(FeedParser::FEED_ATOM, $parser->get_type());
    }

    public function test_get_type_atom03(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $parser->init();
        $this->assertEquals(FeedParser::FEED_ATOM, $parser->get_type());
    }

    public function test_get_type_rdf(): void {
        $parser = new FeedParser(self::RDF_XML);
        $parser->init();
        $this->assertEquals(FeedParser::FEED_RDF, $parser->get_type());
    }

    public function test_get_type_unknown(): void {
        $parser = new FeedParser(self::UNKNOWN_FEED);
        $this->assertEquals(FeedParser::FEED_UNKNOWN, $parser->get_type());
    }

    public function test_get_type_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $this->assertEquals(FeedParser::FEED_UNKNOWN, $parser->get_type());
    }

    // ------------------------------------------------------------------------
    // init() — success/failure
    // ------------------------------------------------------------------------

    public function test_init_rss2_returns_true(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $this->assertTrue($parser->init());
    }

    public function test_init_atom1_returns_true(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $this->assertTrue($parser->init());
    }

    public function test_init_atom03_returns_true(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $this->assertTrue($parser->init());
    }

    public function test_init_rdf_returns_true(): void {
        $parser = new FeedParser(self::RDF_XML);
        $this->assertTrue($parser->init());
    }

    public function test_init_malformed_returns_false(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $this->assertFalse($parser->init());
    }

    public function test_init_unknown_type_returns_false(): void {
        $parser = new FeedParser(self::UNKNOWN_FEED);
        $this->assertFalse($parser->init());
    }

    // ------------------------------------------------------------------------
    // get_title()
    // ------------------------------------------------------------------------

    public function test_get_title_rss2(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $parser->init();
        $this->assertEquals("Test RSS Feed", $parser->get_title());
    }

    public function test_get_title_atom1(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $parser->init();
        $this->assertEquals("Test Atom Feed", $parser->get_title());
    }

    public function test_get_title_atom03(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $parser->init();
        $this->assertEquals("Test Atom 0.3 Feed", $parser->get_title());
    }

    public function test_get_title_rdf(): void {
        $parser = new FeedParser(self::RDF_XML);
        $parser->init();
        $this->assertEquals("RDF Feed Title", $parser->get_title());
    }

    public function test_get_title_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $this->assertEquals("", $parser->get_title());
    }

    public function test_get_title_unknown_type(): void {
        $parser = new FeedParser(self::UNKNOWN_FEED);
        $this->assertEquals("", $parser->get_title());
    }

    // ------------------------------------------------------------------------
    // get_link()
    // ------------------------------------------------------------------------

    public function test_get_link_rss2(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $parser->init();
        $this->assertEquals("https://example.com/rss", $parser->get_link());
    }

    public function test_get_link_rss2_with_attr(): void {
        $parser = new FeedParser(self::RSS2_WITH_LINK_ATTR);
        $parser->init();
        $this->assertEquals("https://example.com/attr-link", $parser->get_link());
    }

    public function test_get_link_atom1(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $parser->init();
        $this->assertEquals("https://example.com/atom", $parser->get_link());
    }

    public function test_get_link_atom03(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $parser->init();
        $this->assertEquals("https://example.com/atom03", $parser->get_link());
    }

    public function test_get_link_rdf(): void {
        $parser = new FeedParser(self::RDF_XML);
        $parser->init();
        $this->assertEquals("https://example.com/rdf", $parser->get_link());
    }

    public function test_get_link_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $this->assertEquals("", $parser->get_link());
    }

    // ------------------------------------------------------------------------
    // get_items()
    // ------------------------------------------------------------------------

    public function test_get_items_rss2_count(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $parser->init();
        $items = $parser->get_items();
        $this->assertCount(2, $items);
    }

    public function test_get_items_atom1_count(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $parser->init();
        $items = $parser->get_items();
        $this->assertCount(2, $items);
    }

    public function test_get_items_atom03_count(): void {
        $parser = new FeedParser(self::ATOM03_XML);
        $parser->init();
        $items = $parser->get_items();
        $this->assertCount(1, $items);
    }

    public function test_get_items_rdf_count(): void {
        $parser = new FeedParser(self::RDF_XML);
        $parser->init();
        $items = $parser->get_items();
        $this->assertCount(1, $items);
    }

    public function test_get_items_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $items = $parser->get_items();
        $this->assertEmpty($items);
    }

    public function test_get_items_unknown_type(): void {
        $parser = new FeedParser(self::UNKNOWN_FEED);
        $items = $parser->get_items();
        $this->assertEmpty($items);
    }

    public function test_get_items_are_feeditem_instances(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $parser->init();
        $items = $parser->get_items();
        foreach ($items as $item) {
            $this->assertInstanceOf(FeedItem::class, $item);
        }
    }

    public function test_get_items_atom_are_feeditem_atom_instances(): void {
        $parser = new FeedParser(self::ATOM1_XML);
        $parser->init();
        $items = $parser->get_items();
        foreach ($items as $item) {
            $this->assertInstanceOf(FeedItem_Atom::class, $item);
        }
    }

    public function test_get_items_rdf_are_feeditem_rss_instances(): void {
        $parser = new FeedParser(self::RDF_XML);
        $parser->init();
        $items = $parser->get_items();
        foreach ($items as $item) {
            $this->assertInstanceOf(FeedItem_RSS::class, $item);
        }
    }

    // ------------------------------------------------------------------------
    // get_links()
    // ------------------------------------------------------------------------

    public function test_get_links_atom_no_filter(): void {
        $parser = new FeedParser(self::ATOM_WITH_MULTIPLE_LINKS);
        $parser->init();
        $links = $parser->get_links('');
        $this->assertCount(3, $links);
    }

    public function test_get_links_atom_with_rel(): void {
        $parser = new FeedParser(self::ATOM_WITH_MULTIPLE_LINKS);
        $parser->init();
        $links = $parser->get_links('self');
        $this->assertCount(1, $links);
        $this->assertEquals("https://example.com/atom/feed.xml", $links[0]);
    }

    public function test_get_links_atom_rel_alternate(): void {
        $parser = new FeedParser(self::ATOM_WITH_MULTIPLE_LINKS);
        $parser->init();
        $links = $parser->get_links('alternate');
        $this->assertCount(1, $links);
        $this->assertEquals("https://example.com/atom", $links[0]);
    }

    public function test_get_links_atom_rel_not_found(): void {
        $parser = new FeedParser(self::ATOM_WITH_MULTIPLE_LINKS);
        $parser->init();
        $links = $parser->get_links('next');
        $this->assertEmpty($links);
    }

    // ------------------------------------------------------------------------
    // error() and errors()
    // ------------------------------------------------------------------------

    public function test_error_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $error = $parser->error();
        $this->assertNotEmpty($error);
    }

    public function test_error_valid_feed(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $this->assertEmpty($parser->error());
    }

    public function test_errors_malformed(): void {
        $parser = new FeedParser(self::MALFORMED_XML);
        $errors = $parser->errors();
        $this->assertNotEmpty($errors);
    }

    public function test_errors_valid_feed(): void {
        $parser = new FeedParser(self::RSS2_XML);
        $errors = $parser->errors();
        $this->assertEmpty($errors);
    }

    // ------------------------------------------------------------------------
    // format_error() — deprecated but still present
    // ------------------------------------------------------------------------

    public function test_format_error_delegates_to_errors_class(): void {
        $error = new LibXMLError();
        $error->level = LIBXML_ERR_FATAL;
        $error->code = 999;
        $error->message = "Test format_error";
        $error->line = 10;
        $error->column = 5;
        $error->file = "fixture.xml";

        $parser = new FeedParser(self::RSS2_XML);
        $result = $parser->format_error($error);

        $this->assertEquals(
            "LibXML error 999 at line 10 (column 5): Test format_error",
            $result
        );
    }

    // ------------------------------------------------------------------------
    // Feed type constants
    // ------------------------------------------------------------------------

    public function test_feed_type_constants(): void {
        $this->assertEquals(-1, FeedParser::FEED_UNKNOWN);
        $this->assertEquals(0, FeedParser::FEED_RDF);
        $this->assertEquals(1, FeedParser::FEED_RSS);
        $this->assertEquals(2, FeedParser::FEED_ATOM);
    }

    // ------------------------------------------------------------------------
    // Edge cases
    // ------------------------------------------------------------------------

    public function test_whitespace_only_is_malformed(): void {
        $parser = new FeedParser('   ');
        $this->assertFalse($parser->init());
        $this->assertNotEmpty($parser->error());
    }

    public function test_title_trimming(): void {
        $xml = '<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <title>  Spaced Title  </title>
    <link>https://example.com</link>
    <item><title>Item</title></item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $parser->init();
        $this->assertEquals("Spaced Title", $parser->get_title());
    }

    public function test_link_trimming(): void {
        $xml = '<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <title>Feed</title>
    <link>  https://example.com/trimmed  </link>
    <item><title>Item</title></item>
  </channel>
</rss>';
        $parser = new FeedParser($xml);
        $parser->init();
        $this->assertEquals("https://example.com/trimmed", $parser->get_link());
    }
}

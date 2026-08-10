<?php
class FeedParser {
	private readonly DOMDocument $doc;

	private ?string $error = null;

	/** @var array<string> */
	private array $libxml_errors = [];

	/** @var array<FeedItem> */
	private array $items = [];

	private ?string $link = null;

	private ?string $title = null;

	/** @var FeedParser::FEED_* */
	private int $type;

	private ?DOMXPath $xpath = null;

	const FEED_UNKNOWN = -1;
	const FEED_RDF = 0;
	const FEED_RSS = 1;
	const FEED_ATOM = 2;

	public function __construct(string $data) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();

		$this->type = $this::FEED_UNKNOWN;
		$this->doc = new DOMDocument();

		if (empty($data)) {
			$this->error = 'Empty feed data provided';
			return;
		}
		$this->doc->loadXML($data);

		mb_substitute_character("none");

		$error = libxml_get_last_error();

		if ($error) {
			foreach (libxml_get_errors() as $error) {
				if ($error->level == LIBXML_ERR_FATAL) {
					// currently only the first error is reported
					$this->error ??= Errors::format_libxml_error($error);
					$this->libxml_errors[] = Errors::format_libxml_error($error);
				}
			}
		}

		libxml_clear_errors();

		if ($this->error)
			return;

		$this->xpath = new DOMXPath($this->doc);
		$this->xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
		$this->xpath->registerNamespace('atom03', 'http://purl.org/atom/ns#');
		$this->xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
		$this->xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		$this->xpath->registerNamespace('slash', 'http://purl.org/rss/1.0/modules/slash/');
		$this->xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
		$this->xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
		$this->xpath->registerNamespace('thread', 'http://purl.org/syndication/thread/1.0');
	}

	/**
	 * @return bool false if initialization couldn't occur (e.g. parsing error or unrecognized feed type), otherwise true
	 */
	public function init(): bool {
		if ($this->error)
			return false;

		$type = $this->get_type();

		if ($type === self::FEED_UNKNOWN)
			return false;

		$xpath = $this->xpath;

		switch ($type) {
			case $this::FEED_ATOM:
				$title = $xpath->query('//atom:feed/atom:title')->item(0)
					?? $xpath->query('//atom03:feed/atom03:title')->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				/** @var DOMElement|null $link */
				$link = $xpath->query('//atom:feed/atom:link[not(@rel)]')->item(0)
					?? $xpath->query("//atom:feed/atom:link[@rel='alternate']")->item(0)
					?? $xpath->query('//atom03:feed/atom03:link[not(@rel)]')->item(0)
					?? $xpath->query("//atom03:feed/atom03:link[@rel='alternate']")->item(0);

				if ($link?->getAttribute('href'))
					$this->link = $link->getAttribute('href');

				$articles = $xpath->query("//atom:entry");

				if (empty($articles) || $articles->length == 0)
					$articles = $xpath->query("//atom03:entry");

				/** @var DOMElement $article */
				foreach ($articles as $article)
					$this->items[] = new FeedItem_Atom($article, $this->doc, $this->xpath);

				break;
			case $this::FEED_RSS:
				$title = $xpath->query("//channel/title")->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				/** @var DOMElement|null $link */
				$link = $xpath->query("//channel/link")->item(0);

				if ($link) {
					if ($link->getAttribute("href"))
						$this->link = $link->getAttribute("href");
					else if ($link->nodeValue)
						$this->link = $link->nodeValue;
				}

				/** @var DOMElement $article */
				foreach ($xpath->query('//channel/item') as $article)
					$this->items[] = new FeedItem_RSS($article, $this->doc, $this->xpath);

				break;
			case $this::FEED_RDF:
				$xpath->registerNamespace('rssfake', 'http://purl.org/rss/1.0/');

				$title = $xpath->query("//rssfake:channel/rssfake:title")->item(0);

				if ($title) {
					$this->title = $title->nodeValue;
				}

				$link = $xpath->query("//rssfake:channel/rssfake:link")->item(0);

				if ($link) {
					$this->link = $link->nodeValue;
				}

				/** @var DOMElement $article */
				foreach ($xpath->query('//rssfake:item') as $article)
					$this->items[] = new FeedItem_RSS($article, $this->doc, $this->xpath);

				break;
		}

		if ($this->title)
			$this->title = trim($this->title);

		if ($this->link)
			$this->link = trim($this->link);

		return true;
	}

	/** @deprecated use Errors::format_libxml_error() instead */
	public function format_error(LibXMLError $error) : string {
		return Errors::format_libxml_error($error);
	}

	// libxml may have invalid unicode data in error messages
	public function error() : string {
		return UConverter::transcode($this->error ?? '', 'UTF-8', 'UTF-8');
	}

	/** @return array<string> - WARNING: may return invalid unicode data */
	public function errors() : array {
		return $this->libxml_errors;
	}

	/**
	 * @return FeedParser::FEED_*
	 */
	public function get_type(): int {
		if ($this->type !== self::FEED_UNKNOWN || $this->error)
			return $this->type;

		$root_list = $this->xpath->query('(//atom03:feed|//atom:feed|//channel|//rdf:rdf|//rdf:RDF)');

		if ($root_list && $root_list->length > 0) {
			/** @var DOMElement $root */
			$root = $root_list->item(0);

			$this->type = match (mb_strtolower($root->tagName)) {
				'rdf:rdf' => self::FEED_RDF,
				'channel' => self::FEED_RSS,
				'feed', 'atom:feed' => self::FEED_ATOM,
				default => self::FEED_UNKNOWN,
			};
		}

		if ($this->type === self::FEED_UNKNOWN)
			$this->error ??= 'Unknown/unsupported feed type';

		return $this->type;
	}

	public function get_link() : string {
		return clean($this->link ?? '');
	}

	public function get_title() : string {
		return clean($this->title ?? '');
	}

	/** @return array<FeedItem> */
	public function get_items() : array {
		return $this->items;
	}

	/** @return array<string> */
	public function get_links(string $rel) : array {
		$rv = [];

		switch ($this->type) {
		case $this::FEED_ATOM:
			$links = $this->xpath->query("//atom:feed/atom:link");

			/** @var DOMElement $link */
			foreach ($links as $link) {
				if (!$rel || $link->hasAttribute('rel') && $link->getAttribute('rel') == $rel)
					$rv[] = clean(trim($link->getAttribute('href')));
			}
			break;
		case $this::FEED_RSS:
			$links = $this->xpath->query("//atom:link");

			/** @var DOMElement $link */
			foreach ($links as $link) {
				if (!$rel || $link->hasAttribute('rel') && $link->getAttribute('rel') == $rel)
					$rv[] = clean(trim($link->getAttribute('href')));
			}
			break;
		}

		return $rv;
	}
}

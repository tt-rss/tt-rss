<?php
class FeedItem_Atom extends FeedItem_Common {
	const NS_XML = "http://www.w3.org/XML/1998/namespace";

	public function get_id(): string {
		$id = $this->elem->getElementsByTagName("id")->item(0);

		if ($id) {
			return $id->nodeValue;
		} else {
			return clean($this->get_link());
		}
	}

	/**
	 * @return int|false a timestamp on success, false otherwise
	 */
	public function get_date(): false|int {
		$updated = $this->elem->getElementsByTagName("updated")->item(0);

		if ($updated) {
			return strtotime($updated->nodeValue ?? '');
		}

		$published = $this->elem->getElementsByTagName("published")->item(0);

		if ($published) {
			return strtotime($published->nodeValue ?? '');
		}

		$date = $this->xpath->query("dc:date", $this->elem)->item(0);

		if ($date) {
			return strtotime($date->nodeValue ?? '');
		}

		// consistent with strtotime failing to parse
		return false;
	}


	public function get_link(): string {
		$links = $this->elem->getElementsByTagName("link");

		foreach ($links as $link) {
			if ($link->hasAttribute("href") &&
				(!$link->hasAttribute("rel")
					|| $link->getAttribute("rel") == "alternate"
					|| $link->getAttribute("rel") == "standout")) {
				$base = $this->xpath->evaluate("string(ancestor-or-self::*[@xml:base][1]/@xml:base)", $link);

				if ($base)
					return UrlHelper::rewrite_relative($base, clean(trim($link->getAttribute("href"))));
				else
					return clean(trim($link->getAttribute("href")));
			}
		}

		return '';
	}

	public function get_title(): string {
		$title = $this->elem->getElementsByTagName("title")->item(0);
		return $title ? clean(trim($title->nodeValue)) : '';
	}

	/**
	 * @param string|null $base optional (returns $content if $base is null)
	 * @param string $content an HTML string
	 *
	 * @return string the rewritten XML or original $content
	 */
	private function rewrite_content_to_base(?string $base = null, ?string $content = '') {

		if (!empty($base) && !empty($content)) {

			$tmpdoc = new DOMDocument();
			if (@$tmpdoc->loadHTML('<?xml encoding="UTF-8">' . $content)) {
				$tmpxpath = new DOMXPath($tmpdoc);

				$elems = $tmpxpath->query("(//*[@href]|//*[@src])");

				/** @var DOMElement $elem */
				foreach ($elems as $elem) {
					if ($elem->hasAttribute("href")) {
						$elem->setAttribute("href",
							UrlHelper::rewrite_relative($base, $elem->getAttribute("href")));
					} else if ($elem->hasAttribute("src")) {
						$elem->setAttribute("src",
							UrlHelper::rewrite_relative($base, $elem->getAttribute("src")));
					}
				}

				// Fall back to $content if saveXML somehow fails (i.e. returns false)
				$modified_content = $tmpdoc->saveXML();
				return $modified_content !== false ? $modified_content : $content;
			}
		}

		return $content;
	}

	public function get_content(): string {
		/** @var DOMElement|null */
		$content = $this->elem->getElementsByTagName("content")->item(0);

		if ($content) {
			$base = $this->xpath->evaluate("string(ancestor-or-self::*[@xml:base][1]/@xml:base)", $content);

			if ($content->hasAttribute('type')) {
				if ($content->getAttribute('type') == 'xhtml') {
					for ($i = 0; $i < $content->childNodes->length; $i++) {
						$child = $content->childNodes->item($i);

						if ($child->hasChildNodes()) {
							return $this->rewrite_content_to_base($base, $this->doc->saveHTML($child));
						}
					}
				}
			}

			return $this->rewrite_content_to_base($base, $this->subtree_or_text($content));
		}

		return '';
	}

	// TODO: duplicate code should be merged with get_content()
	public function get_description(): string {
		/** @var DOMElement|null */
		$content = $this->elem->getElementsByTagName("summary")->item(0);

		if ($content) {
			$base = $this->xpath->evaluate("string(ancestor-or-self::*[@xml:base][1]/@xml:base)", $content);

			if ($content->hasAttribute('type')) {
				if ($content->getAttribute('type') == 'xhtml') {
					for ($i = 0; $i < $content->childNodes->length; $i++) {
						$child = $content->childNodes->item($i);

						if ($child->hasChildNodes()) {
							return $this->rewrite_content_to_base($base, $this->doc->saveHTML($child));
						}
					}
				}
			}

			return $this->rewrite_content_to_base($base, $this->subtree_or_text($content));
		}

		return '';
	}

	/**
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		$categories = $this->elem->getElementsByTagName("category");
		$cats = [];

		foreach ($categories as $cat) {
			if ($cat->hasAttribute('term'))
				$cats[] = $cat->getAttribute('term');
		}

		$categories = $this->xpath->query("dc:subject", $this->elem);

		foreach ($categories as $cat)
			$cats[] = $cat->nodeValue;

		return $this->normalize_categories($cats);
	}

	/**
	 * @return array<int, FeedEnclosure>
	 */
	public function get_enclosures(): array {
		$links = $this->elem->getElementsByTagName("link");

		$encs = [];

		foreach ($links as $link) {
			if ($link->hasAttribute("href") && $link->hasAttribute("rel")) {
				$base = $this->xpath->evaluate("string(ancestor-or-self::*[@xml:base][1]/@xml:base)", $link);

				if ($link->getAttribute("rel") == "enclosure") {
					$enc = new FeedEnclosure();
					$enc->type = clean($link->getAttribute('type'));
					$enc->length = clean($link->getAttribute('length'));
					$enc->link = clean($link->getAttribute('href'));

					if (!empty($base)) {
						$enc->link = UrlHelper::rewrite_relative($base, $enc->link);
					}

					$encs[] = $enc;
				}
			}
		}

		array_push($encs, ...parent::get_enclosures());

		return $encs;
	}

	public function get_language(): string {
		$lang = $this->elem->getAttributeNS(self::NS_XML, "lang");

		if (!empty($lang)) {
			return clean($lang);
		} else {
			// Fall back to the language declared on the feed, if any.
			/** @var DOMElement|DOMNode $child */
			foreach ($this->doc->childNodes as $child) {
				if (method_exists($child, "getAttributeNS")) {
					return clean($child->getAttributeNS(self::NS_XML, "lang"));
				}
			}
		}
		return '';
	}
}

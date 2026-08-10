<?php
class FeedItem_RSS extends FeedItem_Common {
	public function get_id(): string {
		$id = $this->elem->getElementsByTagName("guid")->item(0);

		if ($id) {
			return clean($id->nodeValue);
		} else {
			return clean($this->get_link());
		}
	}

	/**
	 * @return int|false a timestamp on success, false otherwise
	 */
	public function get_date(): false|int {
		$pubDate = $this->elem->getElementsByTagName("pubDate")->item(0);

		if ($pubDate) {
			return strtotime($pubDate->nodeValue ?? '');
		}

		$date = $this->xpath->query("dc:date", $this->elem)->item(0);

		if ($date) {
			return strtotime($date->nodeValue ?? '');
		}

		// consistent with strtotime failing to parse
		return false;
	}

	public function get_link(): string {
		$links = $this->xpath->query("atom:link", $this->elem);

		/** @var DOMElement $link */
		foreach ($links as $link) {
			if ($link->hasAttribute("href") &&
				(!$link->hasAttribute("rel")
					|| $link->getAttribute("rel") == "alternate"
					|| $link->getAttribute("rel") == "standout")) {

				return clean(trim($link->getAttribute("href")));
			}
		}

		/** @var DOMElement|null */
		$link = $this->elem->getElementsByTagName("guid")->item(0);

		if ($link && $link->hasAttributes() && $link->getAttribute("isPermaLink") == "true") {
			return clean(trim($link->nodeValue));
		}

		$link = $this->elem->getElementsByTagName("link")->item(0);

		if ($link) {
			return clean(trim($link->nodeValue));
		}

		return '';
	}

	public function get_title(): string {
		$title = $this->xpath->query("title", $this->elem)->item(0);

		if ($title) {
			return clean(trim($title->nodeValue));
		}

		// if the document has a default namespace then querying for
		// title would fail because of reasons so let's try the old way
		$title = $this->elem->getElementsByTagName("title")->item(0);

		if ($title) {
			return clean(trim($title->nodeValue));
		}

		return '';
	}

	public function get_content(): string {
		/** @var DOMElement|null */
		$contentA = $this->xpath->query("content:encoded", $this->elem)->item(0);

		/** @var DOMElement|null */
		$contentB = $this->elem->getElementsByTagName("description")->item(0);

		if ($contentA && $contentB) {
			$resultA = $this->subtree_or_text($contentA);
			$resultB = $this->subtree_or_text($contentB);

			return mb_strlen($resultA) > mb_strlen($resultB) ? $resultA : $resultB;
		}

		if ($contentA) {
			return $this->subtree_or_text($contentA);
		}

		if ($contentB) {
			return $this->subtree_or_text($contentB);
		}

		return '';
	}

	public function get_description(): string {
		$summary = $this->elem->getElementsByTagName("description")->item(0);

		if ($summary) {
			return $summary->nodeValue;
		}

		return '';
	}

	/**
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		$cats = [];

		foreach ($this->elem->getElementsByTagName('category') as $cat)
			$cats[] = $cat->nodeValue;

		foreach ($this->xpath->query('dc:subject', $this->elem) as $cat)
			$cats[] = $cat->nodeValue;

		return $this->normalize_categories($cats);
	}

	/**
	 * @return array<int, FeedEnclosure>
	 */
	public function get_enclosures(): array {
		$enclosures = $this->elem->getElementsByTagName("enclosure");

		$encs = [];

		foreach ($enclosures as $enclosure) {
			$enc = new FeedEnclosure();
			$enc->type = clean($enclosure->getAttribute('type'));
			$enc->link = clean($enclosure->getAttribute('url'));
			$enc->length = clean($enclosure->getAttribute('length'));
			$enc->height = clean($enclosure->getAttribute('height'));
			$enc->width = clean($enclosure->getAttribute('width'));

			$encs[] = $enc;
		}

		array_push($encs, ...parent::get_enclosures());

		return $encs;
	}

	public function get_language(): string {
		$languages = $this->doc->getElementsByTagName('language');

		if (count($languages) == 0) {
			return "";
		}

		return clean($languages[0]->textContent);
	}
}

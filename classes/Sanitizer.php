<?php
class Sanitizer {
	/**
	 * @param array<int, string> $allowed_elements
	 * @param array<int, string> $disallowed_attributes
	 */
	private static function strip_harmful_tags(DOMDocument $doc, array $allowed_elements, array $disallowed_attributes): DOMDocument {
		$allowed_elements = array_map(strtolower(...), $allowed_elements);
		$disallowed_attributes = array_map(strtolower(...), $disallowed_attributes);

		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('//*');

		foreach ($entries as $entry) {
			/** @var DOMElement $entry */
			$element_lower = strtolower($entry->nodeName);

			if (!in_array($element_lower, $allowed_elements)) {
				$entry->parentNode->removeChild($entry);
				continue;
			}

			if ($entry->hasAttributes()) {
				$attrs_to_remove = [];

				foreach ($entry->attributes as $attr) {
					$attr_lower = strtolower($attr->nodeName);

					if (str_starts_with($attr_lower, 'on')
							|| str_starts_with($attr_lower, 'data-')
							|| ($attr_lower == 'href' && str_starts_with(strtolower(mb_ereg_replace('^\s+', '', $attr->value)), 'javascript:'))
							|| in_array($attr_lower, $disallowed_attributes)) {
						$attrs_to_remove[] = $attr;
					}
				}

				foreach ($attrs_to_remove as $attr)
					$entry->removeAttributeNode($attr);
			}
		}

		return $doc;
	}

	public static function iframe_whitelisted(DOMElement $entry): bool {
		$src = parse_url($entry->getAttribute("src"), PHP_URL_HOST);

		if (!empty($src))
			return PluginHost::getInstance()->run_hooks_until(PluginHost::HOOK_IFRAME_WHITELISTED, true, $src);

		return false;
	}

	private static function is_prefix_https(): bool {
		return parse_url(Config::get_self_url(), PHP_URL_SCHEME) == 'https';
	}

	/** @param array<string> $words */
	public static function highlight_words_str(string $str, array $words) : string {
		$doc = new DOMDocument();

		if ($doc->loadHTML('<?xml encoding="UTF-8"><span>' . $str . '</span>')) {
			$xpath = new DOMXPath($doc);

			if (self::highlight_words($doc, $xpath, $words)) {
				$res = $doc->saveHTML();

				/* strip everything outside of <body>...</body> */
				$res_frag = [];

				if (preg_match('/<body>(.*)<\/body>/is', $res, $res_frag)) {
					return $res_frag[1];
				} else {
					return $res;
				}
			}
		}

		return $str;
	}

	/** @param array<string> $words */
	public static function highlight_words(DOMDocument &$doc, DOMXPath $xpath, array $words) : bool {
		$rv = false;

		foreach ($words as $word) {

			// http://stackoverflow.com/questions/4081372/highlight-keywords-in-a-paragraph
			$elements = $xpath->query("//*/text()");

			foreach ($elements as $child) {

				$fragment = $doc->createDocumentFragment();
				$text = $child->textContent;

				while (($pos = mb_stripos($text, $word)) !== false) {
					$fragment->appendChild(new DOMText(mb_substr($text, 0, (int)$pos)));
					$word = mb_substr($text, (int)$pos, mb_strlen($word));
					$highlight = $doc->createElement('span');
					$highlight->appendChild(new DOMText($word));
					$highlight->setAttribute('class', 'highlight');
					$fragment->appendChild($highlight);
					$text = mb_substr($text, $pos + mb_strlen($word));
				}

				if (!empty($text)) $fragment->appendChild(new DOMText($text));

				$child->parentNode->replaceChild($fragment, $child);

				$rv = true;
			}
		}

		return $rv;
	}

	/**
	 * @param array<int, string>|null $highlight_words Words to highlight in the HTML output.
	 *
	 * @return false|string The HTML, or false if an error occurred.
	 */
	public static function sanitize(string $str, ?bool $force_remove_images = false, ?int $owner = null, ?string $site_url = null, ?array $highlight_words = null, ?int $article_id = null): false|string {
		if (!$owner && isset($_SESSION["uid"]))
			$owner = $_SESSION["uid"];

		$profile = isset($_SESSION['uid']) && $owner == $_SESSION['uid'] && isset($_SESSION['profile']) ? $_SESSION['profile'] : null;

		$res = trim($str);

		if (!$res)
			return '';

		$doc = new DOMDocument();
		$doc->loadHTML('<?xml encoding="UTF-8">' . $res);
		$xpath = new DOMXPath($doc);

		// is it a good idea to possibly rewrite urls to our own prefix?
		// $rewrite_base_url = $site_url ? $site_url : Config::get_self_url();
		$rewrite_base_url = $site_url ?: "http://domain.invalid/";

		$entries = $xpath->query('(//a[@href]|//img[@src|@srcset]|//source[@src|@srcset]|//video[@poster])');

		/** @var DOMElement $entry */
		foreach ($entries as $entry) {

			if ($entry->hasAttribute('href')) {
				$entry->setAttribute('href',
					UrlHelper::rewrite_relative($rewrite_base_url, $entry->getAttribute('href'), $entry->tagName, "href"));

				$entry->setAttribute('rel', 'noopener noreferrer');
				$entry->setAttribute('target', '_blank');
			}

			// used to determine whether the element should be replaced with escaped text
			$should_replace_element = false;
			$src_valid = true;

			if ($entry->hasAttribute('src')) {
				$rewritten_url = UrlHelper::rewrite_relative($rewrite_base_url, $entry->getAttribute('src'), $entry->tagName, 'src');

				if (preg_match('/^data:/i', $rewritten_url)) {
					$entry->setAttribute('src', $rewritten_url);
				} else {
					if ($rewritten_url && !UrlHelper::has_disallowed_ip($rewritten_url)) {
						$entry->setAttribute('src', $rewritten_url);
					} else {
						$should_replace_element = true;
						$src_valid = false;
					}
				}
			}

			if ($entry->hasAttribute('srcset')) {
				$matches = RSSUtils::decode_srcset($entry->getAttribute('srcset'));
				$validated_srcset = [];

				for ($i = 0; $i < count($matches); $i++) {
					$rewritten_url = UrlHelper::rewrite_relative($rewrite_base_url, $matches[$i]['url']);

					// only keep srcset items that are valid
					if ($rewritten_url && !UrlHelper::has_disallowed_ip($rewritten_url)) {
						$matches[$i]['url'] = $rewritten_url;
						$validated_srcset[] = $matches[$i];
					}
				}

				if (count($validated_srcset) > 0) {
					$entry->setAttribute('srcset', RSSUtils::encode_srcset($validated_srcset));
					$should_replace_element = false;
				} else {
					$entry->removeAttribute('srcset');
				}
			}

			// replace with escaped text if 'src' and 'srcset' are invalid
			if ($should_replace_element) {
				$element_html = $doc->saveHTML($entry);
				$text_node = new DOMText($element_html);
				$entry->parentNode->replaceChild($text_node, $entry);
				continue;
			}

			// drop 'src' if invalid and the element wasn't replaced (i.e. 'srcset' was acceptable)
			if (!$src_valid && $entry->hasAttribute('src'))
				$entry->removeAttribute('src');

			if ($entry->nodeName == 'img') {
				$entry->setAttribute('referrerpolicy', 'no-referrer');
				$entry->setAttribute('loading', 'lazy');
			}

			if ($entry->hasAttribute('poster')) {
				$rewritten_url = UrlHelper::rewrite_relative($rewrite_base_url, $entry->getAttribute('poster'), $entry->tagName, 'poster');

				if ($rewritten_url && !UrlHelper::has_disallowed_ip($rewritten_url))
					$entry->setAttribute('poster', $rewritten_url);
				else
					$entry->removeAttribute('poster');
			}

			if ($entry->hasAttribute('src') &&
					($owner && Prefs::get(Prefs::STRIP_IMAGES, $owner, $profile)) || $force_remove_images || ($_SESSION['bw_limit'] ?? false)) {
				$p = $doc->createElement('p');

				$a = $doc->createElement('a');
				$a->setAttribute('href', $entry->getAttribute('src'));

				$a->appendChild(new DOMText($entry->getAttribute('src')));
				$a->setAttribute('target', '_blank');
				$a->setAttribute('rel', 'noopener noreferrer');

				$p->appendChild($a);

				if ($entry->nodeName == 'source') {

					if ($entry->parentNode && $entry->parentNode->parentNode)
						$entry->parentNode->parentNode->replaceChild($p, $entry->parentNode);

				} else if ($entry->nodeName == 'img') {
					if ($entry->parentNode)
						$entry->parentNode->replaceChild($p, $entry);
				}
			}
		}

		$entries = $xpath->query('//iframe');

		/** @var DOMElement $entry */
		foreach ($entries as $entry) {
			if (self::iframe_whitelisted($entry)) {
				// The only known use of HOOK_IFRAME_WHITELISTED is Af_Youtube_Embed, so this "expanded access" is modeled
				// off of what the Internet generally seems to recommend for YouTube.
				$entry->setAttribute('allow',
					'accelerometer; autoplay; clipboard-write; encrypted-media; fullscreen *; gyroscope; picture-in-picture; web-share');
				$entry->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox');

				if (self::is_prefix_https() && preg_match('%^http://(.+)%i', ltrim($entry->getAttribute('src')), $matches))
					$entry->setAttribute('src', 'https://' . $matches[1]);
			} else {
				// Set a more restrictive sandbox for everything else.
				$entry->setAttribute('sandbox', 'allow-scripts');
			}
		}

		$allowed_elements = ['a', 'abbr', 'address', 'acronym', 'audio', 'article', 'aside',
			'b', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br',
			'caption', 'cite', 'center', 'code', 'col', 'colgroup',
			'data', 'dd', 'del', 'details', 'description', 'dfn', 'div', 'dl', 'font',
			'dt', 'em', 'footer', 'figure', 'figcaption',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'html', 'i',
			'img', 'ins', 'kbd', 'li', 'main', 'mark', 'nav', 'noscript',
			'ol', 'p', 'picture', 'pre', 'q', 'ruby', 'rp', 'rt', 's', 'samp', 'section',
			'small', 'source', 'span', 'strike', 'strong', 'sub', 'summary',
			'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time',
			'tr', 'track', 'tt', 'u', 'ul', 'var', 'wbr', 'video', 'xml:namespace' ];

		if ($_SESSION['hasSandbox'] ?? false) $allowed_elements[] = 'iframe';

		$disallowed_attributes = ['id', 'style', 'class', 'width', 'height', 'allow'];

		PluginHost::getInstance()->chain_hooks_callback(PluginHost::HOOK_SANITIZE,
			function ($result) use (&$doc, &$allowed_elements, &$disallowed_attributes) {
				if (is_array($result)) {
					$doc = $result[0];
					$allowed_elements = $result[1];
					$disallowed_attributes = $result[2];
				} else {
					$doc = $result;
				}
			},
			$doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id);

		$doc->removeChild($doc->firstChild); //remove doctype
		$doc = self::strip_harmful_tags($doc, $allowed_elements, $disallowed_attributes);

		$entries = $xpath->query('//iframe');
		foreach ($entries as $entry) {
			$div = $doc->createElement('div');
			$div->setAttribute('class', 'embed-responsive');
			$entry->parentNode->replaceChild($div, $entry);
			$div->appendChild($entry);
		}

		if (is_array($highlight_words))
			self::highlight_words($doc, $xpath, $highlight_words);

		$res = $doc->saveHTML();

		/* strip everything outside of <body>...</body> */

		$res_frag = [];
		if (preg_match('/<body>(.*)<\/body>/is', $res, $res_frag)) {
			return $res_frag[1];
		} else {
			return $res;
		}
	}

}

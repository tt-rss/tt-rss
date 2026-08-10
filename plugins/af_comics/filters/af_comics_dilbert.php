<?php

class Af_Comics_Dilbert extends Af_ComicFilter {

	public function supported() {
		return ["Dilbert"];
	}

	public function process(&$article) {
		if (str_contains($article["link"], "dilbert.com") ||
			str_contains($article["link"], "/DilbertDailyStrip")) {
				$res = UrlHelper::fetch([
					'url' => $article['link'],
					'useragent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0',
				]);

				if (!$res && UrlHelper::$fetch_last_error_content)
					$res = UrlHelper::$fetch_last_error_content;

				$doc = new DOMDocument();

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);

					/** @var DOMElement|null $basenode (image container) */
					$basenode = $xpath->query('(//div[@class="img-comic-container"]/a[@class="img-comic-link"])')->item(0);

					// Get the comic title
					$comic_title = $xpath->query('(//span[@class="comic-title-name"])')->item(0)->textContent;

					// Get tags from the article
					$matches = $xpath->query('(//p[contains(@class, "comic-tags")][1]//a)');
					$tags = [];

					foreach ($matches as $tag) {
						// Only strings starting with a number sign are considered tags
						if (str_starts_with($tag->textContent, '#')) {
							$tags[] = mb_strtolower(substr($tag->textContent, 1), 'utf-8');
						}
					}

					// Get the current comics transcript and set it
					// as the title so it will be visible on mousover
					$transcript = $xpath->query('(//div[starts-with(@id, "js-toggle-transcript-")]//p)')->item(0);
					if ($transcript) {
						$basenode->setAttribute("title", $transcript->textContent);
					}

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}

					// Add comic title to article type if not empty (mostly Sunday strips)
					if ($comic_title) {
						$article["title"] = $article["title"] . " - " . $comic_title;
					}

					if (!empty($tags)) {
						// Ignore existing tags and just replace them all
						$article["tags"] = array_unique($tags);
					}

				}

			return true;
		}

		return false;
	}
}
?>

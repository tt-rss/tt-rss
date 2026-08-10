<?php
class Af_Comics_Tfd extends Af_ComicFilter {

	public function supported() {
		return ["Toothpaste For Dinner", "Married to the Sea"];
	}

	public function process(&$article) {
		if (str_contains($article["link"], "toothpastefordinner.com") ||
			str_contains($article["link"], "marriedtothesea.com")) {
			$res = UrlHelper::fetch([
				'url' => $article['link'],
				'useragent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
			]);

			if (!$res) return false;

			$doc = new DOMDocument();

			$res = UrlHelper::fetch(['url' => $article["link"]]);

			if ($res && $doc->loadHTML($res)) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//img[contains(@src, ".gif")]')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveHTML($basenode);
					return true;
				}
			}
		}

		return false;
	}
}

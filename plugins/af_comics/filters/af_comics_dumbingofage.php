<?php
class Af_Comics_DumbingOfAge extends Af_ComicFilter {

	public function supported() {
		return ["Dumbing of Age"];
	}

	public function process(&$article) {
		if (str_contains($article["link"], "dumbingofage.com")) {
				$res = UrlHelper::fetch([
					'url' => $article['link'],
					'useragent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0',
				]);

				if (!$res && UrlHelper::$fetch_last_error_content)
					$res = UrlHelper::$fetch_last_error_content;

				$doc = new DOMDocument();

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);
					$comic = $xpath->query('//div[@id="comic-1"]')->item(0);
					if ($comic) {
						$article["content"] = $doc->saveHTML($comic) . $article["content"];
					}
				}

			return true;
		}

		return false;
	}
}

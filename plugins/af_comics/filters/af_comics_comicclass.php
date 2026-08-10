<?php
class Af_Comics_ComicClass extends Af_ComicFilter {

	public function supported() {
		return ["Loading Artist"];
	}

	public function process(&$article) {
		if (str_contains($article["guid"], "loadingartist.com")) {

				// lol at people who block clients by user agent
				// oh noes my ad revenue Q_Q

				$res = UrlHelper::fetch([
					'url' => $article['link'],
					'useragent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
				]);

				$doc = new DOMDocument();

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('//div[@class="comic"]')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			 return true;
		}

		return false;
	}
}

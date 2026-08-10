<?php
class Af_Comics_Whomp extends Af_ComicFilter {

	public function supported() {
		return ["Whomp!"];
	}

	public function process(&$article) {
		if (str_contains($article["guid"], "whompcomic.com")) {
			$res = UrlHelper::fetch([
				'url' => $article['link'],
				'useragent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
			]);

			if (!$res && UrlHelper::$fetch_last_error_content)
				$res = UrlHelper::$fetch_last_error_content;

			$doc = new DOMDocument();

			if ($res && $doc->loadHTML($res)) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//img[@id="cc-comic"]')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveHTML($basenode);
				}
			}

			return true;
		}

		return false;
	}
}

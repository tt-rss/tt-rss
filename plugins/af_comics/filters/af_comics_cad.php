<?php
class Af_Comics_Cad extends Af_ComicFilter {

	public function supported() {
		return ["Ctrl+Alt+Del"];
	}

	public function process(&$article) {
		if (str_contains($article["link"], "cad-comic.com")) {
			if (!str_contains($article["title"], "News:")) {
				$doc = new DOMDocument();

				$res = UrlHelper::fetch([
					'url' => $article['link'],
					'useragent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0',
				]);

				if (!$res && UrlHelper::$fetch_last_error_content)
					$res = UrlHelper::$fetch_last_error_content;

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);
					$basenode = $xpath->query('//div[@class="comicpage"]/a/img')->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			}

			return true;
		}

		return false;
	}
}

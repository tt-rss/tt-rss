<?php
class Af_Comics_DanbyDraws extends Af_ComicFilter {

	public function supported() {
		return ["Danby Draws"];
	}

	public function process(&$article) {
		if (str_contains($article["link"], "danbydraws.com")) {
			$res = UrlHelper::fetch(["url" => $article["link"]]);

			$doc = new DOMDocument();

			if ($res && $doc->loadHTML($res)) {
				$xpath = new DOMXPath($doc);
				$basenode = $xpath->query('//div[@id="comic"]|//img[contains(@class, "wp-post-image")]/..')->item(0);

				if ($basenode) {
					$article["content"] = $doc->saveHTML($basenode);
					return true;
				}
			}
		}

		return false;
	}
}

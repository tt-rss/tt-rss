<?php
class Af_Comics_Twp extends Af_ComicFilter {

	public function supported() {
		return ["Three Word Phrase"];
	}

	public function process(&$article) {

		if (str_contains($article["link"], "threewordphrase.com")) {

				$doc = new DOMDocument();

				$res = UrlHelper::fetch(['url' => $article['link']]);

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXpath($doc);

					$basenode = $xpath->query("//td/center/img")->item(0);

					if ($basenode) {
						$article["content"] = $doc->saveHTML($basenode);
					}
				}

			return true;
		}

		return false;
	}
}

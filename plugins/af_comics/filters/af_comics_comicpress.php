<?php
class Af_Comics_ComicPress extends Af_ComicFilter {

	public function supported() {
		return ["Buni", "Buttersafe", "Happy Jar", "CSection",
			"Extra Fabulous Comics", "Nedroid", "Stonetoss",
			"Powerup Comics"];
	}

	public function process(&$article) {
		if (str_contains($article["guid"], "bunicomic.com") ||
				str_contains($article["guid"], "buttersafe.com") ||
				str_contains($article["guid"], "extrafabulouscomics.com") ||
				str_contains($article["guid"], "theduckwebcomics.com/Powerup_Comics") ||
				str_contains($article["guid"], "happyjar.com") ||
				str_contains($article["guid"], "nedroid.com") ||
				str_contains($article["guid"], "stonetoss.com") ||
				str_contains($article["guid"], "csectioncomics.com")) {

				$res = UrlHelper::fetch(["url" => $article["link"]]);

				$doc = new DOMDocument();

				if ($res && $doc->loadHTML($res)) {
					$xpath = new DOMXPath($doc);
					$img = $xpath->query('//div[@id="comic"]')->item(0);
					$text = $xpath->query('//div[@class="entry" or @class="entry-content"]')->item(0);

					if ($img || $text) {
						$article["content"] = '';

						if ($img) {
							$this->cleanup($xpath, $img);
							$article["content"] .= $doc->saveHTML($img);
						}

						if ($text) {
							$this->cleanup($xpath, $text);
							$article["content"] .= $doc->saveHTML($text);
						}

						return true;
					}
				}
		}

		return false;
	}

	private function cleanup(DOMXPath $xpath, DOMNode $content_node): void {
		$toUpdates = $xpath->query('//img[@data-src]', $content_node);
		$this->move_all_attributes($toUpdates, 'data-src', 'src');

		$toUpdates = $xpath->query('//img[@data-srcset]', $content_node);
		$this->move_all_attributes($toUpdates, 'data-srcset', 'srcset');

		$toUpdates = $xpath->query('//img[@data-sizes]', $content_node);
		$this->move_all_attributes($toUpdates, 'data-sizes', 'sizes');

		$toRemoves = $xpath->query('.//*[contains(@class, "sharedaddy") or contains(@class, "relatedposts") or contains(@class, "donation_table") or contains(@class, "above-comic") or contains(@class, "oli_")]', $content_node);
		foreach ($toRemoves as $toRemove) {
			$toRemove->parentNode->removeChild($toRemove);
		}
	}

	/**
	 * @param DOMNodeList<DOMElement> $toUpdates
	 */
	private function move_all_attributes(DOMNodeList $toUpdates, string $srcName, string $dstName): void {
		foreach ($toUpdates as $toUpdate) {
			$attributeValue = $toUpdate->getAttribute($srcName);
			$toUpdate->setAttribute($dstName, $attributeValue);
			$toUpdate->removeAttribute($srcName);
		}
	}
}

<?php
class No_Iframes extends Plugin {

	public function about() {
		return [null,
			"Remove embedded iframes (unless whitelisted)",
			"fox"];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_SANITIZE, $this);
	}

	public function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {

		$xpath = new DOMXpath($doc);
		$entries = $xpath->query('//iframe');

		/** @var DOMElement $entry */
		foreach ($entries as $entry) {
			if (!Sanitizer::iframe_whitelisted($entry))
				$entry->parentNode->removeChild($entry);
		}

		return [$doc, $allowed_elements, $disallowed_attributes];
	}

	public function api_version() {
		return 2;
	}

}

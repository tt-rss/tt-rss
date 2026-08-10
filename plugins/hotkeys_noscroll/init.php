<?php
class Hotkeys_Noscroll extends Plugin {
	public function about() {
		return [null,
			"n/p (and up/down) hotkeys move between articles without scrolling",
			"fox"];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	public function hook_hotkey_map($hotkeys) {

		$hotkeys["(40)|Down"] = "next_article_noscroll";
		$hotkeys["(38)|Up"] = "prev_article_noscroll";
		$hotkeys["n"] = "next_article_noscroll";
		$hotkeys["p"] = "prev_article_noscroll";

		return $hotkeys;
	}

	public function api_version() {
		return 2;
	}

}

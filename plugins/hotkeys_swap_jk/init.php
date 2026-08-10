<?php
class Hotkeys_Swap_JK extends Plugin {

	public function about() {
		return [null,
			"Swap j and k hotkeys (for vi brethren)",
			"fox"];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	public function hook_hotkey_map($hotkeys) {

		$hotkeys["j"] = "next_feed";
		$hotkeys["J"] = "next_unread_feed";
		$hotkeys["k"] = "prev_feed";
		$hotkeys["K"] = "prev_unread_feed";

		return $hotkeys;
	}

	public function api_version() {
		return 2;
	}

}

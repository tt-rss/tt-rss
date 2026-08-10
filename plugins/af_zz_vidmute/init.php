<?php
class Af_Zz_VidMute extends Plugin {

	public function about() {
		return [null,
			"Mute audio in HTML5 videos",
			"fox"];
	}

	public function init($host) {

	}

	public function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	public function api_version() {
		return 2;
	}

}

<?php
class Shorten_Expanded extends Plugin {

	public function about() {
		return [null,
			"Shorten overly long articles in CDM/expanded",
			"fox"];
	}

	public function init($host) {

	}

	public function get_css() {
		return file_get_contents(__DIR__ . "/init.css");
	}

	public function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	public function api_version() {
		return 2;
	}

}

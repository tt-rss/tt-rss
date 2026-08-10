<?php
class Hotkeys_Force_Top extends Plugin {
        public function about() {
                return [null,
                        "Force open article to the top",
                        "itsamenathan"];
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

<?php
require_once __DIR__ . '/../lib/MiniTemplator.class.php';

class Templator extends MiniTemplator {

	/* this reads tt-rss template from templates.local/ or templates/ if only base filename is given */
	function readTemplateFromFile ($fileName) {
		if (!str_contains($fileName, "/")) {

			$templateRootDir = Config::get(Config::LOCAL_TEMPLATES_DIR);
			$fileName = basename($fileName);

			if (file_exists("$templateRootDir/$fileName"))
				return parent::readTemplateFromFile("$templateRootDir/$fileName");
			else
				return parent::readTemplateFromFile("templates/$fileName");

		} else {
			return parent::readTemplateFromFile($fileName);
		}
	}
}

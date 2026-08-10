<?php
class Af_Comics_Template extends Af_ComicFilter {

	public function supported() {
		return ["Example"];
	}

	public function process(&$article) {
		//$owner_uid = $article["owner_uid"];

		return false;
	}
}
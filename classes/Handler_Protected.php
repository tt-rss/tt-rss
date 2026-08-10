<?php
class Handler_Protected extends Handler {

	public function before(string $method): bool {
		return parent::before($method) && !empty($_SESSION['uid']);
	}
}

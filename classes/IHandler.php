<?php
interface IHandler {
	public function csrf_ignore(string $method): bool;
	public function before(string $method): bool;
	public function after(): bool;
}

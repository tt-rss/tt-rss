<?php
class Handler implements IHandler {
	protected PDO $pdo;

	/** @var array<int|string, mixed> */
	protected array $args;

	/**
	 * @param array<int|string, mixed> $args
	 */
	public function __construct(array $args) {
		$this->pdo = Db::pdo();
		$this->args = $args;
	}

	public function csrf_ignore(string $method): bool {
		return false;
	}

	public function before(string $method): bool {
		return true;
	}

	public function after(): bool {
		return true;
	}

	public static function _param_to_bool(mixed $p): bool {
		$p = clean($p);
		return $p && ($p !== "f" && $p !== "false");
	}

	/**
	 * Attempt to convert input to an integer array
	 * @param array<int, string>|string $p An array of integer strings, or a string containing a comma-delimited list of integers
	 * @return array<int, int>|null An array of integers, or null if the input was invalid
	 */
	public static function _param_to_int_array(array|string $p): ?array {
		if (is_string($p)) {
			$p = trim($p);

			if ($p === '')
				return null;

			$p = explode(',', $p);
		}

		$items = array_filter(
			array_map(trim(...), $p),
			fn(string $i): bool => $i !== '');

		if (empty($items))
			return null;

		$filtered_items = array_filter(
			$items,
			fn(string $i): bool => filter_var($i, FILTER_VALIDATE_INT) !== false);

		// if a non-int was found reject the entire input
		if (count($filtered_items) !== count($items))
				return null;

		return array_map(intval(...), $filtered_items);
	}
}

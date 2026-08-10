<?php
class Logger {
	private static ?Logger $instance = null;

	private ?Logger_Adapter $adapter = null;

	const LOG_DEST_SQL = "sql";
	const LOG_DEST_STDOUT = "stdout";
	const LOG_DEST_SYSLOG = "syslog";

	const ERROR_NAMES = [
		1			=> 'E_ERROR',
		2			=> 'E_WARNING',
		4			=> 'E_PARSE',
		8			=> 'E_NOTICE',
		16			=> 'E_CORE_ERROR',
		32			=> 'E_CORE_WARNING',
		64			=> 'E_COMPILE_ERROR',
		128		=> 'E_COMPILE_WARNING',
		256		=> 'E_USER_ERROR',
		512		=> 'E_USER_WARNING',
		1024		=> 'E_USER_NOTICE',
		2048		=> 'E_STRICT',
		4096		=> 'E_RECOVERABLE_ERROR',
		8192		=> 'E_DEPRECATED',
		16384		=> 'E_USER_DEPRECATED',
		32767		=> 'E_ALL'];

	public static function log_error(int $errno, string $errstr, string $file, int $line, string $context): bool {
		return self::get_instance()->_log_error($errno, $errstr, $file, $line, $context);
	}

	private function _log_error(int $errno, string $errstr, string $file, int $line, string $context): bool {
		//if ($errno == E_NOTICE) return false;

		if ($this->adapter)
			return $this->adapter->log_error($errno, $errstr, $file, $line, $context);
		else
			return false;
	}

	public static function log(int $errno, string $errstr, string $context = ""): bool {
		return self::get_instance()->_log($errno, $errstr, $context);
	}

	private function _log(int $errno, string $errstr, string $context = ""): bool {
		if ($this->adapter)
			return $this->adapter->log_error($errno, $errstr, '', 0, $context);
		else
			return user_error($errstr, $errno);
	}

	private function __clone() {
		//
	}

	public function __construct() {
		$this->adapter = match (Config::get(Config::LOG_DESTINATION)) {
			self::LOG_DEST_SQL => new Logger_SQL(),
			self::LOG_DEST_SYSLOG => new Logger_Syslog(),
			self::LOG_DEST_STDOUT => new Logger_Stdout(),
			default => null,
		};

		if ($this->adapter && !implements_interface($this->adapter, "Logger_Adapter"))
			user_error("Adapter for LOG_DESTINATION: " . Config::LOG_DESTINATION . " does not implement required interface.", E_USER_ERROR);
	}

	private static function get_instance() : Logger {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	public static function get() : Logger {
		user_error("Please don't use Logger::get(), call Logger::log(...) instead.", E_USER_DEPRECATED);
		return self::get_instance();
	}
}

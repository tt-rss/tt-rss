<?php
class Logger_Syslog implements Logger_Adapter {

	public function log_error(int $errno, string $errstr, string $file, int $line, string $context): bool {

		$priority = match ($errno) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => LOG_ERR,
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => LOG_WARNING,
            default => LOG_INFO,
        };

		$errname = Logger::ERROR_NAMES[$errno] . " ($errno)";

		syslog($priority, "[tt-rss] $errname ($file:$line) $errstr");

		return true;
	}

}

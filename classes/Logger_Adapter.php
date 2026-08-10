<?php
interface Logger_Adapter {
   public function log_error(int $errno, string $errstr, string $file, int $line, string $context): bool;
}

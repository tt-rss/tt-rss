<?php
class Errors {
	const E_SUCCESS = "E_SUCCESS";
	const E_UNAUTHORIZED = "E_UNAUTHORIZED";
	const E_UNKNOWN_METHOD = "E_UNKNOWN_METHOD";
	const E_UNKNOWN_PLUGIN = "E_UNKNOWN_PLUGIN";
	const E_SCHEMA_MISMATCH = "E_SCHEMA_MISMATCH";
	const E_URL_SCHEME_MISMATCH = "E_URL_SCHEME_MISMATCH";

	/**
	 * @param Errors::E_* $code
	 * @param array<string, string> $params
	 */
	public static function to_json(string $code, array $params = []): string {
		return json_encode(["error" => ["code" => $code, "params" => $params]]);
	}

	public static function libxml_last_error() : string {
		$error = libxml_get_last_error();
		$error_formatted = "";

		if ($error) {
			foreach (libxml_get_errors() as $error) {
				if ($error->level == LIBXML_ERR_FATAL) {
					// currently only the first error is reported
					$error_formatted = self::format_libxml_error($error);
					break;
				}
			}
		}

		return UConverter::transcode($error_formatted, 'UTF-8', 'UTF-8');
	}

	public static function format_libxml_error(LibXMLError $error) : string {
		return sprintf("LibXML error %s at line %d (column %d): %s",
			$error->code, $error->line, $error->column,
			$error->message);
	}
}

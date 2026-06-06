<?php

/**
 * @param array<string, mixed> $attributes
 */
function stylesheet_tag(string $filename, array $attributes = []): string {

	$attributes_str = \Controls\attributes_to_string([
		'href' => $filename
			. (preg_match('/\?\d+$/', $filename) || !is_file($filename) ? '' : '?' . filemtime($filename)),
		'rel' => 'stylesheet',
		'type' => 'text/css',
		'data-orig-href' => $filename,
		...$attributes,
	]);

	return "<link $attributes_str/>\n";
}

/**
 * @param array<string, mixed> $attributes
 */
function javascript_tag(string $filename, array $attributes = []): string {
	$attributes_str = \Controls\attributes_to_string([
		'src' => "$filename?" . filemtime($filename),
		'type' => 'text/javascript',
		'charset' => 'utf-8',
		...$attributes,
	]);

	return "<script $attributes_str></script>\n";
}

function format_warning(string $msg, string $id = ""): string {
	return "<div class=\"alert\" id=\"$id\">$msg</div>";
}

function format_notice(string $msg, string $id = ""): string {
	return "<div class=\"alert alert-info\" id=\"$id\">$msg</div>";
}

function format_error(string $msg, string $id = ""): string {
	return "<div class=\"alert alert-danger\" id=\"$id\">$msg</div>";
}

function print_notice(string $msg): void {
	print format_notice($msg);
}

function print_warning(string $msg): void {
	print format_warning($msg);
}

function print_error(string $msg): void {
	print format_error($msg);
}

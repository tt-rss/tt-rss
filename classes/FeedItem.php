<?php
abstract class FeedItem {
	abstract public function get_id(): string;

	/** @return int|false a timestamp on success, false otherwise */
	abstract public function get_date(): false|int;

	abstract public function get_link(): string;
	abstract public function get_title(): string;
	abstract public function get_description(): string;
	abstract public function get_content(): string;
	abstract public function get_comments_url(): string;
	abstract public function get_comments_count(): int;

	/** @return array<int, string> */
	abstract public function get_categories(): array;

	/** @return array<int, FeedEnclosure> */
	abstract public function get_enclosures(): array;

	abstract public function get_author(): string;
	abstract public function get_language(): string;
}


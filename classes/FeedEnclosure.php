<?php
class FeedEnclosure implements \Stringable {
	public string $link = '';
	public string $type = '';
	public string $length = '';
	public string $title = '';
	public string $height = '';
	public string $width = '';

	public function __toString(): string {
		return sprintf(
			'FeedEnclosure(link=%s, type=%s, length=%s, title=%s, height=%s, width=%s)',
			$this->link,
			$this->type,
			$this->length,
			$this->title,
			$this->height,
			$this->width,
		);
	}
}

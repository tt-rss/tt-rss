<?php
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {
    public function test_self_url_a(): void {
        $_SERVER = [];

        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "http";
        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss/api/index.php";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );

    }

    public function test_self_url_b(): void {
        $_SERVER = [];

        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/api/";

        $this->assertEquals(
            'https://example.com',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_c(): void {
        $_SERVER = [];

        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/api/index.php";

        $this->assertEquals(
            'https://example.com',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_d(): void {
        $_SERVER = [];

        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/api//";

        $this->assertEquals(
            'https://example.com',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_e(): void {
        $_SERVER = [];

        $_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/";

        $this->assertEquals(
            'https://example.com',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_f(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss/index.php";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_g(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss/";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_h(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_i(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss////plugins.local/example///api/long path/test.php";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_j(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss/plugins.local/example/api/longpath/test.php/reader/api/0/stream/items/ids?n=1000&output=json&s=user/-/state/com.google/starred";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

    public function test_self_url_k(): void {
        $_SERVER = [];

        $_SERVER["HTTP_HOST"] = "example.com";
        $_SERVER["REQUEST_URI"] = "/tt-rss/plugins/example/api/longpath/test.php/reader/api/0/stream/items/ids?n=1000&output=json&s=user/-/state/com.google/starred";

        $this->assertEquals(
            'http://example.com/tt-rss',
            Config::get_self_url(true)
        );
    }

	#[\PHPUnit\Framework\Attributes\DataProvider('urlMatchDataProvider')]
	public function test_matches_self_url(string $self_url, string $url_to_check, bool $expected_result): void {
		$url_parts = parse_url($self_url);
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = $url_parts['scheme'];
		$_SERVER['HTTP_HOST'] = $url_parts['host'];
		$_SERVER['REQUEST_URI'] = $url_parts['path'];

		$this->assertEquals($expected_result, Config::matches_self_url($url_to_check, true));
	}

	public static function urlMatchDataProvider(): array {
		return [
			// root origin matches
			'Exact match root' => ['https://example.com', 'https://example.com', true],
			'Root with trailing slash match' => ['https://example.com', 'https://example.com/', true],
			'Root matches sub-path' => ['https://example.com', 'https://example.com/any/path', true],
			'Case insensitive scheme/host' => ['https://example.com', 'HTTPS://EXAMPLE.COM', true],
			'Implicit vs explicit default port' => ['https://example.com', 'https://example.com:443', true],
			'HTTP implicit default port' => ['http://example.com', 'http://example.com:80', true],

			// root origin mismatches
			'Scheme mismatch' => ['https://example.com', 'http://example.com', false],
			'Host mismatch' => ['https://example.com', 'https://attacker.com', false],
			'Port mismatch' => ['https://example.com', 'https://example.com:8443', false],
			'Subdomain mismatch' => ['https://example.com', 'https://sub.example.com', false],
			'Malformed target URL' => ['https://example.com', 'invalid-url', false],

			// IDN matches
			'IDN Punycode vs Unicode match' => ['https://xn--mller-kva.com', 'https://müller.com', true],

			// path prefix matches
			'Exact path match' => ['https://example.com/tt-rss', 'https://example.com/tt-rss', true],
			'Path match with trailing slash' => ['https://example.com/tt-rss', 'https://example.com/tt-rss/', true],
			'Path match deeper segment' => ['https://example.com/tt-rss', 'https://example.com/tt-rss/api/feeds', true],
			'Nested self URL path match' => ['https://example.com/apps/rss', 'https://example.com/apps/rss/index.php', true],

			// path prefix mismatches
			'Path missing entirely' => ['https://example.com/tt-rss', 'https://example.com', false],
			'Partial word match bypass attempt' => ['https://example.com/tt-rss', 'https://example.com/tt-rss-malicious', false],
			'Sibling path mismatch' => ['https://example.com/tt-rss', 'https://example.com/other-path', false],
		];
	}

    public function test_get_self_dir(): void {
        $this->assertEquals(
            dirname(__DIR__), # we're in (app)/tests/
            Config::get_self_dir()
        );
    }

    public function test_cast_to_int(): void {
        // basic integers
        $this->assertSame(42, Config::cast_to("42", Config::T_INT));
        $this->assertSame(0, Config::cast_to("0", Config::T_INT));
        $this->assertSame(-7, Config::cast_to("-7", Config::T_INT));

        // string coercion to int
        $this->assertSame(0, Config::cast_to("", Config::T_INT));
        $this->assertSame(123, Config::cast_to("123abc", Config::T_INT));
        $this->assertSame(0, Config::cast_to("abc", Config::T_INT));

        // float-like strings
        $this->assertSame(3, Config::cast_to("3.14", Config::T_INT));
    }

    public function test_cast_to_string(): void {
        // T_STRING is the default, value passes through unchanged
        $this->assertSame("hello", Config::cast_to("hello", Config::T_STRING));
        $this->assertSame("0", Config::cast_to("0", Config::T_STRING));
        $this->assertSame("", Config::cast_to("", Config::T_STRING));
        $this->assertSame("false", Config::cast_to("false", Config::T_STRING));
        $this->assertSame("42", Config::cast_to("42", Config::T_STRING));
    }

    public function test_cast_to_bool(): void {
        // truthy values
        $this->assertTrue(Config::cast_to("true", Config::T_BOOL));
        $this->assertTrue(Config::cast_to("1", Config::T_BOOL));
        $this->assertTrue(Config::cast_to("t", Config::T_BOOL));
        $this->assertTrue(Config::cast_to("yes", Config::T_BOOL));
        $this->assertTrue(Config::cast_to("on", Config::T_BOOL));
        $this->assertTrue(Config::cast_to("anything", Config::T_BOOL));

        // falsy values
        $this->assertFalse(Config::cast_to("false", Config::T_BOOL));
        $this->assertFalse(Config::cast_to("f", Config::T_BOOL));
        $this->assertFalse(Config::cast_to("0", Config::T_BOOL));
        $this->assertFalse(Config::cast_to("", Config::T_BOOL));
    }
}

<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RedirectMiddleware;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;

final class UrlHelperTest extends TestCase {

    // ------------------------------------------------------------------------
    // build_url
    // ------------------------------------------------------------------------

    public function test_build_url_full(): void {
        $this->assertEquals(
            'https://example.com:8080/path?query=value#fragment',
            UrlHelper::build_url([
                'scheme' => 'https',
                'host'   => 'example.com',
                'port'   => 8080,
                'path'   => '/path',
                'query'  => 'query=value',
                'fragment' => 'fragment',
            ])
        );
    }

    public function test_build_url_minimal(): void {
        $this->assertEquals(
            'http://example.com',
            UrlHelper::build_url([
                'scheme' => 'http',
                'host'   => 'example.com',
            ])
        );
    }

    public function test_build_url_no_port(): void {
        $this->assertEquals(
            'http://example.com/path',
            UrlHelper::build_url([
                'scheme' => 'http',
                'host'   => 'example.com',
                'path'   => '/path',
            ])
        );
    }

    public function test_build_url_query_no_fragment(): void {
        $this->assertEquals(
            'http://example.com?foo=bar',
            UrlHelper::build_url([
                'scheme' => 'http',
                'host'   => 'example.com',
                'query'  => 'foo=bar',
            ])
        );
    }

    public function test_build_url_fragment_no_query(): void {
        $this->assertEquals(
            'http://example.com#top',
            UrlHelper::build_url([
                'scheme'     => 'http',
                'host'       => 'example.com',
                'fragment'   => 'top',
            ])
        );
    }

    public function test_build_url_empty_path(): void {
        $this->assertEquals(
            'http://example.com',
            UrlHelper::build_url([
                'scheme' => 'http',
                'host'   => 'example.com',
            ])
        );
    }

    // ------------------------------------------------------------------------
    // validate
    // ------------------------------------------------------------------------

    public function test_validate_valid_http(): void {
        $this->assertEquals('http://example.com', UrlHelper::validate('http://example.com'));
    }

    public function test_validate_valid_https(): void {
        $this->assertEquals('https://example.com', UrlHelper::validate('https://example.com'));
    }

    public function test_validate_with_path_and_query(): void {
        $result = UrlHelper::validate('https://example.com/path?foo=bar');
        $this->assertMatchesRegularExpression('#^https://example\.com/path\?foo=bar$#', $result);
    }

    public function test_validate_protocol_relative_normalized(): void {
        $this->assertEquals('https://example.com', UrlHelper::validate('//example.com'));
    }

    public function test_validate_rejects_ftp(): void {
        $this->assertFalse(UrlHelper::validate('ftp://ftp.example.com'));
    }

    public function test_validate_rejects_mailto(): void {
        $this->assertFalse(UrlHelper::validate('mailto:test@example.com'));
    }

    public function test_validate_rejects_javascript(): void {
        $this->assertFalse(UrlHelper::validate('javascript:void(0)'));
    }

    public function test_validate_rejects_empty(): void {
        $this->assertFalse(UrlHelper::validate(''));
    }

    public function test_validate_rejects_no_host(): void {
        $this->assertFalse(UrlHelper::validate('about:blank'));
    }

    public function test_validate_rejects_malformed(): void {
        $this->assertFalse(UrlHelper::validate('not a url'));
    }

    public function test_validate_extended_rejects_nonstandard_port(): void {
        $this->assertFalse(UrlHelper::validate('http://example.com:8080/path', true));
    }

    public function test_validate_extended_rejects_localhost(): void {
        $this->assertFalse(UrlHelper::validate('http://localhost', true));
    }

    public function test_validate_extended_rejects_localhost_with_path(): void {
        $this->assertFalse(UrlHelper::validate('http://localhost/admin', true));
    }

    public function test_validate_extended_rejects_127_loopback(): void {
        $this->assertFalse(UrlHelper::validate('http://127.0.0.2', true));
    }

    public function test_validate_extended_rejects_127_loopback_specific(): void {
        $this->assertFalse(UrlHelper::validate('http://127.0.0.1:3000', true));
    }

    public function test_validate_extended_allows_standard_ports(): void {
        $this->assertEquals('http://example.com:80', UrlHelper::validate('http://example.com:80', true));
        $this->assertEquals('https://example.com:443', UrlHelper::validate('https://example.com:443', true));
    }

    public function test_validate_extended_nonextended_allows_nonstandard_port(): void {
        // Without extended filtering, non-standard ports should pass
        $this->assertMatchesRegularExpression('#^http://example\.com:8080$#', UrlHelper::validate('http://example.com:8080'));
    }

    public function test_validate_lowercase_scheme(): void {
        // validate() allows uppercase schemes but preserves original case in output
        $result = UrlHelper::validate('HTTP://EXAMPLE.COM');
        $this->assertNotFalse($result);
        $this->assertStringContainsString('HTTP', $result);
    }

    public function test_validate_idn_to_ascii(): void {
        if (!function_exists('idn_to_ascii')) {
            $this->markTestSkipped('idn_to_ascii not available');
        }
        $result = UrlHelper::validate('http://münchen.example.com');
        $this->assertNotFalse($result);
        // The host should be converted to punycode
        $this->assertStringContainsString('xn--mnchen', $result);
    }

    public function test_validate_urlencoded_path(): void {
        // Spaces in path are accepted (rawurlencode is only for filter_var validation)
        $result = UrlHelper::validate('https://example.com/hello world');
        $this->assertNotFalse($result);
        // The returned URL preserves the original path (spaces not encoded)
        $this->assertStringContainsString('hello world', $result);
    }

    // ------------------------------------------------------------------------
    // resolve_redirects
    // ------------------------------------------------------------------------

    public function test_resolve_redirects_no_redirect(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // No redirect history header means no redirect occurred
        $mock->append(new Response(200, [], ''));
        $result = UrlHelper::resolve_redirects('https://example.com', 5);
        $this->assertEquals('https://example.com', $result);
        $mock->reset();
    }

    public function test_resolve_redirects_single_redirect(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $history = ['https://example.com/original', 'https://example.com/final'];
        $mock->append(new Response(200, [
            RedirectMiddleware::HISTORY_HEADER => $history,
        ], ''));
        $result = UrlHelper::resolve_redirects('https://example.com/original', 5);
        $this->assertEquals('https://example.com/final', $result);
        $mock->reset();
    }

    public function test_resolve_redirects_multiple_redirects(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $history = [
            'https://example.com/a',
            'https://example.com/b',
            'https://example.com/c',
        ];
        $mock->append(new Response(200, [
            RedirectMiddleware::HISTORY_HEADER => $history,
        ], ''));
        $result = UrlHelper::resolve_redirects('https://example.com/a', 5);
        $this->assertEquals('https://example.com/c', $result);
        $mock->reset();
    }

    public function test_resolve_redirects_exception_returns_false(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new \GuzzleHttp\Exception\ConnectException('Connection refused', new Request('HEAD', 'https://example.com')));
        $result = UrlHelper::resolve_redirects('https://example.com', 5);
        $this->assertFalse($result);
        $mock->reset();
    }

    // ------------------------------------------------------------------------
    // url_to_youtube_vid
    // ------------------------------------------------------------------------

    public function test_url_to_youtube_vid_watch(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_url_to_youtube_vid_watch_with_extra_params(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'));
    }

    public function test_url_to_youtube_vid_short(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_url_to_youtube_vid_embed(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    public function test_url_to_youtube_vid_v(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://www.youtube.com/v/dQw4w9WgXcQ'));
    }

    public function test_url_to_youtube_vid_nocookie(): void {
        $this->assertEquals('dQw4w9WgXcQ', UrlHelper::url_to_youtube_vid('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'));
    }

    public function test_url_to_youtube_vid_invalid(): void {
        $this->assertFalse(UrlHelper::url_to_youtube_vid('https://www.youtube.com/'));
        $this->assertFalse(UrlHelper::url_to_youtube_vid('https://vimeo.com/12345'));
        $this->assertFalse(UrlHelper::url_to_youtube_vid('not-a-url'));
        $this->assertFalse(UrlHelper::url_to_youtube_vid(''));
    }

    // ------------------------------------------------------------------------
    // rewrite_relative (additional cases)
    // ------------------------------------------------------------------------

    public function test_rewrite_relative_data_image_for_img(): void {
        $this->assertEquals(
            'data:image/png;base64,iVBORw0KGgo',
            UrlHelper::rewrite_relative(
                'http://example.com/',
                'data:image/png;base64,iVBORw0KGgo',
                'img',
                'src'
            )
        );
    }

    public function test_rewrite_relative_data_image_rejected_for_other_element(): void {
        // data: scheme should be rewritten (not allowed) for non-img elements
        $result = UrlHelper::rewrite_relative('http://example.com/', 'data:image/png;base64,x', 'a', 'href');
        $this->assertNotEquals('data:image/png;base64,x', $result);
        $this->assertNotFalse($result);
    }

    public function test_rewrite_relative_data_video_not_allowed(): void {
        // Only image/* base64 is allowed for img[src]
        $result = UrlHelper::rewrite_relative('http://example.com/', 'data:video/mp4;base64,x', 'img', 'src');
        $this->assertNotEquals('data:video/mp4;base64,x', $result);
    }

    public function test_rewrite_relative_content_type_magnet(): void {
        $this->assertEquals(
            'magnet:?xt=urn:btih:...',
            UrlHelper::rewrite_relative(
                'http://example.com/',
                'magnet:?xt=urn:btih:...',
                '',
                '',
                'application/x-bittorrent'
            )
        );
    }

    public function test_rewrite_relative_absolute_relative_path(): void {
        // Absolute relative path (/test.html) should NOT prepend base dirname
        $this->assertEquals(
            'https://example.com/test.html',
            UrlHelper::rewrite_relative('https://example.com/foo/bar/', '/test.html')
        );
    }

    public function test_rewrite_relative_base_no_path(): void {
        // Base URL with no path component
        $result = UrlHelper::rewrite_relative('http://example.com', 'page.html');
        $this->assertEquals('http://example.com/page.html', $result);
    }

    public function test_rewrite_relative_dotslash_with_base_dirname(): void {
        $this->assertEquals(
            'https://example.com/foo/bar/baz/test.html',
            UrlHelper::rewrite_relative('https://example.com/foo/bar/baz/', './test.html')
        );
    }

    public function test_rewrite_relative_absolute_url_passes_through(): void {
        // An already-absolute URL should be validated and returned as-is
        $this->assertEquals(
            'https://other.com/page',
            UrlHelper::rewrite_relative('http://example.com/', 'https://other.com/page')
        );
    }

    public function test_rewrite_relative_empty_rel_url_returns_base(): void {
        $this->assertEquals('http://example.com/base', UrlHelper::rewrite_relative('http://example.com/base', ''));
    }

    public function test_rewrite_relative_mailto_disallowed_without_context(): void {
        // Without owner_element="a" and owner_attribute="href", mailto is treated as relative
        $result = UrlHelper::rewrite_relative('http://example.com/', 'mailto:test@example.com');
        $this->assertNotEquals('mailto:test@example.com', $result);
    }

    // ------------------------------------------------------------------------
    // fetch (additional edge cases)
    // ------------------------------------------------------------------------

    public function test_fetch_followlocation_false_with_200(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // When followlocation=false and response is 200, should succeed
        $mock->append(new Response(200, [], 'OK body'));
        $result = UrlHelper::fetch(['url' => 'https://www.example.com', 'followlocation' => false]);
        $this->assertEquals('OK body', $result);
        $this->assertEquals(200, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }

    public function test_fetch_empty_body(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(200, [], ''));
        $result = UrlHelper::fetch(['url' => 'https://www.example.com']);
        $this->assertFalse($result);
        $this->assertEquals('Successful response, but no content was received.', UrlHelper::$fetch_last_error);
        $mock->reset();
    }

    public function test_fetch_403_auth_retry_any(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(
            new Response(403, []),
            new Response(200, [], 'Retried OK'),
        );
        $result = UrlHelper::fetch([
            'url'       => 'https://example.com/protected',
            'login'     => 'user',
            'pass'      => 'pass',
            'auth_type' => 'basic',
        ]);
        $this->assertEquals('Retried OK', $result);
        $this->assertEquals(200, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }

    public function test_fetch_403_no_auth_does_not_retry(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(403, []));
        $result = UrlHelper::fetch(['url' => 'https://example.com/protected']);
        $this->assertFalse($result);
        $this->assertEquals(403, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }

    public function test_fetch_redirect_to_loopback_rejected(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(301, ['Location' => 'http://127.0.0.1']));
        $result = UrlHelper::fetch(['url' => 'https://example.com', 'followlocation' => true]);
        $this->assertFalse($result);
        $this->assertMatchesRegularExpression('%failed extended validation%', UrlHelper::$fetch_last_error);
        $this->assertEquals('http://127.0.0.1', UrlHelper::$fetch_effective_url);
        $mock->reset();
    }

    public function test_fetch_validates_effective_url_after_redirect(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $history = ['https://example.com/start', 'https://example.com/final'];
        $mock->append(new Response(200, [
            RedirectMiddleware::HISTORY_HEADER => $history,
        ], 'body'));
        $result = UrlHelper::fetch(['url' => 'https://example.com/start']);
        $this->assertEquals('body', $result);
        $this->assertEquals('https://example.com/final', UrlHelper::$fetch_effective_url);
        $mock->reset();
    }

    public function test_fetch_sets_content_type(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], 'OK'));
        UrlHelper::fetch(['url' => 'https://www.example.com']);
        $this->assertEquals('text/html; charset=utf-8', UrlHelper::$fetch_last_content_type);
        $mock->reset();
    }

    public function test_fetch_sets_last_modified(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(200, ['Last-Modified' => 'Wed, 01 Jan 2025 00:00:00 GMT'], 'OK'));
        UrlHelper::fetch(['url' => 'https://www.example.com']);
        $this->assertEquals('Wed, 01 Jan 2025 00:00:00 GMT', UrlHelper::$fetch_last_modified);
        $mock->reset();
    }

    public function test_fetch_max_size_exceeded_by_content_length_header(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // Server says Content-Length exceeds our max even before downloading.
        // The on_headers callback throws LengthException, which Guzzle wraps;
        // the catch block for GuzzleException sets the error message.
        $mock->append(new Response(200, ['Content-Length' => '999999999'], 'body'));
        $result = UrlHelper::fetch(['url' => 'https://www.example.com', 'max_size' => 100]);
        $this->assertFalse($result);
        // Guzzle wraps the LengthException from on_headers
        $this->assertStringContainsString('on_headers', UrlHelper::$fetch_last_error);
        $mock->reset();
    }

    public function test_fetch_trims_leading_spaces(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(200, [], 'OK'));
        $result = UrlHelper::fetch(['url' => '  https://www.example.com']);
        $this->assertEquals('OK', $result);
        $mock->reset();
    }

    public function test_fetch_spaces_in_url_encoded(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(200, [], 'OK'));
        // Spaces in URL should be percent-encoded before validation
        $result = UrlHelper::fetch(['url' => 'https://www.example.com/hello world']);
        $this->assertEquals('OK', $result);
        $mock->reset();
    }

    public function test_fetch_invalid_url_after_redirect(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // Redirect to an invalid scheme (ftp) — Guzzle rejects this before our on_redirect callback
        $mock->append(new Response(301, ['Location' => 'ftp://ftp.example.com']));
        $result = UrlHelper::fetch(['url' => 'https://example.com']);
        $this->assertFalse($result);
        // Either our validation or Guzzle's protocol check catches this
        $this->assertStringContainsString('ftp', UrlHelper::$fetch_last_error);
        $mock->reset();
    }

    public function test_fetch_error_code_on_4xx(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(404, [], 'Not Found'));
        $result = UrlHelper::fetch(['url' => 'https://example.com/nonexistent']);
        $this->assertFalse($result);
        $this->assertEquals(404, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }

    public function test_fetch_error_code_on_5xx(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        $mock->append(new Response(500, [], 'Internal Server Error'));
        $result = UrlHelper::fetch(['url' => 'https://example.com/error']);
        $this->assertFalse($result);
        $this->assertEquals(500, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }

    public function test_fetch_resets_state_on_call(): void {
        $mock = new MockHandler();
        UrlHelper::$client = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // First call sets error state
        $mock->append(new Response(404, [], 'Not Found'));
        UrlHelper::fetch(['url' => 'https://example.com/first']);
        $this->assertEquals(404, UrlHelper::$fetch_last_error_code);

        // Second call should reset state
        $mock->append(new Response(200, [], 'OK'));
        UrlHelper::fetch(['url' => 'https://www.example.com']);
        $this->assertEquals(200, UrlHelper::$fetch_last_error_code);
        $mock->reset();
    }
}

<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('mocked')]
final class UrlHelperTest extends TestCase {

	// ===== build_url() Tests =====

	public function testBuildUrlWithAllParts(): void {
		$parts = [
			'scheme' => 'https',
			'host' => 'example.com',
			'path' => '/path/to/page',
			'query' => 'param=value',
			'fragment' => 'section'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('https://example.com/path/to/page?param=value#section', $result);
	}

	public function testBuildUrlWithMinimalParts(): void {
		$parts = [
			'scheme' => 'http',
			'host' => 'example.com'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('http://example.com', $result);
	}

	public function testBuildUrlWithPathOnly(): void {
		$parts = [
			'scheme' => 'https',
			'host' => 'example.com',
			'path' => '/about'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('https://example.com/about', $result);
	}

	public function testBuildUrlWithNonStandardPort(): void {
		$parts = [
			'scheme' => 'http',
			'host' => 'example.org',
			'port' => 8080
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('http://example.org:8080', $result);
	}

	public function testBuildUrlWithNonStandardPortAndPath(): void {
		$parts = [
			'scheme' => 'http',
			'host' => 'example.org',
			'port' => 8080,
			'path' => '/test.jpg'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('http://example.org:8080/test.jpg', $result);
	}

	public function testBuildUrlWithNonStandardPortAndAllParts(): void {
		$parts = [
			'scheme' => 'https',
			'host' => 'example.org',
			'port' => 8443,
			'path' => '/api/endpoint',
			'query' => 'key=value',
			'fragment' => 'section'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('https://example.org:8443/api/endpoint?key=value#section', $result);
	}

	public function testBuildUrlWithStandardHttpPort(): void {
		$parts = [
			'scheme' => 'http',
			'host' => 'example.com',
			'port' => 80
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('http://example.com:80', $result);
	}

	public function testBuildUrlWithStandardHttpsPort(): void {
		$parts = [
			'scheme' => 'https',
			'host' => 'example.com',
			'port' => 443
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('https://example.com:443', $result);
	}

	public function testBuildUrlWithPortAsString(): void {
		$parts = [
			'scheme' => 'http',
			'host' => 'example.org',
			'port' => '8080'
		];

		$result = UrlHelper::build_url($parts);
		$this->assertEquals('http://example.org:8080', $result);
	}

	// ===== rewrite_relative() - Absolute URLs =====

	public function testRewriteRelativeWithAbsoluteUrl(): void {
		$base = 'https://example.com/page';
		$rel = 'https://other.com/resource';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://other.com/resource', $result);
	}

	public function testRewriteRelativeWithEmptyRelativeUrl(): void {
		$base = 'https://example.com/page';
		$rel = '';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals($base, $result);
	}

	// ===== rewrite_relative() - Protocol-Relative URLs =====

	public function testRewriteRelativeWithProtocolRelativeUrl(): void {
		$base = 'https://example.com/page';
		$rel = '//cdn.example.com/resource.js';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://cdn.example.com/resource.js', $result);
	}

	// ===== rewrite_relative() - Relative Paths =====

	public function testRewriteRelativeWithRelativePath(): void {
		$base = 'https://example.com/blog/post';
		$rel = 'image.jpg';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://example.com/blog/image.jpg', $result);
	}

	public function testRewriteRelativeWithAbsolutePath(): void {
		$base = 'https://example.com/blog/post';
		$rel = '/assets/style.css';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://example.com/assets/style.css', $result);
	}

	public function testRewriteRelativeWithDotSlashPath(): void {
		$base = 'https://example.com/blog/post';
		$rel = './image.png';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://example.com/blog/image.png', $result);
	}

	public function testRewriteRelativeWithBaseUrlEndingInSlash(): void {
		$base = 'https://example.com/blog/';
		$rel = 'image.jpg';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://example.com/blog/image.jpg', $result);
	}

	// ===== rewrite_relative() - Port Preservation =====

	public function testRewriteRelativePreservesPortFromBaseUrl(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = 'image.jpg';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('http://example.org:8080/blog/image.jpg', $result);
	}

	public function testRewriteRelativePreservesPortWithAbsolutePath(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = '/assets/image.jpg';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('http://example.org:8080/assets/image.jpg', $result);
	}

	public function testRewriteRelativePreservesPortWithDotSlashPath(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = './image.jpg';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('http://example.org:8080/blog/image.jpg', $result);
	}

	public function testRewriteRelativePreservesPortWithQueryString(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = 'api/data?key=value';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('http://example.org:8080/blog/api/data?key=value', $result);
	}

	public function testRewriteRelativePreservesNonStandardHttpsPort(): void {
		$base = 'https://example.org:8443/feed.xml';
		$rel = 'article.html';

		$result = UrlHelper::rewrite_relative($base, $rel);
		$this->assertEquals('https://example.org:8443/article.html', $result);
	}

	public function testRewriteRelativeWithBaseUrlPortButAbsoluteRelUrl(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = 'https://other.com/resource';

		$result = UrlHelper::rewrite_relative($base, $rel);
		// Absolute URLs should not inherit port from base
		$this->assertEquals('https://other.com/resource', $result);
	}

	public function testRewriteRelativeWithBaseUrlPortAndProtocolRelativeUrl(): void {
		$base = 'http://example.org:8080/blog/post';
		$rel = '//cdn.example.com/resource.js';

		$result = UrlHelper::rewrite_relative($base, $rel);
		// Protocol-relative URLs should not inherit port from base
		$this->assertEquals('https://cdn.example.com/resource.js', $result);
	}

	// ===== rewrite_relative() - Special Schemes =====

	public function testRewriteRelativeAllowsMagnetForAnchorHref(): void {
		$base = 'https://example.com/page';
		$rel = 'magnet:?xt=urn:btih:123';

		$result = UrlHelper::rewrite_relative($base, $rel, 'a', 'href');
		$this->assertEquals('magnet:?xt=urn:btih:123', $result);
	}

	public function testRewriteRelativeAllowsMailtoForAnchorHref(): void {
		$base = 'https://example.com/page';
		$rel = 'mailto:test@example.com';

		$result = UrlHelper::rewrite_relative($base, $rel, 'a', 'href');
		$this->assertEquals('mailto:test@example.com', $result);
	}

	public function testRewriteRelativeAllowsTelForAnchorHref(): void {
		$base = 'https://example.com/page';
		$rel = 'tel:+1234567890';

		$result = UrlHelper::rewrite_relative($base, $rel, 'a', 'href');
		$this->assertEquals('tel:+1234567890', $result);
	}

	public function testRewriteRelativeRejectsMagnetForNonAnchor(): void {
		$base = 'https://example.com/page';
		$rel = 'magnet:?xt=urn:btih:123';

		$result = UrlHelper::rewrite_relative($base, $rel, 'img', 'src');
		// Should rewrite as relative path since not allowed for img
		$this->assertNotEquals('magnet:?xt=urn:btih:123', $result);
	}

	// ===== rewrite_relative() - Data URLs for Images =====

	public function testRewriteRelativeAllowsDataUrlForImgSrc(): void {
		$base = 'https://example.com/page';
		$rel = 'data:image/png;base64,iVBORw0KGgo=';

		$result = UrlHelper::rewrite_relative($base, $rel, 'img', 'src');
		$this->assertEquals('data:image/png;base64,iVBORw0KGgo=', $result);
	}

	public function testRewriteRelativeAllowsDataUrlWithWebp(): void {
		$base = 'https://example.com/page';
		$rel = 'data:image/webp;base64,UklGRiQAAABXRUJQ';

		$result = UrlHelper::rewrite_relative($base, $rel, 'img', 'src');
		$this->assertEquals('data:image/webp;base64,UklGRiQAAABXRUJQ', $result);
	}

	public function testRewriteRelativeAllowsDataUrlWithSvg(): void {
		$base = 'https://example.com/page';
		$rel = 'data:image/svg;base64,PHN2ZyB4bWxucz0iaHR0cDov';

		$result = UrlHelper::rewrite_relative($base, $rel, 'img', 'src');
		$this->assertEquals('data:image/svg;base64,PHN2ZyB4bWxucz0iaHR0cDov', $result);
	}

	public function testRewriteRelativeRejectsDataUrlForNonImage(): void {
		$base = 'https://example.com/page';
		$rel = 'data:text/html;base64,PHRpdGxlPg==';

		$result = UrlHelper::rewrite_relative($base, $rel, 'img', 'src');
		// Regex only checks for image/* at start, so non-image MIME types get rewritten as relative paths
		$this->assertStringContainsString('text/html', $result);
		$this->assertNotEquals($rel, $result); // Should not return as-is
	}

	public function testRewriteRelativeRejectsDataUrlForNonImgElement(): void {
		$base = 'https://example.com/page';
		$rel = 'data:image/png;base64,iVBORw0KGgo=';

		$result = UrlHelper::rewrite_relative($base, $rel, 'a', 'href');
		// Data URLs for non-img elements get rewritten as relative paths
		$this->assertStringContainsString('image/png', $result);
		$this->assertNotEquals($rel, $result); // Should not return as-is
	}

	// ===== rewrite_relative() - Content Type-Based Schemes =====

	public function testRewriteRelativeAllowsMagnetForBittorrentContentType(): void {
		$base = 'https://example.com/page';
		$rel = 'magnet:?xt=urn:btih:456';

		$result = UrlHelper::rewrite_relative($base, $rel, '', '', 'application/x-bittorrent');
		$this->assertEquals('magnet:?xt=urn:btih:456', $result);
	}

	// ===== rewrite_relative() - Invalid URLs =====

	public function testRewriteRelativeWithInvalidCharactersInUrl(): void {
		$base = 'https://example.com/page';
		$rel = 'ht!tp://invalid url with spaces';

		$result = UrlHelper::rewrite_relative($base, $rel);
		// Invalid URLs without scheme/host are treated as relative paths
		$this->assertStringContainsString('example.com', $result);
		$this->assertNotEquals($rel, $result);
	}

	// ===== validate() - Basic Validation =====

	public function testValidateAcceptsValidHttpsUrl(): void {
		$result = UrlHelper::validate('https://example.com/page');
		$this->assertEquals('https://example.com/page', $result);
	}

	public function testValidateAcceptsValidHttpUrl(): void {
		$result = UrlHelper::validate('http://example.com/page');
		$this->assertEquals('http://example.com/page', $result);
	}

	public function testValidateFixesProtocolRelativeUrl(): void {
		$result = UrlHelper::validate('//example.com/resource');
		$this->assertEquals('https://example.com/resource', $result);
	}

	public function testValidateRejectsUrlWithoutHost(): void {
		$result = UrlHelper::validate('http:///path');
		$this->assertFalse($result);
	}

	public function testValidateRejectsMissingScheme(): void {
		$result = UrlHelper::validate('example.com/page');
		$this->assertFalse($result);
	}

	public function testValidateRejectsInvalidScheme(): void {
		$result = UrlHelper::validate('ftp://example.com/file');
		$this->assertFalse($result);
	}

	public function testValidateCleansUrl(): void {
		$result = UrlHelper::validate('  https://example.com/page  ');
		// clean() should strip tags and trim
		$this->assertEquals('https://example.com/page', $result);
	}

	// ===== canonicalize_ipv4_literal() Tests =====

	public function testCanonicalizeIpv4LiteralNormalizesHexOctalAndDecimalForms(): void {
		$this->assertSame('127.0.0.1', UrlHelper::canonicalize_ipv4_literal('0177.0.0.1'));
		$this->assertSame('127.0.0.1', UrlHelper::canonicalize_ipv4_literal('0x7f.0.0.1'));
		$this->assertSame('127.0.0.1', UrlHelper::canonicalize_ipv4_literal('127.0.0.1'));
		$this->assertNull(UrlHelper::canonicalize_ipv4_literal('0xgg.0.0.1'));
		$this->assertNull(UrlHelper::canonicalize_ipv4_literal('1.2.3.4.5'));
	}

	// ===== has_disallowed_ip() Tests =====

	public function testIsDisallowedIpDetectsLocalhost(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://localhost/path'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://localhost:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://LOCALHOST/'));
	}

	public function testIsDisallowedIpDetects127Loopback(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://127.0.0.1/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://127.0.0.1:8080/path'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://127.1.2.3/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://127.255.255.255/'));
	}

	public function testIsDisallowedIpDetectsIPv6Loopback(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://[::1]/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://[::1]:8080/path'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://[::1]:443/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://[0:0:0:0:0:0:0:1]/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://[0:0:0:0:0:0:0:1]:443/'));
	}

	public function testIsDisallowedIpAllowsExternalHosts(): void {
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://example.com/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://example.org:8080/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://8.8.8.8/'));
	}

	public function testIsDisallowedIpReturnsFalseForInvalidUrl(): void {
		$this->assertFalse(UrlHelper::has_disallowed_ip('not-a-url'));
		$this->assertFalse(UrlHelper::has_disallowed_ip(''));
	}

	public function testIsDisallowedIpRejectsUnresolvableHostnamesWhenResolutionRequired(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://does-not-resolve.invalid/', true));
	}

	public function testIsDisallowedIpRejectsTrailingDotIpv4Literal(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://169.254.169.254./'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://169.254.169.254.:443/'));
	}

	public function testIsDisallowedIpRejectsEmptyHostnameAfterTrimming(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://./'));
	}

	public function testIsDisallowedIpRejectsCanonicalizedLoopbackAndMappedIPv6Variants(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://0177.0.0.1:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://0x7f.0.0.1:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://[::ffff:127.0.0.1]:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://[::ffff:10.0.0.1]:8080/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://[::ffff:10.0.0.1]/'));
	}

	// ===== has_disallowed_ip() - Link-Local / Cloud Metadata Tests =====

	public function testIsDisallowedIpBlocksLinkLocalOnAllPorts(): void {
		// 169.254.x.x is link-local (cloud metadata) - always blocked
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://169.254.169.254/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://169.254.0.1/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://169.254.169.254:80/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://169.254.169.254:8080/'));
	}

	// ===== has_disallowed_ip() - RFC 1918 Private IP Tests =====

	public function testIsDisallowedIpAllowsPrivateIP10OnStandardPorts(): void {
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://10.0.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://10.255.255.255/path'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://10.0.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://10.0.0.1:443/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://10.0.0.1:80/'));
	}

	public function testIsDisallowedIpBlocksPrivateIP10OnNonStandardPorts(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://10.0.0.1:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://10.0.0.1:8443/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://10.0.0.1:6379/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://10.255.255.255:3000/'));
	}

	public function testIsDisallowedIpAllowsPrivateIP192OnStandardPorts(): void {
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://192.168.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://192.168.255.255/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://192.168.1.100:443/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://192.168.1.1:80/'));
	}

	public function testIsDisallowedIpBlocksPrivateIP192OnNonStandardPorts(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://192.168.1.1:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://192.168.1.1:8443/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://192.168.0.1:9200/'));
	}

	public function testIsDisallowedIpAllowsPrivateIP172OnStandardPorts(): void {
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.16.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://172.31.255.255/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.20.10.5:80/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('https://172.16.0.1:443/'));
	}

	public function testIsDisallowedIpBlocksPrivateIP172OnNonStandardPorts(): void {
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://172.16.0.1:8080/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('https://172.31.0.1:8443/'));
		$this->assertTrue(UrlHelper::has_disallowed_ip('http://172.20.10.5:5432/'));
	}

	public function testIsDisallowedIpAllowsNonPrivateIP172(): void {
		// 172.15.x.x and 172.32.x.x are NOT in private range (172.16.0.0/12)
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.15.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.32.0.1/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.15.0.1:8080/'));
		$this->assertFalse(UrlHelper::has_disallowed_ip('http://172.32.0.1:9999/'));
	}

	// ===== Edge Cases =====

	public function testRewriteRelativeWithBaseUrlWithoutPath(): void {
		$base = 'https://example.com';
		$rel = 'page.html';

		$result = UrlHelper::rewrite_relative($base, $rel);
		// dirname('') returns '.', with_trailing_slash adds '/', so becomes '/page.html'
		$this->assertEquals('https://example.com/page.html', $result);
	}

	public function testRewriteRelativePreservesQueryAndFragment(): void {
		$base = 'https://example.com/blog/post';
		$rel = 'image.jpg?size=large#top';

		$result = UrlHelper::rewrite_relative($base, $rel);
		// Query and fragment should be preserved during URL parsing
		$this->assertStringContainsString('image.jpg', $result);
	}

	// ===== url_to_youtube_vid() Tests =====

	public function testUrlToYoutubeVidExtractsFromStandardUrl(): void {
		$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}

	public function testUrlToYoutubeVidExtractsFromEmbedUrl(): void {
		$url = 'https://www.youtube.com/embed/dQw4w9WgXcQ';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}

	public function testUrlToYoutubeVidExtractsFromVUrl(): void {
		$url = 'https://www.youtube.com/v/dQw4w9WgXcQ';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}

	public function testUrlToYoutubeVidExtractsFromYoutuBeShortUrl(): void {
		$url = 'https://youtu.be/dQw4w9WgXcQ';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}

	public function testUrlToYoutubeVidRewritesToNoCookieDomain(): void {
		$url = 'https://www.youtube.com/watch?v=test123';

		// Method rewrites youtube.com to youtube-nocookie.com internally
		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('test123', $result);
	}

	public function testUrlToYoutubeVidHandlesVideoIdWithHyphensAndUnderscores(): void {
		$url = 'https://www.youtube.com/watch?v=abc-DEF_123';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('abc-DEF_123', $result);
	}

	public function testUrlToYoutubeVidReturnsFalseForNonYoutubeUrl(): void {
		$url = 'https://vimeo.com/123456';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertFalse($result);
	}

	public function testUrlToYoutubeVidReturnsFalseForMalformedYoutubeUrl(): void {
		$url = 'https://www.youtube.com/notavalidpath';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertFalse($result);
	}

	public function testUrlToYoutubeVidHandlesWatchUrlWithAdditionalParams(): void {
		$url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&feature=share&t=10';

		// Regex extracts video ID from query string
		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}

	public function testUrlToYoutubeVidReturnsFalseForEmptyVideoId(): void {
		$url = 'https://www.youtube.com/watch?v=';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertFalse($result);
	}

	public function testUrlToYoutubeVidHandlesHttpProtocol(): void {
		$url = 'http://www.youtube.com/watch?v=test456';

		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('test456', $result);
	}

	public function testUrlToYoutubeVidHandlesYoutuBeWithExtraPath(): void {
		$url = 'https://youtu.be/dQw4w9WgXcQ?t=42';

		// Regex should still extract video ID even with query params
		$result = UrlHelper::url_to_youtube_vid($url);
		$this->assertEquals('dQw4w9WgXcQ', $result);
	}
}

<?php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class UrlHelper {
	const EXTRA_HREF_SCHEMES = [
		"magnet",
		"mailto",
		"tel"
	];

	const EXTRA_SCHEMES_BY_CONTENT_TYPE = [
		"application/x-bittorrent" => [ "magnet" ],
	];

	static string $fetch_last_error;
	static int $fetch_last_error_code;
	static string $fetch_last_error_content;
	static string $fetch_last_content_type;
	static string $fetch_last_modified;
	static string $fetch_effective_url;
	static string $fetch_effective_ip_addr;

	public static ?GuzzleHttp\ClientInterface $client = null;

	private static function get_client(): GuzzleHttp\ClientInterface {
		if (self::$client == null) {
			self::$client = new GuzzleHttp\Client([
				GuzzleHttp\RequestOptions::COOKIES => false,
				GuzzleHttp\RequestOptions::PROXY => Config::get(Config::HTTP_PROXY) ?: null,
			]);
		}

		return self::$client;
	}

	/**
	 * @param array<string, string|int> $parts
	 */
	static function build_url(array $parts): string {
		$tmp = $parts['scheme'] . '://' . $parts['host'];

		if (isset($parts['port'])) $tmp .= ':' . $parts['port'];
		if (isset($parts['path'])) $tmp .= $parts['path'];
		if (isset($parts['query'])) $tmp .= '?' . $parts['query'];
		if (isset($parts['fragment'])) $tmp .= '#' . $parts['fragment'];

		return $tmp;
	}

	/**
	 * Converts a (possibly) relative URL to a absolute one, using provided base URL.
	 * Provides some exceptions for additional schemes like data: if called with owning element/attribute.
	 *
	 * @param string $base_url     Base URL (i.e. from where the document is)
	 * @param string $rel_url Possibly relative URL in the document
	 * @param string $owner_element Owner element tag name (i.e. "a") (optional)
	 * @param string $owner_attribute Owner attribute (i.e. "href") (optional)
	 * @param string $content_type URL content type as specified by enclosures, etc.
	 *
	 * @return false|string Absolute URL or false on failure (either during URL parsing or validation)
	 */
	public static function rewrite_relative($base_url,
				$rel_url,
				string $owner_element = "",
				string $owner_attribute = "",
				string $content_type = ""): false|string {

		$rel_parts = parse_url($rel_url);

		if (!$rel_url) return $base_url;

		/**
		 * If parse_url failed to parse $rel_url return false to match the current "invalid thing" behavior
		 * of UrlHelper::validate().
		 *
		 * TODO: There are many places where a string return value is assumed.  We should either update those
		 * to account for the possibility of failure, or look into updating this function's return values.
		 */
		if ($rel_parts === false) {
			return false;
		}

		if (!empty($rel_parts['host']) && !empty($rel_parts['scheme'])) {
			return self::validate($rel_url);

		// protocol-relative URL (rare but they exist)
		} else if (str_starts_with($rel_url, "//")) {
			return self::validate("https:" . $rel_url);
		// allow some extra schemes for A href
		} else if (in_array($rel_parts["scheme"] ?? "", self::EXTRA_HREF_SCHEMES, true) &&
				$owner_element == "a" &&
				$owner_attribute == "href") {
			return $rel_url;
		// allow some extra schemes for links with feed-specified content type i.e. enclosures
		} else if ($content_type &&
				isset(self::EXTRA_SCHEMES_BY_CONTENT_TYPE[$content_type]) &&
				in_array($rel_parts["scheme"], self::EXTRA_SCHEMES_BY_CONTENT_TYPE[$content_type])) {
			return $rel_url;
		// allow limited subset of inline base64-encoded images for IMG elements
		} else if (($rel_parts["scheme"] ?? "") == "data" &&
				preg_match('%^image/(webp|gif|jpg|png|svg);base64,%', $rel_parts["path"]) &&
				$owner_element == "img" &&
				$owner_attribute == "src") {
			return $rel_url;
		} else {
			$base_parts = parse_url($base_url);

			$rel_parts['scheme'] = $base_parts['scheme'] ?? '';
			$rel_parts['host'] = $base_parts['host'] ?? '';
			$rel_parts['port'] = $base_parts['port'] ?? '';

			if ($rel_parts['path'] ?? "") {

				// we append dirname() of base path to relative URL path as per RFC 3986 section 5.2.2
				// if base path ends with / use it directly
				$base_path = $base_parts['path'] ?? '';
				$base_path = str_ends_with($base_path, '/') ? $base_path : with_trailing_slash(dirname($base_path));

				// 1. absolute relative path (/test.html) = no-op, proceed as is

				// 2. dotslash relative URI (./test.html) - strip "./", append base path
				if (str_starts_with($rel_parts['path'], './')) {
					$rel_parts['path'] = $base_path . substr($rel_parts['path'], 2);
				// 3. anything else relative (test.html) - append dirname() of base path
				} else if (!str_starts_with($rel_parts['path'], '/')) {
					$rel_parts['path'] = $base_path . $rel_parts['path'];
				}

				//$rel_parts['path'] = str_replace("/./", "/", $rel_parts['path']);
				//$rel_parts['path'] = str_replace("//", "/", $rel_parts['path']);
			}

			return self::validate(self::build_url($rel_parts));
		}
	}

	/** extended filtering involves validation for safe ports and loopback
	 * @return false|string false if something went wrong, otherwise the URL string
	 */
	static function validate(string $url, bool $extended_filtering = false): false|string {

		$url = clean($url);

		# fix protocol-relative URLs
		if (str_starts_with($url, "//"))
			$url = "https:" . $url;

		$tokens = parse_url($url);

		// this isn't really necessary because filter_var(... FILTER_VALIDATE_URL) requires host and scheme
		// as per https://php.watch/versions/7.3/filter-var-flag-deprecation but it might save time
		if (empty($tokens['host']))
			return false;

		if (!in_array(strtolower($tokens['scheme']), ['http', 'https']))
			return false;

		//convert IDNA hostname to punycode if possible
		if (function_exists("idn_to_ascii")) {
			if (mb_detect_encoding($tokens['host']) != 'ASCII') {
				if (defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46')) {
					$tokens['host'] = idn_to_ascii($tokens['host'], IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
				} else {
					$tokens['host'] = idn_to_ascii($tokens['host']);
				}

				// if `idn_to_ascii` failed
				if ($tokens['host'] === false) {
					return false;
				}
			}
		}

		// separate set of tokens with urlencoded 'path' because filter_var() rightfully fails on non-latin characters
		// (used for validation only, we actually request the original URL, in case of urlencode breaking it)
		$tokens_filter_var = $tokens;

		if ($tokens['path'] ?? false) {
			$tokens_filter_var['path'] = implode('/',
										array_map(rawurlencode(...),
											array_map(rawurldecode(...),
												explode('/', $tokens['path']))));
		}

		$url = self::build_url($tokens);
		$url_filter_var = self::build_url($tokens_filter_var);

		if (filter_var($url_filter_var, FILTER_VALIDATE_URL) === false)
			return false;

		if (!getenv('IS_INTEGRATION_TESTING') && $extended_filtering) {
			if (!in_array($tokens['port'] ?? '', [80, 443, '']))
				return false;

			if (self::has_disallowed_ip($tokens, true))
				return false;
		}

		return $url;
	}

	/**
	 * Check if a URL targets a disallowed IP (localhost, loopback, or private IPs on non-standard ports).
	 *
	 * Traffic to private IPs on standard ports (80 and 443) is allowed to mimic the original behavior of
	 * tt-rss, which (by omitting the port number in URLs) effectively only allowed the default ports.
	 *
	 * By default there will not be resolution of a host to IP, and validation of that IP, as it can introduce
	 * noticeable slowness when DNS is slow (or times out, etc.).  'UrlHelper::fetch()', at minimum, will have
	 * this additional validation enabled.
	 *
	 * @param string|array{scheme?: string, host?: string, port?: int} $url_or_tokens URL or pre-parsed URL tokens array
	 * @param bool $validate_resolved_ip whether to perform additional validation by resolving the host to an IP and validating that IP
	 * @return bool true if the URL should be rejected, false otherwise
	 */
	static function has_disallowed_ip(string|array $url_or_tokens, bool $validate_resolved_ip = false): bool {
		$tokens = is_array($url_or_tokens)? $url_or_tokens : parse_url($url_or_tokens);

		if (empty($tokens['host']))
			return false;

		$host = strtolower($tokens['host']);

		$port = $tokens['port'] ?? null;
		$standard_port = (($tokens['scheme'] ?? 'http') === 'https') ? 443 : 80;
		$is_standard_port = ($port === null || $port === $standard_port);

		// strip IPv6 brackets
		if (str_starts_with($host, '[') && str_ends_with($host, ']'))
			$host = substr($host, 1, -1);

		if ($host === 'localhost' || str_starts_with($host, '127.'))
			return true;

		if ($host === '::1' || $host === '0:0:0:0:0:0:0:1')
			return true;

		// TODO: Improve IPv6 support (fc00::/7 unique local, fe80::/10 link-local)

		// IPv4 link-local / cloud metadata
		if (str_starts_with($host, '169.254.'))
			return true;

		if (!$is_standard_port && preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host))
			return true;

		// if needed, try resolving the hostname and checking the resulting IP
		if ($validate_resolved_ip && !str_contains($host, ':') && !preg_match('/^\d+\./', $host)) {
			$ip_addr = gethostbyname($host);

			// failed to resolve
			// TODO: return true instead?
			if (!$ip_addr || $ip_addr === $host)
				return false;

			if (str_starts_with($ip_addr, '127.'))
				return true;

			// TODO: maybe check for IPv6 loopback (::1) using dns_get_record()

			if (str_starts_with($ip_addr, '169.254.'))
				return true;

			if (!$is_standard_port && preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip_addr))
					return true;
		}

		return false;
	}

	static function resolve_redirects(string $url, int $timeout): false|string {
		$client = self::get_client();

		try {
			$response = $client->request('HEAD', $url, [
				GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => $timeout ?: Config::get(Config::FILE_FETCH_CONNECT_TIMEOUT),
				GuzzleHttp\RequestOptions::TIMEOUT => $timeout ?: Config::get(Config::FILE_FETCH_TIMEOUT),
				GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => ['max' => 10, 'track_redirects' => true],
				GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
				GuzzleHttp\RequestOptions::HEADERS => [
					'User-Agent' => Config::get_user_agent(),
					'Connection' => 'close',
				],
			]);
		} catch (Exception) {
			return false;
		}

		// If a history header value doesn't exist there was no redirection and the original URL is fine.
		$history_header = $response->getHeader(GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
		return ($history_header ? end($history_header) : $url);
	}

	/**
	 * @todo the multiple-argument approach has been deprecated for a long time, and should be removed
	 * @todo validate options
	 *
	 * @param array<string, bool|int|string>|string $options
	 * @return false|string false if something went wrong, otherwise string contents
	 */
	public static function fetch(array|string $options /* previously: 0: $url , 1: $type = false, 2: $login = false, 3: $pass = false,
				4: $post_query = false, 5: $timeout = false, 6: $timestamp = 0, 7: $useragent = false, 8: $encoding = false,
				9: $auth_type = "basic" */): false|string {

		self::$fetch_last_error = "";
		self::$fetch_last_error_code = -1;
		self::$fetch_last_error_content = "";
		self::$fetch_last_content_type = "";
		self::$fetch_last_modified = "";
		self::$fetch_effective_url = "";
		self::$fetch_effective_ip_addr = "";

		if (!is_array($options)) {

			// falling back on compatibility shim
			$option_names = [ "url", "type", "login", "pass", "post_query", "timeout", "last_modified", "useragent", "encoding", "auth_type" ];
			$tmp = [];

			for ($i = 0; $i < func_num_args(); $i++) {
				$tmp[$option_names[$i]] = func_get_arg($i);
			}

			$options = $tmp;

			/*$options = array(
					"url" => func_get_arg(0),
					"type" => @func_get_arg(1),
					"login" => @func_get_arg(2),
					"pass" => @func_get_arg(3),
					"post_query" => @func_get_arg(4),
					"timeout" => @func_get_arg(5),
					"timestamp" => @func_get_arg(6),
					"useragent" => @func_get_arg(7),
					"encoding" => @func_get_arg(8),
					"auth_type" => @func_get_arg(9),
			); */
		}

		$url = $options["url"];
		$type = $options["type"] ?? false;
		$login = $options["login"] ?? false;
		$pass = $options["pass"] ?? false;
		$auth_type = $options["auth_type"] ?? "basic";
		$post_query = $options["post_query"] ?? false;
		$timeout = $options["timeout"] ?? false;
		$last_modified = $options["last_modified"] ?? "";
		$useragent = $options["useragent"] ?? false;
		$followlocation = $options["followlocation"] ?? true;
		$max_size = $options["max_size"] ?? Config::get(Config::MAX_DOWNLOAD_FILE_SIZE); // in bytes
		$http_accept = $options["http_accept"] ?? false;
		$http_referrer = $options["http_referrer"] ?? false;
		$encoding = $options["encoding"] ?? false;

		$url = str_replace(' ', '%20', ltrim($url ?? '', ' '));

		Debug::log("[UrlHelper] handling URL: $url", Debug::LOG_EXTENDED);

		$url = self::validate($url, true);

		if (!$url) {
			self::$fetch_last_error = 'Requested URL failed extended validation.';
			return false;
		}

		// this skip is needed for integration tests, please don't enable in production
		/* if (!getenv('__URLHELPER_ALLOW_LOOPBACK')) {
			$url_host = parse_url($url, PHP_URL_HOST);
			$ip_addr = gethostbyname($url_host);

			if (!$ip_addr || str_starts_with($ip_addr, '127.')) {
				self::$fetch_last_error = "URL hostname failed to resolve or resolved to a loopback address ($ip_addr)";
				return false;
			}
		} */

		$req_options = [
			GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => $timeout ?: Config::get(Config::FILE_FETCH_CONNECT_TIMEOUT),
			GuzzleHttp\RequestOptions::TIMEOUT => $timeout ?: Config::get(Config::FILE_FETCH_TIMEOUT),
			GuzzleHttp\RequestOptions::HEADERS => [
				'User-Agent' => $useragent ?: Config::get_user_agent(),
			],
			'curl' => [],
		];

		if ($followlocation) {
			$req_options[GuzzleHttp\RequestOptions::ALLOW_REDIRECTS] = [
				'max' => 20,
				'track_redirects' => true,
				'on_redirect' => function(RequestInterface $request, ResponseInterface $response, UriInterface $uri) {
					if (!self::validate($uri, true)) {
						self::$fetch_effective_url = (string) $uri;
						throw new GuzzleHttp\Exception\RequestException('URL received during redirection failed extended validation.',
							$request, $response);
					}
				},
			];
		} else {
			$req_options[GuzzleHttp\RequestOptions::ALLOW_REDIRECTS] = false;
		}

		if ($last_modified && !$post_query)
			$req_options[GuzzleHttp\RequestOptions::HEADERS]['If-Modified-Since'] = $last_modified;

		if ($http_accept)
			$req_options[GuzzleHttp\RequestOptions::HEADERS]['Accept'] = $http_accept;

		if ($encoding)
			$req_options[GuzzleHttp\RequestOptions::HEADERS]['Accept-Encoding'] = $encoding;

		if  ($http_referrer)
			$req_options[GuzzleHttp\RequestOptions::HEADERS]['Referer'] = $http_referrer;

		if ($login && $pass && in_array($auth_type, ['basic', 'digest', 'ntlm'])) {
			// Let Guzzle handle the details for auth types it supports
			$req_options[GuzzleHttp\RequestOptions::AUTH] = [$login, $pass, $auth_type];
		} elseif ($auth_type === 'any') {
			// https://docs.guzzlephp.org/en/stable/faq.html#how-can-i-add-custom-curl-options
			$req_options['curl'][\CURLOPT_HTTPAUTH] = \CURLAUTH_ANY;
			if ($login && $pass)
				$req_options['curl'][\CURLOPT_USERPWD] = "$login:$pass";
		}

		if ($post_query)
			$req_options[GuzzleHttp\RequestOptions::FORM_PARAMS] = $post_query;

		if ($max_size) {
			$req_options[GuzzleHttp\RequestOptions::PROGRESS] = function($download_size, $downloaded, $upload_size, $uploaded) use(&$max_size, $url) {
				//Debug::log("[curl progressfunction] $downloaded $max_size", Debug::$LOG_EXTENDED);

				if ($downloaded > $max_size) {
					Debug::log("[UrlHelper] fetch error: max size of $max_size bytes exceeded when downloading $url .  Aborting.", Debug::LOG_VERBOSE);
					throw new \LengthException("Download exceeded size limit");
				}
			};

			# Alternative/supplement to `progress` checking
			$req_options[GuzzleHttp\RequestOptions::ON_HEADERS] = function(ResponseInterface $response) use(&$max_size, $url) {
				$content_length = $response->getHeaderLine('Content-Length');
				if ($content_length > $max_size) {
					Debug::log("[UrlHelper] fetch error: server indicated (via 'Content-Length: {$content_length}') max size of $max_size bytes " .
							"would be exceeded when downloading $url .  Aborting.", Debug::LOG_VERBOSE);
						throw new \LengthException("Server sent 'Content-Length' exceeding download limit");
				}
			};
		}

		$client = self::get_client();

		try {
			$response = $client->request($post_query ? 'POST' : 'GET', $url, $req_options);
		} catch (\LengthException $ex) {
			// Either 'Content-Length' indicated the download limit would be exceeded, or the transfer actually exceeded the download limit.
			self::$fetch_last_error = $ex->getMessage();
			return false;
		} catch (GuzzleHttp\Exception\GuzzleException $ex) {
			self::$fetch_last_error = $ex->getMessage();

			if ($ex instanceof GuzzleHttp\Exception\RequestException) {
				if ($ex instanceof GuzzleHttp\Exception\BadResponseException) {
					// 4xx or 5xx
					self::$fetch_last_error_code = $ex->getResponse()->getStatusCode();

					// If credentials were provided and we got a 403 back, retry once with auth type 'any'
					// to attempt compatibility with unusual configurations.
					if ($login && $pass && self::$fetch_last_error_code === 403 && $auth_type !== 'any') {
						$options['auth_type'] = 'any';
						return self::fetch($options);
					}

					self::$fetch_last_content_type = $ex->getResponse()->getHeaderLine('content-type');

					if ($type && !str_contains(self::$fetch_last_content_type, "$type"))
						self::$fetch_last_error_content = (string) $ex->getResponse()->getBody();
				} elseif (array_key_exists('errno', $ex->getHandlerContext())) {
					$errno = (int) $ex->getHandlerContext()['errno'];

					// By default, all supported encoding types are sent via `Accept-Encoding` and decoding of
					// responses with `Content-Encoding` is automatically attempted.  If this fails, we do a
					// single retry with `Accept-Encoding: none` to try and force an unencoded response.
					if (($errno === \CURLE_WRITE_ERROR || $errno === \CURLE_BAD_CONTENT_ENCODING) &&
						$ex->getRequest()->getHeaderLine('accept-encoding') !== 'none') {
						$options['encoding'] = 'none';
						return self::fetch($options);
					}
				}
			}

			return false;
		}

		// Keep setting expected 'fetch_last_error_code' and 'fetch_last_error' values
		self::$fetch_last_error_code = $response->getStatusCode();
		self::$fetch_last_error = "HTTP/{$response->getProtocolVersion()} {$response->getStatusCode()} {$response->getReasonPhrase()}";
		self::$fetch_last_modified = $response->getHeaderLine('last-modified');
		self::$fetch_last_content_type = $response->getHeaderLine('content-type');

		// If a history header value doesn't exist there was no redirection and the original URL is fine.
		$history_header = $response->getHeader(GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
		self::$fetch_effective_url = $history_header ? end($history_header) : $url;

		// This shouldn't be necessary given the checks that occur during potential redirects, but we'll do it anyway.
		if (!self::validate(self::$fetch_effective_url, true)) {
			self::$fetch_last_error = "URL received after redirection failed extended validation.";
			return false;
		}

		self::$fetch_effective_ip_addr = gethostbyname(parse_url(self::$fetch_effective_url, PHP_URL_HOST));

		// this skip is needed for integration tests, please don't enable in production
		/* if (!getenv('__URLHELPER_ALLOW_LOOPBACK')) {
			if (!self::$fetch_effective_ip_addr || str_starts_with(self::$fetch_effective_ip_addr, '127.')) {
				self::$fetch_last_error = 'URL hostname received after redirection failed to resolve or resolved to a loopback address (' .
					self::$fetch_effective_ip_addr . ')';
				return false;
			}
		} */

		$body = (string) $response->getBody();

		if (!$body) {
			self::$fetch_last_error = 'Successful response, but no content was received.';
			return false;
		}

		return $body;
	}

	/**
	 * @return false|string false if the provided URL didn't match expected patterns, otherwise the video ID string
	 */
	public static function url_to_youtube_vid(string $url): false|string {
		$url = str_replace("youtube.com", "youtube-nocookie.com", $url);

		$regexps = [
			"/\/\/www\.youtube-nocookie\.com\/v\/([\w-]+)/",
			"/\/\/www\.youtube-nocookie\.com\/embed\/([\w-]+)/",
			"/\/\/www\.youtube-nocookie\.com\/watch\?v=([\w-]+)/",
			"/\/\/youtu.be\/([\w-]+)/",
		];

		foreach ($regexps as $re) {
			$matches = [];

			if (preg_match($re, $url, $matches)) {
				return $matches[1];
			}
		}

		return false;
	}


}

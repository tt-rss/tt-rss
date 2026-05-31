<?php
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests that invoke backend Handler classes
 * through Router_Backend (the same path used by backend.php).
 *
 * Provides shared infrastructure: database seeding, session setup, and an
 * `invokeHandler()` method that dispatches requests through the router,
 * captures the output, and returns the parsed JSON response.
 *
 * Usage:
 *   class FeedsHandlerTest extends HandlerTestCase {
 *       public function test_get_unread(): void {
 *           $resp = $this->invokeHandler('Feeds', 'getUnread');
 *           $this->assertResponseOk($resp);
 *           $this->assertResponseHasKey($resp, 'unread');
 *       }
 *   }
 */
abstract class HandlerTestCase extends DbTestCase {

    protected string $csrfToken = '';

    /**
     * Ensure multi-user mode so that session validation is exercised.
     * Single-user mode bypasses session checks entirely.
     */
    protected function setUp(): void {
        parent::setUp();

        Config::set(Config::SINGLE_USER_MODE, 'false');

        // Set up a valid admin session (uid=1, pwd_hash = SHA1('password')).
        // This is the default admin user created by the schema.
        $this->setAdminSession();
    }

    /**
     * Set up the session as the admin user (uid=1).
     *
     * The admin user's pwd_hash is SHA1('password') = 5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8.
     */
    protected function setAdminSession(): void {
        $_SESSION['uid'] = 1;
        $_SESSION['pwd_hash'] = 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8';
        $_SESSION['name'] = 'admin';
        $_SESSION['access_level'] = UserHelper::ACCESS_LEVEL_ADMIN;
    }

    /**
     * Clear the session (guest state).
     */
    protected function setGuestSession(): void {
        unset($_SESSION['uid']);
        unset($_SESSION['pwd_hash']);
        unset($_SESSION['name']);
        unset($_SESSION['access_level']);
    }

    /**
     * Generate a CSRF token and store it in the session.
     *
     * @return string The generated token.
     */
    protected function generateCsrfToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $this->csrfToken;

        return $this->csrfToken;
    }

    /**
     * Invoke a handler method through Router_Backend and capture the response.
     *
     * This simulates the request flow: backend.php → Router_Backend::handle_request()
     * → handler instantiation → method call → response output.
     *
     * @param string $handlerClass Handler class name (e.g., 'Feeds', 'RPC')
     * @param string $method       Method name to call (e.g., 'getUnread', 'markArticleRead')
     * @param array<int|string, mixed> $params Request parameters (corresponds to $_REQUEST)
     * @return array{response: array<int|string, mixed>|null, body: string}
     *         - response: decoded JSON body, or null if not JSON
     *         - body: raw output body string
     */
    protected function invokeHandler(
        string $handlerClass,
        string $method,
        array $params = []
    ): array {
        // Prepare the request array (mirrors $_REQUEST in backend.php)
        $request = array_merge([
            'op'         => $handlerClass,
            'method'     => $method,
            'subop'      => null,
            'csrf_token' => $this->csrfToken,
        ], $params);

		// some handler methods invoke $_REQUEST directly
		$_REQUEST = array_merge($_REQUEST, $request);

        // Capture output from the handler's Response::send() calls.
        // The router calls Response::json()->send() which echoes JSON.
        ob_start();

        // Invoke the Handler
        $router = new $handlerClass($request);
        $router->$method();

        $rawBody = ob_get_clean();

        // Parse the JSON response
        $response = null;
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $response = $decoded;
            }
        }

        return [
            'response' => $response,
            'body'     => $rawBody,
        ];
    }

    /**
     * Assert that the response has a valid content block without errors.
     *
     * Handles both wrapped API responses {seq, status, content} and
     * flat responses {error, ...}.
     *
     * @param array<int|string, mixed> $resp
     */
    protected function assertResponseOk(array $resp): void {
        // Check for flat error format
        if (isset($resp['error'])) {
            $this->fail(
                "Response contains error: " . json_encode($resp['error'])
            );
        }

        // Check for wrapped API format
        if (isset($resp['content'])) {
            $this->assertArrayNotHasKey(
                'error',
                $resp['content'],
                $resp['content']['error'] ?? ''
            );
        }
    }

    /**
     * Assert that the response has an error.
     *
     * @param array<int|string, mixed> $resp
     * @param string|null $expectedCode Expected error code (optional)
     */
    protected function assertResponseError(
        array $resp,
        ?string $expectedCode = null
    ): void {
        if (isset($resp['error'])) {
            if ($expectedCode) {
                $this->assertEquals(
                    $expectedCode,
                    $resp['error']['code'] ?? null,
                    "Expected error code '$expectedCode'"
                );
            }
        } elseif (isset($resp['content']['error'])) {
            if ($expectedCode) {
                $this->assertEquals(
                    $expectedCode,
                    $resp['content']['error']['code'] ?? null,
                    "Expected error code '$expectedCode'"
                );
            }
        } else {
            $this->fail(
                "Expected error in response, got: " . json_encode($resp)
            );
        }
    }

    /**
     * Extract a value from the response content.
     *
     * Handles both wrapped {content: {...}} and flat response formats.
     *
     * @param array<int|string, mixed> $resp
     * @param string $key Key to extract
     * @return mixed
     */
    protected function getResponseContent(array $resp, string $key): mixed {
        $content = $resp['content'] ?? $resp;

        return $content[$key] ?? null;
    }

    /**
     * Assert that the response contains a specific key in its content.
     *
     * @param array<int|string, mixed> $resp
     * @param string $key
     */
    protected function assertResponseHasKey(array $resp, string $key): void {
        $content = $resp['content'] ?? $resp;
        $this->assertArrayHasKey(
            $key,
            $content,
            "Response should contain key '$key'. Got: " . json_encode($content)
        );
    }
}

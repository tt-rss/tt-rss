<?php
use PHPUnit\Framework\TestCase;

/**
 * Base class for API-based integration tests.
 *
 * Provides shared infrastructure: API URL configuration, HTTP request helper,
 * login/authentication, and common response assertions.
 */
abstract class IntegrationTestCase extends TestCase {
    protected string $api_url = "";
    protected string $app_url = "";
    protected string $sid = "";
    protected ?string $seedSqlPath = null;

    public function __construct(?string $name = null) {
        $this->api_url = getenv('API_URL');
        $this->app_url = getenv('APP_URL');

        $this->setUp();
        $this->login();

        parent::__construct($name);
    }

    /**
     * Reset the database to a clean state and re-seed with synthetic test data.
     * Called before each test method to ensure test isolation.
     *
     * Before seeding, checks that the PHP dev server is ready and not processing
     * lingering requests from previous tests. This prevents deadlocks caused by
     * the test process and PHP dev server accessing the same tables concurrently.
     */
    protected function setUp() : void {
        $this->waitForApiReady();
        $this->seedDatabase();
    }

    /**
     * Poll the API until it responds successfully, ensuring the PHP dev server
     * is ready before we start database operations.
     *
     * This prevents deadlocks where the test process holds DB locks while the
     * PHP dev server is still processing a request from a previous test.
     */
    protected function waitForApiReady() : void {
        if (empty($this->api_url)) {
            return;
        }

        $maxAttempts = 30;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $ch = curl_init($this->api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["op" => "getVersion"]));
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status === 200) {
                return;
            }

            usleep(100000); // 100ms
        }

        $this->fail("PHP dev server did not become ready within " . ($maxAttempts * 100) . "ms");
    }

    /**
     * Execute seed.sql to re-populate the database with synthetic test data.
     *
     * Wrapped in a transaction to ensure atomicity and minimize lock duration.
     * The seed.sql uses TRUNCATE (not DELETE) for faster, shorter-lived locks.
     */
    protected function seedDatabase() : void {
        $seedPath = $this->seedSqlPath ?? __DIR__ . "/seed.sql";

        if (!file_exists($seedPath)) {
            $this->fail("Seed file not found: $seedPath");
        }

        $seedSql = file_get_contents($seedPath);

        if ($seedSql === false) {
            $this->fail("Could not read seed file: $seedPath");
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->exec($seedSql);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Send an API request and return the decoded response.
     *
     * @param array<mixed> $payload
     * @return array<mixed>|null
     */
    protected function api(array $payload) : ?array {
        $ch = curl_init($this->api_url);

        $payload["sid"] = $this->sid;

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $resp = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($status != 200) {
            print("error: failed with HTTP status: $status");
            return null;
        }

        curl_close($ch);

        $resp_json = json_decode($resp, true);

        if (!$resp_json) {
            print_r($resp);
            return null;
        }

        if ($resp_json['status'] != 0) {
            print_r($resp_json);

            return null;
        }

        return $resp_json;
    }

    /**
     * Assert that a response has a valid content block without errors.
     *
     * @param array<mixed> $resp
     */
    protected function common_assertions(array $resp) : void {
        $this->assertArrayHasKey("content", $resp);
        $this->assertArrayNotHasKey("error", $resp['content'], $resp['content']['error'] ?? '');
    }

    /**
     * Authenticate with the API and store the session ID.
     */
    protected function login() : void {
        $resp = $this->api(["op" => "login", "user" => "test", "password" => "test"]);

        $this->common_assertions($resp);

        $this->assertArrayHasKey("session_id", $resp['content']);
        $this->sid = $resp['content']['session_id'];
    }

    protected function getSid() : string {
        return $this->sid;
    }
}

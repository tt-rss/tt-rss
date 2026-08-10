<?php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class ApiTest extends TestCase {
	private string $api_url = "";
	private string $app_url = "";
	private string $sid = "";

	/** @param array<mixed> $payload
	 * @return array<mixed>
	 */
	public function api(array $payload) : ?array {
		$ch = curl_init($this->api_url);

		if ($this->sid)
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

		return json_decode($resp, true);
	}

	public function setUp(): void {
		$this->api_url = getenv('API_URL');
		$this->app_url = getenv('APP_URL');
		$this->test_login();
	}

	/** @param array<mixed> $resp */
	public function common_assertions(array $resp) : void {
		$this->assertArrayHasKey("content", $resp);
		$this->assertArrayNotHasKey("error", $resp['content'], $resp['content']['error'] ?? '');
	}

	public function test_login() : void {
		$resp = $this->api(["op" => "login", "user" => "test", "password" => "test"]);
		$this->common_assertions($resp);

		$this->assertArrayHasKey("session_id", $resp['content']);
		$this->sid = $resp['content']['session_id'];
	}

	public function test_getVersion() : void {
		$resp = $this->api(["op" => "getVersion"]);
		$this->common_assertions($resp);
		$this->assertArrayHasKey("version", $resp['content']);
	}

	public function test_getUnread() : void {
		$resp = $this->api(["op" => "getUnread"]);
		$this->common_assertions($resp);

		$this->assertArrayHasKey("unread", $resp['content']);
	}

	public function test_subscribeToFeed() : void {
		$resp = $this->api(["op" => "subscribeToFeed", "feed_url" => $this->app_url . "/feed.xml"]);
		$this->common_assertions($resp);

		print_r($resp);

		$this->assertArrayHasKey("feed_id", $resp['content']['status']);
	}

	public function test_getCounters() : void {
		$resp = $this->api(["op" => "getCounters"]);
		$this->common_assertions($resp);

		foreach ($resp['content'] as $ctr) {
			$this->assertIsArray($ctr);

			foreach (["id", "counter"] as $k) {
				$this->assertArrayHasKey($k, $ctr);
				$this->assertNotNull($ctr[$k]);
			}
		}
	}

	public function test_getFeedTree() : void {
		$resp = $this->api(["op" => "getFeedTree"]);

		$this->assertArrayHasKey('categories', $resp['content']);
		$this->assertArrayHasKey('items', $resp['content']['categories']);

		foreach ($resp['content']['categories']['items'] as $cat) {

			foreach (["id", "bare_id", "name", "items"] as $k) {
				$this->assertArrayHasKey($k, $cat);
			}

			foreach ($cat['items'] as $feed) {
				$this->assertIsArray($feed);

				foreach (["id", "name", "unread", "bare_id"] as $k) {
					$this->assertArrayHasKey($k, $feed);
					$this->assertNotNull($feed[$k]);
				}
			}
		}
	}

	public function test_getHeadlines() : void {
		foreach (["0", "-1", "-2", "-3", "-4", "-6"] as $feed_id) {
			$resp = $this->api(["op" => "getHeadlines", "feed_id" => $feed_id]);
			$this->assertIsArray($resp);
		}
	}

}

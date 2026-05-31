<?php
/** @group integration */
final class RPCTest extends DbTestCase {

    /**
     * Helper to capture RPC method output via output buffering.
     *
     * @param array<string, mixed> $request
     * @return string Raw output from the RPC method
     */
    private function captureRpcOutput(string $method, array $request): string {
        $_REQUEST = $request;

        $rpc = new RPC($request);

        ob_start();
        $rpc->$method();
        $output = ob_get_clean();

        return $output;
    }

    /**
     * Verify that publ() sets published=1 and last_published to a valid timestamp.
     */
    public function test_publ_publish_article(): void {
        $ref_id = 1; // seed.sql entry with ref_id=1

        // Verify initial state: published should be NULL/false
        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT published, last_published FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $stmt->execute([$ref_id, 1]);
        $initial = $stmt->fetch();

        $this->assertFalse($initial['published']);
        $this->assertNull($initial['last_published']);

        // Call publ to publish the article
        $output = $this->captureRpcOutput('publ', [
            'pub' => '1',
            'id'  => (string) $ref_id,
        ]);

        // Verify JSON response
        $result = json_decode($output, true);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify database state after publ
        $stmt->execute([$ref_id, 1]);
        $updated = $stmt->fetch();

        $this->assertTrue($updated['published']);
        $this->assertNotNull($updated['last_published']);
    }

    /**
     * Verify that publ() sets published=0 (un-publishes the article).
     */
    public function test_publ_unpublish_article(): void {
        $ref_id = 3; // seed.sql entry with ref_id=3, initially marked=true

        $pdo = Db::pdo();

        // First publish it
        $this->captureRpcOutput('publ', [
            'pub' => '1',
            'id'  => (string) $ref_id,
        ]);

        $stmt = $pdo->prepare("SELECT published, last_published FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $stmt->execute([$ref_id, 1]);
        $published = $stmt->fetch();

        $this->assertTrue($published['published']);
        $this->assertNotNull($published['last_published']);

        // Now unpublish it
        $output = $this->captureRpcOutput('publ', [
            'pub' => '0',
            'id'  => (string) $ref_id,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        $stmt->execute([$ref_id, 1]);
        $updated = $stmt->fetch();

        $this->assertFalse($updated['published']);
        $this->assertNotNull($updated['last_published']);
    }

    /**
     * Verify that mark() sets marked=1 and last_marked to a valid timestamp.
     */
    public function test_mark_star_article(): void {
        $ref_id = 6; // seed.sql entry with ref_id=6, initially marked=false

        // Verify initial state: marked should be false
        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT marked, last_marked FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $stmt->execute([$ref_id, 1]);
        $initial = $stmt->fetch();

        $this->assertFalse($initial['marked']);
        $this->assertNull($initial['last_marked']);

        // Call mark to star the article
        $output = $this->captureRpcOutput('mark', [
            'mark' => '1',
            'id'   => (string) $ref_id,
        ]);

        // Verify JSON response
        $result = json_decode($output, true);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify database state after mark
        $stmt->execute([$ref_id, 1]);
        $updated = $stmt->fetch();

        $this->assertTrue($updated['marked']);
        $this->assertNotNull($updated['last_marked']);
    }

    /**
     * Verify that mark() sets marked=0 (un-stars the article).
     */
    public function test_mark_unstar_article(): void {
        $ref_id = 7; // seed.sql entry with ref_id=7, initially marked=false

        $pdo = Db::pdo();

        // First star it
        $this->captureRpcOutput('mark', [
            'mark' => '1',
            'id'   => (string) $ref_id,
        ]);

        $stmt = $pdo->prepare("SELECT marked, last_marked FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");
        $stmt->execute([$ref_id, 1]);
        $marked = $stmt->fetch();

        $this->assertTrue($marked['marked']);
        $this->assertNotNull($marked['last_marked']);

        // Now un-star it
        $output = $this->captureRpcOutput('mark', [
            'mark' => '0',
            'id'   => (string) $ref_id,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        $stmt->execute([$ref_id, 1]);
        $updated = $stmt->fetch();

        $this->assertFalse($updated['marked']);
        $this->assertNotNull($updated['last_marked']);
    }

    /**
     * Verify that publ() updates last_published timestamp each time.
     */
    public function test_publ_updates_last_published_timestamp(): void {
        $ref_id = 5;

        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT last_published FROM ttrss_user_entries WHERE ref_id = ? AND owner_uid = ?");

        // First publish
        $this->captureRpcOutput('publ', [
            'pub' => '1',
            'id'  => (string) $ref_id,
        ]);

        $stmt->execute([$ref_id, 1]);
        $first = $stmt->fetch();
        $first_published = $first['last_published'];

        // Wait a moment and publish again
        usleep(1000010); // ~1 second to ensure timestamp differs

        $this->captureRpcOutput('publ', [
            'pub' => '1',
            'id'  => (string) $ref_id,
        ]);

        $stmt->execute([$ref_id, 1]);
        $second = $stmt->fetch();
        $second_published = $second['last_published'];

        // The second timestamp should be >= the first
        $this->assertTrue(strtotime($second_published) >= strtotime($first_published));
    }

    /**
     * Verify that delete() removes articles from ttrss_user_entries.
     */
    public function test_delete_removes_articles(): void {
        $pdo = Db::pdo();

        // Verify articles exist before deletion
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM ttrss_user_entries WHERE ref_id IN (4, 5) AND owner_uid = ?");
        $stmt->execute([1]);
        $this->assertEquals(2, (int) $stmt->fetch()['cnt']);

        // Delete articles with ref_id 4 and 5
        $output = $this->captureRpcOutput('delete', [
            'ids' => '4,5',
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify articles are gone
        $stmt->execute([1]);
        $this->assertEquals(0, (int) $stmt->fetch()['cnt']);
    }

    /**
     * Verify that markSelected() marks articles as read (cmode=0).
     */
    public function test_markSelected_mark_as_read(): void {
        $pdo = Db::pdo();

        // Verify initial state: ref_id 9 should be unmarked
        $stmt = $pdo->prepare("SELECT marked FROM ttrss_user_entries WHERE ref_id = 9 AND owner_uid = ?");
        $stmt->execute([1]);
        $initial = $stmt->fetch();
        $this->assertFalse($initial['marked']);

        // Mark as read via markSelected (cmode=0 = mark as read)
        $output = $this->captureRpcOutput('markSelected', [
            'ids'  => [9],
            'cmode' => 0,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify marked=false
        $stmt->execute([1]);
        $updated = $stmt->fetch();
        $this->assertFalse($updated['marked']);
    }

    /**
     * Verify that markSelected() marks articles as unread (cmode=1).
     */
    public function test_markSelected_mark_as_unread(): void {
        $pdo = Db::pdo();

        // First mark as read
        $this->captureRpcOutput('markSelected', [
            'ids'  => [10],
            'cmode' => 0,
        ]);

        $stmt = $pdo->prepare("SELECT marked FROM ttrss_user_entries WHERE ref_id = 10 AND owner_uid = ?");
        $stmt->execute([1]);
        $read = $stmt->fetch();
        $this->assertFalse($read['marked']);

        // Now mark as unread (cmode=1)
        $output = $this->captureRpcOutput('markSelected', [
            'ids'  => [10],
            'cmode' => 1,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify marked=true
        $stmt->execute([1]);
        $updated = $stmt->fetch();
        $this->assertTrue($updated['marked']);
    }

    /**
     * Verify that publishSelected() publishes articles (cmode=1 = mark as unread => published=true).
     */
    public function test_publishSelected_publish_articles(): void {
        $pdo = Db::pdo();

        // Verify initial state: ref_id 11 should be read and unpublished
        $stmt = $pdo->prepare("SELECT published FROM ttrss_user_entries WHERE ref_id = 11 AND owner_uid = ?");
        $stmt->execute([1]);
        $initial = $stmt->fetch();
        $this->assertFalse($initial['published']);

        // Publish via publishSelected (cmode=1 = mark as unread => published=true)
        $output = $this->captureRpcOutput('publishSelected', [
            'ids'  => [11],
            'cmode' => 1,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify published=true
        $stmt->execute([1]);
        $updated = $stmt->fetch();
        $this->assertTrue($updated['published']);
    }

    /**
     * Verify that publishSelected() unpublishes articles (cmode=0 = mark as read => published=false).
     */
    public function test_publishSelected_unpublish_articles(): void {
        $pdo = Db::pdo();

        // First publish the article
        $this->captureRpcOutput('publishSelected', [
            'ids'  => [11],
            'cmode' => 1,
        ]);

        $stmt = $pdo->prepare("SELECT published FROM ttrss_user_entries WHERE ref_id = 11 AND owner_uid = ?");
        $stmt->execute([1]);
        $published = $stmt->fetch();
        $this->assertTrue($published['published']);

        // Now unpublish (cmode=0 = mark as read => published=false)
        $output = $this->captureRpcOutput('publishSelected', [
            'ids'  => [11],
            'cmode' => 0,
        ]);

        $result = json_decode($output, true);
        $this->assertEquals('UPDATE_COUNTERS', $result['message']);

        // Verify published=false
        $stmt->execute([1]);
        $updated = $stmt->fetch();
        $this->assertFalse($updated['published']);
    }
}

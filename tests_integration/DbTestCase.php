<?php
use PHPUnit\Framework\TestCase;

/**
 * Base class for database integration tests that invoke classes directly
 * (no API layer).
 *
 * Provides shared infrastructure: database seeding via seed.sql, and
 * a default session user (uid=1) for methods that depend on $_SESSION.
 */
abstract class DbTestCase extends TestCase {

    /**
     * Re-seed the database with synthetic test data before each test.
     */
    protected function setUp(): void {
        init_plugins();

        $this->seedDatabase();

        // fake having an authenticated user
        $_SESSION["uid"] = 1;
    }

    protected function tearDown(): void {
        if (session_status() == PHP_SESSION_ACTIVE)
            session_destroy();

        // reset any possible local config changes
        Config::get_instance()->reload();

		// cancel any possible active transactions
		$pdo = Db::pdo();

		try {
			$pdo->rollBack();
		} catch (\Throwable $e) {
			//
		}
    }

    /**
     * Execute seed.sql to re-populate the database with synthetic test data.
     *
     * Wrapped in a transaction to ensure atomicity and minimize lock duration.
     * The seed.sql uses TRUNCATE (not DELETE) for faster, shorter-lived locks.
     */
    protected function seedDatabase(): void {
        $seedPath = __DIR__ . "/seed.sql";

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
}

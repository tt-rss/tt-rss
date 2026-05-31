<?php
/** @group integration */
final class SchedulerIntegrationTest extends DbTestCase {

    // ── add_scheduled_task: success ──

    public function test_add_scheduled_task_returns_true(): void {
        $scheduler = new Scheduler('test-scheduler');

        $result = $scheduler->add_scheduled_task(
            'test_task',
            '@daily',
            function() { return 0; }
        );

        $this->assertTrue($result);
    }

    public function test_add_scheduled_task_stores_cron(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'hourly_task',
            '0 * * * *',
            function() { return 0; }
        );

        // Reflect to inspect private property
        $ref = new ReflectionClass($scheduler);
        $prop = $ref->getProperty('scheduled_tasks');
        $prop->setAccessible(true);
        $tasks = $prop->getValue($scheduler);

        $this->assertArrayHasKey('hourly_task', $tasks);
        $this->assertInstanceOf(Cron\CronExpression::class, $tasks['hourly_task']['cron']);
        $this->assertIsCallable($tasks['hourly_task']['callback']);
    }

    public function test_add_scheduled_task_name_lowercased(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'UPPERCASE_TASK',
            '@daily',
            function() { return 0; }
        );

        $ref = new ReflectionClass($scheduler);
        $prop = $ref->getProperty('scheduled_tasks');
        $prop->setAccessible(true);
        $tasks = $prop->getValue($scheduler);

        $this->assertArrayHasKey('uppercase_task', $tasks);
        $this->assertArrayNotHasKey('UPPERCASE_TASK', $tasks);
    }

    // ── add_scheduled_task: failure cases ──

    public function test_add_scheduled_task_duplicate_returns_false(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'dup_task',
            '@daily',
            function() { return 0; }
        );

        // Suppress the user_error warning
        set_error_handler(function() { return true; });

        $result = $scheduler->add_scheduled_task(
            'dup_task',
            '@hourly',
            function() { return 0; }
        );

        restore_error_handler();

        $this->assertFalse($result);
    }

    public function test_add_scheduled_task_invalid_cron_returns_false(): void {
        $scheduler = new Scheduler('test-scheduler');

        set_error_handler(function() { return true; });

        $result = $scheduler->add_scheduled_task(
            'bad_cron_task',
            'not-a-cron-expression',
            function() { return 0; }
        );

        restore_error_handler();

        $this->assertFalse($result);
    }

    // ── set_name ──

    public function test_set_name(): void {
        $scheduler = new Scheduler('original-name');

        $ref = new ReflectionClass($scheduler);
        $prop = $ref->getProperty('name');
        $prop->setAccessible(true);

        $this->assertEquals('original-name', $prop->getValue($scheduler));

        $scheduler->set_name('new-name');
        $this->assertEquals('new-name', $prop->getValue($scheduler));
    }

    // ── run_due_tasks: basic execution ──

    public function test_run_due_tasks_executes_due_task(): void {
		$scheduler = new Scheduler('test-scheduler');
        $executed = false;

        $scheduler->add_scheduled_task(
            'due_task',
            '@daily',
            function() use (&$executed) {
                $executed = true;
                return 0;
            }
        );

        // Insert a DB record with last_run far in the past so the task is due
        $pdo = Db::pdo();
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('due_task', 0, 0, '2020-01-01 00:00:00', '@daily')
        ");

        $scheduler->run_due_tasks();

        $this->assertTrue($executed);
    }

    public function test_run_due_tasks_records_result_in_db(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'record_task',
            '@daily',
            function() { return 42; }
        );

        $pdo = Db::pdo();
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('record_task', 0, 0, '2020-01-01 00:00:00', '@daily')
        ");

        $scheduler->run_due_tasks();

        $row = $pdo->query("
            SELECT last_rc, last_duration, last_cron_expression
            FROM ttrss_scheduled_tasks
            WHERE task_name = 'record_task'
        ")->fetch();

        $this->assertEquals(42, (int) $row['last_rc']);
        $this->assertGreaterThanOrEqual(0, (int) $row['last_duration']);
        // Cron aliases are expanded to full expressions
        $this->assertMatchesRegularExpression('/^0\s+0\s+\*\s+\*\s+\*$/', $row['last_cron_expression']);
    }

    public function test_run_due_tasks_creates_db_record_for_new_task(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'new_task',
            '@weekly',
            function() { return 0; }
        );

        // No existing DB record — task should be created
        $pdo = Db::pdo();
        $countBefore = (int) $pdo->query("SELECT count(*) FROM ttrss_scheduled_tasks WHERE task_name = 'new_task'")->fetchColumn();
        $this->assertEquals(0, $countBefore);

        $scheduler->run_due_tasks();

        $countAfter = (int) $pdo->query("SELECT count(*) FROM ttrss_scheduled_tasks WHERE task_name = 'new_task'")->fetchColumn();
        $this->assertEquals(1, $countAfter);
    }

    public function test_run_due_tasks_skips_not_due(): void {
        $scheduler = new Scheduler('test-scheduler');
        $executed = false;

        $scheduler->add_scheduled_task(
            'not_due_task',
            '@yearly',
            function() use (&$executed) {
                $executed = true;
                return 0;
            }
        );

        // Set last_run to yesterday — @yearly next run is in the future
        $pdo = Db::pdo();
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('not_due_task', 0, 0, NOW() - INTERVAL '1 day', '@yearly')
        ");

        $scheduler->run_due_tasks();

        $this->assertFalse($executed);
    }

    // ── run_due_tasks: exception handling ──

    public function test_run_due_tasks_catches_exception(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'failing_task',
            '@daily',
            function() {
                throw new \RuntimeException('Task failed');
            }
        );

        $pdo = Db::pdo();
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('failing_task', 0, 0, '2020-01-01 00:00:00', '@daily')
        ");

        // Suppress warnings from user_error
        set_error_handler(function() { return true; });

        $scheduler->run_due_tasks();

        restore_error_handler();

        $row = $pdo->query("SELECT last_rc FROM ttrss_scheduled_tasks WHERE task_name = 'failing_task'")->fetch();
        $this->assertEquals(Scheduler::TASK_RC_EXCEPTION, (int) $row['last_rc']);
    }

    // ── purge_orphaned_tasks ──

    public function test_purge_orphaned_tasks_removes_old_orphaned(): void {
        $scheduler = new Scheduler('test-scheduler');

        // Register only 'keep_me' — 'orphan_old' and 'orphan_new' are not registered
        $scheduler->add_scheduled_task(
            'keep_me',
            '@daily',
            function() { return 0; }
        );

        $pdo = Db::pdo();

        // Insert an old orphaned task (> 5 weeks ago)
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('orphan_old', 0, 0, NOW() - INTERVAL '6 weeks', '@daily')
        ");

        // Insert a recent orphaned task (< 5 weeks ago) — should NOT be purged
        $pdo->exec("
            INSERT INTO ttrss_scheduled_tasks (task_name, last_duration, last_rc, last_run, last_cron_expression)
            VALUES ('orphan_new', 0, 0, NOW() - INTERVAL '1 week', '@daily')
        ");

        $before = (int) $pdo->query("SELECT count(*) FROM ttrss_scheduled_tasks")->fetchColumn();

        // Use reflection to invoke the private purge_orphaned_tasks method
        $ref = new ReflectionClass($scheduler);
        $purgeMethod = $ref->getMethod('purge_orphaned_tasks');
        $purgeMethod->setAccessible(true);

        $purgeResult = $scheduler->add_scheduled_task(
            'purge_trigger',
            '@daily',
            function() use ($purgeMethod, $scheduler) {
                return $purgeMethod->invoke($scheduler);
            }
        );
        $this->assertTrue($purgeResult);

        // Run the purge via run_due_tasks
        $scheduler->run_due_tasks();

        $after = (int) $pdo->query("SELECT count(*) FROM ttrss_scheduled_tasks")->fetchColumn();

        // We added keep_me and purge_trigger records (+2) and deleted orphan_old (-1)
        $this->assertEquals($before + 1, $after);

        // Verify orphan_new still exists
        $exists = (int) $pdo->query("SELECT count(*) FROM ttrss_scheduled_tasks WHERE task_name = 'orphan_new'")->fetchColumn();
        $this->assertEquals(1, $exists);
    }

    public function test_purge_orphaned_tasks_returns_zero_on_success(): void {
        $scheduler = new Scheduler('test-scheduler');

        $scheduler->add_scheduled_task(
            'keep_me',
            '@daily',
            function() { return 0; }
        );

        // Use reflection to invoke the private purge_orphaned_tasks method
        $ref = new ReflectionClass($scheduler);
        $purgeMethod = $ref->getMethod('purge_orphaned_tasks');
        $purgeMethod->setAccessible(true);

        $purgeResult = $scheduler->add_scheduled_task(
            'purge_trigger',
            '@daily',
            function() use ($purgeMethod, $scheduler) {
                return $purgeMethod->invoke($scheduler);
            }
        );
        $this->assertTrue($purgeResult);

        $scheduler->run_due_tasks();

        $row = Db::pdo()->query("SELECT last_rc FROM ttrss_scheduled_tasks WHERE task_name = 'purge_trigger'")->fetch();
        $this->assertEquals(0, (int) $row['last_rc']);
    }

    // ── Default scheduler (singleton) ──

    public function test_default_scheduler_has_purge_task(): void {
        $scheduler = Scheduler::getInstance();

        $ref = new ReflectionClass($scheduler);
        $prop = $ref->getProperty('scheduled_tasks');
        $prop->setAccessible(true);
        $tasks = $prop->getValue($scheduler);

        $this->assertArrayHasKey('purge_orphaned_scheduled_tasks', $tasks);
    }

    public function test_default_scheduler_name(): void {
        $scheduler = Scheduler::getInstance();

        $ref = new ReflectionClass($scheduler);
        $prop = $ref->getProperty('name');
        $prop->setAccessible(true);

        $this->assertEquals(Scheduler::DEFAULT_NAME, $prop->getValue($scheduler));
    }
}

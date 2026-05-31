<?php
/** @group integration */
final class LoggerSqlTest extends DbTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Ensure SQL logging is enabled
        Config::set(Config::LOG_DESTINATION, Logger::LOG_DEST_SQL);
        // Reset singleton so new adapter is created
        $this->resetLoggerSingleton();
    }

    protected function tearDown(): void {
        $this->resetLoggerSingleton();
        parent::tearDown();
    }

    private function resetLoggerSingleton(): void {
        $ref = new ReflectionClass(Logger::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);
    }

    public function test_sql_adapter_logs_error(): void {
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_WARNING,
            "Test SQL error message",
            "test_sql.php",
            42,
            "Test context for SQL logging"
        );

        $this->assertTrue($result);

        // Verify the error was written to the database
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT id, errno, errstr, filename, lineno, context FROM ttrss_error_log
             WHERE errstr = 'Test SQL error message' AND filename = 'test_sql.php'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        $this->assertEquals(2, (int) $row['errno']); // E_WARNING
        $this->assertEquals("Test SQL error message", $row['errstr']);
        $this->assertEquals("test_sql.php", $row['filename']);
        $this->assertEquals(42, (int) $row['lineno']);
        $this->assertStringContainsString("Test context for SQL logging", $row['context']);
    }

    public function test_sql_adapter_logs_user_error(): void {
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_USER_ERROR,
            "User error in SQL adapter test",
            "user_error_test.php",
            100,
            "User error context"
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT id, errno, errstr FROM ttrss_error_log
             WHERE errstr = 'User error in SQL adapter test'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        $this->assertEquals(256, (int) $row['errno']); // E_USER_ERROR
    }

    public function test_sql_adapter_context_max_length(): void {
        $longContext = str_repeat("x", 20000); // Well over 8192 chars
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_NOTICE,
            "Long context test",
            "long_ctx.php",
            1,
            $longContext
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT context FROM ttrss_error_log
             WHERE errstr = 'Long context test'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        // Context should be truncated to 8192 chars
        $this->assertLessThanOrEqual(8192, mb_strlen($row['context']));
    }

    public function test_sql_adapter_includes_server_params(): void {
        // Set a fake server param to verify it's included in context
        $_SERVER['HTTP_X_REAL_IP'] = '192.168.1.100';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';

        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_NOTICE,
            "Server params test",
            "server_params.php",
            5,
            "Base context"
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT context FROM ttrss_error_log
             WHERE errstr = 'Server params test'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        $this->assertStringContainsString("Base context", $row['context']);
        $this->assertStringContainsString("Real IP: 192.168.1.100", $row['context']);
        $this->assertStringContainsString("Forwarded For: 10.0.0.1", $row['context']);

        // Clean up
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function test_sql_adapter_logs_different_error_levels(): void {
        $adapter = new Logger_SQL();

        $errorLevels = [
            E_ERROR,
            E_WARNING,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_USER_WARNING,
            E_USER_NOTICE,
            E_STRICT,
            E_DEPRECATED,
            E_USER_DEPRECATED,
        ];

        foreach ($errorLevels as $errno) {
            $name = Logger::ERROR_NAMES[$errno];
            $result = $adapter->log_error(
                $errno,
                "Error level test: $name",
                "error_levels.php",
                10,
                ""
            );
            $this->assertTrue($result, "Failed to log error level $name");
        }

        // Verify all errors were logged
        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM ttrss_error_log
             WHERE errstr LIKE 'Error level test:%' AND filename = 'error_levels.php'"
        );
        $sth->execute();
        $row = $sth->fetch();
        $this->assertEquals(count($errorLevels), (int) $row['cnt']);
    }

    public function test_sql_adapter_with_unicode(): void {
        $unicodeStr = "Unicode error: café résumé naïve 日本語 中文";
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_WARNING,
            $unicodeStr,
            "unicode_test.php",
            1,
            "Unicode context: ñ ü ö ä"
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT errstr, context FROM ttrss_error_log
             WHERE errstr = ? AND filename = 'unicode_test.php'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute([$unicodeStr]);
        $row = $sth->fetch();

        $this->assertNotNull($row);
        $this->assertEquals($unicodeStr, $row['errstr']);
        $this->assertStringContainsString("Unicode context", $row['context']);
    }

    public function test_sql_adapter_with_empty_context(): void {
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_NOTICE,
            "Empty context test",
            "empty_ctx.php",
            1,
            ""
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT context FROM ttrss_error_log
             WHERE errstr = 'Empty context test'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        // Context may or may not have server params depending on environment;
        // just verify the row was created with a valid context field
        $this->assertIsString($row['context']);
    }

    public function test_sql_adapter_uses_current_timestamp(): void {
        $adapter = new Logger_SQL();
        $result = $adapter->log_error(
            E_NOTICE,
            "Timestamp test",
            "timestamp.php",
            1,
            ""
        );

        $this->assertTrue($result);

        $pdo = Db::pdo();
        $sth = $pdo->prepare(
            "SELECT created_at FROM ttrss_error_log
             WHERE errstr = 'Timestamp test'
             ORDER BY id DESC LIMIT 1"
        );
        $sth->execute();
        $row = $sth->fetch();

        $this->assertNotNull($row);
        // created_at should be a valid datetime
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $row['created_at']
        );
    }
}

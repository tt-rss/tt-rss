<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Templator — template file resolution and rendering.
 *
 * Templator extends MiniTemplator and adds one responsibility:
 *   - basename resolution: try templates.local/ first, fall back to templates/
 *   - full/relative paths: delegate to parent unchanged
 *
 * These tests use temporary directories to avoid polluting the real
 * templates/ and templates.local/ trees.
 *
 * @group templator
 */
final class TemplatorTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->tempDir = sys_get_temp_dir() . '/tt-rss-templator-test-' . uniqid();
        mkdir($this->tempDir . '/templates.local', 0755, true);
        mkdir($this->tempDir . '/templates', 0755, true);
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDir($this->tempDir);
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTemplator(): Templator
    {
        return new Templator();
    }

    public function testReadTemplateFromTemplatesLocalTakesPriority(): void
    {
        file_put_contents('templates.local/test.tpl', 'FROM_LOCAL');
        file_put_contents('templates/test.tpl', 'FROM_DEFAULT');

        $t = $this->createTemplator();
        $result = $t->readTemplateFromFile('test.tpl');

        $this->assertTrue($result);

        $output = '';
        $t->generateOutputToString($output);
        $this->assertEquals('FROM_LOCAL', $output);
    }

    public function testReadTemplateFromTemplatesAsFallback(): void
    {
        file_put_contents('templates/test.tpl', 'FROM_DEFAULT');

        $t = $this->createTemplator();
        $result = $t->readTemplateFromFile('test.tpl');

        $this->assertTrue($result);

        $output = '';
        $t->generateOutputToString($output);
        $this->assertEquals('FROM_DEFAULT', $output);
    }

    public function testFullPathIsDelegatedUnchanged(): void
    {
        mkdir('templates/subdir', 0755, true);
        file_put_contents('templates/subdir/test.tpl', 'FROM_SUBDIR');

        $t = $this->createTemplator();
        $result = $t->readTemplateFromFile('templates/subdir/test.tpl');

        $this->assertTrue($result);

        $output = '';
        $t->generateOutputToString($output);
        $this->assertEquals('FROM_SUBDIR', $output);
    }

    public function testNonExistentFileReturnsFalse(): void
    {
        // Suppress E_USER_ERROR from MiniTemplator::triggerError so we can
        // assert the return value instead of having the test abort.
        set_error_handler(function ($errno, $errstr) {
            if ($errno === E_USER_ERROR) {
                return true;
            }
            return false;
        });

        try {
            $t = $this->createTemplator();
            $result = $t->readTemplateFromFile('nonexistent.tpl');

            $this->assertFalse($result);
        } finally {
            restore_error_handler();
        }
    }
}

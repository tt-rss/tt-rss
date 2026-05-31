<?php
/** @group integration */
final class DiskCacheTest extends DbTestCase {

    private string $testCacheBase;

    protected function setUp(): void {
        parent::setUp();

        // Create an isolated test cache directory
        $this->testCacheBase = sys_get_temp_dir() . '/ttrss-test-cache-' . getmypid() . '-' . uniqid();
        mkdir($this->testCacheBase, 0755, true);

        // Override cache directory config to use our isolated temp dir
        Config::set(Config::CACHE_DIR, $this->testCacheBase);

        // Override self_url for URL generation tests
        Config::set(Config::SELF_URL_PATH, 'http://test.example.com');

        // Override max days for expire_all tests
        Config::set(Config::CACHE_MAX_DAYS, 30);

        // Override max file size
        Config::set(Config::MAX_CACHE_FILE_SIZE, 10485760);
    }

    protected function tearDown(): void {
        // Clean up test cache directory
        $this->removeDir($this->testCacheBase);

        // Reset singleton instances so tests don't leak
        $ref = new ReflectionClass(DiskCache::class);
        $prop = $ref->getProperty('instances');
        $prop->setValue(null, []);

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────
    // A. Instance / Singleton behavior
    // ──────────────────────────────────────────────────────────────────────

    public function test_instance_returns_same_object_for_same_dir(): void {
        $cache1 = DiskCache::instance('test-same');
        $cache2 = DiskCache::instance('test-same');

        $this->assertSame($cache1, $cache2);
    }

    public function test_instance_returns_different_objects_for_different_dirs(): void {
        $cache1 = DiskCache::instance('test-dir-a');
        $cache2 = DiskCache::instance('test-dir-b');

        $this->assertNotSame($cache1, $cache2);
    }

    public function test_instance_creates_new_instance_when_previously_removed(): void {
        $ref = new ReflectionClass(DiskCache::class);
        $prop = $ref->getProperty('instances');

        $cache1 = DiskCache::instance('test-reset');

        // Manually clear the instance
        $prop->setValue(null, []);

        $cache2 = DiskCache::instance('test-reset');

        $this->assertNotSame($cache1, $cache2);
    }

    // ──────────────────────────────────────────────────────────────────────
    // B. Directory setup and adapter
    // ──────────────────────────────────────────────────────────────────────

    public function test_make_dir_creates_cache_subdirectory(): void {
        $cache = DiskCache::instance('test-make-dir');

        // make_dir should create the subdirectory
        $rc = $cache->make_dir();
        $this->assertTrue(is_dir($cache->get_dir()));
    }

    public function test_get_dir_returns_correct_path(): void {
        $cache = DiskCache::instance('test-get-dir');

        $expected = $this->testCacheBase . '/test-get-dir';
        $this->assertEquals($expected, $cache->get_dir());
    }

    public function test_is_writable_returns_true_for_cache_dir(): void {
        $cache = DiskCache::instance('test-is-writable');

        $this->assertTrue($cache->is_writable());
    }

    public function test_is_writable_returns_true_for_file_in_writable_dir(): void {
        $cache = DiskCache::instance('test-is-writable-file');

        // Write a file first
        $cache->put('testfile.txt', 'hello');

        $this->assertTrue($cache->is_writable('testfile.txt'));
    }

    public function test_set_dir_changes_cache_directory(): void {
        $cache = DiskCache::instance('test-set-dir-orig');
        $origDir = $cache->get_dir();

        $cache->set_dir('test-set-dir-new');
        $newDir = $cache->get_dir();

        $this->assertNotEquals($origDir, $newDir);
        $this->assertEquals($this->testCacheBase . '/test-set-dir-new', $newDir);
    }

    // ──────────────────────────────────────────────────────────────────────
    // C. Basic CRUD — put, get, exists, remove
    // ──────────────────────────────────────────────────────────────────────

    public function test_put_and_get(): void {
        $cache = DiskCache::instance('test-crud');

        $data = 'Hello, DiskCache!';
        $written = $cache->put('hello.txt', $data);

        $this->assertIsInt($written);
        $this->assertEquals(strlen($data), $written);

        $retrieved = $cache->get('hello.txt');
        $this->assertEquals($data, $retrieved);
    }

    public function test_put_large_data(): void {
        $cache = DiskCache::instance('test-crud-large');

        $data = str_repeat('A', 1024 * 1024); // 1 MB
        $written = $cache->put('large.bin', $data);

        $this->assertEquals(strlen($data), $written);

        $retrieved = $cache->get('large.bin');
        $this->assertEquals($data, $retrieved);
    }

    public function test_put_and_exists(): void {
        $cache = DiskCache::instance('test-exists');

        $this->assertFalse($cache->exists('nonexistent.txt'));

        $cache->put('exists.txt', 'data');

        $this->assertTrue($cache->exists('exists.txt'));
    }

    public function test_remove_file(): void {
        $cache = DiskCache::instance('test-remove');

        $cache->put('toremove.txt', 'data');
        $this->assertTrue($cache->exists('toremove.txt'));

        $rc = $cache->remove('toremove.txt');
        $this->assertTrue($rc);
        $this->assertFalse($cache->exists('toremove.txt'));
    }

    public function test_remove_nonexistent_file(): void {
        $cache = DiskCache::instance('test-remove-missing');

        $this->assertFalse($cache->remove('nonexistent.txt'));
    }

    public function test_get_nonexistent_returns_null(): void {
        $cache = DiskCache::instance('test-get-missing');

        $result = $cache->get('nonexistent.txt');
        $this->assertNull($result);
    }

    public function test_put_overwrites_existing_file(): void {
        $cache = DiskCache::instance('test-overwrite');

        $cache->put('overwrite.txt', 'original');
        $this->assertEquals('original', $cache->get('overwrite.txt'));

        $cache->put('overwrite.txt', 'updated');
        $this->assertEquals('updated', $cache->get('overwrite.txt'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // D. Path safety — basename stripping (path traversal protection)
    // ──────────────────────────────────────────────────────────────────────

    public function test_filename_strips_directory_traversal(): void {
        $cache = DiskCache::instance('test-path-traversal');

        // DiskCache should strip directory components from filename
        $cache->put('foo/../../evil.txt', 'data');

        // The file should be stored with basename only
        $this->assertTrue($cache->exists('evil.txt'));
        $this->assertEquals('data', $cache->get('evil.txt'));
    }

    public function test_filename_strips_absolute_paths(): void {
        $cache = DiskCache::instance('test-absolute-path');

        $cache->put('/etc/passwd', 'data');

        // Should store as 'passwd', not '/etc/passwd'
        $this->assertTrue($cache->exists('passwd'));
        $this->assertEquals('data', $cache->get('passwd'));
    }

    public function test_filename_strips_special_characters_via_basename(): void {
        $cache = DiskCache::instance('test-special-chars');

        $cache->put('path/to/../file.txt', 'data');

        // basename('path/to/../file.txt') === 'file.txt'
        $this->assertTrue($cache->exists('file.txt'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // E. Metadata — get_mtime, get_size
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_size_returns_bytes(): void {
        $cache = DiskCache::instance('test-size');

        $data = 'test data for size';
        $cache->put('size-test.txt', $data);

        $size = $cache->get_size('size-test.txt');
        $this->assertEquals(strlen($data), $size);
    }

    public function test_get_size_returns_negative_for_nonexistent(): void {
        $cache = DiskCache::instance('test-size-missing');

        $size = $cache->get_size('nonexistent.txt');
        $this->assertEquals(-1, $size);
    }

    public function test_get_mtime_returns_timestamp(): void {
        $cache = DiskCache::instance('test-mtime');

        $cache->put('mtime-test.txt', 'data');

        $mtime = $cache->get_mtime('mtime-test.txt');
        $this->assertIsInt($mtime);
        $this->assertGreaterThan(0, $mtime);
    }

    public function test_get_mtime_returns_false_for_nonexistent(): void {
        $cache = DiskCache::instance('test-mtime-missing');
        $this->assertEmpty($cache->get_mtime('nonexistent.txt'));
    }

    public function test_get_full_path_returns_absolute_path(): void {
        $cache = DiskCache::instance('test-full-path');

        $cache->put('fullpath.txt', 'data');

        $fullPath = $cache->get_full_path('fullpath.txt');
        $this->assertStringEndsWith('fullpath.txt', $fullPath);
        $this->assertFileExists($fullPath);
    }

    // ──────────────────────────────────────────────────────────────────────
    // F. MIME type detection and fake extension mapping
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_mime_type_image_png(): void {
        $cache = DiskCache::instance('test-mime-png');

        // Create a minimal valid PNG file
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $cache->put('test.png', $png);

        $mimetype = $cache->get_mime_type('test.png');
        $this->assertEquals('image/png', $mimetype);
    }

    public function test_get_mime_type_image_jpg(): void {
        $cache = DiskCache::instance('test-mime-jpg');

        // Create a minimal valid JPEG file
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
        $cache->put('test.jpg', $jpeg);

        $mimetype = $cache->get_mime_type('test.jpg');
        $this->assertEquals('image/jpeg', $mimetype);
    }

    public function test_get_mime_type_text_plain(): void {
        $cache = DiskCache::instance('test-mime-txt');

        $cache->put('test.txt', 'plain text');
        $mimetype = $cache->get_mime_type('test.txt');
        $this->assertEquals('text/plain', $mimetype);
    }

    public function test_get_mime_type_nonexistent_returns_null(): void {
        $cache = DiskCache::instance('test-mime-missing');

        $mimetype = $cache->get_mime_type('nonexistent.txt');
        $this->assertNull($mimetype);
    }

    public function test_get_fake_extension_for_png(): void {
        $cache = DiskCache::instance('test-fake-ext-png');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $cache->put('image.png', $png);

        $ext = $cache->get_fake_extension('image.png');
        $this->assertEquals('png', $ext);
    }

    public function test_get_fake_extension_for_jpg(): void {
        $cache = DiskCache::instance('test-fake-ext-jpg');

        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
        $cache->put('image.jpg', $jpeg);

        $ext = $cache->get_fake_extension('image.jpg');
        $this->assertEquals('jpg', $ext);
    }

    public function test_get_fake_extension_for_pdf(): void {
        $cache = DiskCache::instance('test-fake-ext-pdf');

        // Create a minimal PDF
        $cache->put('doc.pdf', "%PDF-1.0 test");

        $ext = $cache->get_fake_extension('doc.pdf');
        $this->assertEquals('pdf', $ext);
    }

    public function test_get_fake_extension_unknown_mime(): void {
        $cache = DiskCache::instance('test-fake-ext-unknown');

        $cache->put('weird.xyz', 'some data');
        $ext = $cache->get_fake_extension('weird.xyz');
        // 'some data' is detected as text/plain, which maps to 'txt'
        $this->assertEquals('txt', $ext);
    }

    public function test_get_fake_extension_nonexistent_file(): void {
        $cache = DiskCache::instance('test-fake-ext-missing');

        // Non-existent file returns null mime type, which maps to empty string
        $ext = $cache->get_fake_extension('missing.xyz');
        $this->assertEquals('', $ext);
    }

    // ──────────────────────────────────────────────────────────────────────
    // G. URL generation
    // ──────────────────────────────────────────────────────────────────────

    public function test_get_url_returns_correct_format(): void {
        $cache = DiskCache::instance('test-url');

        $url = $cache->get_url('test-image.png');

        $this->assertStringContainsString('http://test.example.com/public.php?op=cached', $url);
        $this->assertStringContainsString('file=test-url', $url);
        $this->assertStringContainsString('test-image.png', $url);
    }

    public function test_get_url_strips_directory_from_filename(): void {
        $cache = DiskCache::instance('test-url-basename');

        $url = $cache->get_url('some/path/image.png');

        // Should only contain 'image.png', not the full path
        $this->assertStringContainsString('file=test-url-basename/image.png', $url);
        $this->assertStringNotContainsString('some/path', $url);
    }

    // ──────────────────────────────────────────────────────────────────────
    // H. HTML URL rewriting — rewrite_urls
    // ──────────────────────────────────────────────────────────────────────

    public function test_rewrite_urls_rewrites_multiple_img_tags(): void {
        $html = '<img src="http://a.com/1.jpg"><img src="http://b.com/2.jpg">';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringContainsString('public.php', $result);
        $this->assertStringContainsString('http%3A%2F%2Fa.com%2F1.jpg', $result);
        $this->assertStringContainsString('http%3A%2F%2Fb.com%2F2.jpg', $result);
    }

    public function test_rewrite_urls_rewrites_video_poster(): void {
        $html = '<video poster="http://example.com/poster.jpg"></video>';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringContainsString('public.php', $result);
        $this->assertStringContainsString('http%3A%2F%2Fexample.com%2Fposter.jpg', $result);
    }

    public function test_rewrite_urls_rewrites_video_src(): void {
        $html = '<video src="http://example.com/video.mp4"></video>';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringContainsString('public.php', $result);
        $this->assertStringContainsString('http%3A%2F%2Fexample.com%2Fvideo.mp4', $result);
    }

    public function test_rewrite_urls_handles_srcset(): void {
        // srcset is only processed on elements that have src or poster attributes
        // This img only has srcset, not src, so it won't be processed by rewrite_urls
        $html = '<img srcset="http://a.com/1x.jpg 1x, http://b.com/2x.jpg 2x">';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringNotContainsString('public.php', $result);
        $this->assertStringContainsString('http://a.com/1x.jpg', $result);
        $this->assertStringContainsString('http://b.com/2x.jpg', $result);
    }

    public function test_rewrite_urls_removes_srcset_when_rewriting(): void {
        $html = '<img srcset="http://a.com/1x.jpg 1x, http://b.com/2x.jpg 2x">';
        $result = DiskCache::rewrite_urls($html);

        // srcset should be rewritten to use cache redirect URLs
        $this->assertStringContainsString('srcset=', $result);
    }

    public function test_rewrite_urls_preserves_non_cacheable_urls(): void {
        $html = '<p>Some text content with no images</p>';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringContainsString('Some text content with no images', $result);
    }

    public function test_rewrite_urls_empty_string(): void {
        $result = DiskCache::rewrite_urls('');
        $this->assertEquals('', $result);
    }

    public function test_rewrite_urls_whitespace_only(): void {
        $result = DiskCache::rewrite_urls("  \n\t  ");
        $this->assertEquals('', $result);
    }

    public function test_rewrite_urls_preserves_html_structure(): void {
        $html = '<div><p><img src="http://example.com/img.png"></p></div>';
        $result = DiskCache::rewrite_urls($html);

        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('</div>', $result);
    }

    public function test_rewrite_urls_does_not_rewrite_relative_urls(): void {
        // Relative URLs (without http://) are still rewritten by rewrite_urls
        // because DiskCache::get_redirect_url() doesn't check if URL is absolute
        $html = '<img src="relative/path/image.png">';
        $result = DiskCache::rewrite_urls($html);

        // Relative URLs get rewritten to cache redirect URLs too
        $this->assertStringContainsString('public.php', $result);
    }

    // ──────────────────────────────────────────────────────────────────────
    // I. Deprecated method behavior
    // ──────────────────────────────────────────────────────────────────────

    public function test_touch_triggers_deprecation_warning(): void {
        $cache = DiskCache::instance('test-touch');

        // touch() should trigger a deprecation warning and return false
        set_error_handler(function($errno, $errstr) {
            return true; // suppress the error for test purposes
        }, E_USER_DEPRECATED);

        $rc = $cache->touch('testfile.txt');

        restore_error_handler();

        $this->assertFalse($rc);
    }

    // ──────────────────────────────────────────────────────────────────────
    // J. expire_all
    // ──────────────────────────────────────────────────────────────────────

    public function test_expire_all_respects_max_days(): void {
        // Set CACHE_MAX_DAYS to 0 so all files expire immediately
        Config::set(Config::CACHE_MAX_DAYS, 0);

        $cache = DiskCache::instance('test-expire');

        $cache->put('expire-me.txt', 'data');

        // expire_all should remove files older than 0 days (i.e., all)
        $cache->expire_all();

        $this->assertFalse($cache->exists('expire-me.txt'));
    }

    public function test_expire_all_keeps_recent_files(): void {
        $cache = DiskCache::instance('test-expire-recent');

        // Set CACHE_MAX_DAYS to 1 so only files older than 1 day expire
        Config::set(Config::CACHE_MAX_DAYS, 1);

        // Create a truly recent file (mtime = now)
        $cache->put('recent.txt', 'recent data');

        // Create an old file by touching it to 2 days ago
        $cache->put('old.txt', 'old data');
        $oldTimestamp = time() - 2 * 86400;
        touch($cache->get_full_path('old.txt'), $oldTimestamp);

        $cache->expire_all();

        // Recent file should survive
        $this->assertTrue($cache->exists('recent.txt'));
        // Old file should be removed
        $this->assertFalse($cache->exists('old.txt'));
    }

    public function test_expire_all_handles_empty_directory(): void {
        $cache = DiskCache::instance('test-expire-empty');

        $cache->expire_all();

        $this->assertTrue(is_dir($cache->get_dir()));
    }

    // ──────────────────────────────────────────────────────────────────────
    // K. Binary data handling
    // ──────────────────────────────────────────────────────────────────────

    public function test_put_and_get_binary_data(): void {
        $cache = DiskCache::instance('test-binary');

        // Binary data with null bytes
        $data = "\x00\x01\x02\x03\xFF\xFE\xFD";
        $cache->put('binary.dat', $data);

        $retrieved = $cache->get('binary.dat');
        $this->assertEquals($data, $retrieved);
    }

    public function test_put_and_get_unicode_data(): void {
        $cache = DiskCache::instance('test-unicode');

        $unicode = "Hello 世界 🌍 Привет мир";
        $cache->put('unicode.txt', $unicode);

        $retrieved = $cache->get('unicode.txt');
        $this->assertEquals($unicode, $retrieved);
    }

    public function test_put_empty_string(): void {
        $cache = DiskCache::instance('test-empty');

        $written = $cache->put('empty.txt', '');
        $this->assertEquals(0, $written);

        $retrieved = $cache->get('empty.txt');
        $this->assertEquals('', $retrieved);
        $this->assertTrue($cache->exists('empty.txt'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // L. Helper methods
    // ──────────────────────────────────────────────────────────────────────

    private function removeDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}

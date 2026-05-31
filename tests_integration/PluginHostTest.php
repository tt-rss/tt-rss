<?php
/** @group integration */
final class PluginHostTest extends DbTestCase {

    /**
     * Create a fresh PluginHost instance for isolated testing.
     * Each test gets its own instance so the global singleton is never touched.
     */
    private function createHost(): PluginHost {
        $host = new PluginHost();

        $ref = new ReflectionClass($host);
        $uidProp = $ref->getProperty('owner_uid');
        $uidProp->setValue($host, 1);

        return $host;
    }

    // ── Singleton ──

    public function test_get_instance_returns_same_object(): void {
        $host1 = PluginHost::getInstance();
        $host2 = PluginHost::getInstance();
        $this->assertSame($host1, $host2);
    }

    // ── Hook system ──

    public function test_add_hook_and_get_hooks(): void {
        $plugin = new TestPluginHook();
        $host = $this->createHost();

        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $plugin, 50);

        $hooks = $host->get_hooks(PluginHost::HOOK_ARTICLE_BUTTON);

        $this->assertCount(1, $hooks);
        $this->assertSame($plugin, $hooks[0]);
    }

    public function test_add_hook_respects_priority_order(): void {
        $pluginLow = new TestPluginHook();
        $pluginHigh = new TestPluginHook();
        $host = $this->createHost();

        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $pluginLow, 90);
        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $pluginHigh, 10);

        $hooks = $host->get_hooks(PluginHost::HOOK_ARTICLE_BUTTON);

        $this->assertSame($pluginHigh, $hooks[0]);
        $this->assertSame($pluginLow, $hooks[1]);
    }

    public function test_del_hook_removes_plugin(): void {
        $plugin = new TestPluginHook();
        $host = $this->createHost();

        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $plugin, 50);
        $host->del_hook(PluginHost::HOOK_ARTICLE_BUTTON, $plugin);

        $hooks = $host->get_hooks(PluginHost::HOOK_ARTICLE_BUTTON);
        $this->assertCount(0, $hooks);
    }

    public function test_run_hooks_invokes_all(): void {
        $plugin1 = new TestPluginHook();
        $plugin2 = new TestPluginHook();
        $host = $this->createHost();

        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $plugin1, 50);
        $host->add_hook(PluginHost::HOOK_ARTICLE_BUTTON, $plugin2, 60);

        $host->run_hooks(PluginHost::HOOK_ARTICLE_BUTTON);

        $this->assertTrue($plugin1->hookCalled);
        $this->assertTrue($plugin2->hookCalled);
    }

    public function test_run_hooks_until_stops_on_match(): void {
        $plugin1 = new TestPluginHook();
        $plugin2 = new TestPluginHook();
        $host = $this->createHost();

        $plugin1->hookReturn = true; // first plugin returns true

        $host->add_hook(PluginHost::HOOK_ARTICLE_FILTER, $plugin1, 50);
        $host->add_hook(PluginHost::HOOK_ARTICLE_FILTER, $plugin2, 60);

        $host->run_hooks_until(PluginHost::HOOK_ARTICLE_FILTER, true);

        $this->assertTrue($plugin1->hookCalled);
        // plugin2 should not be called because plugin1 returned true (the check value)
        $this->assertFalse($plugin2->hookCalled);
    }

    public function test_run_hooks_callback_stops_on_true(): void {
        $plugin1 = new TestPluginHook();
        $plugin2 = new TestPluginHook();
        $host = $this->createHost();

        $plugin1->hookReturn = 'stop'; // first plugin returns 'stop'

        $host->add_hook(PluginHost::HOOK_ARTICLE_FILTER, $plugin1, 50);
        $host->add_hook(PluginHost::HOOK_ARTICLE_FILTER, $plugin2, 60);

        $host->run_hooks_callback(PluginHost::HOOK_ARTICLE_FILTER, fn($result) => $result === 'stop');

        $this->assertTrue($plugin1->hookCalled);
        $this->assertFalse($plugin2->hookCalled);
    }

    public function test_get_hooks_returns_empty_for_unknown_hook(): void {
        $host = $this->createHost();
        // @phpstan-ignore argument.type (testing behavior with an unknown hook string)
        $hooks = $host->get_hooks('nonexistent_hook');
        $this->assertCount(0, $hooks);
    }

    // ── Plugin storage (DB-backed) ──

    public function test_set_and_get(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->set($plugin, 'key1', 'value1');

        $this->assertEquals('value1', $host->get($plugin, 'key1'));
    }

    public function test_get_returns_default_when_missing(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $this->assertFalse($host->get($plugin, 'missing_key', false));
        $this->assertEquals('default', $host->get($plugin, 'missing_key', 'default'));
    }

    public function test_set_array(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->set_array($plugin, ['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertEquals(1, $host->get($plugin, 'a'));
        $this->assertEquals(2, $host->get($plugin, 'b'));
        $this->assertEquals(3, $host->get($plugin, 'c'));
    }

    public function test_get_array(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->set($plugin, 'mylist', ['x' => 10, 'y' => 20]);

        $result = $host->get_array($plugin, 'mylist');
        $this->assertEquals(['x' => 10, 'y' => 20], $result);

        // Non-array returns default
        $host->set($plugin, 'notarray', 'string');
        $this->assertEquals(['default'], $host->get_array($plugin, 'notarray', ['default']));
    }

    public function test_get_all(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->set($plugin, 'k1', 'v1');
        $host->set($plugin, 'k2', 'v2');

        $all = $host->get_all($plugin);
        $this->assertArrayHasKey('k1', $all);
        $this->assertArrayHasKey('k2', $all);
        $this->assertEquals('v1', $all['k1']);
        $this->assertEquals('v2', $all['k2']);
    }

    public function test_clear_data_removes_from_db(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->set($plugin, 'to_remove', 'value');
        $host->clear_data($plugin);

        $this->assertFalse($host->get($plugin, 'to_remove', false));

        // Verify it's gone from DB
        $pdo = Db::pdo();
        $row = $pdo->prepare("SELECT count(*) FROM ttrss_plugin_storage WHERE name = ? AND owner_uid = ?");
        $row->execute([$plugin::class, 1]);
        $this->assertEquals(0, (int) $row->fetchColumn());
    }

    // ── Command system ──

    public function test_add_command_and_get_commands(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->add_command('my_command', 'A test command', $plugin);
        $host->add_command('another', 'Another cmd', $plugin);

        $commands = $host->get_commands();

        $this->assertArrayHasKey('my_command', $commands);
        $this->assertArrayHasKey('another', $commands);
        $this->assertEquals('A test command', $commands['my_command']['description']);
        $this->assertSame($plugin, $commands['my_command']['class']);
    }

    // ── Special feeds ──

    public function test_add_feed_returns_id(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $id = $host->add_feed(Feeds::CATEGORY_SPECIAL, 'Test Feed', 'icon.png', $plugin);

        $this->assertIsInt($id);
        $this->assertEquals(0, $id);
    }

    public function test_add_feed_rejects_non_special_category(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $id = $host->add_feed(1, 'Bad Feed', 'icon.png', $plugin);
        $this->assertFalse($id);
    }

    public function test_get_feeds_returns_special_feeds(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->add_feed(Feeds::CATEGORY_SPECIAL, 'Feed A', 'icon_a.png', $plugin);

        $feeds = $host->get_feeds(Feeds::CATEGORY_SPECIAL);

        $this->assertCount(1, $feeds);
        $this->assertEquals('Feed A', $feeds[0]['title']);
        $this->assertEquals('icon_a.png', $feeds[0]['icon']);
    }

    public function test_get_feeds_empty_for_non_special(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->add_feed(Feeds::CATEGORY_SPECIAL, 'Special', 'icon.png', $plugin);

        $feeds = $host->get_feeds(1);
        $this->assertCount(0, $feeds);
    }

    public function test_get_feed_handler_returns_sender(): void {
        $plugin = new TestPluginVirtualFeed();
        $host = $this->createHost();

        $id = $host->add_feed(Feeds::CATEGORY_SPECIAL, 'Virtual Feed', 'icon.png', $plugin);
        $handler = $host->get_feed_handler($id);

        $this->assertSame($plugin, $handler);
    }

    // ── pfeed/feed ID conversion ──

    public function test_pfeed_to_feed_id(): void {
        $this->assertEquals(
            PluginHost::pfeed_to_feed_id(1),
            PLUGIN_FEED_BASE_INDEX - 2
        );
    }

    public function test_feed_to_pfeed_id(): void {
        $this->assertEquals(
            PluginHost::feed_to_pfeed_id(1),
            PLUGIN_FEED_BASE_INDEX
        );
    }

    public function test_pfeed_feed_conversion_is_negation(): void {
        $feedId = 12345;
        $pfeedId = PluginHost::feed_to_pfeed_id($feedId);
        $back = PluginHost::pfeed_to_feed_id($pfeedId);

        // The roundtrip gives the negation (by design for feed ID space separation)
        $this->assertEquals(-$feedId, $back);
    }

    // ── API methods ──

    public function test_add_api_method_and_get(): void {
        $plugin = new TestPluginSystem(); // system plugin required for API methods
        $host = $this->createHost();

        $host->add_api_method('my_api_method', $plugin);

        $this->assertSame($plugin, $host->get_api_method('my_api_method'));
    }

    public function test_get_api_method_case_insensitive(): void {
        $plugin = new TestPluginSystem();
        $host = $this->createHost();

        $host->add_api_method('MyMethod', $plugin);

        $this->assertSame($plugin, $host->get_api_method('mymethod'));
    }

    public function test_get_api_method_returns_null_when_missing(): void {
        $host = $this->createHost();
        $this->assertNull($host->get_api_method('nonexistent'));
    }

    // ── Filter actions ──

    public function test_add_filter_action_and_get(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $host->add_filter_action($plugin, 'custom_action', 'Custom action description');

        $actions = $host->get_filter_actions();
        $pluginClass = $plugin::class;

        $this->assertArrayHasKey($pluginClass, $actions);
        $this->assertCount(1, $actions[$pluginClass]);
        $this->assertEquals('custom_action', $actions[$pluginClass][0]['action']);
        $this->assertEquals('Custom action description', $actions[$pluginClass][0]['description']);
    }

    // ── Plugin info ──

    public function test_get_plugin_names(): void {
        $host = $this->createHost();
        $names = $host->get_plugin_names();

        // @phpstan-ignore method.alreadyNarrowedType (phpstan knows the return type)
        $this->assertIsArray($names);
        // plugin names are strings
        foreach ($names as $name) {
            // @phpstan-ignore method.alreadyNarrowedType (phpstan knows the element type)
            $this->assertIsString($name);
        }
    }

    public function test_get_plugins_returns_array(): void {
        $host = $this->createHost();
        $plugins = $host->get_plugins();

        // @phpstan-ignore method.alreadyNarrowedType (phpstan knows the return type)
        $this->assertIsArray($plugins);
        // each plugin is an object
        foreach ($plugins as $plugin) {
            $this->assertInstanceOf(Plugin::class, $plugin);
        }
    }

    public function test_get_pdo(): void {
        $host = $this->createHost();
        $pdo = $host->get_pdo();

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function test_get_owner_uid(): void {
        $host = $this->createHost();
        $uid = $host->get_owner_uid();

        $this->assertNotNull($uid);
    }

    public function test_api_version_constant(): void {
        $this->assertEquals(2, PluginHost::API_VERSION);
    }

    // ── Scheduler integration ──

    public function test_add_scheduled_task(): void {
        $plugin = new TestPluginStorage();
        $host = $this->createHost();

        $result = $host->add_scheduled_task(
            $plugin,
            'test_task',
            '@daily',
            fn() => 0
        );

        $this->assertTrue($result);
    }

    // ── object_to_domain ──

    public function test_object_to_domain(): void {
        $plugin = new TestPluginStorage();
        $domain = PluginHost::object_to_domain($plugin);

        $this->assertEquals('testpluginstorage', $domain);
    }
}

// ── Test plugin classes ──

/**
 * Minimal plugin for testing hook functionality.
 */
class TestPluginHook extends Plugin {
    public bool $hookCalled = false;
    public mixed $hookReturn = null;

    public function init($host): void {}
    public function about(): array { return [1.0, 'test', '', false]; }
    public function api_version(): int { return PluginHost::API_VERSION; }

    // @phpstan-ignore-next-line return.type (parent returns string but this hook only needs side effects)
    public function hook_article_button(...$args): void { $this->hookCalled = true; }
    public function hook_article_filter(...$args): mixed {
        $this->hookCalled = true;
        return $this->hookReturn ?? null;
    }
}

/**
 * Minimal plugin for testing storage functionality.
 */
class TestPluginStorage extends Plugin {
    public function init($host): void {}
    public function about(): array { return [1.0, 'test', '', false]; }
    public function api_version(): int { return PluginHost::API_VERSION; }
}

/**
 * System plugin for testing API methods (only system plugins can register API methods).
 */
class TestPluginSystem extends Plugin {
    public function init($host): void {}
    public function about(): array { return [1.0, 'test', '', true]; }
    public function api_version(): int { return PluginHost::API_VERSION; }
}

/**
 * Virtual feed plugin for testing special feed handlers.
 */
class TestPluginVirtualFeed extends Plugin implements IVirtualFeed {
    public function init($host): void {}
    public function about(): array { return [1.0, 'test', '', false]; }
    public function api_version(): int { return PluginHost::API_VERSION; }

    public function get_unread(int $feed_id): int { return 0; }
    public function get_total(int $feed_id): int { return 0; }
    public function get_headlines(int $feed_id, array $options): array { return []; }
}

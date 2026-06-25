<?php

/**
 * @file tests/classes/queue/PKPContextSchemaReloadTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Regression tests for the context schema/object reload performed for context-aware CLI
 *   jobs (pkp/pkp-lib#9345).
 *
 * Background: a context-aware CLI job sets up its context in PKPQueueProvider's Queue::before
 * listener. The context schema is cached and the Context object is built BEFORE context-scoped
 * plugins register, so any property a plugin adds to the `context` schema (via the
 * Schema::get::context hook, e.g. the plagiarism plugin's iThenticate settings) is dropped from
 * both the cached schema and the Context object. PKPQueueProvider::reconcileCliContextAfterPluginLoad()
 * fixes this by force-reloading the context schema and rebuilding the Context object after plugins
 * load, mirroring Dispatcher::dispatch().
 */

namespace PKP\tests\classes\queue;

use APP\core\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\PKPQueueProvider;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;
use ReflectionMethod;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPQueueProvider::class)]
class PKPContextSchemaReloadTest extends PKPTestCase
{
    /** A property name that does NOT exist in the core context schema. */
    private const PLUGIN_PROP = 'i9345TestPluginContextSetting';

    protected function setUp(): void
    {
        parent::setUp();
        Application::get()->clearCliContext();
    }

    protected function tearDown(): void
    {
        Hook::clear('Schema::get::context');
        Application::get()->clearCliContext();
        parent::tearDown();
    }

    /**
     * Register a Schema::get::context hook that adds a custom property, exactly as a plugin's
     * register() would (see PlagiarismPlugin::addIthenticateConfigSettingsToContextSchema()).
     */
    private function registerContextSchemaPlugin(): void
    {
        Hook::add('Schema::get::context', function (string $hookName, array $params): bool {
            $schema = &$params[0];
            $schema->properties->{self::PLUGIN_PROP} = (object) [
                'type' => 'string',
                'validation' => ['nullable'],
            ];
            return Hook::CONTINUE;
        });
    }

    /**
     * Root-cause check: once the context schema is cached, registering a plugin hook later does NOT
     * change the cached copy — only a force-reload re-fires the hook. This is exactly why the queue
     * path must explicitly reload the context schema after plugins load.
     */
    public function testCachedContextSchemaIsStaleUntilForceReloaded(): void
    {
        $schemaService = app()->get('schema');

        // Prime the cache with the plugin-less schema (as the early getById() in Queue::before does).
        $initial = $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT);
        $this->assertFalse(
            isset($initial->properties->{self::PLUGIN_PROP}),
            'Custom property must be absent before any plugin registers its hook'
        );

        // A plugin registers its Schema::get::context hook AFTER the schema was first cached.
        $this->registerContextSchemaPlugin();

        // A plain (cache-hit) get() still returns the stale, plugin-less schema — the bug condition.
        $cached = $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT);
        $this->assertFalse(
            isset($cached->properties->{self::PLUGIN_PROP}),
            'Cache hit must not re-fire the hook, so the property stays absent (the bug condition)'
        );

        // A force-reload re-fires the hook and surfaces the plugin property.
        $reloaded = $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT, true);
        $this->assertTrue(
            isset($reloaded->properties->{self::PLUGIN_PROP}),
            'Force-reload must re-fire Schema::get::context so the plugin property appears'
        );
    }

    /**
     * The fix: reconcileCliContextAfterPluginLoad() force-reloads the context schema (so plugin
     * properties appear) and rebuilds the CLI Context object from the enriched schema.
     */
    public function testReconcileRefreshesContextSchemaAndRebuildsCliContext(): void
    {
        $schemaService = app()->get('schema');

        // Prime the plugin-less schema, as the early getById() in Queue::before would.
        $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT);

        // Simulate a plugin registering its context-schema hook during loadCategory().
        $this->registerContextSchemaPlugin();

        // Build a mock context + DAO so the rebuild step does not require the database.
        $contextId = 999;
        $contextClass = get_class(Application::getContextDAO()->newDataObject());
        $rebuiltContext = $this->getMockBuilder($contextClass)
            ->onlyMethods(['getId'])
            ->getMock();
        $rebuiltContext->method('getId')->willReturn($contextId);

        $mockDao = $this->getMockBuilder(Application::getContextDAO()::class)
            ->onlyMethods(['getById'])
            ->getMock();
        $mockDao->expects($this->once())
            ->method('getById')
            ->with($contextId)
            ->willReturn($rebuiltContext);

        // Invoke the protected reconcile method on the provider instance.
        $provider = app('pkpJobQueue');
        $this->assertInstanceOf(PKPQueueProvider::class, $provider);

        $method = new ReflectionMethod($provider, 'reconcileCliContextAfterPluginLoad');
        $method->setAccessible(true);
        $method->invoke($provider, $mockDao, $contextId);

        // The schema cache now carries the plugin-added property.
        $enriched = $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT);
        $this->assertTrue(
            isset($enriched->properties->{self::PLUGIN_PROP}),
            'After reconcile, the cached context schema must include the plugin-added property'
        );

        // The CLI context was rebuilt from the enriched schema (the object returned by getById()).
        $this->assertSame(
            $rebuiltContext,
            Application::get()->getCliContext(),
            'After reconcile, the CLI context must be the freshly rebuilt Context object'
        );
    }
}

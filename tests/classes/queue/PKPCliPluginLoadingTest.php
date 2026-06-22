<?php

/**
 * @file tests/classes/queue/PKPCliPluginLoadingTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for plugin loading in CLI mode during job processing.
 *
 * These tests verify that plugins load correctly with CLI context, hooks register
 * and fire properly .
 */

namespace PKP\tests\classes\queue;

use APP\core\Application;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\config\Config;
use PKP\core\PKPQueueProvider;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\CliWorkerTestJobWithHook;
use PKP\jobs\testJobs\CliWorkerTestJobWithHookNoContext;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\plugins\PluginSettingsDAO;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[CoversClass(PluginRegistry::class)]
#[CoversClass(Hook::class)]
#[CoversClass(PKPQueueProvider::class)]
class PKPCliPluginLoadingTest extends PKPTestCase
{
    protected $tmpErrorLog;
    protected string $originalErrorLog;

    /**
     * Static tracking props for plugin lifecycle verification
     */
    public static bool $pluginRegistered = false;
    public static bool $pluginEnabled = false;
    public static bool $hookFired = false;

    /**
     * The id of the live CLI context observed at the moment the worker hook fires (null = no context).
     * Captured inside a running job to assert what plugin code would resolve mid-execution.
     */
    public static ?int $cliContextIdAtHookFire = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();

        ini_set('error_log', stream_get_meta_data($this->tmpErrorLog)['uri']);

        // Clear CLI context
        Application::get()->clearCliContext();

        // Clear plugins registry
        Registry::delete('plugins');

        // Reset static tracking props
        self::$pluginRegistered = false;
        self::$pluginEnabled = false;
        self::$hookFired = false;
        self::$cliContextIdAtHookFire = null;
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        Application::get()->clearCliContext();

        // Clean up test hooks
        Hook::clear('PKPCliPluginLoadingTest::workerHook');
        Hook::clear('Schema::get::context');

        // Clean up test jobs
        PKPJobModel::query()->onQueue(PKPJobModel::TESTING_QUEUE)->delete();

        Mockery::close();

        parent::tearDown();
    }

    /**
     * Setup mock context DAO and request that returns mock context for given ID.
     * Creates application-specific context mock (Journal/Press/Server) to satisfy
     * type constraints when plugins call Request::getContext().
     */
    protected function setupMockContextForId(int $contextId): \Mockery\MockInterface
    {
        $application = Application::get();

        // Create application-specific context mock (not generic Context)
        // This is required because Request::getContext() has return type of ?Journal, ?Server, ?Press etc.
        $contextClass = get_class(Application::getContextDAO()->newDataObject());

        $mockContext = Mockery::mock($contextClass);
        $mockContext->shouldReceive('getId')->andReturn($contextId);
        $mockContext->shouldIgnoreMissing();

        // Mock request to return our application-specific context
        $mockRequest = Mockery::mock(\APP\core\Request::class)->makePartial();
        $mockRequest->shouldReceive('getContext')->andReturn($mockContext);
        $mockRequest->shouldReceive('getUser')->andReturn(null);
        Registry::set('request', $mockRequest);

        // Mock ContextDAO
        $contextDao = $application->getContextDAO();
        $mockDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getById'])
            ->getMock();

        $mockDao->expects($this->any())
            ->method('getById')
            ->with($contextId)
            ->willReturn($mockContext);

        DAORegistry::registerDAO(
            match ($application->getName()) {
                'ojs2' => 'JournalDAO',
                'omp' => 'PressDAO',
                'ops' => 'ServerDAO',
            },
            $mockDao
        );

        return $mockContext;
    }

    /**
     * Process the next job in test queue via queue worker
     */
    protected function processNextTestJob(): void
    {
        $worker = app('queue.worker');
        $jobQueue = app('pkpJobQueue');

        $worker->runNextJob(
            Config::getVar('queues', 'default_connection', 'database'),
            PKPJobModel::TESTING_QUEUE,
            $jobQueue->getWorkerOptions()
        );
    }

    /**
     * Setup mock PluginSettingsDAO that returns enabled=true for given contexts.
     * Contexts not in the array will return null (disabled).
     *
     * @param array<int> $enabledContexts Context IDs where plugins should be enabled
     */
    protected function setupMockPluginSettingsDAOForContexts(array $enabledContexts): void
    {
        $mockPluginSettingsDao = $this->getMockBuilder(PluginSettingsDAO::class)
            ->onlyMethods(['getSetting'])
            ->getMock();

        $mockPluginSettingsDao->expects($this->any())
            ->method('getSetting')
            ->willReturnCallback(function ($ctxId, $pluginName, $settingName) use ($enabledContexts) {
                if ($settingName === 'enabled') {
                    return in_array($ctxId, $enabledContexts, true);
                }
                return null;
            });

        DAORegistry::registerDAO('PluginSettingsDAO', $mockPluginSettingsDao);
    }

    /**
     * Run $callback with PHPUnit's "running unit tests" flag temporarily OFF so that the REAL plugin
     * loading block inside PKPQueueProvider::Queue::before executes (it is gated by
     * `!app()->runningUnitTests()`). The flag is always restored, even on failure.
     *
     * NOTE: with the guard off, PluginRegistry::loadCategory('generic'|'pubIds', true, $contextId) runs
     * real loadFromDatabase plugin loading against the test database. Side effects are isolated by
     * #[RunTestsInSeparateProcesses]. This is intentional for the end-to-end reconcile assertion below.
     */
    protected function withRealPluginLoading(callable $callback): mixed
    {
        app()->unsetRunningUnitTests();
        try {
            return $callback();
        } finally {
            app()->setRunningUnitTests();
        }
    }

    /**
     * Test that a plugin registered for a context has its hooks fire
     * when a ContextAwareJob is processed via CLI worker.
     *
     * This tests the Queue::before → plugin load → job execute → hook fire flow.
     */
    public function testPluginRegistersAndHookFiresForContextAwareJob(): void
    {
        // Step 1: Create test plugin that checks getEnabled() and registers hook
        $plugin = new class () extends \PKP\plugins\GenericPlugin {
            public function register($category, $path, $mainContextId = null): bool
            {
                $success = parent::register($category, $path, $mainContextId);

                // Use mainContextId for getEnabled() check in CLI context
                // where there's no request context
                if (!$success || !$this->getEnabled($mainContextId)) {
                    return $success;
                }

                // Track that plugin registered and is enabled
                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginRegistered = true;
                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginEnabled = true;

                // Register hook that will be fired by the job
                Hook::add('PKPCliPluginLoadingTest::workerHook', function (string $hookName, ...$args) {
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$hookFired = true;
                    return Hook::CONTINUE;
                });

                return $success;
            }

            public function getName(): string
            {
                return 'testCliWorker';
            }

            public function getDisplayName(): string
            {
                return 'Test CLI Worker Plugin';
            }

            public function getDescription(): string
            {
                return 'Test plugin for CLI worker integration tests';
            }
        };

        class_alias($plugin::class, 'APP\\plugins\\generic\\testCliWorker\\TestCliWorkerPlugin');

        // Step 2: Mock context and request for context_id=42
        $this->setupMockContextForId(42);

        // Step 3: Mock PluginSettingsDAO so getEnabled() returns true
        $this->setupMockPluginSettingsDAOForContexts([42]);

        // Step 4: Pre-register plugin
        PluginRegistry::loadPlugin('generic', 'testCliWorker', 42);

        // Verify plugin registered and is enabled after loadPlugin
        $this->assertTrue(self::$pluginRegistered, 'Plugin should have registered');
        $this->assertTrue(self::$pluginEnabled, 'Plugin should be enabled for context');

        // Verify hook is registered before dispatching job
        $hooks = Hook::getHooks('PKPCliPluginLoadingTest::workerHook');
        $this->assertNotNull($hooks, 'Hook should be registered before job dispatch');

        // Step 5: Dispatch ContextAwareJob that fires the hook
        dispatch(new CliWorkerTestJobWithHook(42));

        // Step 6: Process via worker
        $this->processNextTestJob();

        // Verify hook fired during job execution
        $this->assertTrue(self::$hookFired, 'Hook should have fired during job execution');
    }

    /**
     * Test that a plugin disabled for a context does NOT register hooks
     * and hooks do NOT fire when processing a job for that context.
     *
     * This tests the "disabled plugin" code path where:
     * - Plugin exists and can be registered
     * - getEnabled($contextId) returns false
     * - Plugin skips hook registration
     * - Job processes without hook firing
     */
    public function testPluginDisabledForContextDoesNotRegisterHooks(): void
    {
        // Step 1: Create test plugin that checks getEnabled() before registering hooks
        $plugin = new class () extends \PKP\plugins\GenericPlugin {
            public function register($category, $path, $mainContextId = null): bool
            {
                $success = parent::register($category, $path, $mainContextId);

                // Track registration attempt
                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginRegistered = true;

                // Check enabled status - should return FALSE for context 99
                if (!$this->getEnabled($mainContextId)) {
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginEnabled = false;
                    return $success; // Return without registering hooks
                }

                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginEnabled = true;

                // Register hook that will be fired by the job
                Hook::add('PKPCliPluginLoadingTest::workerHook', function (string $hookName, ...$args) {
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$hookFired = true;
                    return Hook::CONTINUE;
                });

                return $success;
            }

            public function getName(): string
            {
                return 'testCliWorkerDisabled';
            }

            public function getDisplayName(): string
            {
                return 'Test CLI Worker Disabled Plugin';
            }

            public function getDescription(): string
            {
                return 'Test plugin for disabled plugin path tests';
            }
        };

        class_alias(
            $plugin::class,
            'APP\\plugins\\generic\\testCliWorkerDisabled\\TestCliWorkerDisabledPlugin'
        );

        // Step 2: Mock context for ID 99
        $this->setupMockContextForId(99);

        // Step 3: Mock PluginSettingsDAO - plugin DISABLED for context 99
        // Pass empty array = no contexts enabled
        $this->setupMockPluginSettingsDAOForContexts([]);

        // Step 4: Pre-register plugin for context 99
        PluginRegistry::loadPlugin('generic', 'testCliWorkerDisabled', 99);

        // Verify: Plugin registered but NOT enabled
        $this->assertTrue(self::$pluginRegistered, 'Plugin should have attempted registration');
        $this->assertFalse(self::$pluginEnabled, 'Plugin should NOT be enabled for context 99');

        // Verify: Hook is NOT registered
        $hooks = Hook::getHooks('PKPCliPluginLoadingTest::workerHook');
        $this->assertNull($hooks, 'Hook should NOT be registered when plugin is disabled');

        // Step 5: Dispatch job for context 99
        dispatch(new CliWorkerTestJobWithHook(99));

        // Step 6: Process via worker
        $this->processNextTestJob();

        // Verify: Hook did NOT fire
        $this->assertFalse(self::$hookFired, 'Hook should NOT have fired for disabled plugin');
    }

    //
    // Coverage added for the #9345 CLI mechanism updates. The mechanism now (PKPQueueProvider::boot):
    //   - loads BOTH `generic` AND `pubIds` with enabledOnly=true in Queue::before,
    //   - sets the CLI context before the job runs (so plugin code resolves it mid-execution), and
    //   - force-reloads the `context` schema + rebuilds the Context object after plugins load
    //     (reconcileCliContextAfterPluginLoad).
    //
    // NOTE on a testing boundary: the committedContextId/contextCommitted state and the daemon relaunch
    // decision live on the PKPQueueProvider instance that Laravel BOOTS (PKPContainer::registerConfigured-
    // Providers, the `new PKPQueueProvider($this)` whose Queue::before/Looping closures capture $this).
    // PKPContainer::register() never stores that instance and app('pkpJobQueue') is a different singleton,
    // so a real worker run's commit state is not observable here — the relaunch decision is covered
    // directly in PKPLoopingContextChangeTest. Likewise, enabledOnly=true switches discovery to
    // loadFromDatabase (disabled plugins' register() no longer runs); that requires installed-version
    // fixtures and is not asserted here.
    //

    /**
     * P1 — pubIds parity: the mechanism now loads the `pubIds` category alongside `generic`. Verify a
     * pubIds-category plugin registers and its hook fires when a context-aware job runs via the worker.
     * (Lightweight GenericPlugin subclass aliased into the pubIds path — subclassing PKPPubIdPlugin's
     * large abstract surface adds nothing to a hook-fire assertion.)
     */
    public function testPubIdPluginRegistersAndHookFiresForContextAwareJob(): void
    {
        $plugin = new class () extends \PKP\plugins\GenericPlugin {
            public function register($category, $path, $mainContextId = null): bool
            {
                $success = parent::register($category, $path, $mainContextId);

                if (!$success || !$this->getEnabled($mainContextId)) {
                    return $success;
                }

                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginRegistered = true;
                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginEnabled = true;

                Hook::add('PKPCliPluginLoadingTest::workerHook', function (string $hookName, ...$args) {
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$hookFired = true;
                    return Hook::CONTINUE;
                });

                return $success;
            }

            public function getName(): string
            {
                return 'testCliPubId';
            }

            public function getDisplayName(): string
            {
                return 'Test CLI PubId Plugin';
            }

            public function getDescription(): string
            {
                return 'Test pubIds plugin for CLI worker integration tests';
            }
        };

        class_alias($plugin::class, 'APP\\plugins\\pubIds\\testCliPubId\\TestCliPubIdPlugin');

        $this->setupMockContextForId(42);
        $this->setupMockPluginSettingsDAOForContexts([42]);

        // Pre-register under the pubIds category (the second category Queue::before now loads).
        PluginRegistry::loadPlugin('pubIds', 'testCliPubId', 42);

        $this->assertTrue(self::$pluginRegistered, 'pubIds plugin should have registered');
        $this->assertTrue(self::$pluginEnabled, 'pubIds plugin should be enabled for context');

        $hooks = Hook::getHooks('PKPCliPluginLoadingTest::workerHook');
        $this->assertNotNull($hooks, 'Hook should be registered before job dispatch');

        dispatch(new CliWorkerTestJobWithHook(42));
        $this->processNextTestJob();

        $this->assertTrue(self::$hookFired, 'pubIds plugin hook should have fired during job execution');
    }

    /**
     * P2 — the CLI context is live and equals the job's context DURING execution. Queue::before sets the
     * context before the job runs (this part runs even under the unit-test guard), so plugin code firing
     * mid-job resolves the job's context via Application::getCliContext(). This is the core #9345
     * guarantee; the existing tests only checked a boolean.
     */
    public function testCliContextIsLiveAndMatchesJobDuringExecution(): void
    {
        $this->setupMockContextForId(42);

        // Probe the live CLI context at the exact moment the job fires the hook.
        Hook::add('PKPCliPluginLoadingTest::workerHook', function (string $hookName, ...$args) {
            self::$cliContextIdAtHookFire = Application::get()->getCliContext()?->getId();
            self::$hookFired = true;
            return Hook::CONTINUE;
        });

        dispatch(new CliWorkerTestJobWithHook(42));
        $this->processNextTestJob();

        $this->assertTrue(self::$hookFired, 'Hook should have fired during job execution');
        $this->assertSame(
            42,
            self::$cliContextIdAtHookFire,
            'CLI context must be live and equal the job context while the job runs'
        );
    }

    /**
     * P3 — site/null-context job: a site-level plugin's hook still fires, and the worker establishes NO
     * CLI context (Queue::before only sets the context for context-aware jobs). Contrast with P2. Uses
     * the non-context-aware CliWorkerTestJobWithHookNoContext fixture.
     */
    public function testSiteLevelJobFiresHookWithoutEstablishingCliContext(): void
    {
        $plugin = new class () extends \PKP\plugins\GenericPlugin {
            public function register($category, $path, $mainContextId = null): bool
            {
                $success = parent::register($category, $path, $mainContextId);

                if (!$success) {
                    return $success;
                }

                // A non-lazy/site-wide plugin registers its hooks unconditionally (no per-context gate).
                \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$pluginRegistered = true;

                Hook::add('PKPCliPluginLoadingTest::workerHook', function (string $hookName, ...$args) {
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$hookFired = true;
                    \PKP\tests\classes\queue\PKPCliPluginLoadingTest::$cliContextIdAtHookFire = Application::get()->getCliContext()?->getId();
                    return Hook::CONTINUE;
                });

                return $success;
            }

            public function getName(): string
            {
                return 'testCliSite';
            }

            public function getDisplayName(): string
            {
                return 'Test CLI Site Plugin';
            }

            public function getDescription(): string
            {
                return 'Test site-level plugin for CLI worker integration tests';
            }
        };

        class_alias($plugin::class, 'APP\\plugins\\generic\\testCliSite\\TestCliSitePlugin');

        // Site-level registration (mainContextId = null).
        PluginRegistry::loadPlugin('generic', 'testCliSite', null);
        $this->assertTrue(self::$pluginRegistered, 'Site plugin should have registered');

        dispatch(new CliWorkerTestJobWithHookNoContext());
        $this->processNextTestJob();

        $this->assertTrue(self::$hookFired, 'Site plugin hook should fire for a site-level job');
        $this->assertNull(
            self::$cliContextIdAtHookFire,
            'A null/site-level job must NOT establish a CLI context'
        );
        $this->assertNull(
            Application::get()->getCliContext(),
            'CLI context must remain unset after a site-level job'
        );
    }

    /**
     * P4 — end-to-end reconcile through the REAL Queue::before. A plugin can extend the `context` schema
     * via the Schema::get::context hook; because the schema is cached before plugins load,
     * reconcileCliContextAfterPluginLoad() must force-reload it after loadCategory. This drives that path
     * through an actual processed job (guard bypassed), unlike PKPContextSchemaReloadTest which invokes
     * reconcile in isolation.
     */
    public function testRealQueueBeforeReconcilesContextSchemaForContextAwareJob(): void
    {
        $pluginProp = 'i9345CliPluginLoadingContextProp';
        $schemaService = app()->get('schema');

        // Prime the cache with the plugin-less context schema (as the early getById in Queue::before would).
        $initial = $schemaService->get(PKPSchemaService::SCHEMA_CONTEXT);
        $this->assertFalse(
            isset($initial->properties->{$pluginProp}),
            'Custom property must be absent before the plugin hook registers'
        );

        // A plugin registers its Schema::get::context hook (as PlagiarismPlugin does for iThenticate).
        Hook::add('Schema::get::context', function (string $hookName, array $params) use ($pluginProp): bool {
            $schema = &$params[0];
            $schema->properties->{$pluginProp} = (object) ['type' => 'string', 'validation' => ['nullable']];
            return Hook::CONTINUE;
        });

        // A plain cache hit must still be stale (the bug condition the reconcile fixes).
        $this->assertFalse(
            isset($schemaService->get(PKPSchemaService::SCHEMA_CONTEXT)->properties->{$pluginProp}),
            'Cache hit must not re-fire the hook, so the property stays absent until reconcile'
        );

        $this->setupMockContextForId(42);

        // Run the REAL Queue::before block (loadCategory + reconcile) by processing an actual job.
        $this->withRealPluginLoading(function () {
            dispatch(new CliWorkerTestJobWithHook(42));
            $this->processNextTestJob();
        });

        // After the job, reconcile must have force-reloaded the context schema so the plugin prop appears.
        $this->assertTrue(
            isset($schemaService->get(PKPSchemaService::SCHEMA_CONTEXT)->properties->{$pluginProp}),
            'Real Queue::before must reconcile (force-reload) the context schema so the plugin prop appears'
        );

        Hook::clear('Schema::get::context');
    }
}

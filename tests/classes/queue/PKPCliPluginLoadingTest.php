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

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\PluginSettingsDAO;
use PKP\config\Config;
use PKP\job\models\Job as PKPJobModel;
use PKP\jobs\testJobs\CliWorkerTestJobWithHook;
use APP\core\Application;

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
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        Application::get()->clearCliContext();

        // Clean up test hooks
        Hook::clear('PKPCliPluginLoadingTest::workerHook');

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
     * Test that a plugin registered for a context has its hooks fire
     * when a ContextAwareJob is processed via CLI worker.
     *
     * This tests the Queue::before → plugin load → job execute → hook fire flow.
     */
    public function testPluginRegistersAndHookFiresForContextAwareJob(): void
    {
        // Step 1: Create test plugin that checks getEnabled() and registers hook
        $plugin = new class extends \PKP\plugins\GenericPlugin {
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
        CliWorkerTestJobWithHook::dispatch(42);

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
        $plugin = new class extends \PKP\plugins\GenericPlugin {            
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
}

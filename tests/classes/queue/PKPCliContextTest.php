<?php

/**
 * @file tests/classes/queue/PKPCliContextTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for CLI context management in job processing.
 */

namespace PKP\tests\classes\queue;

use APP\core\Application;
use APP\core\Request;
use Illuminate\Container\Container;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\context\Context;
use PKP\core\PKPComponentRouter;
use PKP\core\PKPRouter;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[RunTestsInSeparateProcesses]
#[CoversClass(Application::class)]
#[CoversMethod(PKPRouter::class, 'getContext')]
class PKPCliContextTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Application::get()->clearCliContext();
    }

    protected function tearDown(): void
    {
        $_SERVER['PATH_INFO'] = null;
        Application::get()->clearCliContext();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test setCliContext with Context object works directly
     */
    public function testSetCliContextWithContextObject(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(1);

        Application::get()->setCliContext($mockContext);

        $cliContext = Application::get()->getCliContext();
        $this->assertNotNull($cliContext);
        $this->assertEquals(1, $cliContext->getId());
    }

    /**
     * Test setCliContext throws for invalid context ID
     */
    public function testSetCliContextThrowsForInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid context ID');

        // Use a context ID that definitely doesn't exist
        Application::get()->setCliContext(999999);
    }

    /**
     * Test getCliContext returns set context
     */
    public function testGetCliContextReturnsSetContext(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(42);

        Application::get()->setCliContext($mockContext);

        $result = Application::get()->getCliContext();

        $this->assertNotNull($result);
        $this->assertEquals(42, $result->getId());
    }

    /**
     * Test clearCliContext resets to null
     */
    public function testClearCliContextResetsToNull(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(1);

        Application::get()->setCliContext($mockContext);
        $this->assertNotNull(Application::get()->getCliContext());

        Application::get()->clearCliContext();

        $this->assertNull(Application::get()->getCliContext());
    }

    /**
     * Test getCliContext returns null when not set
     */
    public function testGetCliContextReturnsNullWhenNotSet(): void
    {
        Application::get()->clearCliContext();

        $this->assertNull(Application::get()->getCliContext());
    }

    /**
     * Test CLI context can be updated
     */
    public function testCliContextCanBeUpdated(): void
    {
        $mockContext1 = Mockery::mock(Context::class);
        $mockContext1->shouldReceive('getId')->andReturn(1);

        $mockContext2 = Mockery::mock(Context::class);
        $mockContext2->shouldReceive('getId')->andReturn(2);

        Application::get()->setCliContext($mockContext1);
        $this->assertEquals(1, Application::get()->getCliContext()->getId());

        Application::get()->setCliContext($mockContext2);
        $this->assertEquals(2, Application::get()->getCliContext()->getId());
    }

    /**
     * Test setCliContext with null does update existing context
     */
    public function testSetCliContextWithNullUpdateExisting(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(5);

        Application::get()->setCliContext($mockContext);
        $this->assertNotNull(Application::get()->getCliContext());

        Application::get()->setCliContext(null);
        $this->assertNull(Application::get()->getCliContext());
        $this->assertNotEquals(5, Application::get()->getCliContext()?->getId());
    }

    //
    // Router-level PKPRouter::getContext() tests for the CLI-context fallback .
    //
    // getContext() resolves the requested context path first and only falls back to the CLI context
    // when (a) the path is site-level or (b) a real path fails to resolve — and ONLY while running in
    // console with a CLI context set. An already-resolved context always wins; a genuine 404 with no
    // CLI context still throws. In CLI, getRequestedContextPath() always resolves to the site level
    // (no PATH_INFO), which is why the fallback must live in the site-level branch. See claude/QUEUE.md.
    //

    /** Build a mocked Context with the given id. */
    private function mockContext(int $id): Context
    {
        $context = Mockery::mock(Context::class);
        $context->shouldReceive('getId')->andReturn($id);
        return $context;
    }

    /**
     * Register a mock context DAO whose getByPath($expectedPath) returns $result, so getContext()'s
     * path resolution is fully controlled without touching the database.
     */
    private function mockContextResolution(string $expectedPath, ?Context $result): void
    {
        $contextDao = Application::get()->getContextDAO();
        $mockDao = $this->getMockBuilder($contextDao::class)
            ->onlyMethods(['getByPath'])
            ->getMock();
        $mockDao->method('getByPath')->with($expectedPath)->willReturn($result);

        $daoName = ucfirst(Application::get()->getContextName()) . 'DAO';
        DAORegistry::registerDAO($daoName, $mockDao);
    }

    /**
     * Run $callback with the application forced into NON-console (web) mode. getContext()'s only
     * container dependency is app()->runningInConsole(), so we proxy just that on the live container
     * and restore it immediately. php_sapi_name() is always 'cli' under PHPUnit, so overriding the
     * container method is the only way to exercise the web-mode branches.
     */
    private function inWebMode(callable $callback): mixed
    {
        $realContainer = Container::getInstance();
        $proxy = Mockery::mock($realContainer);
        $proxy->shouldReceive('runningInConsole')->andReturn(false);
        Container::setInstance($proxy);
        try {
            return $callback();
        } finally {
            Container::setInstance($realContainer);
        }
    }

    /** (1) CLI + site-level path + CLI context set => returns the CLI context (the queue case). */
    public function testCliSiteLevelReturnsCliContext(): void
    {
        $cliContext = $this->mockContext(10);
        Application::get()->setCliContext($cliContext);
        $_SERVER['PATH_INFO'] = null; // resolves to SITE_CONTEXT_PATH ('index')

        $result = (new PKPComponentRouter())->getContext(new Request());
        $this->assertSame($cliContext, $result);
    }

    /** (2) WEB + site-level path + CLI context set => CLI context is ignored, returns site (null). */
    public function testWebSiteLevelIgnoresCliContextReturnsNull(): void
    {
        Application::get()->setCliContext($this->mockContext(10));
        $_SERVER['PATH_INFO'] = null;

        $router = new PKPComponentRouter();
        $result = $this->inWebMode(fn () => $router->getContext(new Request()));
        $this->assertNull($result);
    }

    /** (3) CLI + resolvable path + CLI context set => the resolved context wins over the CLI context. */
    public function testCliResolvedPathWinsOverCliContext(): void
    {
        $cliContext = $this->mockContext(10);
        $resolved = $this->mockContext(20);
        Application::get()->setCliContext($cliContext);
        $_SERVER['PATH_INFO'] = '/testjournal';
        $this->mockContextResolution('testjournal', $resolved);

        $result = (new PKPComponentRouter())->getContext(new Request());
        $this->assertSame($resolved, $result);
        $this->assertNotSame($cliContext, $result);
    }

    /** (4) WEB + resolvable path + CLI context set => still returns the resolved context. */
    public function testWebResolvedPathReturnedEvenWithCliContext(): void
    {
        $resolved = $this->mockContext(20);
        Application::get()->setCliContext($this->mockContext(10));
        $_SERVER['PATH_INFO'] = '/testjournal';
        $this->mockContextResolution('testjournal', $resolved);

        $router = new PKPComponentRouter();
        $result = $this->inWebMode(fn () => $router->getContext(new Request()));
        $this->assertSame($resolved, $result);
    }

    /** (5) CLI + unresolvable path + CLI context set => returns CLI context WITHOUT throwing. */
    public function testCliUnresolvedPathReturnsCliContextNoThrow(): void
    {
        $cliContext = $this->mockContext(10);
        Application::get()->setCliContext($cliContext);
        $_SERVER['PATH_INFO'] = '/missingjournal';
        $this->mockContextResolution('missingjournal', null);

        $result = (new PKPComponentRouter())->getContext(new Request());
        $this->assertSame($cliContext, $result);
    }

    /** (6) WEB + unresolvable path + CLI context set => throws NotFoundHttpException (CLI ctx ignored). */
    public function testWebUnresolvedPathThrowsEvenWithCliContext(): void
    {
        Application::get()->setCliContext($this->mockContext(10));
        $_SERVER['PATH_INFO'] = '/missingjournal';
        $this->mockContextResolution('missingjournal', null);

        $router = new PKPComponentRouter();
        $this->expectException(NotFoundHttpException::class);
        $this->inWebMode(fn () => $router->getContext(new Request()));
    }

    /** (7) CLI + site-level path + NO CLI context => null (plain CLI tool; guards the && right operand). */
    public function testCliSiteLevelWithoutCliContextReturnsNull(): void
    {
        Application::get()->clearCliContext();
        $_SERVER['PATH_INFO'] = null;

        $result = (new PKPComponentRouter())->getContext(new Request());
        $this->assertNull($result);
    }

    /** (8) CLI + unresolvable path + NO CLI context => still throws (fallback never swallows a real 404). */
    public function testCliUnresolvedPathWithoutCliContextThrows(): void
    {
        Application::get()->clearCliContext();
        $_SERVER['PATH_INFO'] = '/missingjournal';
        $this->mockContextResolution('missingjournal', null);

        $this->expectException(NotFoundHttpException::class);
        (new PKPComponentRouter())->getContext(new Request());
    }

    /** (9) CLI context is re-read per call (not cached) => supports per-job context switching in a daemon. */
    public function testCliContextReReadPerCallNotCached(): void
    {
        $_SERVER['PATH_INFO'] = null;
        $router = new PKPComponentRouter();

        $contextA = $this->mockContext(1);
        Application::get()->setCliContext($contextA);
        $this->assertSame($contextA, $router->getContext(new Request()));

        $contextB = $this->mockContext(2);
        Application::get()->setCliContext($contextB);
        $this->assertSame($contextB, $router->getContext(new Request()));
    }

    /** (10) An already-resolved context takes precedence over the CLI context (the reviewer's principle). */
    public function testAlreadyResolvedContextTakesPrecedenceOverCliContext(): void
    {
        $resolved = $this->mockContext(30);
        Application::get()->setCliContext($this->mockContext(10));
        $_SERVER['PATH_INFO'] = null;

        $router = new PKPComponentRouter();
        $router->_context = $resolved; // public; simulate a context already resolved/injected

        $result = $router->getContext(new Request());
        $this->assertSame($resolved, $result);
    }
}

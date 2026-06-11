<?php

/**
 * @file tests/classes/core/APIRouterTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIRouterTest
 *
 * @see \PKP\core\APIRouter
 *
 * @brief Tests for the APIRouter class.
 */

namespace PKP\tests\classes\core;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\core\APIRouter;
use PKP\core\PKPBaseController;
use PKP\core\Registry;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use stdClass;

#[BackupGlobals(true)]
#[CoversClass(APIRouter::class)]
class APIRouterTest extends PKPRouterTestCase
{
    /** The router under test, typed as APIRouter so static analysis resolves API-specific methods. */
    protected APIRouter $apiRouter;

    protected function setUp(): void
    {
        parent::setUp();
        
        // To make sure specific methods of APIRouter not flagged by intelephense as not defined
        $this->router = $this->apiRouter = new APIRouter();
    }

    //
    // Helpers
    //
    /**
     * Invoke a protected/private method on the router under test.
     */
    private function invokeProtected(string $method, string ...$args)
    {
        $reflection = new \ReflectionMethod($this->apiRouter, $method);
        return $reflection->invoke($this->apiRouter, ...$args);
    }

    /**
     * Read the router's protected list of registered plugin API controllers.
     */
    private function registeredControllers(): array
    {
        $property = new \ReflectionProperty($this->apiRouter, 'registeredPluginApiControllers');
        return $property->getValue($this->apiRouter);
    }

    /**
     * Build a mock PKPBaseController whose getHandlerPath() returns the given path.
     */
    private function createMockApiController(string $handlerPath): PKPBaseController
    {
        $controller = $this->createMock(PKPBaseController::class);
        $controller->expects($this->any())
            ->method('getHandlerPath')
            ->willReturn($handlerPath);
        return $controller;
    }

    //
    // supports()
    //
    /**
     * The parent PKPRouterTestCase::testSupports() asserts a bare request is supported,
     * which is never true for the API router (it requires an /api path segment). Skip it;
     * the real coverage is in testSupportsVariants(). The signature must match the parent's
     * to avoid a fatal "declaration must be compatible" error.
     */
    public function testSupports()
    {
        $this->markTestSkipped('Not relevant for the API router; see testSupportsVariants().');
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function supportsProvider(): array
    {
        return [
            'context-level API request' => ['/context1/api/v1/submissions', true],
            'site-wide API request' => ['/index/api/v1/contexts', true],
            'page request (no api segment)' => ['/context1/page/op', false],
            'too short (fewer than 2 parts)' => ['/context1', false],
            'empty path info' => ['', false],
            'unset path info' => [null, false],
            'api segment in wrong position' => ['/context1/handler/api/v1', false],
        ];
    }

    #[DataProvider('supportsProvider')]
    public function testSupportsVariants(?string $pathInfo, bool $expected)
    {
        $this->_setUpMockEnvironment();

        if ($pathInfo === null) {
            unset($_SERVER['PATH_INFO']);
        } else {
            $_SERVER['PATH_INFO'] = $pathInfo;
        }

        self::assertSame($expected, $this->apiRouter->supports($this->request));
    }

    //
    // getVersion()
    //
    public function testGetVersionWithSanitized()
    {
        $_SERVER['PATH_INFO'] = '/context1/api/v1/submissions';
        self::assertEquals('v1', $this->apiRouter->getVersion());

        // Missing version segment
        $_SERVER['PATH_INFO'] = '/context1/api';
        self::assertEquals('', $this->apiRouter->getVersion());

        // strips everything except word characters and hyphens.
        $_SERVER['PATH_INFO'] = '/context1/api/v1<script>/submissions';
        $version = $this->apiRouter->getVersion();
        self::assertEquals('v1script', $version);
    }

    //
    // getEntity()
    //
    public function testGetEntityWithSanitized()
    {
        $_SERVER['PATH_INFO'] = '/context1/api/v1/submissions';
        self::assertEquals('submissions', $this->apiRouter->getEntity());

        // Hyphens are preserved by Core::cleanFileVar()
        $_SERVER['PATH_INFO'] = '/context1/api/v1/temp-files';
        self::assertEquals('temp-files', $this->apiRouter->getEntity());

        // Missing entity segment
        $_SERVER['PATH_INFO'] = '/context1/api/v1';
        self::assertEquals('', $this->apiRouter->getEntity());

        $_SERVER['PATH_INFO'] = '/context1/api/v1/submissions<script>';
        $entity = $this->apiRouter->getEntity();
        self::assertEquals('submissionsscript', $entity);
    }

    //
    // getSourceFilePath()
    //
    public function testGetSourceFilePath()
    {
        $_SERVER['PATH_INFO'] = '/context1/api/v1/submissions';
        self::assertEquals('api/v1/submissions/index.php', $this->invokeProtected('getSourceFilePath'));

        // Hyphenated entity preserved
        $_SERVER['PATH_INFO'] = '/context1/api/v1/temp-files';
        self::assertEquals('api/v1/temp-files/index.php', $this->invokeProtected('getSourceFilePath'));
    }

    //
    // matchesPluginHandlerPath()
    //
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function pluginHandlerPathProvider(): array
    {
        return [
            'exact match' => ['/index.php/ctx/api/v1/custom-path', 'custom-path', true],
            'sub-resource prefix' => ['/index.php/ctx/api/v1/custom-path/data', 'custom-path', true],
            'collision rejected (report vs report-advanced)' => ['/index.php/ctx/api/v1/report-advanced', 'report', false],
            'query string stripped' => ['/index.php/ctx/api/v1/custom-path?foo=bar', 'custom-path', true],
            'multi-segment handler prefix' => ['/index.php/ctx/api/v1/a/b/c', 'a/b', true],
            'multi-segment partial (a/bc not under a/b)' => ['/index.php/ctx/api/v1/a/bc', 'a/b', false],
            'handler with surrounding slashes' => ['/index.php/ctx/api/v1/custom-path', '/custom-path/', true],
            'no api version segment' => ['/index.php/ctx/page/op', 'custom-path', false],
        ];
    }

    #[DataProvider('pluginHandlerPathProvider')]
    public function testMatchesPluginHandlerPath(string $requestPath, string $handlerPath, bool $expected)
    {
        self::assertSame($expected, $this->invokeProtected('matchesPluginHandlerPath', $requestPath, $handlerPath));
    }

    //
    // registerPluginApiControllers()
    //
    public function testRegisterPluginApiControllers()
    {
        $controller = $this->createMockApiController('custom-path');
        $this->apiRouter->registerPluginApiControllers([$controller]);

        $registered = $this->registeredControllers();
        self::assertArrayHasKey('custom-path', $registered);
        self::assertSame($controller, $registered['custom-path']);
    }

    public function testRegisterPluginApiControllersRejectsDuplicatePath()
    {
        $this->apiRouter->registerPluginApiControllers([$this->createMockApiController('custom-path')]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('already registered');
        $this->apiRouter->registerPluginApiControllers([$this->createMockApiController('custom-path')]);
    }

    public function testRegisterPluginApiControllersRejectsInvalidController()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid API controller');
        $this->apiRouter->registerPluginApiControllers([new stdClass()]);
    }

    //
    // url()
    //
    /**
     * Generate a context-level API URL when no base_url override is configured.
     */
    public function testUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config');
        $this->_setUpMockEnvironment();
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
        ];

        $result = $this->apiRouter->url($this->request, 'current-context1', 'submissions');
        self::assertEquals('http://mydomain.org/index.php/current-context1/api/v1/submissions', $result);
    }

    /**
     * Query parameters are appended and escaped, and the single-slash path is preserved.
     */
    public function testUrlWithQueryParameters()
    {
        $this->setTestConfiguration('request1', 'classes/core/config');
        $this->_setUpMockEnvironment();
        $_SERVER = [
            'SERVER_NAME' => 'mydomain.org',
            'SCRIPT_NAME' => '/index.php',
        ];

        $result = $this->apiRouter->url($this->request, 'current-context1', 'submissions', null, null, ['count' => 20, 'search' => 'a?b'], null, true);
        self::assertEquals('http://mydomain.org/index.php/current-context1/api/v1/submissions?count=20&amp;search=a%3Fb', $result);
    }

    /**
     * When the base URL is overridden with a value that contains a path component,
     * the path is preserved and the context slug is dropped.
     */
    public function testUrlWithOverriddenBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains overridden context
        $this->_setUpMockEnvironment();

        $result = $this->apiRouter->url($this->request, 'overridden-context', 'submissions');
        self::assertEquals('http://some-domain/xyz-context/api/v1/submissions', $result);
    }

    /**
     * Regression test for pkp/pkp-lib#12767: when the base URL is overridden with a
     * bare domain (no path component), the generated API URL must not contain a
     * double slash between the host and the "api" segment.
     */
    public function testUrlWithOverriddenBareDomainBaseUrl()
    {
        $this->setTestConfiguration('request1', 'classes/core/config'); // contains bare-domain override
        $this->_setUpMockEnvironment();

        $result = $this->apiRouter->url($this->request, 'bare-context', 'submissions');
        self::assertEquals('http://some-domain/api/v1/submissions', $result);
        self::assertStringNotContainsString('//api', $result);
    }

    //
    // url() bakes all routing into the $endpoint string; passing an $op must be rejected.
    // All four guard conditions (op/path/anchor/non-scalar context) raise the same message.
    //

    public function testUrlRejectsOp()
    {
        $this->_setUpMockEnvironment();
        $this->expectException(Exception::class);
        $this->apiRouter->url($this->request, 'context1', 'submissions', 'edit');
    }

    public function testUrlRejectsPath()
    {
        $this->_setUpMockEnvironment();
        $this->expectException(Exception::class);
        $this->apiRouter->url($this->request, 'context1', 'submissions', null, ['123']);
    }

    public function testUrlRejectsAnchor()
    {
        $this->_setUpMockEnvironment();
        $this->expectException(Exception::class);
        $this->apiRouter->url($this->request, 'context1', 'submissions', null, null, null, 'top');
    }

    public function testUrlRejectsNullContext()
    {
        $this->_setUpMockEnvironment();
        $this->expectException(Exception::class);
        $this->apiRouter->url($this->request, null, 'submissions');
    }

    //
    // route() — plugin API endpoint integration (APIHandler::endpoints::plugin hook)
    //
    /**
     * Wire the App's request/router and the $_SERVER globals for a site-wide API request to
     * /index/api/v1/{handlerPath}, then rebuild the container's Illuminate request from those
     * globals so dispatch resolves the same path. Site-wide (/index) means
     * SetupContextBasedOnRequestUrl treats it as non-contextual and skips the context DB lookup.
     */
    private function setUpPluginApiRequest(string $handlerPath): void
    {
        // request1 config defines [i18n] locale = en so route()'s 404 fall-through branch can
        // call __() (which resolves the default locale from config) without a DB / site lookup.
        $this->setTestConfiguration('request1', 'classes/core/config');
        $this->_setUpMockEnvironment();

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['PATH_INFO'] = "/index/api/v1/{$handlerPath}";
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_NAME'] = 'mydomain.org';

        // The handler resolves the current request via the registry; the dispatch pipeline
        // resolves a separate Illuminate request from the container. Wire both.
        Registry::set('request', $this->request);
        $this->request->setRouter($this->apiRouter);

        // Build the dispatched request explicitly rather than via createFromGlobals(): the latter
        // relies on Symfony's environment-dependent base-URL heuristic to strip "/index.php", which
        // does not strip under the CI phpunit runner, leaving getPathInfo() = "/index.php/index/..."
        // so the context middleware treats "index.php" as a real context and the route match fails.
        // create() leaves SCRIPT_NAME empty, so getPathInfo() is exactly the supplied script-less
        // path in every environment ("/index" → NON_CONTEXTUAL_PATHS → context null, no DB lookup).
        $illuminateRequest = IlluminateRequest::create("/index/api/v1/{$handlerPath}", 'GET');
        app()->instance('request', $illuminateRequest);
        app()->instance(IlluminateRequest::class, $illuminateRequest);
    }

    /**
     * Build an on-the-fly generic plugin that, when enabled, registers a site-wide API
     * controller via the APIHandler::endpoints::plugin hook
     */
    private function makeOnTheFlyApiPlugin(PKPBaseController $controller, bool $enabled): GenericPlugin
    {
        $plugin = new class () extends GenericPlugin {
            public PKPBaseController $apiController;
            public bool $pluginEnabled = true;

            public function getEnabled($contextId = null)
            {
                return $this->pluginEnabled;
            }

            public function register($category, $path, $mainContextId = null): bool
            {
                if (!$this->getEnabled($mainContextId)) {
                    return true;
                }

                Hook::add('APIHandler::endpoints::plugin', function (string $hookName, APIRouter $apiRouter): bool {
                    $apiRouter->registerPluginApiControllers([$this->apiController]);
                    return Hook::CONTINUE;
                });

                return true;
            }

            public function getName(): string
            {
                return 'onTheFlyApiPlugin';
            }

            public function getDisplayName(): string
            {
                return 'On-the-fly API plugin';
            }

            public function getDescription(): string
            {
                return 'Test plugin registering a site-wide API controller via the endpoints hook';
            }
        };

        $plugin->apiController = $controller;
        $plugin->pluginEnabled = $enabled;
        return $plugin;
    }

    /**
     * Build an on-the-fly site-wide API controller exposing a single GET endpoint
     */
    private function makeSiteWideApiController(string $handlerPath): PKPBaseController
    {
        return new class ($handlerPath) extends PKPBaseController {
            public function __construct(private string $handlerPath)
            {
            }

            public function getHandlerPath(): string
            {
                return $this->handlerPath;
            }

            public function isSiteWide(): bool
            {
                return true;
            }

            public function getRouteGroupMiddleware(): array
            {
                return [];
            }

            public function getGroupRoutes(): void
            {
                Route::get('', $this->getData(...))->name('test.plugin.api.getData');
            }

            public function getData(IlluminateRequest $illuminateRequest): JsonResponse
            {
                return response()->json(['message' => 'plugin api ok'], 200);
            }
        };
    }

    /**
     * End-to-end: an enabled plugin registers a site-wide API controller through the
     * APIHandler::endpoints::plugin hook, and a GET to /index/api/v1/{handlerPath} is routed,
     * dispatched, and returns the controller's JSON. Proves the full hook → register → match →
     * APIHandler → runRoutes → response chain.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPluginEndpointsHookServesSiteWideApi()
    {
        $handlerPath = 'custom-admin-plugin-path';
        $this->setUpPluginApiRequest($handlerPath);

        $controller = $this->makeSiteWideApiController($handlerPath);
        $this->makeOnTheFlyApiPlugin($controller, true)
            ->register('generic', 'plugins/generic/onTheFlyApiPlugin');

        $this->expectOutputRegex('/plugin api ok/');
        $this->apiRouter->route($this->request);
    }

    /**
     * Negative case: a disabled plugin registers no hook listener, so firing the
     * APIHandler::endpoints::plugin hook (the exact call route() makes) registers no plugin
     * controller — the endpoint is never wired in. Kept in-process (no route() dispatch) because
     * the disabled path ends in route()'s filesystem 404 branch, which calls exit().
     *
     * @hook APIHandler::endpoints::plugin [$this->apiRouter]
     */
    public function testPluginEndpointsHookSkippedForDisabledPlugin()
    {
        $this->_setUpMockEnvironment();

        $controller = $this->makeSiteWideApiController('custom-admin-plugin-path');
        $this->makeOnTheFlyApiPlugin($controller, false)
            ->register('generic', 'plugins/generic/onTheFlyApiPlugin');

        Hook::run('APIHandler::endpoints::plugin', [$this->apiRouter]);

        self::assertSame([], $this->registeredControllers());
    }
}

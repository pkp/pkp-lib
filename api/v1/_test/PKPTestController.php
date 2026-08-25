<?php

/**
 * @file api/v1/_test/PKPTestController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTestController
 *
 * @brief The test-only API namespace (/api/v1/_test/*) behind the Playwright
 * e2e harness. NEVER present in a production install: the endpoint's
 * index.php answers 404 unless TEST_API_KEY is in the server's process
 * environment, and every request must carry a matching X-Test-Key header
 * (403 otherwise).
 *
 * Routes (site-wide — /index.php/index/api/v1/_test/…):
 * - GET  bootstrap             — warm/cold probe ({installed, seeded})
 * - POST bootstrap             — declarative base seed (warm calls no-op)
 * - POST scenarios/context     — scratch context
 * - POST scenarios/submission  — submission at a declared end-state
 *
 * Every mutating request runs under Mail::fake() (seeding-side email is
 * dropped; only test-action mail reaches Mailpit) and inside a DB
 * transaction: a failed build rolls back rather than leaving half-created
 * state (design record 4). The acting user is the installer's admin, which is
 * why scratch contexts count admin among their managers (parity fact).
 */

namespace PKP\API\v1\_test;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\security\authorization\PublicAccessPolicy;
use PKP\testing\PKPBootstrapSeeder;
use PKP\testing\PKPContextScenarioBuilder;
use PKP\testing\PKPSubmissionScenarioBuilder;
use PKP\testing\SpecException;

abstract class PKPTestController extends PKPBaseController
{
    abstract protected function bootstrapSeeder(): PKPBootstrapSeeder;

    abstract protected function contextScenarioBuilder(): PKPContextScenarioBuilder;

    abstract protected function submissionScenarioBuilder(): PKPSubmissionScenarioBuilder;

    public function getHandlerPath(): string
    {
        return '_test';
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
        Route::get('bootstrap', $this->probe(...))->name('_test.bootstrap.probe');
        Route::post('bootstrap', $this->bootstrap(...))->name('_test.bootstrap');
        Route::post('scenarios/context', $this->contextScenario(...))->name('_test.scenarios.context');
        Route::post('scenarios/submission', $this->submissionScenario(...))->name('_test.scenarios.submission');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $envKey = getenv('TEST_API_KEY');
        if (!$envKey) {
            // Defense in depth — index.php already refuses to register.
            http_response_code(404);
            exit;
        }
        $headerKey = (string) app('request')->header('X-Test-Key');
        if (!$headerKey || !hash_equals($envKey, $headerKey)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'X-Test-Key header missing or wrong']);
            exit;
        }
        $this->setEnforceRestrictedSite(false);
        $this->addPolicy(new PublicAccessPolicy());
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function probe(Request $illuminateRequest): JsonResponse
    {
        $contextPath = (string) $illuminateRequest->query('context', '');
        try {
            return response()->json($this->bootstrapSeeder()->probe($contextPath), 200);
        } catch (\Throwable $e) {
            // An empty DB cannot even answer the context lookup — that IS the
            // cold signal, but report it as JSON rather than a raw 500 page.
            return response()->json([
                'installed' => false,
                'seeded' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function bootstrap(Request $illuminateRequest): JsonResponse
    {
        return $this->runBuilder(fn () => $this->bootstrapSeeder()->seed((array) $illuminateRequest->json()->all()));
    }

    public function contextScenario(Request $illuminateRequest): JsonResponse
    {
        return $this->runBuilder(fn () => $this->contextScenarioBuilder()->build((array) $illuminateRequest->json()->all()));
    }

    public function submissionScenario(Request $illuminateRequest): JsonResponse
    {
        return $this->runBuilder(fn () => $this->submissionScenarioBuilder()->build((array) $illuminateRequest->json()->all()));
    }

    /**
     * Shared builder harness: Mail::fake, admin as acting user, one DB
     * transaction. SpecException → 400 with the dotted specKey; anything
     * else → 500 with the exception summary.
     */
    protected function runBuilder(callable $build): JsonResponse
    {
        Mail::fake();
        $admin = Repo::user()->getByUsername('admin', true);
        if ($admin) {
            Registry::set('user', $admin);
        }

        DB::beginTransaction();
        try {
            $result = $build();
            DB::commit();
            return response()->json($result, 200);
        } catch (SpecException $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
                'specKey' => $e->specKey,
            ], 400);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }
}

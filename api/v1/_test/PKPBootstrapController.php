<?php

/**
 * @file api/v1/_test/PKPBootstrapController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBootstrapController
 *
 * @ingroup api_v1_test
 *
 * @brief Handles POST /api/v1/_test/bootstrap — the baseline test-data
 *        endpoint that creates the shared journal(s), users, sections,
 *        categories and issues for a Playwright test session.
 *
 * Gated by the TestModeGate middleware (APPLICATION_ENV === 'test' and
 * X-Test-Key header match). The entire build runs inside a single
 * DB::transaction so any processor failure rolls the whole scenario back.
 *
 * Apps subclass this to add app-specific processors (e.g. OJS's issues).
 */

namespace PKP\API\v1\_test;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\testing\bootstrap\Processor\CategoryProcessor;
use PKP\testing\bootstrap\Processor\IssueProcessor;
use PKP\testing\bootstrap\Processor\JournalProcessor;
use PKP\testing\bootstrap\Processor\SectionProcessor;
use PKP\testing\bootstrap\Processor\UserProcessor;
use PKP\testing\scenario\ScenarioContext;

class PKPBootstrapController extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return '_test';
    }

    public function getRouteGroupMiddleware(): array
    {
        return ['test.mode'];
    }

    public function getGroupRoutes(): void
    {
        Route::post('bootstrap', $this->bootstrap(...))
            ->name('test.bootstrap');
    }

    /**
     * TestModeGate is the only authorization we apply to this endpoint.
     * Skip the usual role-based authorization flow.
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        return true;
    }

    public function bootstrap(Request $illuminateRequest): JsonResponse
    {
        $spec = $illuminateRequest->all();

        $schemaPath = __DIR__ . '/../../../classes/testing/bootstrap/schema/bootstrap.json';
        $validationError = $this->validateAgainstSchema($spec, $schemaPath);
        if ($validationError !== null) {
            return response()->json(['error' => 'Invalid spec', 'details' => $validationError], Response::HTTP_BAD_REQUEST);
        }

        if ($this->alreadyBootstrapped($spec)) {
            return response()->json(
                ['error' => 'A journal with one of the requested paths already exists. Recreate the container to re-bootstrap.'],
                Response::HTTP_CONFLICT
            );
        }

        $ctx = new ScenarioContext();
        $journalProcessor = new JournalProcessor(
            new SectionProcessor(),
            new CategoryProcessor(),
            new IssueProcessor(),
        );
        $userProcessor = new UserProcessor();

        try {
            DB::transaction(function () use ($spec, $ctx, $journalProcessor, $userProcessor) {
                if ($journalProcessor->appliesTo($spec)) {
                    $journalProcessor->run($spec, $ctx);
                }
                if ($userProcessor->appliesTo($spec)) {
                    $userProcessor->run($spec, $ctx);
                }
                // Deferred because section editors reference users by username.
                $journalProcessor->applyDeferredAssignments($ctx);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Bootstrap failed',
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json($ctx->idMap(), Response::HTTP_OK);
    }

    /**
     * Naive schema validation using Opis if available, else a loose key check.
     * Returns null on success, or a short error string.
     */
    private function validateAgainstSchema(array $spec, string $schemaPath): ?string
    {
        if (!is_readable($schemaPath)) {
            return "Schema file not found: {$schemaPath}";
        }

        if (class_exists(\Opis\JsonSchema\Validator::class)) {
            $validator = new \Opis\JsonSchema\Validator();
            $result = $validator->validate(
                json_decode(json_encode($spec)),
                file_get_contents($schemaPath)
            );
            if (!$result->isValid()) {
                $error = $result->error();
                return $error ? $error->message() . ' at ' . implode('/', $error->data()->path()) : 'Validation failed';
            }
            return null;
        }

        // Opis unavailable — fall back to minimal structural check.
        if (!isset($spec['journals']) || !is_array($spec['journals']) || empty($spec['journals'])) {
            return 'spec.journals is required and must be a non-empty array';
        }
        foreach ($spec['journals'] as $i => $journal) {
            if (empty($journal['path']) || empty($journal['name'])) {
                return "journals[{$i}] requires 'path' and 'name'";
            }
        }
        return null;
    }

    /**
     * Cheap 409 guard: if any of the requested journal paths already exist,
     * refuse to proceed. Prevents silent duplicate-journal bugs.
     */
    private function alreadyBootstrapped(array $spec): bool
    {
        $contextDao = \APP\core\Application::getContextDAO();
        foreach ($spec['journals'] ?? [] as $journalSpec) {
            $path = $journalSpec['path'] ?? null;
            if (!$path) {
                continue;
            }
            if (method_exists($contextDao, 'getByPath')) {
                if ($contextDao->getByPath($path)) {
                    return true;
                }
            }
        }
        return false;
    }
}

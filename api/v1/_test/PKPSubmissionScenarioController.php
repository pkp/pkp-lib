<?php

/**
 * @file api/v1/_test/PKPSubmissionScenarioController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionScenarioController
 *
 * @ingroup api_v1_test
 *
 * @brief Handles POST /api/v1/_test/scenarios/submission — creates a
 *        single submission with any combination of participants,
 *        decisions, review rounds, and publications as declared in the
 *        spec.
 *
 * Gated by the TestModeGate middleware (APPLICATION_ENV === 'test' and
 * X-Test-Key header match). Dispatches a fixed pipeline of processors
 * inside a single DB::transaction so any processor failure rolls the
 * whole scenario back.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\Validation;
use PKP\testing\scenario\Processor\DecisionProcessor;
use PKP\testing\scenario\Processor\ParticipantProcessor;
use PKP\testing\scenario\Processor\PublicationsProcessor;
use PKP\testing\scenario\Processor\ReviewRoundProcessor;
use PKP\testing\scenario\Processor\SubmissionBuilderProcessor;
use PKP\testing\scenario\ScenarioContext;

class PKPSubmissionScenarioController extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return '_test/scenarios';
    }

    public function getRouteGroupMiddleware(): array
    {
        return ['test.mode'];
    }

    public function getGroupRoutes(): void
    {
        Route::post('submission', $this->submission(...))
            ->name('test.scenarios.submission');
    }

    /**
     * TestModeGate is the only authorization we apply to this endpoint.
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        return true;
    }

    public function submission(Request $illuminateRequest): JsonResponse
    {
        $spec = $illuminateRequest->all();

        $schemaPath = __DIR__ . '/../../../classes/testing/scenario/schema/submission.json';
        $validationError = $this->validateAgainstSchema($spec, $schemaPath);
        if ($validationError !== null) {
            return response()->json(['error' => 'Invalid spec', 'details' => $validationError], Response::HTTP_BAD_REQUEST);
        }

        // Capture outbound mail for the whole request so decisions etc.
        // don't queue real messages. Other events (event log, notifications)
        // fire normally so tests can observe them.
        Mail::fake();

        // The scenario endpoint isn't routed through a context-bearing URL,
        // so OJS's Request->getContext() returns null. A number of Repo
        // side-effects (notifications, event log) dereference that context,
        // NPE'ing on us. Attach the spec's journal to the PKPRouter so those
        // internals see something sane. This is a workaround for an OJS
        // internal assumption, not a user-facing contract — tests still
        // think of the endpoint as context-agnostic.
        //
        // Stash + restore the prior `_context` so a residue can't leak to
        // a sibling request handled by the same PHP-CLI worker process
        // under workers=2 (mirrors the Registry::set/get save-restore
        // pattern in ContextBuilderProcessor).
        $context = Application::getContextDAO()->getByPath($spec['journal'] ?? '');
        if (!$context) {
            return response()->json(
                ['error' => "Journal '{$spec['journal']}' not found. Bootstrap must seed it first."],
                Response::HTTP_BAD_REQUEST
            );
        }
        $router = Application::get()->getRequest()->getRouter();
        $previousRouterContext = $router->_context;
        $router->_context = $context;

        // Do NOT call Validation::registerUserSession here — Playwright's
        // `request` fixture shares cookies with the browser context, so
        // mutating the session from this endpoint regenerates the browser
        // user's OJSSID and drops their remember_web cookie. The net
        // effect is the browser ends up logged out mid-test. The Repo
        // side-effects we need only require a context ($request->getContext())
        // and an editor-by-id (passed in each decision spec), not a
        // "current user" from the session.

        $ctx = new ScenarioContext();
        $submissionBuilder = new SubmissionBuilderProcessor();
        $participantProcessor = new ParticipantProcessor();
        $reviewRoundProcessor = new ReviewRoundProcessor();
        $decisionProcessor = new DecisionProcessor($reviewRoundProcessor);
        $publicationsProcessor = new PublicationsProcessor();

        try {
            try {
                DB::transaction(function () use ($spec, $ctx, $submissionBuilder, $participantProcessor, $decisionProcessor, $publicationsProcessor) {
                    $submissionBuilder->run($spec, $ctx);
                    if ($participantProcessor->appliesTo($spec)) {
                        $participantProcessor->run($spec, $ctx);
                    }
                    if ($decisionProcessor->appliesTo($spec)) {
                        $decisionProcessor->run($spec, $ctx);
                    }
                    if ($publicationsProcessor->appliesTo($spec)) {
                        $publicationsProcessor->run($spec, $ctx);
                    }
                });
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'Scenario build failed',
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return response()->json($ctx->submissionResponse($spec['tag'] ?? ''), Response::HTTP_OK);
        } finally {
            $router->_context = $previousRouterContext;
        }
    }

    /**
     * Naive schema validation — Opis if available, else a minimal check
     * that required top-level keys are present. Same pattern as Phase 1's
     * bootstrap controller.
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

        // Opis unavailable — minimal structural check.
        foreach (['tag', 'journal', 'submitter', 'section'] as $required) {
            if (empty($spec[$required])) {
                return "spec.{$required} is required";
            }
        }
        return null;
    }
}

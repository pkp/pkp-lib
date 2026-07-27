<?php

/**
 * @file api/v1/_test/BootstrapRoutes.php
 *
 * Copyright (c) 2023-2026 Simon Fraser University
 * Copyright (c) 2023-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait BootstrapRoutes
 *
 * @ingroup api_v1__test
 *
 * @brief POST /api/v1/_test/bootstrap — seed the suite's shared base context.
 *
 * A TRAIT rather than a base class: bootstrap is a context scenario plus the
 * suite's user roster, so each app's bootstrap controller extends that app's own
 * context scenario controller (inheriting its overlay) and mixes this in.
 *
 * The base seed is DECLARED BY THE TEST SUITE, not by PHP: the payload carries
 * the context, its structure and its user roster, so changing the base seed is a
 * change to the harness's fixture file and never to application code.
 *
 * Idempotency: calling bootstrap when the context already exists is a NO-OP that
 * answers 200 with `seeded: false`. Every Playwright worker starts by asking for
 * the base seed, and a hard failure there would turn a warm database into a red
 * suite; the JS setup's "detect seeded, skip" check and this no-op agree.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait BootstrapRoutes
{
    /**
     * Report whether a base context has been seeded, so the JS setup can skip.
     */
    public function status(Request $illuminateRequest): JsonResponse
    {
        $urlPath = (string) $illuminateRequest->query('context', '');
        $context = $urlPath ? Application::getContextDAO()->getByPath($urlPath) : null;

        return response()->json([
            'seeded' => (bool) $context,
            'contextId' => $context?->getId(),
        ]);
    }

    public function bootstrap(Request $illuminateRequest): JsonResponse
    {
        return $this->build(function () use ($illuminateRequest) {
            $spec = $this->readSpec($illuminateRequest, $this->bootstrapSchema());
            $contextSpec = $spec['context'];

            $existing = Application::getContextDAO()->getByPath($contextSpec['urlPath']);

            if ($existing) {
                return [
                    'seeded' => false,
                    'reason' => 'already-seeded',
                    'contextId' => $existing->getId(),
                    'urlPath' => $existing->getPath(),
                ];
            }

            return $this->actingAs($this->siteAdmin(), function () use ($spec, $contextSpec) {
                $context = $this->createContext($contextSpec, 'context');
                $users = $this->seedUsers($context, $spec['users'] ?? [], 'users');

                return [
                    'seeded' => true,
                    'contextId' => $context->getId(),
                    'urlPath' => $context->getPath(),
                    'users' => $users,
                ] + $this->contextEcho($context, $contextSpec);
            });
        });
    }

    /**
     * The bootstrap schema wraps the context spec so the base seed reads as one
     * document. The context spec is the same one the scenario endpoint validates,
     * minus the scenario-only `tag` requirement and with `users` lifted to the top
     * level.
     */
    protected function bootstrapSchema(): array
    {
        $contextSchema = $this->schema('context');
        unset($contextSchema['properties']['users']);
        $contextSchema['required'] = ['urlPath', 'name'];
        $contextSchema['properties']['tag'] = ['type' => 'string', 'maxLength' => 64];

        $userSchema = $this->userSchema();
        $userSchema['required'] = ['username', 'roles'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['context'],
            'properties' => [
                'context' => $contextSchema,
                'users' => ['type' => 'array', 'items' => $userSchema],
            ],
        ];
    }
}

<?php

/**
 * @file api/v1/editTaskTemplates/PKPEditTaskTemplateController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEditTaskTemplateController
 *
 * @ingroup api_v1_edit_templates
 *
 * @brief Controller class to handle API requests for edit templates.
 */

namespace PKP\API\v1\editTaskTemplates;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use APP\facades\Repo;
use PKP\editorialTask\Template;
use PKP\API\v1\editTaskTemplates\formRequests\ListTaskTemplates;
use PKP\API\v1\editTaskTemplates\resources\EditTaskTemplateResource;

class PKPEditTaskTemplateController extends PKPBaseController
{
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new CanAccessSettingsPolicy());
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getHandlerPath(): string
    {
        return 'editTaskTemplates';
    }

    public function getRouteGroupMiddleware(): array
    {
        return ['has.user', 'has.context'];
    }

    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ])->group(function () {
            Route::get('', $this->index(...));
        });
    }

    /**
     * GET /api/v1/editTaskTemplates
     */
    public function index(ListTaskTemplates $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $validated = $illuminateRequest->validated();

        $q = Template::query()
            ->where('context_id', $context->getId())
            ->with('userGroups');

        if (isset($validated['stageId'])) {
            $q->where('stage_id', (int) $validated['stageId']);
        }

        if (array_key_exists('include', $validated) && $validated['include'] !== null) {
            $q->where('include', (bool) $validated['include']);
        }

        if (!empty($validated['emailTemplateKey'])) {
            $et = Repo::emailTemplate()->getByKey($context->getId(), $validated['emailTemplateKey']);
            $q->where('email_template_id', $et?->getId() ?? 0);
        }

        $items = $q->orderByPkDesc()->get()
            ->map(fn ($tpl) => (new EditTaskTemplateResource($tpl))->toArray($illuminateRequest))
            ->values()
            ->all();

        return response()->json(['items' => $items], Response::HTTP_OK);
    }
}

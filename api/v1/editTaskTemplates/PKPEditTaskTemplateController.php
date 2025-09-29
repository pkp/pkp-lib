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
use Illuminate\Support\Facades\DB;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\API\v1\editTaskTemplates\formRequests\AddTaskTemplate;
use PKP\API\v1\editTaskTemplates\resources\TaskTemplateResource;
use Illuminate\Http\Request;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\editorialTask\Template;
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
            Route::post('', $this->add(...));
            Route::get('', $this->getMany(...));
        });
    }

    /**
     * POST /api/v1/editTaskTemplates
     */
    public function add(AddTaskTemplate $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $validated = $illuminateRequest->validated();

        $template = DB::transaction(function () use ($validated, $context) {
            $tpl = Template::create([
                'stage_id' => $validated['stageId'],
                'title' => $validated['title'],
                'context_id' => $context->getId(),
                'include' => $validated['include'] ?? false,
                'email_template_key' => $validated['emailTemplateKey'] ?? null,
            ]);

            $tpl->userGroups()->sync($validated['userGroupIds']);

            return $tpl;
        });

        // return via Resource
        return response()->json(
            (new TaskTemplateResource($template->refresh()->load('userGroups')))
                ->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    /**
     * GET /api/v1/editTaskTemplates
     */
    public function getMany(Request $request): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        // Start with our standard collector/scopes
        $collector = Template::byContextId((int) $context->getId())
            ->with('userGroups');

        // Apply supported filters from query params via model scopes
        foreach ($request->query() as $param => $val) {
            switch ($param) {
                case 'stageId':
                    if ($val !== null && $val !== '') {
                        $collector = $collector->withStageId((int) $val);
                    }
                    break;

                case 'include':
                    // Accept "true"/"false", 1/0, etc.
                    $bool = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool !== null) {
                        $collector = $collector->withInclude($bool);
                    }
                    break;

                case 'emailTemplateKey':
                    $key = trim((string) $val);
                    if ($key !== '') {
                        $collector = $collector->withEmailTemplateKey($key);
                    }
                    break;

                // ignore unknown params
            }
        }

        $collection = $collector->orderByPkDesc()->get();

        return EditTaskTemplateResource::collection($collection)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

}

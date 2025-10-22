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
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\editTaskTemplates\formRequests\AddTaskTemplate;
use PKP\API\v1\editTaskTemplates\resources\TaskTemplateResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\editorialTask\Template;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

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

    protected function _processAllowedParams(array $query, array $allowed): array
    {
        $allowedMap = array_flip($allowed);
        $filtered = array_intersect_key($query, $allowedMap);

        foreach ($filtered as $k => $v) {
            if ($v === '' || $v === null) {
                unset($filtered[$k]);
            }
        }

        return $filtered;
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
                'type' => $validated['type'],
                'stageId' => $validated['stageId'],
                'title' => $validated['title'],
                'contextId' => $context->getId(),
                'include' => $validated['include'] ?? false,
                'description' => $validated['description'] ?? null,
                'dueInterval' => $validated['dueInterval'] ?? null,
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

        $collector = Template::query()
            ->byContextId((int) $context->getId())
            ->with('userGroups');

        $queryParams = $this->_processAllowedParams($request->query(), [
            'stageId',
            'include',
            'count',
            'offset',
        ]);

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'stageId':
                    $collector->filterByStageId((int) $val);
                    break;

                case 'include':
                    $bool = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($bool !== null) {
                        $collector->filterByInclude($bool);
                    }
                    break;

                case 'count':
                    $collector->limit((int) $val);
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;
            }
        }

        $collection = $collector->orderByPkDesc()->get();

        return TaskTemplateResource::collection($collection)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

}

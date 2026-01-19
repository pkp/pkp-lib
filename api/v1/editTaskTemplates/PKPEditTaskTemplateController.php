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
use PKP\API\v1\editTaskTemplates\formRequests\UpdateTaskTemplate;
use PKP\API\v1\editTaskTemplates\resources\TaskTemplateResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\editorialTask\Template;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class PKPEditTaskTemplateController extends PKPBaseController
{
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
            Route::put('{templateId}', $this->update(...))->whereNumber('templateId');
            Route::delete('{templateId}', $this->delete(...))->whereNumber('templateId');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...));
        });
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);
        ;

        if (in_array($actionName, ['add', 'update', 'delete'])) {
            // These actions require access to settings
            $this->addPolicy(new CanAccessSettingsPolicy());
        }

        return parent::authorize($request, $args, $roleAssignments);
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
                'restrictToUserGroups' => $validated['restrictToUserGroups'] ?? false,
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
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();

        $collector = Template::query()
            ->byContextId((int) $context->getId())
            ->with('userGroups');

        $queryParams = $this->_processAllowedParams($illuminateRequest->query(), [
            'search',
            'title',
            'type',
            'stageId',
            'include',
            'count',
            'offset',
        ]);

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'search':
                    $collector->filterBySearch((string) $val);
                    break;
                case 'title':
                    $collector->filterByTitleLike((string) $val);
                    break;
                case 'type':
                    $type = (int) $val;
                    if (in_array($type, array_column(EditorialTaskType::cases(), 'value'), true)) {
                        $collector->filterByType($type);
                    }
                    break;
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

        // Get the current user's user groups in the current context
        $userGroups = UserGroup::withContextIds([$context->getId()])
            ->withUserIds([$user->getId()])
            ->get();

        $isManager = $userGroups->contains(
            fn (UserGroup $userGroup) =>
            in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])
        );

        // Get templates accounting for user group restrictions
        if (!$isManager) {
            $collector->withUserGroupIds($userGroups->pluck('id')->toArray());
        }

        $collection = $collector->orderByPkDesc()->get();

        return TaskTemplateResource::collection($collection)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * UPDATE /api/v1/editTaskTemplates/{templateId}
     */
    public function update(UpdateTaskTemplate $request): JsonResponse
    {
        $contextId = (int) $this->getRequest()->getContext()->getId();
        $id = (int) $request->route('templateId');

        $template = Template::query()
            ->byContextId($contextId)
            ->find($id);

        if (!$template) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validated();

        DB::transaction(function () use ($template, $data) {
            $userGroupIds = \Illuminate\Support\Arr::pull($data, 'userGroupIds');

            $template->update($data);

            if ($userGroupIds !== null) {
                $template->userGroups()->sync($userGroupIds);
            }
        });

        return response()->json(
            (new TaskTemplateResource($template->refresh()->load('userGroups')))->toArray($request),
            Response::HTTP_OK
        );
    }

    /**
     * DELETE /api/v1/editTaskTemplates/{templateId}
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $contextId = (int) $this->getRequest()->getContext()->getId();
        $id = (int) $illuminateRequest->route('templateId');

        $template = Template::query()
            ->byContextId($contextId)
            ->find($id);

        if (!$template) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $resource = new TaskTemplateResource($template->load('userGroups'));

        DB::transaction(function () use ($template) {
            // Pivot/settings rows cascade via FKs defined in migration
            $template->delete();
        });

        return response()->json(
            $resource->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

}

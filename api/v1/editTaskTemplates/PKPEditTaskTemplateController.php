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
use Illuminate\Support\Arr;
use PKP\API\v1\editTaskTemplates\formRequests\AddTaskTemplate;
use PKP\API\v1\editTaskTemplates\resources\TaskTemplateResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\editorialTask\Template;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\API\v1\editTaskTemplates\formRequests\UpdateTaskTemplate;

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
            Route::put('{templateId}', $this->update(...))->whereNumber('templateId');
            Route::delete('{templateId}', $this->delete(...))->whereNumber('templateId');
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
                'type' => (int) $validated['type'],
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
                    if (is_numeric($val)) {
                        $type = (int) $val;
                        if (in_array($type, [EditorialTaskType::DISCUSSION->value, EditorialTaskType::TASK->value], true)) {
                            $collector->filterByType($type);
                        }
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
            $userGroupIds = Arr::pull($data, 'userGroupIds');
            $updates = collect($data)
                ->only(['stageId', 'title', 'include', 'type', 'description', 'dueInterval'])
                ->mapWithKeys(function ($v, $k) {
                    return match ($k) {
                        'stageId' => ['stageId' => (int) $v],
                        'include' => ['include' => (bool) $v],
                        'type' => ['type' => (int) $v],
                        'dueInterval' => ['dueInterval' => $v],
                        default => [$k => $v], // 'title', 'description'
                    };
                })
                ->all();

            $template->update($updates);

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

        DB::transaction(function () use ($template) {
            // Pivot/settings rows cascade via FKs defined in migration
            $template->delete();
        });

        return response()->json([], Response::HTTP_OK);
    }

}

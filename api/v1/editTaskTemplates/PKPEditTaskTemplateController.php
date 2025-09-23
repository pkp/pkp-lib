<<<<<<< HEAD
=======
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
            Route::get('', $this->getMany(...));
        });
    }

    /**
     * GET /api/v1/editTaskTemplates
     */
    public function getMany(Request $request): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $q = Template::query()
            ->byContextId((int) $context->getId())
            ->with('userGroups');

        if ($request->filled('stageId')) {
            $q->where('stage_id', (int) $request->query('stageId'));
        }

        if ($request->has('include')) {
            $include = filter_var($request->query('include'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($include !== null) {
                $q->where('include', $include);
            }
        }

        if ($request->filled('emailTemplateKey')) {
            $q->where('email_template_key', trim((string) $request->query('emailTemplateKey')));
        }
        $collection = $q->orderByPkDesc()->get();
        return EditTaskTemplateResource::collection($collection)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
     }
}
>>>>>>> 3036c1a2c0... pkp/pkp-lib#11833 Rename to getMany, drop validation, use scope, return collection

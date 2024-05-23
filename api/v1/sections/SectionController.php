<?php

/**
 * @file api/v1/sections/SectionController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionController
 *
 * @ingroup api_v1_section
 *
 * @brief Controller class to handle API requests for section operations.
 *
 */

namespace PKP\API\v1\sections;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class SectionController extends PKPBaseController
{
    /** @var int The default number of sections to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of sections to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'sections';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('section.getMany');

        Route::get('{sectionId}', $this->get(...))
            ->name('section.getSection')
            ->whereNumber('sectionId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single submission
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $section = Repo::section()->get((int) $illuminateRequest->route('sectionId'));

        if (!$section) {
            return response()->json([
                'error' => __('api.sections.404.sectionNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // The assocId in sections should always point to the contextId
        if ($section->getContextId() !== $this->getRequest()->getContext()?->getId()) {
            return response()->json([
                'error' => __('api.sections.400.contextsNotMatched')
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(Repo::section()->getSchemaMap()->map($section), Response::HTTP_OK);
    }

    /**
     * Get a collection of sections
     *
     * @hook API::sections::params [$collector, $illuminateRequest]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::section()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'typeIds':
                    $collector->filterByTypeIds(
                        array_map('intval', paramToArray($val))
                    );
                    break;
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;
            }
        }

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        Hook::run('API::sections::params', [$collector, $illuminateRequest]);

        $sections = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::section()->getSchemaMap()->summarizeMany($sections)->values(),
        ], Response::HTTP_OK);
    }
}

<?php

/**
 * @file api/v1/_dois/PKPBackendDoiController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendDoiController
 *
 * @ingroup api_v1_backend
 *
 * @brief Controller class to handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_dois;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
<<<<<<< HEAD
use PKP\submission\GenreDAO;
use PKP\userGroup\UserGroup;

=======
use PKP\submission\genre\Genre;
>>>>>>> 9de162ec3b... pkp/pkp-lib#10133 Port Genre and GenreDAO to Use Eloquent Model

class PKPBackendDoiController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_dois';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
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
        Route::put('publications/{publicationId}', $this->editPublication(...))
            ->name('_doi.backend.publication.edit')
            ->whereNumber('publicationId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $this->addPolicy(new DoisEnabledPolicy($request->getContext()));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }


    /**
     * Edit publication to add DOI
     *
     * @throws \Exception
     */
    public function editPublication(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $publication = Repo::publication()->get($illuminateRequest->route('publicationId'));
        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        if ($submission->getData('contextId') !== $context->getId()) {
            return response()->json([
                'error' => __('api.dois.403.editItemOutOfContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_PUBLICATION, $illuminateRequest->input());

        $doi = Repo::doi()->get((int) $params['doiId']);
        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        Repo::publication()->edit($publication, ['doiId' => $doi->getId()]);
        $publication = Repo::publication()->get($publication->getId());

        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $contextId = $submission->getData('contextId');
        $userGroups = UserGroup::withContextIds($contextId)->get();


        $genres = Genre::where('context_id', $submission->getData('contextId'))->get()->toArray();


        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            Response::HTTP_OK
        );
    }
}

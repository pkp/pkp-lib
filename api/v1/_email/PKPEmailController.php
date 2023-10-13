<?php

/**
 * @file api/v1/_email/PKPEmailController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailController
 *
 * @ingroup api_v1__email
 *
 * @brief Controller class to handle API request to send bulk email
 */

namespace PKP\API\v1\_email;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\jobs\bulk\BulkEmailSender;
use PKP\mail\Mailer;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPEmailController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_email';
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
        Route::post('', $this->create(...))->name('_email.create');
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
     * Create a jobs queue to send a bulk email to users in one or more user groups
     */
    public function create(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $contextId = $context->getId();

        if (!in_array($contextId, (array) $this->getRequest()->getSite()->getData('enableBulkEmails'))) {
            return response()->json([
                'error' => __('api.emails.403.disabled'),
            ], Response::HTTP_FORBIDDEN);
        }

        $requestParams = $illuminateRequest->input();

        $params = [];
        foreach ($requestParams as $param => $val) {
            switch ($param) {
                case 'userGroupIds':
                    if (!is_array($val)) {
                        $val = strlen(trim($val))
                            ? explode(',', $val)
                            : [];
                    }
                    $params[$param] = array_map('intval', $val);
                    break;
                case 'body':
                case 'subject':
                    $params[$param] = $val;
                    break;
                case 'copy':
                    $params[$param] = (bool) $val;
                    break;
            }
        }

        $errors = [];
        if (empty($params['body'])) {
            $errors['body'] = [__('api.emails.400.missingBody')];
        }

        if (empty($params['subject'])) {
            $errors['subject'] = [__('api.emails.400.missingSubject')];
        }

        if (empty($params['userGroupIds'])) {
            $errors['userGroupIds'] = [__('api.emails.400.missingUserGroups')];
        }

        if ($errors) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        foreach ($params['userGroupIds'] as $userGroupId) {
            if (!Repo::userGroup()->contextHasGroup($contextId, $userGroupId)
                    || in_array($userGroupId, (array) $context->getData('disableBulkEmailUserGroups'))) {
                return response()->json([
                    'userGroupIds' => [__('api.emails.403.notAllowedUserGroup')],
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $userIds = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByUserGroupIds($params['userGroupIds'])
            ->getIds()
            ->toArray();

        if (!empty($params['copy'])) {
            $currentUserId = $this->getRequest()->getUser()->getId();
            if (!in_array($currentUserId, $userIds)) {
                $userIds[] = $currentUserId;
            }
        }

        $batches = array_chunk($userIds, Mailer::BULK_EMAIL_SIZE_LIMIT);
        $jobs = [];

        foreach ($batches as $userIds) {
            $jobs[] = new BulkEmailSender(
                $userIds,
                $contextId,
                $params['subject'],
                $params['body'],
                $context->getData('contactEmail'),
                $context->getData('contactName')
            );
        }

        Bus::batch($jobs)->dispatch();

        return response()->json([
            'totalBulkJobs' => count($batches),
        ], Response::HTTP_OK);
    }
}

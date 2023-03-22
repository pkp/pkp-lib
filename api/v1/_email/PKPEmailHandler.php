<?php

/**
 * @file api/v1/_email/PKPEmailHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailHandler
 * @ingroup api_v1_announcement
 *
 * @brief Handle API request to send bulk email
 *
 */

namespace PKP\API\v1\_email;

use APP\facades\Repo;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\jobs\bulk\BulkEmailSender;
use PKP\mail\Mailer;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Psr\Http\Message\ServerRequestInterface;

class PKPEmailHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = '_email';
        
        $this->_endpoints = [
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'create'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
                ],
            ],
        ];

        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
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
     * Create a jobs queue to send a bulk email to users in one or
     * more user groups
     *
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function create(ServerRequestInterface $slimRequest, APIResponse $response, array $args)
    {
        $context = $this->getRequest()->getContext();
        $contextId = $context->getId();

        if (!in_array($contextId, (array) $this->getRequest()->getSite()->getData('enableBulkEmails'))) {
            return $response->withStatus(403)->withJsonError('api.emails.403.disabled');
        }

        $requestParams = $slimRequest->getParsedBody();

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
            return $response->withJson($errors, 400);
        }

        foreach ($params['userGroupIds'] as $userGroupId) {
            if (!Repo::userGroup()->contextHasGroup($contextId, $userGroupId)
                    || in_array($userGroupId, (array) $context->getData('disableBulkEmailUserGroups'))) {
                return $response->withJson([
                    'userGroupIds' => [__('api.emails.403.notAllowedUserGroup')],
                ], 400);
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

        return $response->withJson([
            'totalBulkJobs' => count($batches),
        ], 200);
    }

}

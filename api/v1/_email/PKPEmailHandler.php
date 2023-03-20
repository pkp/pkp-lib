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
 * @brief Handle API requests for announcement operations.
 *
 */

namespace PKP\API\v1\_email;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\jobs\bulk\BulkEmailSender;
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
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{queueId}',
                    'handler' => [$this, 'process'],
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

        $queueId = 'email_' . uniqid();
        $batches = array_chunk($userIds, BulkEmailSender::EMAILS_PER_JOB);

        foreach ($batches as $userIds) {
            Queue::push(
                new BulkEmailSender(
                    $userIds, 
                    $contextId, 
                    $params['subject'], 
                    $params['body'], 
                    $context->getData('contactEmail'), 
                    $context->getData('contactName')
                ),
                [],
                $queueId
            );
        }

        return $response->withJson([
            'queueId' => $queueId,
            'totalJobs' => count($batches),
        ], 200);
    }

    /**
     * Process a jobs queue for sending a bulk email
     *
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function process(ServerRequestInterface $slimRequest, APIResponse $response, array $args)
    {
        $countRunning = Repo::job()->getRunningJobCount($args['queueId']);
        $countPending = Repo::job()->getPendingJobCount($args['queueId']);
        $jobQueue = app('pkpJobQueue'); /** @var \PKP\core\PKPQueueProvider $jobQueue */

        // Don't run another job if one is already running.
        // This should ensure jobs are run one after the other and
        // prevent long-running jobs from running simultaneously
        // and piling onto the server like a DDOS attack.
        if (!$countRunning && $countPending) {

            $jobQueue->forQueue($args['queueId'])->runJobInQueue();

            // Update count of pending jobs
            $countPending = Repo::job()->getPendingJobCount($args['queueId']);
        }

        return $response->withJson([
            'pendingJobs' => $countPending,
        ], 200);
    }

}

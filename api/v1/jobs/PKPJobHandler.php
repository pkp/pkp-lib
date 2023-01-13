<?php

/**
 * @file api/v1/jobs/PKPJobHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiHandler
 * @ingroup api_v1_jobs
 *
 * @brief Handle API requests for jobs
 *
 */

namespace PKP\API\v1\jobs;

use APP\facades\Repo;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

class PKPJobHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_apiForAdmin = true;

        $this->_handlerPath = 'jobs';
        
        $roles = [Role::ROLE_ID_SITE_ADMIN];

        $this->_endpoints = array_merge_recursive($this->_endpoints, [
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/redispatch/{jobId}',
                    'handler' => [$this, 'redispatchFailedJob'],
                    'roles' => $roles,
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/failed/delete/{jobId}',
                    'handler' => [$this, 'deleteFailedJob'],
                    'roles' => $roles,
                ],
            ],
        ]);

        parent::__construct();
    }

    /**
     * @param \PKP\handler\Request $request
     * @param array $args
     * @param array $roleAssignments
     *
     * @return bool
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
     * Redispatch a failed job back to queue
     */
    public function redispatchFailedJob(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $failedJob = Repo::failedJob()->get((int) $args['jobId']);
        
        if (!$failedJob) {
            return $response->withStatus(404)->withJson([
                'errorMessage' => __('api.jobs.404.failedJobNotFound')
            ]);
        }

        if (!$failedJob->payload) {
            return $response->withStatus(406)->withJson([
                'errorMessage' => __('api.jobs.406.failedJobPayloadMissing')
            ]);
        }

        return Repo::failedJob()->redispatchToQueue(null ,[$failedJob->id])
            ? $response->withJson(['message' => __('api.jobs.200.failedJobRedispatchedSucceed')], 200)
            : $response->withStatus(400)->withJson(['errorMessage' => __('api.jobs.400.failedJobRedispatchedFailed')]);
    }

    /**
     * Delete a failed job from failed list
     */
    public function deleteFailedJob(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $failedJob = Repo::failedJob()->get((int) $args['jobId']);
        
        if (!$failedJob) {
            return $response->withStatus(404)->withJson([
                'errorMessage' => __('api.jobs.404.failedJobNotFound')
            ]);
        }

        return $failedJob->delete()
            ? $response->withJson(['message' => __('api.jobs.200.failedJobDeleteSucceed')], 200)
            : $response->withStatus(400)->withJson(['errorMessage' => __('api.jobs.400.failedJobDeleteFailed')]);
    }
}

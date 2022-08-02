<?php
/**
 * @file api/v1/_payments/PKPBackendPaymentsSettingsHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendPaymentsSettingsHandler
 * @ingroup api_v1_backend
 *
 * @brief A private API endpoint handler for payment settings. It may be
 *  possible to deprecate this when we have a working endpoint for plugin
 *  settings.
 */

namespace PKP\API\v1\_payments;

use APP\core\Services;
use PKP\handler\APIHandler;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

use PKP\services\interfaces\EntityWriteInterface;

class PKPBackendPaymentsSettingsHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $rootPattern = '/{contextPath}/api/{version}/_payments';
        $this->_endpoints = array_merge_recursive($this->_endpoints, [
            'PUT' => [
                [
                    'pattern' => $rootPattern,
                    'handler' => [$this, 'edit'],
                    'roles' => [
                        Role::ROLE_ID_SITE_ADMIN,
                        Role::ROLE_ID_MANAGER,
                    ],
                ],
            ],
        ]);
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Receive requests to edit the payments form
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     *
     * @return Response
     */
    public function edit($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $params = $slimRequest->getParsedBody();
        $contextService = Services::get('context');

        // Process query params to format incoming data as needed
        foreach ($slimRequest->getParsedBody() as $param => $val) {
            switch ($param) {
                case 'paymentsEnabled':
                    $params[$param] = $val === 'true';
                    break;
                case 'currency':
                    $params[$param] = (string) $val;
                    break;
            }
        }

        if (isset($params['currency'])) {
            $errors = $contextService->validate(
                EntityWriteInterface::VALIDATE_ACTION_EDIT,
                ['currency' => $params['currency']],
                $context->getSupportedFormLocales(),
                $context->getPrimaryLocale()
            );
            if (!empty($errors)) {
                return $response->withStatus(400)->withJson($errors);
            }
        }

        $paymentPlugins = PluginRegistry::loadCategory('paymethod', true);
        $errors = [];
        foreach ($paymentPlugins as $paymentPlugin) {
            $errors = array_merge(
                $errors,
                $paymentPlugin->saveSettings($params, $slimRequest, $request)
            );
        }
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $context = $contextService->get($context->getId());
        $context = $contextService->edit($context, $params, $request);

        return $response->withJson($params);
    }
}

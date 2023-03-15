<?php
/**
 * @file api/v1/mailables/MailableHandler.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailableHandler
 * @ingroup api_v1_mailables
 *
 * @brief Base class to handle API requests for mailables.
 */

namespace PKP\API\v1\mailables;

use APP\facades\Repo;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

class MailableHandler extends APIHandler
{
    /**
     * @copydoc APIHandler::__construct()
     */
    public function __construct()
    {
        $this->_handlerPath = 'mailables';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{id}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
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

        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextRequiredPolicy($request));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get an array of mailables
     */
    public function getMany(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $mailables = Repo::mailable()->getMany(
                $this->getRequest()->getContext(),
                $slimRequest->getQueryParam('searchPhrase')
            )
            ->map(fn(string $class) => Repo::mailable()->summarizeMailable($class))
            ->sortBy('name');

        return $response->withJson($mailables->values(), 200);
    }

    /**
     * Get a mailable by its class name
     * @param APIResponse $response
     */
    public function get(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $context = $this->getRequest()->getContext();

        $mailable = Repo::mailable()->get($args['id'], $context);

        if (!$mailable) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        return $response->withJson(Repo::mailable()->describeMailable($mailable, $context->getId()), 200);
    }
}

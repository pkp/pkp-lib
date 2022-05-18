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
namespace PKP\api\v1\mailables;

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
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
                    'pattern' => $this->getEndpointPattern() . '/{className}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{className}',
                    'handler' => [$this, 'edit'],
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
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextRequiredPolicy($request));

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
        $mailables = Repo::mailable()->getMany($this->getRequest()->getContext(), $slimRequest->getQueryParam('searchPhrase'));

        return $response->withJson($mailables, 200);
    }

    /**
     * Get a mailable by its class name
     */
    public function get(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $request = $this->getRequest();

        $mailable = Repo::mailable()->getByClass($args['className'], true, $request->getContext()->getId());

        if (!$mailable) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        return $response->withJson($mailable, 200);
    }

    /**
     * Add or remove associated email template to/from a mailable
     */
    public function edit(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $request = $this->getRequest();
        $requestContextId = $request->getContext()->getId();

        $mailable = Repo::mailable()->getByClass($args['className']);

        if (!$mailable) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        $templateKey = $slimRequest->getParsedBodyParam('templateKey');
        $template = Repo::emailTemplate()->getByKey($requestContextId, $templateKey);

        if (!$template) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.templateNotFound');
        }

        // Don't allow edit of the default templates
        if (!$template->getId()) {
            return $response->withJson(403)->withJsonError('api.mailables.403.notAllowedTemplate');
        }

        // Reject modified default templates
        $templateCollection = Repo::emailTemplate()->getMany(
            Repo::emailTemplate()->getCollector()
                ->filterByContext($requestContextId)
                ->filterByIsCustom(true)
                ->filterByKeys([$template->getData('key')])
        );
        if ($templateCollection->count() === 0) {
            return $response->withJson(403)->withJsonError('api.mailables.403.notAllowedTemplate');
        }

        Repo::mailable()->edit($mailable, [$template]);
        $mailable = Repo::mailable()->getByClass($mailable['className'], true, $requestContextId);

        return $response->withJson($mailable, 200);
    }
}

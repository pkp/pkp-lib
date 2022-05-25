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
use Illuminate\Support\Facades\Mail;
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
                    'pattern' => $this->getEndpointPattern() . '/{className}/emailTemplates',
                    'handler' => [$this, 'assignTemplates'],
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
        $mailables = Repo::mailable()->getMany($this->getRequest()->getContext(), $slimRequest->getQueryParam('searchPhrase'));

        return $response->withJson($mailables, 200);
    }

    /**
     * Get a mailable by its class name
     */
    public function get(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $mailable = Repo::mailable()->getByClass($args['className']);

        if (!$mailable) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        return $response->withJson($mailable, 200);
    }

    /**
     * Add or remove associated email template to/from a mailable
     */
    public function assignTemplates(SlimRequest $slimRequest, Response $response, array $args): Response
    {
        $request = $this->getRequest();
        $requestedContext = $request->getContext();
        $requestContextId = $requestedContext->getId();

        // Check if Mailable class name exists
        $existingClassNames = Mail::getMailables($requestedContext);
        if (!in_array($args['className'], $existingClassNames)) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        $mailable = Repo::mailable()->getByClass($args['className']);

        if (!$mailable) {
            return $response->withStatus(404)->withJsonError('api.mailables.404.mailableNotFound');
        }

        $templateKeys = $slimRequest->getParsedBodyParam('templateKeys');
        if (!$templateKeys) {
            return $response->withJson(400)->withJsonError('api.mailables.400.templateKeysMissing');
        }

        // Reject modified default templates
        $templateCollection = Repo::emailTemplate()->getMany(
            Repo::emailTemplate()->getCollector()
                ->filterByContext($requestContextId)
                ->filterByIsCustom(true)
                ->filterByKeys($templateKeys)
        );

        if ($templateCollection->isEmpty() || ($templateCollection->count() !== count($templateKeys))) {
            return $response->withJson(404)->withJsonError('api.mailables.404.templateNotFound');
        }

        Repo::mailable()->edit($mailable['className'], $templateCollection->toArray());

        // Attach associated custom email templates to the response
        $collector = Repo::emailTemplate()->getCollector()->filterByMailables([$mailable['className']]);
        if ($requestContextId) {
            $collector = $collector->filterByContext($requestContextId);
        }

        $templates = Repo::emailTemplate()->getMany($collector);
        foreach ($templates as $template) {
            $mailable['customTemplateKeys'][] = $template->getData('key');
        }

        return $response->withJson($mailable, 200);
    }
}

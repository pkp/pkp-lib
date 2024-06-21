<?php
/**
 * @file api/v1/emailTemplates/PKPEmailTemplateController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplateController
 *
 * @ingroup api_v1_email_templates
 *
 * @brief Controller class to handle API requests for email templates.
 */

namespace PKP\API\v1\emailTemplates;

use APP\core\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\facades\Repo;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPEmailTemplateController extends PKPBaseController
{
    public const MAX_PER_PAGE = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'emailTemplates';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                ROLE::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {

            Route::get('', $this->getMany(...))
                ->name('emailTemplate.getMany');

            Route::get('{key}', $this->get(...))
                ->name('emailTemplate.getTemplate');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ])->group(function () {

            Route::post('', $this->add(...))
                ->name('emailTemplate.add');

            Route::put('{key}', $this->edit(...))
                ->name('emailTemplate.edit');

            Route::delete('restoreDefaults', $this->restoreDefaults(...))
                ->name('emailTemplate.restoreDefaults');

            Route::delete('{key}', $this->delete(...))
                ->name('emailTemplate.delete');
        });
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

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
     * Get a collection of email templates
     *
     * @hook API::emailTemplates::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $collector = Repo::emailTemplate()->getCollector($request->getContext()->getId());

        // Process query params to format incoming data as needed
        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'alternateTo':
                    $collector->alternateTo(paramToArray($val));
                    break;
                case 'isModified':
                    $collector->isModified((bool) $val);
                    break;
                case 'searchPhrase':
                    $collector->searchPhrase(trim($val));
                    break;
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_PER_PAGE));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
            }
        }

        Hook::call('API::emailTemplates::params', [$collector, $illuminateRequest]);

        $emailTemplates = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::emailTemplate()->getSchemaMap()->summarizeMany($emailTemplates),
        ], Response::HTTP_OK);
    }

    /**
     * Get a single email template
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $emailTemplate = Repo::emailTemplate()->getByKey($request->getContext()->getId(), $illuminateRequest->route('key'));

        if (!$emailTemplate) {
            return response()->json([
                'error' => __('api.emailTemplates.404.templateNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(Repo::emailTemplate()->getSchemaMap()->map($emailTemplate), Response::HTTP_OK);
    }

    /**
     * Add an email template
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE, $illuminateRequest->input());
        $params['contextId'] = $requestContext->getId();

        $errors = Repo::emailTemplate()->validate(null, $params, $requestContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $emailTemplate = Repo::emailTemplate()->newDataObject($params);
        Repo::emailTemplate()->add($emailTemplate);
        $emailTemplate = Repo::emailTemplate()->getByKey($emailTemplate->getData('contextId'), $emailTemplate->getData('key'));

        return response()->json(Repo::emailTemplate()->getSchemaMap()->map($emailTemplate), Response::HTTP_OK);
    }

    /**
     * Edit an email template
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $emailTemplate = Repo::emailTemplate()->getByKey($requestContext->getId(), $illuminateRequest->route('key'));

        if (!$emailTemplate) {
            return response()->json([
                'error' => __('api.emailTemplates.404.templateNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_EMAIL_TEMPLATE, $illuminateRequest->input());
        $params['key'] = $illuminateRequest->route('key');

        // Only allow admins to change the context an email template is attached to.
        // Set the contextId if it has not been passed or the user is not an admin
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (isset($params['contextId'])
                && !in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)
                && $params['contextId'] !== $requestContext->getId()) {
            return response()->json([
                'error' => __('api.emailTemplates.403.notAllowedChangeContext'),
            ], Response::HTTP_FORBIDDEN);
        } elseif (!isset($params['contextId'])) {
            $params['contextId'] = $requestContext->getId();
        }

        $errors = Repo::emailTemplate()->validate(
            $emailTemplate,
            $params,
            $requestContext
        );

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::emailTemplate()->edit($emailTemplate, $params);

        $emailTemplate = Repo::emailTemplate()->getByKey(
            // context ID is null if edited for the first time
            $emailTemplate->getData('contextId') ?? $params['contextId'],
            $emailTemplate->getData('key')
        );

        return response()->json(Repo::emailTemplate()->getSchemaMap()->map($emailTemplate), Response::HTTP_OK);
    }

    /**
     * Delete an email template
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $emailTemplate = Repo::emailTemplate()->getByKey($requestContext->getId(), $illuminateRequest->route('key'));

        // Only custom email templates can be deleted, so return 404 if no id exists
        if (!$emailTemplate || !$emailTemplate->getData('id')) {
            return response()->json([
                'error' => __('api.emailTemplates.404.templateNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $props = Repo::emailTemplate()->getSchemaMap()->map($emailTemplate);
        Repo::emailTemplate()->delete($emailTemplate);

        return response()->json($props, Response::HTTP_OK);
    }

    /**
     * Restore defaults in the email template settings
     */
    public function restoreDefaults(Request $illuminateRequest): JsonResponse
    {
        $contextId = $this->getRequest()->getContext()->getId();
        $deletedKeys = Repo::emailTemplate()->restoreDefaults($contextId);

        return response()->json($deletedKeys, Response::HTTP_OK);
    }
}

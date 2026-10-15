<?php

/**
 * @file classes/security/authorization/ReauthenticationRequiredPolicy.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReauthenticationRequiredPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Policy to require to re-authenticate when accessing sensitive pages within the application.
 * Currently only applicable to admins. This can also be used to guard administration endpoints and handlers during a session.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\core\APIRouter;
use PKP\core\PKPPageRouter;
use PKP\core\PKPRequest;

class ReauthenticationRequiredPolicy extends AuthorizationPolicy
{
    private PKPRequest $request;

    /**
     */
    public function __construct(PKPRequest $request)
    {
        parent::__construct();
        $this->request = $request;
    }

    /** @copydoc */
    public function effect(): int
    {
        $user = $this->request->getUser();

        if (!$user) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // A request authenticated with an API token has no browser session to protect,
        // so the re-authentication does not apply to it.
        if ($this->isAuthenticatedViaApiToken($user)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        if (Application::get()->getRequest()->getSessionGuard()->isElevatedSessionActive()) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Only page requests can be redirected to the Confirm Access page.
        // Other requests (API, grids, forms, AJAX, etc.) are refused with a message instead.
        $router = $this->request->getRouter();
        if (!($router instanceof PKPPageRouter)) {
            $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.confirmAccess.required');
            if ($router instanceof APIRouter) {
                $this->setAdvice(
                    AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY,
                    [$router, 'handleAuthorizationFailure', [$this->request, 'user.confirmAccess.required']]
                );
            }
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // User needs to re-authenticate
        $this->setAdvice(
            AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY,
            [$this, 'callOnDeny', []]
        );

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }

    /**
     * Whether the request was authenticated with an API token or by session.
     * Assumes that if no user ID is available in the session, the user was authenticated via API token.
     */
    private function isAuthenticatedViaApiToken(\PKP\user\User $user): bool
    {
        if (!($this->request->getRouter() instanceof APIRouter)
            || $this->request->getSessionGuard()->getUserId()) {
            return false;
        }

        return true;
    }

    /**
     * Callback when a user needs to re-authenticate. Redirects user to the admin/confirmAccess page.
     */
    public function callOnDeny(): void
    {
        $dispatcher = $this->request->getDispatcher();
        $isActionRequest = $_SERVER['REQUEST_METHOD'] !== 'GET' || $this->request->getUserVar('csrfToken');
        $params = [];

        if ($isActionRequest) {
            $requestedPage = $this->request->getRequestedPage();
            $contextId = $this->request->getContext()?->getId();
            // Cannot complete a POST/DELETE/PUT/PATCH request after reauthentication since redirecting to the source page will be a GET request.
            // Therefore, redirect to the page the user is coming from (referer) or if no referer is available, redirect to the base page that the current request is coming from.
            // Example of base page:
            // If admin is attempting to perform a POST action via `admin/clearTemplateCache` path, after reauthenticating they would be redirected to `/admin` which is the base page for admin actions.
            $source = $this->request->getRefererPath() ?? $this->request->url($contextId, $requestedPage);
            $params['isActionRequest'] = true;

        } else {
            $source = $this->request->getRequestPath();
        }

        $params['source'] = $source;

        // reauthentication is handled in the admin page handler since this is currently only applicable to admins
        $reauthenticationUrl = $dispatcher->url(
            $this->request,
            Application::ROUTE_PAGE,
            null,
            'admin',
            'confirmAccess',
            null,
            $params
        );

        $this->request->redirectUrl($reauthenticationUrl);
    }
}

<?php

/**
 * @file classes/core/PKPPageRouter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPageRouter
 *
 * @ingroup core
 *
 * @brief Class mapping an HTTP request to a handler or context.
 */

namespace PKP\core;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\Auth;
use PKP\config\Config;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\Validation;

class PKPPageRouter extends PKPRouter
{
    /** @var array pages that don't need an installed system to be displayed */
    public $_installationPages = ['install', 'help', 'header', 'sidebar'];

    public const ROUTER_DEFAULT_PAGE = './pages/index/index.php';
    public const ROUTER_DEFAULT_OP = 'index';
    //
    // Internal state cache variables
    // NB: Please do not access directly but
    // only via their respective getters/setters
    //
    /** @var string the requested page */
    public string $_page;
    /** @var string the requested operation */
    public string $_op;
    /** @var string cache filename */
    public string $_cacheFilename;

    /**
     * get the installation pages
     */
    public function getInstallationPages(): array
    {
        return $this->_installationPages;
    }

    /**
     * get the cacheable pages
     */
    public function getCacheablePages(): array
    {
        return [];
    }

    /**
     * Determine whether or not the request is cacheable.
     *
     * @param bool $testOnly required for unit test to bypass session check.
     */
    public function isCacheable(PKPRequest $request, bool $testOnly = false): bool
    {
        if (PKPSessionGuard::isSessionDisable() && !$testOnly) {
            return false;
        }
        if (Application::isUnderMaintenance()) {
            return false;
        }
        if (!empty($_POST) || Validation::isLoggedIn()) {
            return false;
        }

        if (!empty($_GET)) {
            return false;
        }

        if (in_array($this->getRequestedPage($request), $this->getCacheablePages())) {
            return true;
        }

        return false;
    }

    /**
     * Get the page requested in the URL.
     */
    public function getRequestedPage(PKPRequest $request): string
    {
        if (!isset($this->_page)) {
            $this->_page = $this->_getRequestedUrlParts(Core::getPage(...), $request);
        }
        return $this->_page;
    }

    /**
     * Get the operation requested in the URL (assumed to exist in the requested page handler).
     */
    public function getRequestedOp(PKPRequest $request): string
    {
        if (!isset($this->_op)) {
            $this->_op = $this->_getRequestedUrlParts(Core::getOp(...), $request);
        }
        return $this->_op;
    }

    /**
     * Get the arguments requested in the URL.
     */
    public function getRequestedArgs(PKPRequest $request): array
    {
        return $this->_getRequestedUrlParts(Core::getArgs(...), $request);
    }

    /**
     * Get the anchor (#anchor) requested in the URL
     */
    public function getRequestedAnchor(PKPRequest $request): string
    {
        $url = $request->getRequestUrl();
        $parts = explode('#', $url);
        if (count($parts) < 2) {
            return '';
        }
        return $parts[1];
    }


    //
    // Implement template methods from PKPRouter
    //
    /**
     * @copydoc PKPRouter::getCacheFilename()
     */
    public function getCacheFilename(PKPRequest $request): string
    {
        if (!isset($this->_cacheFilename)) {
            $id = $_SERVER['PATH_INFO'] ?? Application::SITE_CONTEXT_PATH;
            $id .= '-' . Locale::getLocale();
            $path = Core::getBaseDir();
            $this->_cacheFilename = $path . '/cache/wc-' . md5($id) . '.html';
        }
        return $this->_cacheFilename;
    }

    /**
     * @copydoc PKPRouter::route()
     *
     * @hook LoadHandler [[&$page, &$op, &$sourceFile, &$handler]]
     */
    public function route(PKPRequest $request): void
    {
        // Determine the requested page and operation
        $page = $this->getRequestedPage($request);
        $op = $this->getRequestedOp($request);

        // If the application has not yet been installed we only
        // allow installer pages to be displayed,
        // or is installed and one of the installer pages was called
        if (!Application::isInstalled() && !in_array($page, $this->getInstallationPages())) {
            // A non-installation page was called although
            // the system is not yet installed. Redirect to
            // the installation page.
            $request->redirect(Application::SITE_CONTEXT_PATH, 'install');
        } elseif (Application::isInstalled() && in_array($page, $this->getInstallationPages())) {
            // Redirect to the index page
            $request->redirect(Application::SITE_CONTEXT_PATH, 'index');
        }

        // Redirect requests from logged-out users to a context which is not
        // publicly enabled
        if (!PKPSessionGuard::isSessionDisable()) {
            $user = $request->getUser();
            $currentContext = $request->getContext();
            if ($currentContext && !$currentContext->getEnabled() && !$user instanceof \PKP\user\User) {
                if ($page != 'login') {
                    $request->redirect(null, 'login');
                }
            }
        }

        // Determine the page index file. This file contains the
        // logic to resolve a page to a specific handler class.
        $sourceFile = sprintf('pages/%s/index.php', $page);

        // If a hook has been registered to handle this page, give it the
        // opportunity to load required resources and set the handler.
        $handler = null;
        if (!Hook::call('LoadHandler', [&$page, &$op, &$sourceFile, &$handler])) {
            if (file_exists($sourceFile)) {
                $result = require('./' . $sourceFile);
                if (is_object($result)) {
                    $handler = $result;
                }
            } elseif (file_exists(PKP_LIB_PATH . "/{$sourceFile}")) {
                $result = require('./' . PKP_LIB_PATH . "/{$sourceFile}");
                if (is_object($result)) {
                    $handler = $result;
                }
            } elseif (empty($page)) {
                $handler = require(self::ROUTER_DEFAULT_PAGE);
            } else {
                $dispatcher = $this->getDispatcher();
                $dispatcher->handle404();
            }
        }

        // Set locale from URL or from 'setLocale'-op/search-params
        $setLocale = ($op === 'setLocale'
            ? ($this->getRequestedArgs($request)[0] ?? null)
            : ($page === 'install'
                ? ($_GET['setLocale'] ?? null)
                : null));
        $this->_setLocale($request, $setLocale);

        // Call the selected handler's index operation if
        // no operation was defined in the request.
        if (empty($op)) {
            $op = self::ROUTER_DEFAULT_OP;
        }

        if (defined('HANDLER_CLASS')) {
            // Deprecated with 3.4.0; error added for 3.5; remove this post-3.6
            throw new \Exception('The use of HANDLER_CLASS is no longer supported for injecting handlers.');
        }

        // Redirect to 404 if the operation doesn't exist
        // for the handler.
        if (!is_object($handler) || !in_array($op, get_class_methods($handler))) {
            $dispatcher = $this->getDispatcher();
            $dispatcher->handle404();
        }

        $this->setHandler($handler);

        // Authorize and initialize the request but don't call the
        // validate() method on page handlers.
        // FIXME: We should call the validate() method for page
        // requests also (last param = true in the below method
        // call) once we've made sure that all validate() calls can
        // be removed from handler operations without damage (i.e.
        // they don't depend on actions being performed before the
        // call to validate().
        $args = $this->getRequestedArgs($request);
        $serviceEndpoint = [$handler, $op];
        $this->_authorizeInitializeAndCallRequest($serviceEndpoint, $request, $args, false);
    }

    /**
     * @copydoc PKPRouter::url()
     */
    public function url(
        PKPRequest $request,
        ?string $newContext = null,
        ?string $page = null,
        ?string $op = null,
        ?array $path = null,
        ?array $params = null,
        ?string $anchor = null,
        bool $escape = false,
        ?string $urlLocaleForPage = null,
    ): string {
        //
        // Base URL, context, and additional path info
        //
        [$baseUrl, $context] = $this->_urlGetBaseAndContext($request, $newContext);
        $additionalPath = array_map(rawurlencode(...), $path ?? []);

        //
        // Page and Operation
        //

        // Are we in a page request?
        $currentRequestIsAPageRequest = $request->getRouter() instanceof PKPPageRouter;

        // Determine the operation
        if ($op) {
            // If an operation has been explicitly set then use it.
            $op = rawurlencode($op);
        } else {
            // No operation has been explicitly set so let's determine a sensible
            // default operation.
            if (empty($newContext) && empty($page) && $currentRequestIsAPageRequest) {
                // If we remain in the existing context and on the existing page then
                // we will default to the current operation. We can only determine a
                // current operation if the current request is a page request.
                $op = $this->getRequestedOp($request);
            } else {
                // If a new context (or page) has been set then we'll default to the
                // index operation within the new context (or on the new page).
                if (empty($additionalPath)) {
                    // If no additional path is set we can simply leave the operation
                    // undefined which automatically defaults to the index operation
                    // but gives shorter (=nicer) URLs.
                    $op = null;
                } else {
                    // If an additional path is set then we have to explicitly set the
                    // index operation to disambiguate the path info.
                    $op = 'index';
                }
            }
        }

        // Determine the page
        if ($page) {
            // If a page has been explicitly set then use it.
            $page = rawurlencode($page);
        } else {
            // No page has been explicitly set so let's determine a sensible default page.
            if (empty($newContext) && $currentRequestIsAPageRequest) {
                // If we remain in the existing context then we will default to the current
                // page. We can only determine a current page if the current request is a
                // page request.
                $page = $this->getRequestedPage($request);
            } else {
                // If a new context has been set then we'll default to the index page
                // within the new context.
                if (empty($op)) {
                    // If no explicit operation is set we can simply leave the page
                    // undefined which automatically defaults to the index page but gives
                    // shorter (=nicer) URLs.
                    $page = null;
                } else {
                    // If an operation is set then we have to explicitly set the index
                    // page to disambiguate the path info.
                    $page = 'index';
                }
            }
        }

        //
        // Additional query parameters
        //
        $additionalParameters = $this->_urlGetAdditionalParameters($request, $params, $escape);

        //
        // Anchor
        //
        $anchor = (empty($anchor) ? '' : '#' . preg_replace("/[^a-zA-Z0-9\-\_\/\.\~]/", '', $anchor));

        //
        // Assemble URL
        //
        // Context, locale?, page, operation and additional path go into the path info.
        $pathInfoArray = $context ? [$context] : [];
        if ($urlLocaleForPage !== '') {
            [$contextObject, $contextLocales] = $this->_getContextAndLocales($request, $context ?? '');
            if (count($contextLocales) > 1) {
                $pathInfoArray[] = $this->_getLocaleForUrl($request, $contextObject, $contextLocales, $urlLocaleForPage);
            }
        }
        if (!empty($page)) {
            $pathInfoArray[] = $page;
            if (!empty($op)) {
                $pathInfoArray[] = $op;
            }
        }
        $pathInfoArray = array_merge($pathInfoArray, $additionalPath);

        // Query parameters
        $queryParametersArray = $additionalParameters;

        return $this->_urlFromParts($baseUrl, $pathInfoArray, $queryParametersArray, $anchor, $escape);
    }

    /**
     * @copydoc PKPRouter::handleAuthorizationFailure()
     */
    public function handleAuthorizationFailure(
        PKPRequest $request,
        string $authorizationMessage,
        array $messageParams = []
    ): void {
        // Redirect to the authorization denied page.
        if (!$request->getUser()) {
            Validation::redirectLogin();
        }
        $request->redirect(null, 'user', 'authorizationDenied', null, ['message' => $authorizationMessage]);
    }

    /**
     * Redirect to user home page (or the user group home page if the user has one user group).
     */
    public function redirectHome(PKPRequest $request): void
    {
        $request->redirectUrl($this->getHomeUrl($request));
    }

    /**
     * Get the user's "home" page URL (e.g. where they are sent after login).
     */
    public function getHomeUrl(PKPRequest $request): string
    {
        $user = Auth::user(); /** @var \PKP\user\User $user */
        $userId = $user->getId();

        if ($context = $this->getContext($request)) {
            // If the user has no roles, or only one role and this is reader, go to "Index" page.
            // Else go to "submissions" page
            $userGroups = Repo::userGroup()->userUserGroups($userId, $context->getId());

            if ($userGroups->isEmpty()
                || ($userGroups->count() == 1 && $userGroups->first()->getRoleId() == Role::ROLE_ID_READER)
            ) {
                return $request->url(null, 'index');
            }

            if(Config::getVar('features', 'enable_new_submission_listing')) {

                $roleIds = $userGroups->map(function ($group) {
                    return $group->getRoleId();
                });

                $roleIdsArray = $roleIds->all();

                if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $roleIdsArray))) {
                    return $request->url(null, 'dashboard', 'editorial');

                }
                if(count(array_intersect([ Role::ROLE_ID_REVIEWER], $roleIdsArray))) {
                    return $request->url(null, 'dashboard', 'reviewAssignments');

                }
                if(count(array_intersect([  Role::ROLE_ID_AUTHOR], $roleIdsArray))) {
                    return $request->url(null, 'dashboard', 'mySubmissions');
                }
            }

            return $request->url(null, 'submissions');
        } else {
            // The user is at the site context, check to see if they are
            // only registered in one place w/ one role
            $userGroups = Repo::userGroup()->userUserGroups($userId, \PKP\core\PKPApplication::SITE_CONTEXT_ID);
            if ($userGroups->count() == 1) {
                $firstUserGroup = $userGroups->first();
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($firstUserGroup->getContextId());
                if (!isset($context)) {
                    $request->redirect(Application::SITE_CONTEXT_PATH, 'index');
                }
                if ($firstUserGroup->getRoleId() == Role::ROLE_ID_READER) {
                    $request->redirect(null, 'index');
                }
            }
            return $request->url(Application::SITE_CONTEXT_PATH, 'index');
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Retrieve part of the current requested
     * url using the passed callback method.
     *
     * @param array $callback Core method to retrieve
     * page, operation or arguments from url.
     * @param PKPRequest $request
     *
     */
    private function _getRequestedUrlParts($callback, $request): array|string|null
    {
        $url = null;
        if (!$request->getRouter() instanceof PKPPageRouter) {
            throw new \Exception('Router is not expected PKPPageRouter!');
        }

        if (isset($_SERVER['PATH_INFO'])) {
            $url = $_SERVER['PATH_INFO'];
        }

        $userVars = $request->getUserVars();
        return $callback($url ?? '', $userVars);
    }

    /**
     * Get context object and context/site/all locales.
     */
    private function _getContextAndLocales(PKPRequest $request, string $contextPath): array
    {
        return [
            /** @deprecated 3.5 The usage of "_" as a site context has been deprecated */
            $context = $this->getCurrentContext() ?? (in_array($contextPath, [Application::SITE_CONTEXT_PATH, '', '_'])
                ? null
                : Application::getContextDAO()->getByPath($contextPath)),
            $context?->getSupportedLocales()
                ?? ($contextPath === Application::SITE_CONTEXT_PATH
                    ? (Application::isInstalled() ? $request->getSite()->getSupportedLocales() : array_keys(Locale::getLocales()))
                    : [])
        ];
    }

    /**
     * Get locale for URL from session or primary
     */
    private function _getLocaleForUrl(PKPRequest $request, ?Context $context, array $locales, ?string $urlLocaleForPage): string
    {
        return in_array($locale = $urlLocaleForPage ?: Locale::getLocale(), $locales)
            ? $locale
            : (($context ?? $request->getSite())?->getPrimaryLocale() ?? Locale::getLocale());
    }

    /**
     * Change the locale for the current user.
     * Redirect to url with(out) locale if locale changed or context set to multi/monolingual.
     */
    private function _setLocale(PKPRequest $request, ?string $setLocale): void
    {
        $contextPath = $this->_getRequestedUrlParts(['Core', 'getContextPath'], $request);
        $urlLocale = $this->_getRequestedUrlParts(['Core', 'getLocalization'], $request);
        $multiLingual = count($this->_getContextAndLocales($request, $contextPath)[1]) > 1;

        if (!$multiLingual && !$urlLocale && !$setLocale || $multiLingual && !$setLocale && $urlLocale === Locale::getLocale()) {
            return;
        }

        $sessionLocale = (function (string $l) use ($request): string {
            $session = $request->getSession();
            if (Locale::isSupported($l) && $l !== $session->get('currentLocale')) {
                $session->put('currentLocale', $l);
                $request->setCookieVar('currentLocale', $l);
            }
            // In case session current locale has been set to non-supported locale, or is null, somewhere else
            if (!Locale::isSupported($session->get('currentLocale') ?? '')) {
                $session->put('currentLocale', Locale::getLocale());
                $request->setCookieVar('currentLocale', Locale::getLocale());
            }
            return $session->get('currentLocale');
        })($setLocale ?? $urlLocale);

        if (preg_match('#^/\w#', $source = str_replace('@', '', $request->getUserVar('source') ?? ''))) {
            $request->redirectUrl($source);
        }

        $indexUrl = $this->getIndexUrl($request);
        $uri = preg_replace("#^{$indexUrl}#", '', $setLocale ? ($_SERVER['HTTP_REFERER'] ?? '') : $request->getCompleteUrl(), 1);
        $newUrlLocale = $multiLingual ? "/{$sessionLocale}" : '';
        $pathInfo = ($uri)
            ? preg_replace("#^/{$contextPath}" . ($urlLocale ? "/{$urlLocale}" : '') . '(?=[/?\\#]|$)#', "/{$contextPath}{$newUrlLocale}", $uri, 1)
            : "/index{$newUrlLocale}";

        $request->redirectUrl($indexUrl . $pathInfo);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPPageRouter', '\PKPPageRouter');
}

<?php

/**
 * @file classes/handler/PKPHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @package core
 *
 * @class PKPHandler
 *
 * Base request handler abstract class.
 *
 */

namespace PKP\handler;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\Dispatcher;
use PKP\core\PKPString;
use PKP\core\Registry;
use PKP\db\DBResultRange;
use PKP\security\authorization\AllowedHostsPolicy;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\HttpsPolicy;
use PKP\security\authorization\RestrictedSiteAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Validation;
use PKP\session\SessionManager;

class PKPHandler
{
    /** @var string|null API token */
    protected $_apiToken = null;

    /**
     * @var string identifier of the controller instance - must be unique
     *  among all instances of a given controller type.
     */
    public $_id;

    /** @var Dispatcher mainly needed for cross-router url construction */
    public $_dispatcher;

    /** @var array validation checks for this page - deprecated! */
    public $_checks = [];

    /**
     * @var array
     *  The value of this variable should look like this:
     *  array(
     *    ROLE_ID_... => array(...allowed handler operations...),
     *    ...
     *  )
     */
    public $_roleAssignments = [];

    /** @var AuthorizationDecisionManager authorization decision manager for this handler */
    public $_authorizationDecisionManager;

    /** @var bool Whether to enforce site access restrictions. */
    public $_enforceRestrictedSite = true;

    /** @var bool Whether role assignments have been checked. */
    public $_roleAssignmentsChecked = false;

    /** @var bool Whether this is a handler for a page in the backend editorial UI */
    public $_isBackendPage = false;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    //
    // Setters and Getters
    //
    public function setEnforceRestrictedSite($enforceRestrictedSite)
    {
        $this->_enforceRestrictedSite = $enforceRestrictedSite;
    }

    /**
     * Set the controller id
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * Get the controller id
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Get the dispatcher
     *
     * NB: The dispatcher will only be set after
     * handler instantiation. Calling getDispatcher()
     * in the constructor will fail.
     *
     * @return Dispatcher
     */
    public function &getDispatcher()
    {
        assert(!is_null($this->_dispatcher));
        return $this->_dispatcher;
    }

    /**
     * Set the dispatcher
     *
     * @param Dispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * Fallback method in case request handler does not implement index method.
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request)
    {
        $dispatcher = $this->getDispatcher();
        if (isset($dispatcher)) {
            $dispatcher->handle404();
        } else {
            Dispatcher::handle404();
        } // For old-style handlers
    }

    /**
     * Add an authorization policy for this handler which will
     * be applied in the authorize() method.
     *
     * Policies must be added in the class constructor or in the
     * subclasses' authorize() method before the parent::authorize()
     * call so that PKPHandler::authorize() will be able to enforce
     * them.
     *
     * @param AuthorizationPolicy|\PKP\security\authorization\PolicySet $authorizationPolicy
     * @param bool $addToTop whether to insert the new policy
     *  to the top of the list.
     */
    public function addPolicy($authorizationPolicy, $addToTop = false)
    {
        if (is_null($this->_authorizationDecisionManager)) {
            // Instantiate the authorization decision manager
            $this->_authorizationDecisionManager = new AuthorizationDecisionManager();
        }

        // Add authorization policies to the authorization decision manager.
        $this->_authorizationDecisionManager->addPolicy($authorizationPolicy, $addToTop);
    }

    /**
     * Retrieve authorized context objects from the decision manager.
     *
     * Gets an object that was previously stored using the same assoc type.
     * The authorization policies populate these -- when an object is fetched
     * and checked for permission in the policy class, it's then chucked into
     * the authorized context for later retrieval by code that needs it.
     *
     * @param int $assocType any of the Application::ASSOC_TYPE_* constants
     */
    public function &getAuthorizedContextObject($assocType)
    {
        assert($this->_authorizationDecisionManager instanceof AuthorizationDecisionManager);
        return $this->_authorizationDecisionManager->getAuthorizedContextObject($assocType);
    }

    /**
     * Get the authorized context.
     *
     * NB: You should avoid accessing the authorized context
     * directly to avoid accidentally overwriting an object
     * in the context. Try to use getAuthorizedContextObject()
     * instead where possible.
     *
     * @return array
     */
    public function &getAuthorizedContext()
    {
        assert($this->_authorizationDecisionManager instanceof AuthorizationDecisionManager);
        return $this->_authorizationDecisionManager->getAuthorizedContext();
    }

    /**
     * Retrieve the last authorization message from the
     * decision manager.
     *
     * @return string
     */
    public function getLastAuthorizationMessage()
    {
        if (!$this->_authorizationDecisionManager instanceof AuthorizationDecisionManager) {
            return '';
        }
        $authorizationMessages = $this->_authorizationDecisionManager->getAuthorizationMessages();
        return end($authorizationMessages);
    }

    /**
     * Add role - operation assignments to the handler.
     *
     * @param int|array $roleIds one or more of the ROLE_ID_*
     *  constants
     * @param string|array $operations a single method name or
     *  an array of method names to be assigned.
     */
    public function addRoleAssignment($roleIds, $operations)
    {
        // Allow single operations to be passed in as scalars.
        if (!is_array($operations)) {
            $operations = [$operations];
        }

        // Allow single roles to be passed in as scalars.
        if (!is_array($roleIds)) {
            $roleIds = [$roleIds];
        }

        // Add the given operations to all roles.
        foreach ($roleIds as $roleId) {
            // Create an empty assignment array if no operations
            // have been assigned to the given role before.
            if (!isset($this->_roleAssignments[$roleId])) {
                $this->_roleAssignments[$roleId] = [];
            }

            // Merge the new operations with the already assigned
            // ones for the given role.
            $this->_roleAssignments[$roleId] = array_merge(
                $this->_roleAssignments[$roleId],
                $operations
            );
        }

        // Flag role assignments as needing checking.
        $this->_roleAssignmentsChecked = false;
    }

    /**
     * This method returns an assignment of operation names for the
     * given role.
     *
     * @param int $roleId
     *
     * @return ?array assignment for the given role.
     */
    public function getRoleAssignment($roleId)
    {
        if (!is_null($roleId)) {
            if (isset($this->_roleAssignments[$roleId])) {
                return $this->_roleAssignments[$roleId];
            } else {
                return null;
            }
        }
    }

    /**
     * This method returns an assignment of roles to operation names.
     *
     * @return array assignments for all roles.
     */
    public function getRoleAssignments()
    {
        return $this->_roleAssignments;
    }

    /**
     * Flag role assignment checking as completed.
     */
    public function markRoleAssignmentsChecked()
    {
        $this->_roleAssignmentsChecked = true;
    }

    /**
     * Authorize this request.
     *
     * Routers will call this method automatically thereby enforcing
     * authorization. This method will be called before the
     * validate() method and before passing control on to the
     * handler operation.
     *
     * NB: This method will be called once for every request only.
     *
     * @param Request $request
     * @param array $args request arguments
     * @param array $roleAssignments the operation role assignment,
     *  see getRoleAssignment() for more details.
     *
     * @return bool
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Enforce restricted site access if required.
        if ($this->_enforceRestrictedSite) {
            $this->addPolicy(new RestrictedSiteAccessPolicy($request), true);
        }

        // Enforce SSL site-wide.
        if ($this->requireSSL()) {
            $this->addPolicy(new HttpsPolicy($request), true);
        }

        // Ensure the allowed hosts setting, when provided, is respected.
        $this->addPolicy(new AllowedHostsPolicy($request), true);
        if (!SessionManager::isDisabled()) {
            // Add user roles in authorized context.
            $user = $request->getUser();
            if ($user instanceof \PKP\user\User) {
                $this->addPolicy(new UserRolesRequiredPolicy($request), true);
            }
        }

        // Make sure that we have a valid decision manager instance.
        assert($this->_authorizationDecisionManager instanceof AuthorizationDecisionManager);

        $router = $request->getRouter();
        if ($router instanceof \PKP\core\PKPPageRouter) {
            // We have to apply a blacklist approach for page
            // controllers to maintain backwards compatibility:
            // Requests are implicitly authorized if no policy
            // explicitly denies access.
            $this->_authorizationDecisionManager->setDecisionIfNoPolicyApplies(AuthorizationPolicy::AUTHORIZATION_PERMIT);
        } else {
            // We implement a strict whitelist approach for
            // all other components: Requests will only be
            // authorized if at least one policy explicitly
            // grants access and none denies access.
            $this->_authorizationDecisionManager->setDecisionIfNoPolicyApplies(AuthorizationPolicy::AUTHORIZATION_DENY);
        }

        // Let the authorization decision manager take a decision.
        $decision = $this->_authorizationDecisionManager->decide();
        if ($decision == AuthorizationPolicy::AUTHORIZATION_PERMIT && (empty($this->_roleAssignments) || $this->_roleAssignmentsChecked)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Perform data integrity checks.
     *
     * This method will be called once for every request only.
     *
     * NB: Any kind of authorization check is now deprecated
     * within this method. This method is purely meant for data
     * integrity checks that do not lead to denial of access
     * to resources (e.g. via redirect) like handler operations
     * or data objects.
     *
     * @param array $requiredContexts
     * @param Request $request
     */
    public function validate($requiredContexts = null, $request = null)
    {
        // FIXME: for backwards compatibility only - remove when request/router refactoring complete
        if (!isset($request)) {
            $request = & Registry::get('request');
            if (Config::getVar('debug', 'deprecation_warnings')) {
                trigger_error('Deprecated call without request object.');
            }
        }

        foreach ($this->_checks as $check) {
            // Using authorization checks in the validate() method is deprecated
            // FIXME: Trigger a deprecation warning.

            // check should redirect on fail and continue on pass
            // default action is to redirect to the index page on fail
            if (!$check->isValid()) {
                if ($check->redirectToLogin) {
                    Validation::redirectLogin();
                } else {
                    // An unauthorized page request will be re-routed
                    // to the index page.
                    $request->redirect(null, 'index');
                }
            }
        }

        return true;
    }

    /**
     * Subclasses can override this method to configure the
     * handler.
     *
     * NB: This method will be called after validation and
     * authorization.
     *
     * @param Request $request
     */
    public function initialize($request)
    {
        // Set the controller id to the requested
        // page (page routing) or component name
        // (component routing) by default.
        $router = $request->getRouter();
        if ($router instanceof \PKP\core\PKPComponentRouter) {
            $componentId = $router->getRequestedComponent($request);
            // Create a somewhat compressed but still globally unique
            // and human readable component id.
            // Example: "grid.citation.CitationGridHandler"
            // becomes "grid-citation-citationgrid"
            $componentId = str_replace('.', '-', PKPString::strtolower(PKPString::substr($componentId, 0, -7)));
            $this->setId($componentId);
        } elseif ($router instanceof \PKP\core\APIRouter) {
            $this->setId($router->getEntity());
        } else {
            assert($router instanceof \PKP\core\PKPPageRouter);
            $this->setId($router->getRequestedPage($request));
        }
    }

    /**
     * Return the DBResultRange structure and misc. variables describing the current page of a set of pages.
     *
     * @param Request $request
     * @param string $rangeName Symbolic name of range of pages; must match the Smarty {page_list ...} name.
     * @param array $contextData If set, this should contain a set of data that are required to
     * 	define the context of this request (for maintaining page numbers across requests).
     *	To disable persistent page contexts, set this variable to null.
     *
     * @return DBResultRange
     */
    public static function getRangeInfo($request, $rangeName, $contextData = null)
    {
        $context = $request->getContext();
        $pageNum = $request->getUserVar(self::getPageParamName($rangeName));
        if (empty($pageNum)) {
            $session = $request->getSession();
            $pageNum = 1; // Default to page 1
            if ($session && $contextData !== null) {
                // See if we can get a page number from a prior request
                $contextHash = self::hashPageContext($request, $contextData);

                if ($request->getUserVar('clearPageContext')) {
                    // Explicitly clear the old page context
                    $session->unsetSessionVar("page-{$contextHash}");
                } else {
                    $oldPage = $session->getSessionVar("page-{$contextHash}");
                    if (is_numeric($oldPage)) {
                        $pageNum = $oldPage;
                    }
                }
            }
        } else {
            $session = $request->getSession();
            if ($session && $contextData !== null) {
                // Store the page number
                $contextHash = self::hashPageContext($request, $contextData);
                $session->setSessionVar("page-{$contextHash}", $pageNum);
            }
        }

        if ($context) {
            $count = $context->getData('itemsPerPage');
        }
        if (!isset($count)) {
            $count = Config::getVar('interface', 'items_per_page');
        }

        if (isset($count)) {
            return new DBResultRange($count, $pageNum);
        } else {
            return new DBResultRange(-1, -1);
        }
    }

    /**
     * Get the range info page parameter name.
     *
     * @param string $rangeName
     *
     * @return string
     */
    public static function getPageParamName($rangeName)
    {
        return $rangeName . 'Page';
    }

    /**
     * Set up the basic template.
     *
     * @param Request $request
     */
    public function setupTemplate($request)
    {
        // FIXME: for backwards compatibility only - remove
        if (!isset($request)) {
            $request = & Registry::get('request');
            if (Config::getVar('debug', 'deprecation_warnings')) {
                trigger_error('Deprecated call without request object.');
            }
        }
        assert($request instanceof \PKP\core\PKPRequest);

        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('userRoles', $userRoles);

        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        if ($accessibleWorkflowStages) {
            $templateMgr->assign('accessibleWorkflowStages', $accessibleWorkflowStages);
        }

        // Set up template requirements for the backend editorial UI
        if ($this->_isBackendPage) {
            $templateMgr->setupBackendPage();
        }
    }

    /**
     * Generate a unique-ish hash of the page's identity, including all
     * context that differentiates it from other similar pages (e.g. all
     * articles vs. all articles starting with "l").
     *
     * @param Request $request
     * @param array $contextData A set of information identifying the page
     *
     * @return string hash
     */
    public static function hashPageContext($request, $contextData = [])
    {
        return md5(
            implode(',', $request->getRouter()->getRequestedContextPath($request)) . ',' .
            $request->getRequestedPage() . ',' .
            $request->getRequestedOp() . ',' .
            serialize($contextData)
        );
    }

    /**
     * Return the context that is configured in site redirect setting.
     *
     * @param Request $request
     *
     * @return ?\PKP\context\Context Either Context or null
     */
    public function getSiteRedirectContext($request)
    {
        $site = $request->getSite();
        if ($site && ($contextId = $site->getRedirect())) {
            $contextDao = Application::getContextDAO();
            return $contextDao->getById($contextId);
        }
        return null;
    }

    /**
     * Return the first context that user is enrolled with.
     *
     * @param \PKP\user\User $user
     * @param array $contexts
     *
     * @return mixed Either Context or null
     */
    public function getFirstUserContext($user, $contexts)
    {
        $context = null;
        foreach ($contexts as $workingContext) {
            $userIsEnrolled = Repo::userGroup()
                ->userUserGroups($user->getId(), $workingContext->getId())
                ->count();

            if ($userIsEnrolled) {
                $context = $workingContext;
                break;
            }
        }
        return $context;
    }

    /**
     * Assume SSL is required for all handlers, unless overridden in subclasses.
     *
     * @return bool
     */
    public function requireSSL()
    {
        return true;
    }

    /**
     * Return API token string
     *
     * @return string|null
     */
    public function getApiToken()
    {
        return $this->_apiToken;
    }

    /**
     * Set API token string
     *
     */
    public function setApiToken($apiToken)
    {
        return $this->_apiToken = $apiToken;
    }

    /**
     * Returns a "best-guess" context, based in the request data, if
     * a request needs to have one in its context but may be in a site-level
     * context as specified in the URL.
     *
     * @param Request $request
     * @param bool $hasNoContexts Optional reference to receive true iff no contexts were found.
     *
     * @return mixed Either a Context or null if none could be determined.
     */
    public function getTargetContext($request, &$hasNoContexts = null)
    {
        // Get the requested path.
        $router = $request->getRouter();
        $requestedPath = $router->getRequestedContextPath($request);

        if ($requestedPath === 'index' || $requestedPath === '') {
            // No context requested. Check how many contexts the site has.
            $contextDao = Application::getContextDAO();
            $contexts = $contextDao->getAll(true);
            [$firstContext, $secondContext] = [$contexts->next(), $contexts->next()];
            if ($firstContext && !$secondContext) {
                // Return the unique context.
                $context = $firstContext;
                $hasNoContexts = false;
            } elseif ($firstContext && $secondContext) {
                // Get the site redirect.
                $context = $this->getSiteRedirectContext($request);
                $hasNoContexts = false;
            } else {
                $context = null;
                $hasNoContexts = true;
            }
        } else {
            // Return the requested context.
            $context = $router->getContext($request);

            // If the specified context does not exist, respond with a 404.
            if (!$context) {
                $request->getDispatcher()->handle404();
            }
        }
        if ($context instanceof \PKP\context\Context) {
            return $context;
        }
        return null;
    }
}

<?php

/**
 * @file classes/core/PKPBaseController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBaseController
 *
 * @ingroup core
 *
 * @brief Base abstract controller class that all controller must extend
 *
 */

namespace PKP\core;

use APP\core\Application;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route;
use PKP\security\authorization\AllowedHostsPolicy;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\HttpsPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RestrictedSiteAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\statistics\PKPStatisticsHelper;
use PKP\validation\ValidatorFactory;
use ReflectionFunction;

abstract class PKPBaseController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     *  The value of this variable should look like this:
     *  [
     *      ROLE_ID_... => [...allowed route controller operations...],
     *      ...
     *  ]
     */
    protected array $_roleAssignments = [];

    /**
     * Whether role assignments have been checked.
     */
    protected bool $_roleAssignmentsChecked = false;

    /**
     * Whether to enforce site access restrictions.
     */
    protected bool $_enforceRestrictedSite = true;

    /**
     * Authorization decision manager
     */
    protected ?AuthorizationDecisionManager $_authorizationDecisionManager = null;

    /**
     * The PKP request object
     */
    protected ?PKPRequest $_request = null;

    /**
     * The unique endpoint string for the APIs that will be served through controller.
     *
     * This is equivalent to property \PKP\handler\APIHandler::_handlerPath
     */
    abstract public function getHandlerPath(): string;

    /**
     * Return the middlewares that will be applied to all defined routes in controller
     */
    abstract public function getRouteGroupMiddleware(): array;

    /**
     * Contains all the routes for this controller
     */
    abstract public function getGroupRoutes(): void;

    /**
     * Get the curernt requested route
     */
    public static function getRequestedRoute(?Request $request = null): ?Route
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */
        $routes = $router->getRoutes(); /** @var \Illuminate\Routing\RouteCollection $routes */
        $request ??= app('request');

        if($routes->count() <= 0) {
            return null;
        }

        return $routes->match($request);
    }

    /**
     * Get the curernt requested route's controller instance
     */
    public static function getRouteController(?Request $request = null): ?static
    {
        if (!$requestedRoute = static::getRequestedRoute($request)) {
            return null;
        }
        
        $calledRouteController = (new ReflectionFunction($requestedRoute->action['uses']))->getClosureThis();

        // When the routes are added to router as a closure/callable from other section like from a 
        // plugin through the hook, the resolved called route class may not be an instance of
        // `PKPBaseController` and we need to resolve the current controller instance from 
        // `APIHandler::getApiController` method
        if ($calledRouteController instanceof self) {
            return $calledRouteController;
        }

        $apiHandler = Application::get()->getRequest()->getRouter()->getHandler(); /** @var \PKP\handler\APIHandler $apiHandler */
        return $apiHandler->getApiController();
    }

    /**
     * Get the curernt requested route's controller action/method name
     */
    public static function getRouteActionName(?Request $request = null): ?string
    {
        if (!$requestedRoute = static::getRequestedRoute($request)) {
            return null;
        }

        return (new ReflectionFunction($requestedRoute->action['uses']))->getName();
    }

    /**
     * Return the role authorizer middleware ('has.roles') with proper role params binding
     */
    public static function roleAuthorizer(array $roles): string
    {
        if (empty($roles)) {
            throw new Exception('must provide roles as array to authorize');
        }

        $roles = implode('|', $roles);

        return "has.roles:{$roles}";
    }

    /**
     * The endpoint pattern for the APIs that will be served through controller.
     *
     * This is equivalent to property \PKP\handler\APIHandler::_pathPattern
     */
    public function getPathPattern(): ?string
    {
        return null;
    }

    /**
     * Define if all the path building for admin api use rather than at context level
     *
     * This is equivalent to property \PKP\handler\APIHandler::_apiForAdmin
     */
    public function isSiteWide(): bool
    {
        return false;
    }

    /**
     * Get the PKP core request object
     */
    public function getRequest(): PKPRequest
    {
        $this->_request ??= Application::get()->getRequest();

        return $this->_request;
    }

    /**
     * Add an authorization policy for this controller which will
     * be applied in the authorize() method.
     *
     * Policies must be added in the authorize() method before the parent::authorize()
     * call so that PKPBaseController::authorize() will be able to enforce them.
     *
     * @param bool                          $addToTop               Whether to insert the new policy to the top of the list.
     *
     */
    public function addPolicy(AuthorizationPolicy|PolicySet $authorizationPolicy, bool $addToTop = false): void
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
     * @param int $assocType Any of the Application::ASSOC_TYPE_* constants
     */
    public function &getAuthorizedContextObject(int $assocType): mixed
    {
        return $this->_authorizationDecisionManager->getAuthorizedContextObject($assocType);
    }

    /**
     * Get the authorized context.
     *
     * NB: You should avoid accessing the authorized context
     * directly to avoid accidentally overwriting an object
     * in the context. Try to use getAuthorizedContextObject()
     * instead where possible.
     */
    public function &getAuthorizedContext(): array
    {
        return $this->_authorizationDecisionManager->getAuthorizedContext();
    }

    /**
     * Retrieve the last authorization message from the decision manager.
     */
    public function getLastAuthorizationMessage(): string
    {
        if (!$this->_authorizationDecisionManager instanceof AuthorizationDecisionManager) {
            return '';
        }

        $authorizationMessages = $this->_authorizationDecisionManager->getAuthorizationMessages();

        return end($authorizationMessages);
    }

    /**
     * Set the site restriction enforcement
     */
    public function setEnforceRestrictedSite(bool $enforceRestrictedSite): void
    {
        $this->_enforceRestrictedSite = $enforceRestrictedSite;
    }

    /**
     * Assume SSL is required for all routes, unless overridden in subclasses.
     */
    public function requireSSL(): bool
    {
        return true;
    }

    /**
     * Add role - operation assignments to the controller action/method.
     *
     * @param int|array     $roleIds        One or more of the ROLE_ID_* constants
     * @param string|array  $operations     A single method name or an array of method names to be assigned.
     */
    public function addRoleAssignment(int|array $roleIds, string|array $operations): void
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
     * Flag role assignment checking as completed.
     */
    public function markRoleAssignmentsChecked(): void
    {
        $this->_roleAssignmentsChecked = true;
    }

    /**
     * Authorize this request.
     *
     * This method will be called via the \PKP\middleware\PolicyAuthorizer middleware
     * authomatically for the current target route to execute before execuring the
     * route itself .
     *
     * NB: This method will be called once for every request only.
     *
     * @param array         $args               request arguments
     * @param array         $roleAssignments    the operation role assignment,
     *                                          see getRoleAssignment() for more details.
     *
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
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
        if (!PKPSessionGuard::isSessionDisable()) {
            // Add user roles in authorized context.
            $user = $request->getUser();
            if ($user instanceof \PKP\user\User) {
                $this->addPolicy(new UserRolesRequiredPolicy($request), true);
            }
        }

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
     * Fetches parameter value
     */
    public function getParameter(string $parameterName, mixed $default = null): mixed
    {
        $illuminateRequest = app('request'); /** @var \Illuminate\Http\Request $illuminateRequest */
        $route = static::getRequestedRoute();

        // we probably have an invalid url if route is null
        if (!is_null($route)) {
            $arguments = $route->parameters();
            if (isset($arguments[$parameterName])) {
                return $arguments[$parameterName];
            }

            $queryParams = $illuminateRequest->query();
            if (isset($queryParams[$parameterName])) {
                return $queryParams[$parameterName];
            }
        }

        return $default;
    }

    /**
     * Convert string values in boolean, integer and number parameters to their
     * appropriate type when the string is in a recognizable format.
     *
     * Converted booleans: False: "0", "false". True: "true", "1"
     * Converted integers: Anything that passes ctype_digit()
     * Converted floats: Anything that passes is_numeric()
     *
     * Empty strings will be converted to null.
     *
     * @param string $schema One of the SCHEMA_... constants
     * @param array $params Key/value parameters to be validated
     *
     * @return array Converted parameters
     */
    public function convertStringsToSchema(string $schema, array $params): array
    {
        $schema = app()->get('schema')->get($schema);

        foreach ($params as $paramName => $paramValue) {
            if (!property_exists($schema->properties, $paramName)) {
                continue;
            }
            if (!empty($schema->properties->{$paramName}->multilingual)) {
                foreach ($paramValue as $localeKey => $localeValue) {
                    $params[$paramName][$localeKey] = $this->_convertStringsToSchema(
                        $localeValue,
                        $schema->properties->{$paramName}->type,
                        $schema->properties->{$paramName}
                    );
                }
            } else {
                $params[$paramName] = $this->_convertStringsToSchema(
                    $paramValue,
                    $schema->properties->{$paramName}->type,
                    $schema->properties->{$paramName}
                );
            }
        }

        return $params;
    }

    /**
     * Helper function to convert a string to a specified type if it meets
     * certain conditions.
     *
     * This function can be called recursively on nested objects and arrays.
     *
     * @see self::convertStringsToTypes
     *
     * @param string $type One of boolean, integer or number
     */
    private function _convertStringsToSchema(mixed $value, mixed $type, object $schema): mixed
    {
        // Convert all empty strings to null except arrays (see note below)
        if (is_string($value) && !strlen($value) && $type !== 'array') {
            return null;
        }
        switch ($type) {
            case 'boolean':
                if (is_string($value)) {
                    if ($value === 'true' || $value === '1') {
                        return true;
                    } elseif ($value === 'false' || $value === '0') {
                        return false;
                    }
                }
                break;
            case 'integer':
                if (is_string($value) && ctype_digit($value)) {
                    return (int) $value;
                }
                break;
            case 'number':
                if (is_string($value) && is_numeric($value)) {
                    return floatval($value);
                }
                break;
            case 'array':
                if (is_array($value)) {
                    $newArray = [];
                    if (is_array($schema->items)) {
                        foreach ($schema->items as $i => $itemSchema) {
                            $newArray[$i] = $this->_convertStringsToSchema($value[$i], $itemSchema->type, $itemSchema);
                        }
                    } else {
                        foreach ($value as $i => $v) {
                            $newArray[$i] = $this->_convertStringsToSchema($v, $schema->items->type, $schema->items);
                        }
                    }
                    return $newArray;

                    // An empty string is accepted as an empty array. This addresses the
                    // issue where browsers strip empty arrays from post data before sending.
                    // See: https://bugs.jquery.com/ticket/6481
                } elseif (is_string($value) && !strlen($value)) {
                    return [];
                } elseif (is_null($value)) { // if null, then return empty array
                    return [];
                }
                break;
            case 'object':
                if (is_array($value)) {
                    // In some cases a property may be defined as an object but it may not
                    // contain specific details about that object's properties. In these cases,
                    // leave the properties alone.
                    if (!property_exists($schema, 'properties')) {
                        return $value;
                    }
                    $newObject = [];
                    foreach ($schema->properties as $propName => $propSchema) {
                        if (!isset($value[$propName])) {
                            continue;
                        }
                        $newObject[$propName] = $this->_convertStringsToSchema($value[$propName], $propSchema->type, $propSchema);
                    }
                    return $newObject;
                }
                break;
        }
        return $value;
    }

    /**
     * A helper method to validate start and end date params for stats in API controllers
     *
     * 1. Checks the date formats
     * 2. Ensures a start date is not earlier than PKPStatisticsHelper::STATISTICS_EARLIEST_DATE
     * 3. Ensures an end date is no later than yesterday
     * 4. Ensures the start date is not later than the end date
     *
     * @param array     $params             The params to validate
     * @param string    $dateStartParam     Where the find the start date in the array of params
     * @param string    $dateEndParam       Where to find the end date in the array of params
     *
     * @return bool|string  True if they validate, or a string which
     *                      contains the locale key of an error message.
     */
    protected function _validateStatDates(array $params, string $dateStartParam = 'dateStart', string $dateEndParam = 'dateEnd'): bool|string
    {
        $validator = ValidatorFactory::make(
            $params,
            [
                $dateStartParam => [
                    'date_format:Y-m-d',
                    'after_or_equal:' . PKPStatisticsHelper::STATISTICS_EARLIEST_DATE,
                    'before_or_equal:' . $dateEndParam,
                ],
                $dateEndParam => [
                    'date_format:Y-m-d',
                    'before_or_equal:yesterday',
                    'after_or_equal:' . $dateStartParam,
                ],
            ],
            [
                '*.date_format' => 'invalidFormat',
                $dateStartParam . '.after_or_equal' => 'tooEarly',
                $dateEndParam . '.before_or_equal' => 'tooLate',
                $dateStartParam . '.before_or_equal' => 'invalidRange',
                $dateEndParam . '.after_or_equal' => 'invalidRange',
            ]
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->getMessages();
            if ((!empty($errors[$dateStartParam]) && in_array('invalidFormat', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidFormat', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateFormat';
            }
            if (!empty($errors[$dateStartParam]) && in_array('tooEarly', $errors[$dateStartParam])) {
                return 'api.stats.400.earlyDateRange';
            }
            if (!empty($errors[$dateEndParam]) && in_array('tooLate', $errors[$dateEndParam])) {
                return 'api.stats.400.lateDateRange';
            }
            if ((!empty($errors[$dateStartParam]) && in_array('invalidRange', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidRange', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateRange';
            }
        }

        return true;
    }
}

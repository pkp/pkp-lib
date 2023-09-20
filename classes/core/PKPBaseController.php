<?php

/**
 * @file classes/core/PKPBaseController.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBaseController
 *
 * @ingroup core
 *
 * @brief Base abstract controller that all controller must extend
 *
 */

namespace PKP\core;

use APP\core\Application;
use APP\core\Services;
use Exception;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Controller;
use PKP\core\PKPRequest;
use PKP\validation\ValidatorFactory;
use PKP\statistics\PKPStatisticsHelper;
use PKP\session\SessionManager;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\authorization\AllowedHostsPolicy;
use PKP\security\authorization\HttpsPolicy;
use PKP\security\authorization\RestrictedSiteAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\AuthorizationDecisionManager;
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

    abstract public function getHandlerPath(): string;

    abstract public function getRouteGroupMiddleware(): array;

    abstract public function getGroupRoutes(): void;

    public static function getRequestedRoute(Request $request = null): ?Route
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */
        $routes = $router->getRoutes(); /** @var \Illuminate\Routing\RouteCollection $routes */
        $request ??= app('request');

        if($routes->count() <= 0) {
            return null;
        }

        return $routes->match($request);
    }

    public static function getRouteController(Request $request = null): ?static
    {
        if (!$requestedRoute = static::getRequestedRoute($request)) {
            return null;
        }

        return (new ReflectionFunction($requestedRoute->action['uses']))->getClosureThis();
    }

    public static function getRouteActionName(Request $request = null): ?string
    {
        if (!$requestedRoute = static::getRequestedRoute($request)) {
            return null;
        }

        return (new ReflectionFunction($requestedRoute->action['uses']))->getName();
    }

    public static function roleAuthorizer(array $roles): string 
    {
        if (empty($roles)) {
            throw new Exception('must provide roles as array to authorize');
        }

        $roles = implode('|', $roles);

        return "has.roles:{$roles}";
    }

    public function getPathPattern(): ?string
    {
        return null;
    }

    public function isSiteWide(): bool
    {
        return false;
    }

    public function getRequest(): PKPRequest
    {
        if (!$this->_request) {
            $this->_request = Application::get()->getRequest();
        }

        return $this->_request;
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
     * @param AuthorizationPolicy|PolicySet $authorizationPolicy
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
    public function &getAuthorizedContext(): array
    {
        assert($this->_authorizationDecisionManager instanceof AuthorizationDecisionManager);
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
     * Routers will call this method automatically thereby enforcing
     * authorization. This method will be called before the
     * validate() method and before passing control on to the
     * handler operation.
     *
     * NB: This method will be called once for every request only.
     *
     * @param PKPRequest    $request
     * @param array         $args               request arguments
     * @param array         $roleAssignments    the operation role assignment,
     *                                          see getRoleAssignment() for more details.
     *
     * @return bool
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
     * 
     * FIXME#7698: may need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
     */
    public function convertStringsToSchema(string $schema, array $params): array
    {
        $schema = Services::get('schema')->get($schema);

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
     * 
     * FIXME#7698: May need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
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
     * 
     * FIXME#7698: May need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
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
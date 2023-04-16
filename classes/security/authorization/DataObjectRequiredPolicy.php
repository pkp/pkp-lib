<?php
/**
 * @file classes/security/authorization/DataObjectRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectRequiredPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Abstract base class for policies that check for a data object from a parameter.
 */

namespace PKP\security\authorization;

use Exception;

class DataObjectRequiredPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /** @var array */
    public $_args;

    public ?string $_parameterName;

    /** @var array */
    public $_operations;

    //
    // Getters and Setters
    //
    /**
     * Return the request.
     *
     * @return PKPRequest
     */
    public function &getRequest()
    {
        return $this->_request;
    }

    /**
     * Return the request arguments
     *
     * @return array
     */
    public function &getArgs()
    {
        return $this->_args;
    }

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param ?string $parameterName the request parameter we expect
     * @param string $message
     * @param array $operations Optional list of operations for which this check takes effect. If specified, operations outside this set will not be checked against this policy.
     */
    public function __construct($request, &$args, ?string $parameterName, $message = null, $operations = null)
    {
        parent::__construct($message);
        $this->_request = $request;
        assert(is_array($args));
        $this->_args = & $args;
        $this->_parameterName = $parameterName;
        $this->_operations = $operations;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Check if the object is required for the requested Op. (No operations means check for all.)
        if (is_array($this->_operations) && !in_array($this->_request->getRequestedOp(), $this->_operations)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return $this->dataObjectEffect();
        }
    }

    //
    // Protected helper method
    //
    /**
     * Test the data object's effect
     *
     * @return AUTHORIZATION_DENY|AUTHORIZATION_ACCEPT
     */
    public function dataObjectEffect()
    {
        // Deny by default. Must be implemented by subclass.
        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }

    /**
     * Identifies a data object id in the request.
     *
     * @param bool $lookOnlyByParameterName True iff page router
     *  requests should only look for named parameters.
     *
     * @return int|false returns false if no valid submission id could be found.
     */
    public function getDataObjectId($lookOnlyByParameterName = false)
    {
        // Identify the data object id.
        $router = $this->_request->getRouter();
        switch (true) {
            case $router instanceof \PKP\core\PKPPageRouter:
                if ($this->_parameterName !== null && ctype_digit((string) $this->_request->getUserVar($this->_parameterName))) {
                    // We may expect a object id in the user vars
                    return (int) $this->_request->getUserVar($this->_parameterName);
                } elseif (!$lookOnlyByParameterName && isset($this->_args[0]) && ctype_digit((string) $this->_args[0])) {
                    // Or the object id can be expected as the first path in the argument list
                    return (int) $this->_args[0];
                }
                break;

            case $router instanceof \PKP\core\PKPComponentRouter:
                // We expect a named object id argument.
                if ($this->_parameterName !== null && isset($this->_args[$this->_parameterName])
                        && ctype_digit((string) $this->_args[$this->_parameterName])) {
                    return (int) $this->_args[$this->_parameterName];
                }
                break;

            case $router instanceof \PKP\core\APIRouter:
                if ($this->_parameterName !== null) {
                    $handler = $router->getHandler();
                    return $handler->getParameter($this->_parameterName);
                }
                break;

            default: throw new Exception('DataObjectRequiredPolicy does not support routers of type ' . get_class($router) . '!');
        }

        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\DataObjectRequiredPolicy', '\DataObjectRequiredPolicy');
}

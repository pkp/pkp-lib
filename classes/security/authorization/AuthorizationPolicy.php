<?php

/**
 * @file classes/security/authorization/AuthorizationPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorizationPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to represent an authorization policy.
 *
 * We use some of the terminology specified in the draft XACML V3.0 standard,
 * please see <http://www.oasis-open.org/committees/tc_home.php?wg_abbrev=xacml>
 * for details.
 *
 * We try to stick closely enough to XACML concepts to make sure that
 * future improvements to the authorization framework can be done in a
 * consistent manner.
 *
 * This of course doesn't mean that we are "XACML compliant" in any way.
 */

namespace PKP\security\authorization;

class AuthorizationPolicy
{
    public const AUTHORIZATION_PERMIT = 1;
    public const AUTHORIZATION_DENY = 2;

    public const AUTHORIZATION_ADVICE_DENY_MESSAGE = 1;
    public const AUTHORIZATION_ADVICE_CALL_ON_DENY = 2;

    /** @var array advice to be returned to the decision point */
    public array $_advice = [];

    /**
     * @var array a list of authorized context objects that should be
     *  returned to the caller
     */
    public array $_authorizedContext = [];


    /**
     * Constructor
     */
    public function __construct(?string $message = null)
    {
        if (!is_null($message)) {
            $this->setAdvice(self::AUTHORIZATION_ADVICE_DENY_MESSAGE, $message);
        }
    }

    //
    // Setters and Getters
    //
    /**
     * Set an advice
     */
    public function setAdvice(int $adviceType, mixed $adviceContent): void
    {
        $this->_advice[$adviceType] = $adviceContent;
    }

    /**
     * Whether this policy implements
     * the given advice type.
     */
    public function hasAdvice(int $adviceType): bool
    {
        return isset($this->_advice[$adviceType]);
    }

    /**
     * Get advice for the given advice type.
     */
    public function getAdvice(int $adviceType): mixed
    {
        return $this->_advice[$adviceType] ?? null;
    }

    /**
     * Add an object to the authorized context
     */
    public function addAuthorizedContextObject(int $assocType, mixed $authorizedObject)
    {
        $this->_authorizedContext[$assocType] = $authorizedObject;
    }

    /**
     * Check whether an object already exists in the
     * authorized context.
     */
    public function hasAuthorizedContextObject(int $assocType): bool
    {
        return isset($this->_authorizedContext[$assocType]);
    }

    /**
     * Retrieve an object from the authorized context
     */
    public function getAuthorizedContextObject(int $assocType): mixed
    {
        return $this->_authorizedContext[$assocType] ?? null;
    }

    /**
     * Set the authorized context
     */
    public function setAuthorizedContext(&$authorizedContext): void
    {
        $this->_authorizedContext = & $authorizedContext;
    }

    /**
     * Get the authorized context
     */
    public function &getAuthorizedContext(): array
    {
        return $this->_authorizedContext;
    }

    //
    // Protected template methods to be implemented by sub-classes
    //
    /**
     * Whether this policy applies.
     */
    public function applies(): bool
    {
        // Policies apply by default
        return true;
    }

    /**
     * This method must return a value of either
     * AUTHORIZATION_DENY or AUTHORIZATION_PERMIT.
     */
    public function effect(): int
    {
        // Deny by default.
        return self::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\AuthorizationPolicy', '\AuthorizationPolicy');
    foreach ([
        'AUTHORIZATION_PERMIT',
        'AUTHORIZATION_DENY',
        'AUTHORIZATION_ADVICE_DENY_MESSAGE',
        'AUTHORIZATION_ADVICE_CALL_ON_DENY',
    ] as $constantName) {
        define($constantName, constant('\AuthorizationPolicy::' . $constantName));
    }
}

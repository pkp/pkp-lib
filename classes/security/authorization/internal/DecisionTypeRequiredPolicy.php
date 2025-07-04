<?php

/**
 * @file classes/security/authorization/internal/DecisionTypeRequiredPolicy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionTypeRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the requested decision type is valid
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPRequest;
use PKP\decision\DecisionType;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;

class DecisionTypeRequiredPolicy extends DataObjectRequiredPolicy
{
    protected int $decision;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param int $decision The decision constant to check
     */
    public function __construct($request, &$args, int $decision)
    {
        parent::__construct($request, $args, '', 'editor.submission.workflowDecision.typeInvalid');
        $this->decision = $decision;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        /** @var ?DecisionType $decisionType */
        $decisionType = Repo::decision()->getDecisionType($this->decision);

        if (!$decisionType) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE, $decisionType);

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

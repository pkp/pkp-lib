<?php
/**
 * @file classes/security/authorization/internal/DecisionStageValidPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionStageValidPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Checks whether the authorized submission is in the correct stage
 *   to take the authorized decision
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\i18n\AppLocale;
use PKP\security\authorization\AuthorizationPolicy;

class DecisionStageValidPolicy extends AuthorizationPolicy
{
    /**
     * Constructor
     *
     * @param $request PKPRequest
     */
    public function __construct()
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_EDITOR,
            LOCALE_COMPONENT_APP_EDITOR
        );
        parent::__construct('editor.submission.workflowDecision.invalidStage');
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE);

        if ($submission->getData('stageId') === $decisionType->getStageId()) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\DecisionStageValidPolicy', '\DecisionStageValidPolicy');
}

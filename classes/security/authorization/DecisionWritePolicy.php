<?php
/**
 * @file classes/security/authorization/DecisionWritePolicy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionWritePolicy
 * @ingroup security_authorization
 *
 * @brief Checks access to take a decision based on authorized roles and submission.
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\DecisionAllowedPolicy;
use PKP\security\authorization\internal\DecisionStageValidPolicy;
use PKP\security\authorization\internal\DecisionTypeRequiredPolicy;
use PKP\user\User;

class DecisionWritePolicy extends ContextPolicy
{
    public function __construct($request, $args, int $decision, ?User $editor)
    {
        parent::__construct($request);
        $this->addPolicy(new DecisionTypeRequiredPolicy($request, $args, $decision));
        $this->addPolicy(new DecisionStageValidPolicy());
        $this->addPolicy(new DecisionAllowedPolicy($editor));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\DecisionWritePolicy', '\DecisionWritePolicy');
}

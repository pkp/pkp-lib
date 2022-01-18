<?php
/**
 * @file classes/security/authorization/internal/DecisionTypeRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionTypeRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the requested decision type is valid
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use PKP\decision\Type;
use PKP\security\authorization\AuthorizationPolicy;

use PKP\security\authorization\DataObjectRequiredPolicy;

class DecisionTypeRequiredPolicy extends DataObjectRequiredPolicy
{
    protected int $decision;

    /**
     * Constructor
     *
     * @param $request PKPRequest
     * @param $args array request parameters
     * @param int $decision The decision constant to check
     */
    public function __construct($request, &$args, int $decision)
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_EDITOR,
            LOCALE_COMPONENT_APP_EDITOR
        );
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
        /** @var Type|null $type */
        $type = Repo::decision()->getTypes()->first(function ($type) {
            return $type->getDecision() === $this->decision;
        });

        if (!$type) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE, $type);

        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\DecisionTypeRequiredPolicy', '\DecisionTypeRequiredPolicy');
}

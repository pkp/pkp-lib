<?php

/**
 * @file classes/observers/events/DecisionAdded.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionAdded
 * @ingroup observers_events
 *
 * @brief An event fired when an editorial decision is recorded.
 */

namespace PKP\observers\events;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Foundation\Events\Dispatchable;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\user\User;

class DecisionAdded
{
    use Dispatchable;

    /** The decision that was recorded */
    public Decision $decision;

    /** The type of decision that was recorded */
    public DecisionType $decisionType;

    /** The journal, press or preprint server this decision was recorded in */
    public Context $context;

    /** The submission for which this decision was recorded */
    public Submission $submission;

    /** The editor who recorded the decision */
    public User $editor;

    /**
     * Any additional actions that were requested when this
     * decision was recorded.
     *
     * Actions include emails sent, files promoted, and other form
     * data submitted when the decision was recorded. Each decision
     * supports different actions. See the REST API documentation
     * for more information about what actions to expect with each
     * decision.
     */
    public array $actions;

    public function __construct(Decision $decision, DecisionType $decisionType, Submission $submission, User $editor, Context $context, array $actions)
    {
        $this->actions = $actions;
        $this->context = $context;
        $this->decision = $decision;
        $this->decisionType = $decisionType;
        $this->editor = $editor;
        $this->submission = $submission;
    }
}

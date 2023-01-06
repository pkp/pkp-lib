<?php
/**
 * @file classes/observers/events/SubmissionSubmitted.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitted
 * @ingroup observers_events
 *
 * @brief Event fired when a submission is submitted.
 */

namespace PKP\observers\events;

use APP\submission\Submission;
use PKP\context\Context;

class SubmissionSubmitted
{
    public Context $context;
    public Submission $submission;

    public function __construct(Submission $submission, Context $context)
    {
        $this->context = $context;
        $this->submission = $submission;
    }
}

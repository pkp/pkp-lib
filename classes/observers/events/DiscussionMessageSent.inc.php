<?php

/**
 * @file classes/observers/events/DiscussionMessageSent.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DiscussionMessageSent
 * @ingroup observers_events
 *
 * @brief An event fired when a discussion message is created.
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\context\Context;
use PKP\mail\mailables\FormEmailData;
use PKP\query\Query;
use PKP\submission\PKPSubmission;

class DiscussionMessageSent
{
    use Dispatchable;

    public Query $query;

    public Context $context;

    public PKPSubmission $submission;

    // Request data entered by user
    public FormEmailData $formEmailData;

    public function __construct(Query $query, Context $context, PKPSubmission $submission, FormEmailData $formData)
    {
        $this->query = $query;
        $this->context = $context;
        $this->submission = $submission;
        $this->formEmailData = $formData;
    }
}

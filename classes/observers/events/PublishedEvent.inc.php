<?php

/**
 * @file classes/observers/events/PublishedEvent.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishedEvent
 * @ingroup core
 *
 * @brief Event fired when publication is being published
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;
use PKP\publication\PKPPublication;
use PKP\submission\PKPSubmission;

class PublishedEvent
{
    use Dispatchable;

    /** @var PKPPublication $newPublication The publication being published */
    public $newPublication;

    /** @var PKPPublication $publication Old publication, before processing */
    public $publication;

    /** @var PKPSubmission $submission Submission associated with the publication */
    public $submission;

    public function __construct(PKPPublication $newPublication, PKPPublication $publication, PKPSubmission $submission)
    {
        $this->newPublication = $newPublication;
        $this->publication = $publication;
        $this->submission = $submission;
    }
}

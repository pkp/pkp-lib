<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/MetadataChanged.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataChanged
 * @ingroup core
 *
 * @brief Event fired when metadata changed
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

use PKP\submission\PKPSubmission;

class MetadataChanged
{
    use Dispatchable;

    /** @var PKPSubmission $submission Submission associated */
    public $submission;

    public function __construct(PKPSubmission $submission)
    {
        $this->submission = $submission;
    }
}

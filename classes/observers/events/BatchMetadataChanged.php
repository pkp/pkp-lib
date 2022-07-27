<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/BatchMetadataChanged.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BatchMetadataChanged
 * @ingroup core
 *
 * @brief Event fired when metadata changes batch as called
 */

namespace PKP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

class BatchMetadataChanged
{
    use Dispatchable;

    /** @var array $submissionIds Submission ids associated */
    public $submissionIds;

    public function __construct(array $submissionIds = [])
    {
        $this->submissionIds = $submissionIds;
    }
}

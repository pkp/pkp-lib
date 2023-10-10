<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/PublicationPublished.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationPublished
 *
 * @ingroup observers_events
 *
 * @brief Event fired when publication is published
 */

namespace PKP\observers\events;

use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Foundation\Events\Dispatchable;
use PKP\context\Context;

class PublicationPublished
{
    use Dispatchable;

    /** @var Publication $publication The publication that was published */
    public Publication $publication;

    /** @var Publication $publication The publication before it was published */
    public Publication $oldPublication;

    public Submission $submission;

    public Context $context;

    /**
     * Class construct
     *
     * @param Publication $publication The publication that was published
     * @param Publication $oldPublication The publication before it was published
     */
    public function __construct(
        Publication $publication,
        Publication $oldPublication,
        Submission $submission,
        Context $context
    ) {
        $this->publication = $publication;
        $this->oldPublication = $oldPublication;
        $this->submission = $submission;
        $this->context = $context;
    }
}

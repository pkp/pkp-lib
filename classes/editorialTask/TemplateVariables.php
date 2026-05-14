<?php

/**
 * @file classes/editorialTask/TemplateVariables.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateVariables
 *
 * @ingroup editorialTask
 *
 * @brief The class is used to describe variables used in email templates
 */

namespace PKP\editorialTask;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;

class TemplateVariables extends Mailable
{
    use Sender;
    use Recipient;

    protected EditorialTask $editorialTask;
    protected Submission $submission;
    protected Context $context;

    public function __construct(EditorialTask $editorialTask, Submission $submission, Context $context)
    {
        parent::__construct(func_get_args());
        $this->editorialTask = $editorialTask;
        $this->submission = $submission;
        $this->context = $context;
    }
}

<?php

/**
 * @file classes/mail/mailables/ReviewCancel.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewCancel
 *
 * @ingroup mail_mailables
 *
 * @brief Email sent when a review is cancelled
 */

namespace PKP\mail\mailables;

class ReviewCancel extends ReviewerUnassign
{
    protected static ?string $name = 'mailable.reviewCancel.name';
    protected static ?string $description = 'mailable.reviewCancel.description';
    protected static ?string $emailTemplateKey = 'REVIEW_CANCEL';
}

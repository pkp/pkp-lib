<?php

/**
 * @file controllers/grid/users/reviewer/form/UnassignReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnassignReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to remove a review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewerUnassign;
use PKP\submission\reviewAssignment\ReviewAssignment;

class UnassignReviewerForm extends ClearReviewForm
{
    /**
     * Constructor
     *
     * @param mixed $reviewAssignment ReviewAssignment
     * @param mixed $reviewRound ReviewRound
     * @param mixed $submission Submission
     */
    public function __construct($reviewAssignment, $reviewRound, $submission)
    {
        parent::__construct($reviewAssignment, $reviewRound, $submission, 'controllers/grid/users/reviewer/form/unassignReviewerForm.tpl');
    }

    /**
     * @copydoc ReviewerNotifyActionForm::getMailable()
     */
    public function getMailable(Context $context, Submission $submission, ReviewAssignment $reviewAssignment): Mailable
    {
        return new ReviewerUnassign($context, $submission, $reviewAssignment);
    }
}

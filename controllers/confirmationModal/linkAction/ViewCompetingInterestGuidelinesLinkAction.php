<?php

/**
 * @file controllers/confirmationModal/linkAction/ViewCompetingInterestGuidelinesLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewCompetingInterestGuidelinesLinkAction
 *
 * @ingroup controllers_confirmationModal_linkAction
 *
 * @brief An action to open the competing interests confirmation modal.
 */

namespace PKP\controllers\confirmationModal\linkAction;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\ConfirmationModal;

class ViewCompetingInterestGuidelinesLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     */
    public function __construct($request)
    {
        $context = $request->getContext();
        // Instantiate the view competing interests modal.
        $viewCompetingInterestsModal = new ConfirmationModal(
            $context->getLocalizedData('competingInterests'),
            __('reviewer.submission.competingInterests'),
            null,
            null,
            false,
            false
        );

        // Configure the link action.
        parent::__construct('viewCompetingInterestGuidelines', $viewCompetingInterestsModal, __('reviewer.submission.competingInterests'));
    }
}

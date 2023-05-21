<?php
/**
 * @defgroup controllers_confirmationModal_linkAction Confirmation Modal Link Action
 */

/**
 * @file controllers/confirmationModal/linkAction/ViewReviewGuidelinesLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewReviewGuidelinesLinkAction
 *
 * @ingroup controllers_confirmationModal_linkAction
 *
 * @brief An action to open the review guidelines confirmation modal.
 */

namespace PKP\controllers\confirmationModal\linkAction;

use APP\core\Request;
use PKP\context\Context;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\ConfirmationModal;

class ViewReviewGuidelinesLinkAction extends LinkAction
{
    /** @var Context */
    public $_context;

    /** @var int WORKFLOW_STAGE_ID_... */
    public $_stageId;

    /**
     * Constructor
     *
     * @param Request $request
     * @param int $stageId Stage ID of review assignment
     */
    public function __construct($request, $stageId)
    {
        $this->_context = $request->getContext();
        $this->_stageId = $stageId;

        $viewGuidelinesModal = new ConfirmationModal(
            $this->getGuidelines(),
            __('reviewer.submission.guidelines'),
            null,
            null,
            false
        );

        // Configure the link action.
        parent::__construct('viewReviewGuidelines', $viewGuidelinesModal, __('reviewer.submission.guidelines'));
    }

    /**
     * Get the guidelines for the specified stage.
     *
     * @return ?string
     */
    public function getGuidelines()
    {
        return $this->_context->getLocalizedData(
            $this->_stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW ? 'reviewGuidelines' : 'internalReviewGuidelines'
        );
    }
}

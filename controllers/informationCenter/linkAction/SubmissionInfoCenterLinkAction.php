<?php

/**
 * @file controllers/informationCenter/linkAction/SubmissionInfoCenterLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionInfoCenterLinkAction
 * @ingroup controllers_informationCenter
 *
 * @brief An action to open up the information center for a submission.
 */

namespace PKP\controllers\informationCenter\linkAction;

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class SubmissionInfoCenterLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId the ID of the submission to present link for
     * to show information about.
     * @param string $linkKey optional locale key to display for link
     */
    public function __construct($request, $submissionId, $linkKey = 'informationCenter.editorialHistory')
    {
        // Instantiate the information center modal.

        $submission = Repo::submission()->get($submissionId);

        $primaryAuthor = $submission->getPrimaryAuthor();
        if (!isset($primaryAuthor)) {
            $authors = Repo::author()->getSubmissionAuthors($submission);
            $primaryAuthor = $authors->first();
        }

        $title = (isset($primaryAuthor)) ? implode(', ', [$primaryAuthor->getFullName(), $submission->getLocalizedTitle()]) : $submission->getLocalizedTitle();

        $dispatcher = $request->getDispatcher();
        $ajaxModal = new AjaxModal(
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'informationCenter.SubmissionInformationCenterHandler',
                'viewInformationCenter',
                null,
                ['submissionId' => $submissionId]
            ),
            htmlspecialchars($title),
            'modal_information'
        );

        // Configure the link action.
        parent::__construct(
            'editorialHistory',
            $ajaxModal,
            __($linkKey),
            'more_info'
        );
    }
}

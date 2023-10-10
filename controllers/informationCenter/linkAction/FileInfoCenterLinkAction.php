<?php
/**
 * @file controllers/informationCenter/linkAction/FileInfoCenterLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileInfoCenterLinkAction
 *
 * @ingroup controllers_informationCenter
 *
 * @brief A base action to open up the information center for a file.
 */

namespace PKP\controllers\informationCenter\linkAction;

use APP\core\Request;
use PKP\controllers\api\file\linkAction\FileLinkAction;
use PKP\core\PKPRequest;
use PKP\linkAction\request\AjaxModal;
use PKP\submissionFile\SubmissionFile;

class FileInfoCenterLinkAction extends FileLinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param SubmissionFile $submissionFile the submission file
     * to show information about.
     * @param int $stageId (optional) The stage id that user is looking at.
     */
    public function __construct($request, $submissionFile, $stageId = null)
    {
        // Instantiate the information center modal.
        $ajaxModal = $this->getModal($request, $submissionFile, $stageId);

        // Configure the file link action.
        parent::__construct(
            'moreInformation',
            $ajaxModal,
            __('grid.action.moreInformation'),
            'more_info'
        );
    }

    /**
     * returns the modal for this link action.
     *
     * @param PKPRequest $request
     * @param SubmissionFile $submissionFile
     * @param int $stageId
     *
     * @return AjaxModal
     */
    public function getModal($request, $submissionFile, $stageId)
    {
        $router = $request->getRouter();

        $title = (isset($submissionFile)) ? implode(': ', [__('informationCenter.informationCenter'), htmlspecialchars($submissionFile->getLocalizedData('name'))]) : __('informationCenter.informationCenter');

        $ajaxModal = new AjaxModal(
            $router->url(
                $request,
                null,
                'informationCenter.FileInformationCenterHandler',
                'viewInformationCenter',
                null,
                $this->getActionArgs($submissionFile, $stageId)
            ),
            $title,
            'modal_information'
        );

        return $ajaxModal;
    }
}

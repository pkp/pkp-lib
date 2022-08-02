<?php
/**
 * @defgroup controllers_api_file_linkAction Link action API controller
 */

/**
 * @file controllers/api/file/linkAction/BaseAddFileLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseAddFileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief Abstract base class for file upload actions.
 */

namespace PKP\controllers\api\file\linkAction;

use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\WizardModal;

class BaseAddFileLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId The submission the file should be
     *  uploaded to.
     * @param int $stageId The workflow stage in which the file
     *  uploader is being instantiated (one of the WORKFLOW_STAGE_ID_*
     *  constants).
     * @param array $uploaderRoles The ids of all roles allowed to upload
     *  in the context of this action.
     * @param array $actionArgs The arguments to be passed into the file
     *  upload wizard.
     * @param string $wizardTitle The title to be displayed in the file
     *  upload wizard.
     * @param string $buttonLabel The link action's button label.
     */
    public function __construct(
        $request,
        $submissionId,
        $stageId,
        $uploaderRoles,
        $actionArgs,
        $wizardTitle,
        $buttonLabel
    ) {

        // Augment the action arguments array.
        $actionArgs['submissionId'] = $submissionId;
        $actionArgs['stageId'] = $stageId;
        assert(is_array($uploaderRoles) && count($uploaderRoles) >= 1);
        $actionArgs['uploaderRoles'] = implode('-', (array) $uploaderRoles);

        // Instantiate the file upload modal.
        $dispatcher = $request->getDispatcher();
        $modal = new WizardModal(
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'wizard.fileUpload.FileUploadWizardHandler',
                'startWizard',
                null,
                $actionArgs
            ),
            $wizardTitle,
            'modal_add_file'
        );

        // Configure the link action.
        parent::__construct('addFile', $modal, $buttonLabel, 'add');
    }
}

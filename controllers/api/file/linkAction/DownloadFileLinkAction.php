<?php
/**
 * @file controllers/api/file/linkAction/DownloadFileLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadFileLinkAction
 *
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to download a file.
 */

namespace PKP\controllers\api\file\linkAction;

use APP\core\Request;
use PKP\linkAction\request\PostAndRedirectAction;
use PKP\submissionFile\SubmissionFile;

class DownloadFileLinkAction extends FileLinkAction
{
    /** @var string Optional label to use instead of file name */
    public $label;

    /**
     * Constructor
     *
     * @param Request $request
     * @param SubmissionFile $submissionFile the submission file to
     *  link to.
     * @param int $stageId (optional)
     * @param string $label (optional) Label to use instead of filename
     * @param int $fileId (optional) Download a specific revision of a file
     * @param string $filename (optional) The filename to use for the file
     */
    public function __construct($request, $submissionFile, $stageId = null, $label = null, $fileId = null, $filename = null)
    {
        // Instantiate the redirect action request.
        $router = $request->getRouter();
        $this->label = $label;
        $actionArgs = $this->getActionArgs($submissionFile, $stageId);
        if ($fileId) {
            $actionArgs['fileId'] = $fileId;
        }
        if ($filename) {
            $actionArgs['filename'] = $filename;
        }
        $redirectRequest = new PostAndRedirectAction(
            $router->url(
                $request,
                null,
                'api.file.FileApiHandler',
                'recordDownload',
                null,
                $actionArgs
            ),
            $router->url(
                $request,
                null,
                'api.file.FileApiHandler',
                'downloadFile',
                null,
                $actionArgs
            )
        );

        // Configure the file link action.
        parent::__construct(
            'downloadFile',
            $redirectRequest,
            htmlspecialchars($this->getLabel($submissionFile))
        );
    }

    /**
     * Get the label for the file download action.
     *
     * @param SubmissionFile $submissionFile
     *
     * @return string
     */
    public function getLabel($submissionFile)
    {
        if ($this->label !== null) {
            return $this->label;
        }
        return $submissionFile->getLocalizedData('name');
    }
}

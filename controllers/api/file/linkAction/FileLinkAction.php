<?php
/**
 * @file controllers/api/file/linkAction/FileLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An abstract file action.
 */

namespace PKP\controllers\api\file\linkAction;

use PKP\linkAction\LinkAction;

class FileLinkAction extends LinkAction
{
    //
    // Protected helper function
    //
    /**
     * Return the action arguments to address a file.
     *
     * @param SubmissionFile $submissionFile
     * @param int $stageId (optional)
     *
     * @return array
     */
    public function getActionArgs($submissionFile, $stageId = null)
    {
        assert($submissionFile instanceof \PKP\submissionFile\SubmissionFile);

        // Create the action arguments array.
        $args = [
            'submissionFileId' => $submissionFile->getId(),
            'submissionId' => $submissionFile->getData('submissionId')
        ];
        if ($stageId) {
            $args['stageId'] = $stageId;
        }

        return $args;
    }
}

<?php
/**
 * @file classes/submissionFile/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief Read and write submissionFiles to the database.
 */

namespace APP\submissionFile;

use Exception;
use PKP\db\DAORegistry;
use PKP\submissionFile\DAO as BaseDAO;

use PKP\submissionFile\SubmissionFile;

class DAO extends BaseDAO
{
    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(SubmissionFile $submissionFile): int
    {
        $galley = null;

        if ($submissionFile->getData('assocType') === ASSOC_TYPE_REPRESENTATION) {
            $galleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /* @var $galleyDao PreprintGalleyDAO */
            $galley = $galleyDao->getById($submissionFile->getData('assocId'));
            if (!$galley) {
                throw new Exception('Galley not found when adding submission file.');
            }
        }

        $submissionFileId = parent::insert($submissionFile);

        if ($galley) {
            $galley->setFileId($submissionFile->getId());
            $galleyDao->updateObject($galley);
        }

        return $submissionFileId;
    }
}

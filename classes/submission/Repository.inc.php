<?php
/**
 * @file classes/submission/Repository.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A repository to find and manage submissions.
 */

namespace APP\submission;

use APP\core\Services;
use APP\facades\Repo;
use APP\preprint\PreprintTombstoneManager;
use PKP\db\DAORegistry;

class Repository extends \PKP\submission\Repository
{
    /** @copydoc \PKP\submission\Repository::$schemaMap */
    public $schemaMap = maps\Schema::class;

    /** @copydoc \PKP\submission\Repo::updateStatus() */
    public function updateStatus(Submission $submission)
    {
        $oldStatus = $submission->getData('status');
        parent::updateStatus($submission);
        $newStatus = Repo::submission()->get($submission->getId())->getData('status');

        // Add or remove tombstones when submission is published or unpublished
        if ($newStatus === Submission::STATUS_PUBLISHED && $newStatus !== $oldStatus) {
            $tombstoneDao = DAORegistry::getDAO('DataObjectTombstoneDAO'); /** @var DataObjectTombstoneDAO $tombstoneDao */
            $tombstoneDao->deleteByDataObjectId($submission->getId());
        } elseif ($oldStatus === Submission::STATUS_PUBLISHED && $newStatus !== $oldStatus) {
            $requestContext = $this->request->getContext();
            if ($requestContext && $requestContext->getId() === $submission->getData('contextId')) {
                $context = $requestContext;
            } else {
                $context = Services::get('context')->get($submission->getData('contextId'));
            }
            $preprintTombstoneManager = new PreprintTombstoneManager();
            $preprintTombstoneManager->insertPreprintTombstone($submission, $context);
        }
    }
}

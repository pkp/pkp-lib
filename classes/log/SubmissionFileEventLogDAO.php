<?php

/**
 * @file classes/log/SubmissionFileEventLogDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogDAO
 *
 * @ingroup log
 *
 * @see EventLogDAO
 *
 * @brief Extension to EventLogDAO for submission file specific log entries.
 */

namespace PKP\log;

use APP\core\Application;

class SubmissionFileEventLogDAO extends EventLogDAO
{
    /**
     * Instantiate a submission file event log entry.
     *
     * @return SubmissionFileEventLogEntry
     */
    public function newDataObject()
    {
        $returner = new SubmissionFileEventLogEntry();
        $returner->setAssocType(Application::ASSOC_TYPE_SUBMISSION_FILE);
        return $returner;
    }

    /**
     * Get event log entries by submission file ID.
     *
     * @param int $submissionFileId
     *
     * @return \PKP\db\DAOResultFactory<SubmissionFileEventLogEntry>
     */
    public function getBySubmissionFileId($submissionFileId)
    {
        return $this->getByAssoc(Application::ASSOC_TYPE_SUBMISSION_FILE, $submissionFileId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionFileEventLogDAO', '\SubmissionFileEventLogDAO');
}

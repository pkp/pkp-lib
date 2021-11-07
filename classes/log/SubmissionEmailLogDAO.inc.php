<?php

/**
 * @file classes/log/SubmissionEmailLogDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEmailLogDAO
 * @ingroup log
 *
 * @see EmailLogDAO
 *
 * @brief Extension to EmailLogDAO for submission-specific log entries.
 */

namespace PKP\log;

class SubmissionEmailLogDAO extends EmailLogDAO
{
    /**
     * Instantiate and return a SubmissionEmailLogEntry
     *
     * @return SubmissionEmailLogEntry
     */
    public function newDataObject()
    {
        $returner = new SubmissionEmailLogEntry();
        $returner->setAssocType(ASSOC_TYPE_SUBMISSION);
        return $returner;
    }

    /**
     * Get submission email log entries by submission ID and event type
     *
     * @param int $submissionId
     * @param int $userId optional Return only emails sent to this user.
     *
     * @return DAOResultFactory
     */
    public function getByEventType($submissionId, $eventType, $userId = null)
    {
        return parent::_getByEventType(ASSOC_TYPE_SUBMISSION, $submissionId, $eventType, $userId);
    }

    /**
     * Get submission email log entries by submission ID
     *
     * @param int $submissionId
     *
     * @return DAOResultFactory
     */
    public function getBySubmissionId($submissionId)
    {
        return $this->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionEmailLogDAO', '\SubmissionEmailLogDAO');
}

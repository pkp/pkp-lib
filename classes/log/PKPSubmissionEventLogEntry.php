<?php

/**
 * @file classes/log/PKPSubmissionEventLogEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionEventLogEntry
 *
 * @ingroup log
 *
 * @see SubmissionEventLogDAO
 *
 * @brief Describes an entry in the submission history log.
 */

namespace PKP\log;

use APP\core\Application;

class PKPSubmissionEventLogEntry extends EventLogEntry
{
    public const SUBMISSION_LOG_SUBMISSION_SUBMIT = 0x10000001;
    public const SUBMISSION_LOG_METADATA_UPDATE = 0x10000002;
    public const SUBMISSION_LOG_ADD_PARTICIPANT = 0x10000003;
    public const SUBMISSION_LOG_REMOVE_PARTICIPANT = 0x10000004;

    public const SUBMISSION_LOG_METADATA_PUBLISH = 0x10000006;
    public const SUBMISSION_LOG_METADATA_UNPUBLISH = 0x10000007;

    public const SUBMISSION_LOG_CREATE_VERSION = 0x10000008;

    public const SUBMISSION_LOG_COPYRIGHT_AGREED = 0x10000009;

    public const SUBMISSION_LOG_EDITOR_DECISION = 0x30000003;
    public const SUBMISSION_LOG_EDITOR_RECOMMENDATION = 0x30000004;
    public const SUBMISSION_LOG_DECISION_EMAIL_SENT = 0x40000020;

    public const SUBMISSION_LOG_REVIEW_ASSIGN = 0x40000001;
    public const SUBMISSION_LOG_REVIEW_REINSTATED = 0x40000005;
    public const SUBMISSION_LOG_REVIEW_ACCEPT = 0x40000006;
    public const SUBMISSION_LOG_REVIEW_DECLINE = 0x40000007;
    public const SUBMISSION_LOG_REVIEW_UNCONSIDERED = 0x40000009;
    public const SUBMISSION_LOG_REVIEW_SET_DUE_DATE = 0x40000011;
    public const SUBMISSION_LOG_REVIEW_CLEAR = 0x40000014;
    public const SUBMISSION_LOG_REVIEW_READY = 0x40000018;
    public const SUBMISSION_LOG_REVIEW_CONFIRMED = 0x40000019;
    public const SUBMISSION_LOG_REVIEW_REMIND = 0x40000020;
    public const SUBMISSION_LOG_REVIEW_REMIND_AUTO = 0x40000020;


    //
    // Getters/setters
    //
    /**
     * Set the submission ID
     */
    public function setSubmissionId($submissionId)
    {
        return $this->setAssocId($submissionId);
    }


    /**
     * Get the submission ID
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->getAssocId();
    }


    /**
     * Get the assoc ID
     *
     * @return int
     */
    public function getAssocType()
    {
        return Application::ASSOC_TYPE_SUBMISSION;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\PKPSubmissionEventLogEntry', '\PKPSubmissionEventLogEntry');
    foreach ([
        'SUBMISSION_LOG_SUBMISSION_SUBMIT',
        'SUBMISSION_LOG_METADATA_UPDATE',
        'SUBMISSION_LOG_ADD_PARTICIPANT',
        'SUBMISSION_LOG_REMOVE_PARTICIPANT',
        'SUBMISSION_LOG_METADATA_PUBLISH',
        'SUBMISSION_LOG_METADATA_UNPUBLISH',
        'SUBMISSION_LOG_CREATE_VERSION',
        'SUBMISSION_LOG_EDITOR_DECISION',
        'SUBMISSION_LOG_EDITOR_RECOMMENDATION',
        'SUBMISSION_LOG_REVIEW_ASSIGN',
        'SUBMISSION_LOG_REVIEW_REINSTATED',
        'SUBMISSION_LOG_REVIEW_DECLINE',
        'SUBMISSION_LOG_REVIEW_UNCONSIDERED',
        'SUBMISSION_LOG_REVIEW_SET_DUE_DATE',
        'SUBMISSION_LOG_REVIEW_CLEAR',
        'SUBMISSION_LOG_REVIEW_READY',
        'SUBMISSION_LOG_REVIEW_CONFIRMED',
    ] as $constantName) {
        define($constantName, constant('\PKPSubmissionEventLogEntry::' . $constantName));
    }
}

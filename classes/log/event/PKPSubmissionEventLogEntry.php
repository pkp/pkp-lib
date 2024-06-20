<?php

/**
 * @file classes/log/event/PKPSubmissionEventLogEntry.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionEventLogEntry
 *
 * @brief Describes an entry in the submission history log.
 */

namespace PKP\log\event;

use APP\core\Application;

class PKPSubmissionEventLogEntry extends EventLogEntry
{
    public const SUBMISSION_LOG_SUBMISSION_SUBMIT = 268435457; // 0x10000001
    public const SUBMISSION_LOG_METADATA_UPDATE = 268435458; // 0x10000002
    public const SUBMISSION_LOG_ADD_PARTICIPANT = 268435459; // 0x10000003
    public const SUBMISSION_LOG_REMOVE_PARTICIPANT = 268435460; // 0x10000004

    public const SUBMISSION_LOG_METADATA_PUBLISH = 268435462; // 0x10000006
    public const SUBMISSION_LOG_METADATA_UNPUBLISH = 268435463; // 0x10000007

    public const SUBMISSION_LOG_CREATE_VERSION = 268435464; // 0x10000008

    public const SUBMISSION_LOG_COPYRIGHT_AGREED = 268435465; // 0x10000009

    public const SUBMISSION_LOG_EDITOR_DECISION = 805306371; // 0x30000003
    public const SUBMISSION_LOG_EDITOR_RECOMMENDATION = 805306372; // 0x30000004
    public const SUBMISSION_LOG_DECISION_EMAIL_SENT = 805306375; // 0x30000007

    public const SUBMISSION_LOG_REVIEW_ASSIGN = 1073741825; // 0x40000001
    public const SUBMISSION_LOG_REVIEW_REINSTATED = 1073741829; // 0x40000005
    public const SUBMISSION_LOG_REVIEW_ACCEPT = 1073741830; // 0x40000006
    public const SUBMISSION_LOG_REVIEW_DECLINE = 1073741831; // 0x40000007
    public const SUBMISSION_LOG_REVIEW_UNCONSIDERED = 1073741833; // 0x40000009
    public const SUBMISSION_LOG_REVIEW_SET_DUE_DATE = 1073741841; // 0x40000011
    public const SUBMISSION_LOG_REVIEW_CLEAR = 1073741844; // 0x40000014
    public const SUBMISSION_LOG_REVIEW_READY = 1073741848; // 0x40000018
    public const SUBMISSION_LOG_REVIEW_CONFIRMED = 0x40000019; // 0x40000019
    public const SUBMISSION_LOG_REVIEW_REMIND = 1073741856; // 0x40000020
    public const SUBMISSION_LOG_REVIEW_REMIND_AUTO = 1073741857; // 0x40000021


    //
    // Getters/setters
    //
    /**
     * Set the submission ID
     */
    public function setSubmissionId(int $submissionId): void
    {
        $this->setAssocId($submissionId);
    }


    /**
     * Get the submission ID
     */
    public function getSubmissionId(): int
    {
        return $this->getAssocId();
    }


    /**
     * Get the assoc ID
     */
    public function getAssocType(): int
    {
        return Application::ASSOC_TYPE_SUBMISSION;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\event\PKPSubmissionEventLogEntry', '\PKPSubmissionEventLogEntry');
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

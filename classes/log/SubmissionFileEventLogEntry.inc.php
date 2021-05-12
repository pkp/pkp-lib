<?php

/**
 * @file classes/log/SubmissionFileEventLogEntry.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogEntry
 * @ingroup log
 *
 * @see SubmissionFileEventLogDAO
 *
 * @brief Describes an entry in the submission file history log.
 */

namespace PKP\log;

class SubmissionFileEventLogEntry extends EventLogEntry
{
    // File upload/delete event types.
    public const SUBMISSION_LOG_FILE_UPLOAD = 0x50000001;
    public const SUBMISSION_LOG_FILE_DELETE = 0x50000002;
    public const SUBMISSION_LOG_FILE_REVISION_UPLOAD = 0x50000008;
    public const SUBMISSION_LOG_FILE_EDIT = 0x50000010;

    // Audit events
    public const SUBMISSION_LOG_FILE_AUDITOR_ASSIGN = 0x50000004;
    public const SUBMISSION_LOG_FILE_AUDITOR_CLEAR = 0x50000005;
    public const SUBMISSION_LOG_FILE_AUDIT_UPLOAD = 0x50000006;
    public const SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF = 0x50000007;

    // Deprecated events. Preserve for historical logs
    public const SUBMISSION_LOG_FILE_REVISION_DELETE = 0x50000009; // uses submission.event.revisionDeleted
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\log\SubmissionFileEventLogEntry', '\SubmissionFileEventLogEntry');
    foreach ([
        'SUBMISSION_LOG_FILE_UPLOAD',
        'SUBMISSION_LOG_FILE_DELETE',
        'SUBMISSION_LOG_FILE_REVISION_UPLOAD',
        'SUBMISSION_LOG_FILE_EDIT',
        'SUBMISSION_LOG_FILE_AUDITOR_ASSIGN',
        'SUBMISSION_LOG_FILE_AUDITOR_CLEAR',
        'SUBMISSION_LOG_FILE_AUDIT_UPLOAD',
        'SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF',
        'SUBMISSION_LOG_FILE_REVISION_DELETE',
    ] as $constantName) {
        define($constantName, constant('\SubmissionFileEventLogEntry::' . $constantName));
    }
}

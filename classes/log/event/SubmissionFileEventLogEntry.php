<?php

/**
 * @file classes/log/event/SubmissionFileEventLogEntry.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogEntry
 *
 * @brief Describes an entry in the submission file history log.
 */

namespace PKP\log\event;

class SubmissionFileEventLogEntry extends EventLogEntry
{
    // File upload/delete event types.
    public const SUBMISSION_LOG_FILE_UPLOAD = 1342177281; // 0x50000001
    public const SUBMISSION_LOG_FILE_DELETE = 1342177282; // 0x50000002
    public const SUBMISSION_LOG_FILE_REVISION_UPLOAD = 1342177288; // 0x50000008
    public const SUBMISSION_LOG_FILE_EDIT = 1342177296; // 0x50000010

    public const SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF = 1342177287; // 0x50000007

    // Deprecated events. Preserve for historical logs
    /*
    public const SUBMISSION_LOG_FILE_AUDITOR_ASSIGN = 1342177284; // 0x50000004
    public const SUBMISSION_LOG_FILE_AUDITOR_CLEAR = 1342177285; // 0x50000005
    public const SUBMISSION_LOG_FILE_AUDIT_UPLOAD = 1342177286; // 0x50000006
    public const SUBMISSION_LOG_FILE_REVISION_DELETE = 1342177289; // 0x50000009
    */
}

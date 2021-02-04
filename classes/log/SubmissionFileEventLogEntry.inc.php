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
 * @see SubmissionFileEventLogDAO
 *
 * @brief Describes an entry in the submission file history log.
 */

import('lib.pkp.classes.log.EventLogEntry');


// File upload/delete event types.
define('SUBMISSION_LOG_FILE_UPLOAD',	0x50000001);
define('SUBMISSION_LOG_FILE_DELETE',	0x50000002);
define('SUBMISSION_LOG_FILE_REVISION_UPLOAD',	0x50000008);
define('SUBMISSION_LOG_FILE_EDIT',	0x50000010);

// Audit events
define('SUBMISSION_LOG_FILE_AUDITOR_ASSIGN',		0x50000004);
define('SUBMISSION_LOG_FILE_AUDITOR_CLEAR',		0x50000005);
define('SUBMISSION_LOG_FILE_AUDIT_UPLOAD', 		0x50000006);
define('SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF', 	0x50000007);

// Deprecated events. Preserve for historical logs
define('SUBMISSION_LOG_FILE_REVISION_DELETE',	0x50000009); // uses submission.event.revisionDeleted

class SubmissionFileEventLogEntry extends EventLogEntry {
}



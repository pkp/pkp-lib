<?php

/**
 * @file classes/logParser/PKPLaravelLog.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLaravelLog
 *
 * @brief Laravel log parser for Log Viewer whose email previews are safe to display.
 *
 * When mail is sent to the log ([general] sandbox, or [email] default = log), the vendor parser
 * extracts each email and the viewer renders its HTML part in an iframe without a sandbox
 * attribute, so any script in that HTML would run in the site's origin with the administrator's
 * session. The HTML part is therefore passed through the site's HTML purifier first.
 */

namespace PKP\logParser;

use Opcodes\LogViewer\Logs\LaravelLog;
use PKP\core\PKPString;

class PKPLaravelLog extends LaravelLog
{
    /**
     * @copydoc \Opcodes\LogViewer\Logs\LaravelLog::extractMailPreview()
     */
    protected function extractMailPreview(string $originalText): void
    {
        parent::extractMailPreview($originalText);

        if (isset($this->extra['mail_preview']['html'])) {
            $this->extra['mail_preview']['html'] = PKPString::stripUnsafeHtml($this->extra['mail_preview']['html']);
        }
    }
}

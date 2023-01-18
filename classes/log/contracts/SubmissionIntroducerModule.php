<?php

/**
 * @file classes/log/contracts/SubmissionIntroducerEventEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

namespace PKP\log\contracts;
use APP\core\Application;
use PKP\site\Version;
use PKP\plugins\Plugin;

class SubmissionIntroducerModule
{
    public bool $isPlugin = false;
    public ?string $moduleName = null;
    public ?Version $moduleVersion = null;

    public ?string $moduleClass = null;

    private string $uknownSubmissionIntroducerName = 'Unknown Submission Introducer Module';

    public function __construct(?iSubmissionIntroducer $introducerModule) {
        if (is_null($introducerModule)) {
            $this->moduleName = $this->uknownSubmissionIntroducerName;
            $this->moduleClass = '';
        }
        
        if ($introducerModule instanceof Plugin) {
            $this->isPlugin = true;
            $this->moduleName = $introducerModule->getName();
            $this->moduleVersion = $introducerModule->getCurrentVersion();
        } else if ($introducerModule instanceof Application) {
            $this->isPlugin = false;
            $this->moduleName = $introducerModule->getName();
            $this->moduleVersion = $introducerModule->getCurrentVersion();
        } else {
            $this->moduleName = $this->uknownSubmissionIntroducerName;
        }

        $this->moduleClass = get_class($introducerModule);
    }
}

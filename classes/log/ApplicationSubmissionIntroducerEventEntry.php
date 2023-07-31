<?php

/**
 * @file classes/log/ApplicationSubmissionIntroducerEventEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApplicationSubmissionIntroducerEventEntry
 * @ingroup log
 *
 * @brief Submission Introducer Event Entry for Application.
 */

namespace PKP\log;
use APP\core\Application;
use PKP\log\contracts\SubmissionIntroducerEventEntry;

class ApplicationSubmissionIntroducerEventEntry extends SubmissionIntroducerEventEntry
{
    public function __construct() {
        $this->addParam("ModuleName", Application::getName());
        $this->addParam("IsModulePlugin", 0);
        $this->addParam("IntroducerClass", get_class($this));
        $this->addParam("ModuleVersion.Major", Application::get()->getCurrentVersion()->getMajor());
        $this->addParam("ModuleVersion.Minor", Application::get()->getCurrentVersion()->getMinor());
        $this->addParam("ModuleVersion.Revision", Application::get()->getCurrentVersion()->getRevision());
        $this->addParam("ModuleVersion.Current", Application::get()->getCurrentVersion()->getCurrent());
    }
}

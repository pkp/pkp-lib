<?php

/**
 * @file plugins/importexport/native/NativeImportExportSubmissionIntroducerEventEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeImportExportSubmissionIntroducerEventEntry
 * @ingroup plugins_importexport_native
 *
 * @brief Submission Introducer Event Entry class for NativeImportExport plugin.
 */

namespace PKP\plugins\importexport\native;

use PKP\log\contracts\SubmissionIntroducerEventEntry;
use PKP\plugins\importexport\native\PKPNativeImportExportPlugin;

class NativeImportExportSubmissionIntroducerEventEntry extends SubmissionIntroducerEventEntry
{
    public function __construct(PKPNativeImportExportPlugin $plugin) {
        $this->addParam("ModuleName", $plugin->getName());
        $this->addParam("IsModulePlugin", 1);
        $this->addParam("IntroducerClass", get_class($this));

        $version = $plugin->getCurrentVersion();
        if (!is_null($version)) {
            $this->addParam("ModuleVersion.Major", $plugin->getCurrentVersion()->getMajor());
            $this->addParam("ModuleVersion.Minor", $plugin->getCurrentVersion()->getMinor());
            $this->addParam("ModuleVersion.Revision", $plugin->getCurrentVersion()->getRevision());
            $this->addParam("ModuleVersion.Current", $plugin->getCurrentVersion()->getCurrent());
        }
    }
}

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

use Exception;

class SubmissionIntroducerEventEntry
{
    private ?SubmissionIntroducerModule $introducerModule = null;
    private string $introducerClass;

    private array $params;

    public function __construct(?iSubmissionIntroducer $introducerModule) {
        $this->introducerModule = new SubmissionIntroducerModule($introducerModule);

        $this->introducerClass = get_class($introducerModule);
    }

    public function addParam(string $paramKey, string $paramValue): void {
        $this->params[$paramKey] = $paramValue;
    }

    public function getParams() : array {
        if (is_null($this->introducerModule)) {
            throw new Exception("Introducer Module is mandatory for a SubmissionIntroducerEventEntry:: " + get_class($this));
        }

        $this->addParam("ModuleName", $this->introducerModule->moduleName);

        if (!is_null($this->introducerModule->moduleVersion)) {
            $this->addParam("ModuleVersion.Major", $this->introducerModule->moduleVersion->getMajor());
            $this->addParam("ModuleVersion.Minor", $this->introducerModule->moduleVersion->getMinor());
            $this->addParam("ModuleVersion.Revision", $this->introducerModule->moduleVersion->getRevision());
            $this->addParam("ModuleVersion.Current", $this->introducerModule->moduleVersion->getCurrent());
        }
        
        $this->addParam("IsModulePlugin", $this->introducerModule->isPlugin ? 1 : 0);
        $this->addParam("IntroducerClass", $this->introducerClass);

        return $this->params;
    }
}

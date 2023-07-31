<?php

/**
 * @file classes/log/contracts/SubmissionIntroducerEventEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionIntroducerEventEntry
 * @ingroup log_contracts
 *
 * @brief Parent class for all Submission Introducer Event Entry classes.
 */

namespace PKP\log\contracts;

class SubmissionIntroducerEventEntry
{
    protected array $params;

    public function addParam(string $paramKey, string $paramValue): void {
        $this->params[$paramKey] = $paramValue;
    }

    public function getParams() : array {
        return $this->params;
    }
}

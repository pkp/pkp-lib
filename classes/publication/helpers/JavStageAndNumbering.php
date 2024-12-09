<?php

/**
 * @file classes/publication/JavStageAndNumbering.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JavStageAndNumbering
 *
 * @brief Base class for JavStageAndNumbering.
 */

namespace PKP\publication\helpers;

use PKP\publication\enums\JavStage;

class JavStageAndNumbering extends \PKP\core\DataObject
{
    public const JAV_DEFAULT_NUMBERING_MINOR = 0;
    public const JAV_DEFAULT_NUMBERING_MAJOR = 1;

    public JavStage $javStage;
    public int $javVersionMajor;
    public int $javVersionMinor;

    public function getVersionStageDisplay(): string 
    {
        $versionStageValue = $this->javStage->value;

        return "$versionStageValue $this->javVersionMajor.$this->javVersionMinor";
    }
}


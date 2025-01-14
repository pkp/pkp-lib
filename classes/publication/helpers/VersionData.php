<?php

/**
 * @file classes/publication/VersionData.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionData
 *
 * @brief Base class for VersionData. Incorpoprates the underlying data for defining a publication version
 */

namespace PKP\publication\helpers;

use PKP\publication\enums\VersionStage;

class VersionData extends \PKP\core\DataObject
{
    public const DEFAULT_MINOR_NUMBERING = 0;
    public const DEFAULT_MAJOR_NUMBERING = 1;

    public VersionStage $stage;
    public int $majorNumbering;
    public int $minorNumbering;

    public function getVersionStageDisplay(): string 
    {
        $versionStageLabel = $this->stage->label();

        return __('publication.versionStage.display', [
            'stage' => $versionStageLabel,
            'majorNumbering' => $this->majorNumbering,
            'minorNumbering' => $this->minorNumbering,
        ]);
    }
}


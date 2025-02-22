<?php

/**
 * @file classes/publication/helpers/VersionData.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionData
 *
 * @brief The underlying data of a publication's version
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

    public function display(): string 
    {
        $versionStageLabel = $this->stage->label();

        return __('publication.versionStage.display', [
            'stage' => $versionStageLabel,
            'majorNumbering' => $this->majorNumbering,
            'minorNumbering' => $this->minorNumbering,
        ]);
    }

    public static function createDefaultForStage(VersionStage $versionStage): VersionData
    {
        $defaultVersionStage = new VersionData();
        $defaultVersionStage->stage = $versionStage;
        $defaultVersionStage->majorNumbering = VersionData::DEFAULT_MAJOR_NUMBERING;
        $defaultVersionStage->minorNumbering = VersionData::DEFAULT_MINOR_NUMBERING;

        return $defaultVersionStage;
    }
}


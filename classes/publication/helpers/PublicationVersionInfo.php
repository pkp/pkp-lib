<?php

/**
 * @file classes/publication/helpers/PublicationVersionInfo.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationVersionInfo
 *
 * @brief The underlying data of a publication's version
 */

namespace PKP\publication\helpers;

use PKP\publication\enums\VersionStage;
use Stringable;

class PublicationVersionInfo extends \PKP\core\DataObject
    implements Stringable
{
    public const DEFAULT_MINOR_NUMBERING = 0;
    public const DEFAULT_MAJOR_NUMBERING = 1;

    public function __construct(
        public VersionStage $stage,
        public int $majorNumbering = self::DEFAULT_MAJOR_NUMBERING,
        public int $minorNumbering = self::DEFAULT_MINOR_NUMBERING
    ) {
        $this->stage = $stage;
        $this->majorNumbering = $majorNumbering;
        $this->minorNumbering = $minorNumbering;
    }

    public function __toString(): string
    {
        $versionStageLabel = $this->stage->label();

        return __('publication.versionStage.display', [
            'stage' => $versionStageLabel,
            'majorNumbering' => $this->majorNumbering,
            'minorNumbering' => $this->minorNumbering,
        ]);
    }

    /**
     * Serialize the object to a plain array for JSON storage.
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage->value,
            'major' => $this->majorNumbering,
            'minor' => $this->minorNumbering,
        ];
    }

    /**
     * Create a PublicationVersionInfo object from an array. Used to be deserialised from JSON.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            VersionStage::from($data['stage']),
            (int) ($data['major'] ?? self::DEFAULT_MAJOR_NUMBERING),
            (int) ($data['minor'] ?? self::DEFAULT_MINOR_NUMBERING)
        );
    }
}
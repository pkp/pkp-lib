<?php

/**
 * @file classes/bodyText/ConversionException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Represents the error during conversion of article full-text into HTML and PDF
 */

namespace PKP\bodyText;

class ConversionException extends \RuntimeException
{
    public const INVALID_GALLEY_FILE = 'invalidGalleyFile';
    public const IMAGE_NOT_ATTACHED = 'imageNotAttached';
    public const IMAGE_NOT_EXPORTABLE = 'imageNotExportable';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * Get the translation key for a known conversion refusal.
     */
    public function getTranslationKey(): ?string
    {
        return match ($this->reason) {
            self::INVALID_GALLEY_FILE => 'publication.bodyText.error.invalidGalleyFile',
            self::IMAGE_NOT_ATTACHED => 'publication.bodyText.error.imageNotAttached',
            self::IMAGE_NOT_EXPORTABLE => 'publication.bodyText.error.imageNotExportable',
            default => null,
        };
    }
}

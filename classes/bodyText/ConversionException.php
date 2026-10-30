<?php

/**
 * @file classes/bodyText/ConversionException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief An expected body-text conversion refusal, localized by the API controller.
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
}

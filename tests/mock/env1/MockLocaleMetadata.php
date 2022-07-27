<?php

/**
 * @file tests/mock/env1/MockLocaleMetadata.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_mock_env1
 *
 * @brief Mock implementation of the LocaleMetadata class
 */

use PKP\i18n\LocaleMetadata;

class MockLocaleMetadata extends LocaleMetadata
{
    protected bool $isComplete = false;

    public function isComplete(float $minimumThreshold = 0.9, ?string $referenceLocale = null): bool
    {
        return $this->isComplete;
    }

    public static function create(string $locale, bool $isComplete = false): self
    {
        $instance = parent::create($locale);
        $instance->isComplete = $isComplete;
        return $instance;
    }
}

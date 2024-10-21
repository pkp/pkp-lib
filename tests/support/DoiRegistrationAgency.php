<?php

/**
 * @file tests/support/DoiRegistrationAgency.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiRegistrationAgency
 *
 * @brief A mock implementation of IDoiRegistrationAgency
 */

namespace PKP\tests\support;

use stdClass;
use PKP\context\Context;
use APP\plugins\IDoiRegistrationAgency;
use PKP\doi\RegistrationAgencySettings;

class DoiRegistrationAgency implements IDoiRegistrationAgency
{
    public function addAsRegistrationAgencyOption(string $hookName, array $args)
    {
    }

    public function isPluginConfigured(Context $context): bool
    {
        return true;
    }

    public function getRegistrationAgencyName(): string
    {
        return '';
    }

    public function getErrorMessageKey(): ?string
    {
        return null;
    }

    public function getRegisteredMessageKey(): ?string
    {
        return null;
    }

    public function getSettingsObject(): RegistrationAgencySettings
    {
        return new class($this) extends RegistrationAgencySettings
        {
            public function getSchema(): stdClass
            {
                return new stdClass;
            }

            public function getFields(Context $context): array
            {
                return [];
            }
        };
    }

    public function getAllowedDoiTypes(): array
    {
        return [];
    }

    public function exportSubmissions(array $submissions, Context $context): array
    {
        return [];
    }

    public function depositSubmissions(array $submissions, Context $context): array
    {
        return [];
    }

    public function exportIssues(array $issues, Context $context): array
    {
        return [];
    }

    public function depositIssues(array $issues, Context $context): array
    {
        return [];
    }
}

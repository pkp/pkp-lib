<?php

/**
 * @file classes/plugins/interfaces/HasContextNameHelper.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasContextNameHelper
 *
 * @brief Provides an API for easily getting application-dependent context tables and key names
 */

namespace PKP\migration;

use APP\core\Application;

trait HasContextNameHelper
{
    private const CONTEXT_TABLE_KEYS = [
        'ojs2' => 'journal_id',
        'omp' => 'press_id',
        'ops' => 'server_id',
    ];

    private const CONTEXT_TABLE_NAMES = [
        'ojs2' => 'journals',
        'omp' => 'presses',
        'ops' => 'servers',
    ];

    private const CONTEXT_SETTING_TABLE_NAMES = [
        'ojs2' => 'journal_settings',
        'omp' => 'press_settings',
        'ops' => 'server_settings',
    ];

    private ?string $applicationName = null;

    protected function getContextTableKey(): string
    {
        return self::CONTEXT_TABLE_KEYS[$this->getApplicationName()];
    }

    protected function getContextTableName(): string
    {
        return self::CONTEXT_TABLE_NAMES[$this->getApplicationName()];
    }

    protected function getContextSettingsTableName(): string
    {
        return self::CONTEXT_SETTING_TABLE_NAMES[$this->getApplicationName()];
    }

    private function getApplicationName(): string
    {
        if ($this->applicationName === null) {
            $this->applicationName = Application::get()->getName();
        }

        return $this->applicationName;
    }
}

<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_OrphanedMetrics.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_OrphanedMetrics
 *
 * @briefMigrate metrics data from objects that do not exist any more into a temporary table.
 */

namespace APP\migration\upgrade\v3_4_0;

class I6782_OrphanedMetrics extends \PKP\migration\upgrade\v3_4_0\I6782_OrphanedMetrics
{
    private const ASSOC_TYPE_CONTEXT = 0x0000100;

    protected function getMetricType(): string
    {
        return 'ops::counter';
    }

    protected function getContextAssocType(): int
    {
        return self::ASSOC_TYPE_CONTEXT;
    }

    protected function getContextTable(): string
    {
        return 'servers';
    }

    protected function getContextKeyField(): string
    {
        return 'server_id';
    }

    protected function getRepresentationTable(): string
    {
        return 'publication_galleys';
    }

    protected function getRepresentationKeyField(): string
    {
        return 'galley_id';
    }

    protected function getAssocTypesToMigrate(): array
    {
        return array_merge(
            [
                self::ASSOC_TYPE_CONTEXT,
            ],
            parent::getAssocTypesToMigrate()
        );
    }
}

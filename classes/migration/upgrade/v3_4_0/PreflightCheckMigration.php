<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreflightCheckMigration
 *
 * @brief Check for common problems early in the upgrade process.
 */

namespace APP\migration\upgrade\v3_4_0;

class PreflightCheckMigration extends \PKP\migration\upgrade\v3_4_0\PreflightCheckMigration
{
    protected function getContextTable(): string
    {
        return 'journals';
    }

    protected function getContextKeyField(): string
    {
        return 'journal_id';
    }

    protected function getContextSettingsTable(): string
    {
        return 'journal_settings';
    }

    protected function buildOrphanedEntityProcessor(): void
    {
        parent::buildOrphanedEntityProcessor();

        $this->addTableProcessor('publications', function (): int {
            $affectedRows = 0;
            $affectedRows += $this->cleanOptionalReference('publications', 'section_id', 'sections', 'section_id');
            return $affectedRows;
        });

        $this->addTableProcessor('publication_galleys', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: doi_id->dois.doi_id(not found in previous version) publication_id->publications.publication_id submission_file_id->submission_files.submission_file_id
            $affectedRows += $this->deleteRequiredReference('publication_galleys', 'publication_id', 'publications', 'publication_id');
            $affectedRows += $this->deleteOptionalReference('publication_galleys', 'submission_file_id', 'submission_files', 'submission_file_id');
            return $affectedRows;
        });

        $this->addTableProcessor('sections', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: review_form_id->review_forms.review_form_id server_id->servers.server_id(not found in previous version)
            $affectedRows += $this->deleteRequiredReference('sections', $this->getContextKeyField(), $this->getContextTable(), $this->getContextKeyField());
            $affectedRows += $this->cleanOptionalReference('sections', 'review_form_id', 'review_forms', 'review_form_id');
            return $affectedRows;
        });

        $this->addTableProcessor('publication_galley_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: galley_id->publication_galleys.galley_id
            $affectedRows += $this->deleteRequiredReference('publication_galley_settings', 'galley_id', 'publication_galleys', 'galley_id');
            return $affectedRows;
        });

        $this->addTableProcessor('section_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: section_id->sections.section_id
            $affectedRows += $this->deleteRequiredReference('section_settings', 'section_id', 'sections', 'section_id');
            return $affectedRows;
        });

        $this->addTableProcessor('server_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: server_id->servers.server_id(not found in previous version)
            $affectedRows += $this->deleteRequiredReference($this->getContextSettingsTable(), $this->getContextKeyField(), $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });
    }
}

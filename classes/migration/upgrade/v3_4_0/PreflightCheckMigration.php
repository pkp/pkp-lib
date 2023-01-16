<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreflightCheckMigration
 * @brief Check for common problems early in the upgrade process.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;

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

    public function up(): void
    {
        parent::up();
        try {
            // Clean orphaned sections entries by journal_id
            $orphanedIds = DB::table('sections AS s')->leftJoin('journals AS j', 's.journal_id', '=', 'j.journal_id')->whereNull('j.journal_id')->distinct()->pluck('s.journal_id');
            foreach ($orphanedIds as $journalId) {
                DB::table('sections')->where('journal_id', '=', $journalId)->delete();
            }

            // Clean orphaned section_settings entries
            $orphanedIds = DB::table('section_settings AS ss')->leftJoin('sections AS s', 'ss.section_id', '=', 's.section_id')->whereNull('s.section_id')->distinct()->pluck('ss.section_id');
            foreach ($orphanedIds as $sectionId) {
                DB::table('section_settings')->where('section_id', '=', $sectionId)->delete();
            }

            // Clean orphaned publications entries by submission_id
            $orphanedIds = DB::table('publications AS p')->leftJoin('submissions AS s', 's.submission_id', '=', 'p.submission_id')->whereNull('s.submission_id')->distinct()->pluck('p.publication_id');
            foreach ($orphanedIds as $publicationId) {
                DB::table('publication_settings')->where('publication_id', '=', $publicationId)->delete();
                DB::table('publications')->where('publication_id', '=', $publicationId)->delete();
            }

            // Clean orphaned publications entries by primary_contact_id
            switch (true) {
                case DB::connection() instanceof MySqlConnection:
                    DB::statement('UPDATE publications p LEFT JOIN users u ON (p.primary_contact_id = u.user_id) SET p.primary_contact_id = NULL WHERE u.user_id IS NULL');
                    break;
                case DB::connection() instanceof PostgresConnection:
                    DB::statement('UPDATE publications SET primary_contact_id = NULL WHERE publication_id IN (SELECT publication_id FROM publications p LEFT JOIN users u ON (p.primary_contact_id = u.user_id) WHERE u.user_id IS NULL AND p.primary_contact_id IS NOT NULL)');
                    break;
                default: throw new \Exception('Unknown database connection type!');
            }

            // Clean orphaned publication_galleys entries by publication_id
            $orphanedIds = DB::table('publication_galleys AS pg')->leftJoin('publications AS p', 'pg.publication_id', '=', 'p.publication_id')->whereNull('p.publication_id')->distinct()->pluck('pg.publication_id');
            foreach ($orphanedIds as $publicationId) {
                DB::table('publication_galleys')->where('publication_id', '=', $publicationId)->delete();
            }

            // Clean orphaned publication_galley_settings entries
            $orphanedIds = DB::table('publication_galley_settings AS pgs')->leftJoin('publication_galleys AS pg', 'pgs.galley_id', '=', 'pg.galley_id')->whereNull('pg.galley_id')->distinct()->pluck('pgs.galley_id');
            foreach ($orphanedIds as $galleyId) {
                DB::table('publication_galley_settings')->where('galley_id', '=', $galleyId)->delete();
            }

            DB::table('sections')->where('review_form_id', '=', 0)->update(['review_form_id' => null]);
        } catch (\Exception $e) {
            if ($fallbackVersion = $this->setFallbackVersion()) {
                $this->_installer->log("A pre-flight check failed. The software was successfully upgraded to ${fallbackVersion} but could not be upgraded further (to " . $this->_installer->newVersion->getVersionString() . '). Check and correct the error, then try again.');
            }
            throw $e;
        }
    }
}

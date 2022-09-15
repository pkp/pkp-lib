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

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;

use PKP\db\DAORegistry;

abstract class PreflightCheckMigration extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // pkp/pkp-lib#6903 Prepare to add foreign key relationships
            // Clean orphaned assoc_type/assoc_id data in announcement_types
            $orphanedIds = DB::table('announcement_types AS at')->leftJoin($this->getContextTable() . ' AS c', 'at.assoc_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->orWhere('at.assoc_type', '<>', Application::get()->getContextAssocType())->distinct()->pluck('at.type_id');
            foreach ($orphanedIds as $typeId) {
                $this->_installer->log("Removing orphaned announcement type ID ${typeId} with no matching context ID.");
                DB::table('announcement_types')->where('type_id', '=', $typeId)->delete();
            }

            // Clean orphaned announcement_type_setting entries
            $orphanedIds = DB::table('announcement_type_settings AS ats')->leftJoin('announcement_types AS at', 'ats.type_id', '=', 'at.type_id')->whereNull('at.type_id')->distinct()->pluck('ats.type_id');
            foreach ($orphanedIds as $typeId) {
                $this->_installer->log("Removing orphaned settings for missing announcement type ID ${typeId}");
                DB::table('announcement_type_settings')->where('type_id', '=', $typeId)->delete();
            }

            if ($count = DB::table('announcements AS a')->leftJoin('announcement_types AS at', 'a.type_id', '=', 'at.type_id')->whereNull('at.type_id')->whereNotNull('a.type_id')->update(['a.type_id' => null])) {
                $this->_installer->log("Reset ${count} announcements with orphaned (non-null) announcement types to no announcement type.");
            }

            // Clean orphaned announcement_setting entries
            $orphanedIds = DB::table('announcement_settings AS a_s')->leftJoin('announcements AS a', 'a_s.announcement_id', '=', 'a.announcement_id')->whereNull('a.announcement_id')->distinct()->pluck('a_s.announcement_id');
            foreach ($orphanedIds as $announcementId) {
                $this->_installer->log("Removing orphaned settings for missing announcement ID ${announcementId}");
                DB::table('announcement_settings')->where('announcement_id', '=', $announcementId)->delete();
            }

            // Clean orphaned category data
            $orphanedIds = DB::table('categories AS ca')->leftJoin($this->getContextTable() . ' AS c', 'ca.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('ca.category_id');
            foreach ($orphanedIds as $categoryId) {
                $this->_installer->log("Removing orphaned category ID ${categoryId} with no matching context ID.");
                DB::table('categories')->where('category_id', '=', $categoryId)->delete();
            }
            // Clean orphaned category_setting entries
            $orphanedIds = DB::table('category_settings AS cs')->leftJoin('categories AS c', 'cs.category_id', '=', 'c.category_id')->whereNull('c.category_id')->distinct()->pluck('cs.category_id');
            foreach ($orphanedIds as $categoryId) {
                $this->_installer->log("Removing orphaned settings for missing category ID ${categoryId}");
                DB::table('category_settings')->where('category_id', '=', $categoryId)->delete();
            }

            // Clean orphaned publication_categories entries
            $orphanedIds = DB::table('publication_categories AS pc')->leftJoin('categories AS c', 'pc.category_id', '=', 'c.category_id')->whereNull('c.category_id')->distinct()->pluck('pc.category_id');
            foreach ($orphanedIds as $categoryId) {
                $this->_installer->log("Removing orphaned category/publication associations for missing category ID ${categoryId}");
                DB::table('publication_categories')->where('category_id', '=', $categoryId)->delete();
            }
            $orphanedIds = DB::table('publication_categories AS pc')->leftJoin('publications AS p', 'pc.publication_id', '=', 'p.publication_id')->whereNull('p.publication_id')->distinct()->pluck('pc.publication_id');
            foreach ($orphanedIds as $publicationId) {
                $this->_installer->log("Removing orphaned category/publication associations for missing publication ID ${publicationId}");
                DB::table('publication_categories')->where('publication_id', '=', $publicationId)->delete();
            }

            // Clean out orphaned views entries
            DB::table('item_views AS v')->leftJoin('users AS u', 'v.user_id', '=', 'u.user_id')->whereNull('u.user_id')->whereNotNull('v.user_id')->delete();

            // Clean orphaned genre data
            $orphanedIds = DB::table('genres AS g')->leftJoin($this->getContextTable() . ' AS c', 'g.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('g.genre_id');
            foreach ($orphanedIds as $genreId) {
                $this->_installer->log("Removing orphaned genre ID ${genreId} with no matching context ID.");
                DB::table('genres')->where('genre_id', '=', $genreId)->delete();
            }
            // Clean orphaned genre_setting entries
            $orphanedIds = DB::table('genre_settings AS gs')->leftJoin('genres AS g', 'gs.genre_id', '=', 'g.genre_id')->whereNull('g.genre_id')->distinct()->pluck('gs.genre_id');
            foreach ($orphanedIds as $genreId) {
                $this->_installer->log("Removing orphaned settings for missing genre ID ${genreId}");
                DB::table('genre_settings')->where('genre_id', '=', $genreId)->delete();
            }
            // Clean orphan notification_settings
            $orphanedIds = DB::table('notification_settings AS ns')->leftJoin('notifications AS n', 'ns.notification_id', '=', 'n.notification_id')->whereNull('n.notification_id')->distinct()->pluck('ns.notification_id');
            foreach ($orphanedIds as $notificationId) {
                $this->_installer->log("Removing orphaned settings for missing notification ID ${notificationId}");
                DB::table('notification_settings')->where('notification_id', '=', $notificationId)->delete();
            }
            // Clean orphan submission_file_settings
            $orphanedIds = DB::table('submission_file_settings AS sfs')->leftJoin('submission_files AS sf', 'sfs.submission_file_id', '=', 'sf.submission_file_id')->whereNull('sf.submission_file_id')->distinct()->pluck('sfs.submission_file_id');
            foreach ($orphanedIds as $submissionFileId) {
                $this->_installer->log("Removing orphaned settings for missing submission file ID ${submissionFileId}");
                DB::table('submission_file_settings')->where('submission_file_id', '=', $submissionFileId)->delete();
            }
            // Clean orphan query_participants - non existing user
            $orphanedIds = DB::table('query_participants AS qp')->leftJoin('users AS u', 'qp.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('qp.user_id');
            foreach ($orphanedIds as $userId) {
                $this->_installer->log("Removing orphaned query_participants for missing user ID ${userId}");
                DB::table('query_participants')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphan query_participants - non existing query
            $orphanedIds = DB::table('query_participants AS qp')->leftJoin('queries AS q', 'qp.query_id', '=', 'q.query_id')->whereNull('q.query_id')->distinct()->pluck('qp.query_id');
            foreach ($orphanedIds as $queryId) {
                $this->_installer->log("Removing orphaned query_participants for missing query ID ${queryId}");
                DB::table('query_participants')->where('query_id', '=', $queryId)->delete();
            }
            // Clean orphaned controlled_vocab_entry entries
            $orphanedIds = DB::table('controlled_vocab_entries AS cve')->leftJoin('controlled_vocabs AS cv', 'cve.controlled_vocab_id', '=', 'cv.controlled_vocab_id')->whereNull('cv.controlled_vocab_id')->distinct()->pluck('cve.controlled_vocab_id');
            foreach ($orphanedIds as $controlledVocabId) {
                $this->_installer->log("Removing orphaned controlled_vocab_entries for missing controlled_vocab_id ${controlledVocabId}");
                DB::table('controlled_vocab_entries')->where('controlled_vocab_id', '=', $controlledVocabId)->delete();
            }
            // Clean orphaned controlled_vocab_entry_settings entries
            $orphanedIds = DB::table('controlled_vocab_entry_settings AS cves')->leftJoin('controlled_vocab_entries AS cve', 'cves.controlled_vocab_entry_id', '=', 'cve.controlled_vocab_entry_id')->whereNull('cve.controlled_vocab_entry_id')->distinct()->pluck('cves.controlled_vocab_entry_id');
            foreach ($orphanedIds as $controlledVocabEntryId) {
                $this->_installer->log("Removing orphaned controlled_vocab_entry_settings for missing controlled_vocab_entry_id ${controlledVocabEntryId}");
                DB::table('controlled_vocab_entry_settings')->where('controlled_vocab_entry_id', '=', $controlledVocabEntryId)->delete();
            }
            // Clean orphaned user_interests entries by user ID
            $orphanedIds = DB::table('user_interests AS ui')->leftJoin('users AS u', 'ui.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('ui.user_id');
            foreach ($orphanedIds as $userId) {
                $this->_installer->log("Removing orphaned user_interests for missing user_id ${userId}");
                DB::table('user_interests')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned user_interests entries by controlled_vocab_entry_id
            $orphanedIds = DB::table('user_interests AS ui')->leftJoin('controlled_vocab_entries AS cve', 'ui.controlled_vocab_entry_id', '=', 'cve.controlled_vocab_entry_id')->whereNull('ui.controlled_vocab_entry_id')->distinct()->pluck('ui.controlled_vocab_entry_id');
            foreach ($orphanedIds as $controlledVocabEntryId) {
                $this->_installer->log("Removing orphaned user_interests for missing controlled_vocab_entry_id ${controlledVocabEntryId}");
                DB::table('user_interests')->where('controlled_vocab_entry_id', '=', $controlledVocabEntryId)->delete();
            }
            // Clean orphaned user_setting entries
            $orphanedIds = DB::table('user_settings AS us')->leftJoin('users AS u', 'us.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('us.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('user_settings')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned sessions entries by user_id
            $orphanedIds = DB::table('sessions AS s')->leftJoin('users AS u', 's.user_id', '=', 'u.user_id')->whereNull('u.user_id')->whereNotNull('s.user_id')->distinct()->pluck('s.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('sessions')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned email_template entries by context_id
            $orphanedIds = DB::table('email_templates AS et')->leftJoin($this->getContextTable() . ' AS c', 'et.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('et.context_id');
            foreach ($orphanedIds as $contextId) {
                $this->_installer->log("Removing orphaned email_templates for missing context_id ${contextId}");
                DB::table('email_templates')->where('context_id', '=', $contextId)->delete();
            }
            // Clean orphaned email_templates_settings entries by email_id
            $orphanedIds = DB::table('email_templates_settings AS ets')->leftJoin('email_templates AS et', 'et.email_id', '=', 'ets.email_id')->whereNull('et.email_id')->distinct()->pluck('ets.email_id');
            foreach ($orphanedIds as $emailId) {
                $this->_installer->log("Removing orphaned email_templates_settings for missing email_id ${emailId}");
                DB::table('email_templates_settings')->where('email_id', '=', $emailId)->delete();
            }
        } catch (\Exception $e) {
            if ($fallbackVersion = $this->setFallbackVersion()) {
                $this->_installer->log("A pre-flight check failed. The software was successfully upgraded to ${fallbackVersion} but could not be upgraded further (to " . $this->_installer->newVersion->getVersionString() . '). Check and correct the error, then try again.');
            }
            throw $e;
        }
    }

    /**
     * Rollback the migrations.
     */
    public function down(): void
    {
        if ($fallbackVersion = $this->setFallbackVersion()) {
            $this->_installer->log("An upgrade step failed! Fallback set to ${fallbackVersion}. Check and correct the error and try the upgrade again. We recommend restoring from backup, though you may be able to continue without doing so.");
            // Prevent further downgrade migrations from executing.
            $this->_installer->migrations = [];
        }
    }

    /**
     * Store the fallback version in the database, permitting resumption of partial upgrades.
     *
     * @return ?string Fallback version, if one was identified
     */
    protected function setFallbackVersion(): ?string
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            return $fallbackVersion;
        }
        return null;
    }
}

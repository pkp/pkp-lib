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
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\db\DAORegistry;
use Throwable;

abstract class PreflightCheckMigration extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {

            // pkp/pkp-lib#8183 check to see if all contexts' contact name/email are set
            $this->checkContactSetting();

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
            // Clean orphaned library_files entries by context_id
            $orphanedIds = DB::table('library_files AS lf')->leftJoin($this->getContextTable() . ' AS c', 'lf.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('lf.context_id');
            foreach ($orphanedIds as $contextId) {
                $this->_installer->log("Removing orphaned library_files for missing context_id ${contextId}");
                DB::table('library_files')->where('context_id', '=', $contextId)->delete();
            }
            // Clean orphaned library_files entries by submission_id
            $orphanedIds = DB::table('library_files AS lf')->leftJoin('submissions AS s', 's.submission_id', '=', 'lf.submission_id')->whereNull('s.submission_id')->distinct()->pluck('lf.submission_id');
            foreach ($orphanedIds as $submissionId) {
                $this->_installer->log("Removing orphaned library_files for missing submission_id ${submissionId}");
                DB::table('library_files')->where('submission_id', '=', $submissionId)->delete();
            }
            // Clean orphaned library_file_setting entries
            $orphanedIds = DB::table('library_file_settings AS lfs')->leftJoin('library_files AS lf', 'lfs.file_id', '=', 'lf.file_id')->whereNull('lf.file_id')->distinct()->pluck('lfs.file_id');
            foreach ($orphanedIds as $fileId) {
                $this->_installer->log("Removing orphaned settings for missing library_file ${fileId}");
                DB::table('library_file_settings')->where('file_id', '=', $fileId)->delete();
            }
            // Clean orphaned event_log entries by user_id
            $orphanedIds = DB::table('event_log AS el')->leftJoin('users AS u', 'el.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('el.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('event_log')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned event_log_settings entries
            $orphanedIds = DB::table('event_log_settings AS els')->leftJoin('event_log AS el', 'els.log_id', '=', 'el.log_id')->whereNull('el.log_id')->distinct()->pluck('els.log_id');
            foreach ($orphanedIds as $logId) {
                DB::table('event_log_settings')->where('log_id', '=', $logId)->delete();
            }
            // Clean orphaned email_log_users entries by user_id
            $orphanedIds = DB::table('email_log_users AS elu')->leftJoin('users AS u', 'elu.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('elu.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('email_log_users')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned email_log_users entries by email_log_id
            $orphanedIds = DB::table('email_log_users AS elu')->leftJoin('email_log AS el', 'el.log_id', '=', 'elu.email_log_id')->whereNull('el.log_id')->distinct()->pluck('elu.email_log_id');
            foreach ($orphanedIds as $logId) {
                DB::table('email_log_users')->where('email_log_id', '=', $logId)->delete();
            }
            // Clean orphaned citations entries by publication_id
            $orphanedIds = DB::table('citations AS c')->leftJoin('publications AS p', 'c.publication_id', '=', 'p.publication_id')->whereNull('p.publication_id')->distinct()->pluck('c.publication_id');
            foreach ($orphanedIds as $publicationId) {
                DB::table('citations')->where('publication_id', '=', $publicationId)->delete();
            }
            // Clean orphaned citation_settings entries
            $orphanedIds = DB::table('citation_settings AS cs')->leftJoin('citations AS c', 'cs.citation_id', '=', 'c.citation_id')->whereNull('c.citation_id')->distinct()->pluck('cs.citation_id');
            foreach ($orphanedIds as $citationId) {
                DB::table('citation_settings')->where('citation_id', '=', $citationId)->delete();
            }
            // Clean orphaned filters entries by filter_group_id
            $orphanedIds = DB::table('filters AS f')->leftJoin('filter_groups AS fg', 'f.filter_group_id', '=', 'fg.filter_group_id')->whereNull('fg.filter_group_id')->distinct()->pluck('f.filter_group_id');
            foreach ($orphanedIds as $filterGroupId) {
                $this->_installer->log("Removing orphaned filters for missing filter_group ${filterGroupId}");
                DB::table('filters')->where('filter_group_id', '=', $filterGroupId)->delete();
            }
            // Clean orphaned filter_settings entries
            $orphanedIds = DB::table('filter_settings AS fs')->leftJoin('filters AS f', 'fs.filter_id', '=', 'f.filter_id')->whereNull('f.filter_id')->distinct()->pluck('fs.filter_id');
            foreach ($orphanedIds as $filterId) {
                DB::table('filter_settings')->where('filter_id', '=', $filterId)->delete();
            }
            // Clean orphaned temporary_files entries by user_id
            $orphanedIds = DB::table('temporary_files AS tf')->leftJoin('users AS u', 'tf.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('tf.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('temporary_files')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned notes entries by user_id
            $orphanedIds = DB::table('notes AS n')->leftJoin('users AS u', 'n.user_id', '=', 'u.user_id')->whereNull('u.user_id')->distinct()->pluck('n.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('notes')->where('user_id', '=', $userId)->delete();
            }
            // Clean orphaned navigation_menu_item_settings entries
            $orphanedIds = DB::table('navigation_menu_item_settings AS nmis')->leftJoin('navigation_menu_items AS nmi', 'nmis.navigation_menu_item_id', '=', 'nmi.navigation_menu_item_id')->whereNull('nmi.navigation_menu_item_id')->distinct()->pluck('nmis.navigation_menu_item_id');
            foreach ($orphanedIds as $navigationMenuItemId) {
                DB::table('navigation_menu_item_settings')->where('navigation_menu_item_id', '=', $navigationMenuItemId)->delete();
            }
            // Clean orphaned navigation_menu_item_assignments by navigation_menu_item_id
            $orphanedIds = DB::table('navigation_menu_item_assignments AS nmia')->leftJoin('navigation_menu_items AS nmi', 'nmia.navigation_menu_item_id', '=', 'nmi.navigation_menu_item_id')->whereNull('nmi.navigation_menu_item_id')->distinct()->pluck('nmia.navigation_menu_item_id');
            foreach ($orphanedIds as $navigationMenuItemId) {
                DB::table('navigation_menu_item_assignments')->where('navigation_menu_item_id', '=', $navigationMenuItemId)->delete();
            }
            // Clean orphaned navigation_menu_item_assignments by navigation_menu_id
            $orphanedIds = DB::table('navigation_menu_item_assignments AS nmia')->leftJoin('navigation_menus AS nm', 'nmia.navigation_menu_id', '=', 'nm.navigation_menu_id')->whereNull('nm.navigation_menu_id')->distinct()->pluck('nmia.navigation_menu_id');
            foreach ($orphanedIds as $navigationMenuId) {
                DB::table('navigation_menu_item_assignments')->where('navigation_menu_id', '=', $navigationMenuId)->delete();
            }
            // Clean orphaned navigation_menu_item_assignment_settings entries
            $orphanedIds = DB::table('navigation_menu_item_assignment_settings AS nmias')->leftJoin('navigation_menu_item_assignments AS nmia', 'nmias.navigation_menu_item_assignment_id', '=', 'nmia.navigation_menu_item_assignment_id')->whereNull('nmia.navigation_menu_item_assignment_id')->distinct()->pluck('nmias.navigation_menu_item_assignment_id');
            foreach ($orphanedIds as $navigationMenuItemAssignmentId) {
                DB::table('navigation_menu_item_assignment_settings')->where('navigation_menu_item_assignment_id', '=', $navigationMenuItemAssignmentId)->delete();
            }
            if (Schema::hasTable('review_form_settings')) {
                // Clean orphaned review_form_settings entries
                $orphanedIds = DB::table('review_form_settings AS rfs')->leftJoin('review_forms AS rf', 'rf.review_form_id', '=', 'rfs.review_form_id')->whereNull('rf.review_form_id')->distinct()->pluck('rfs.review_form_id');
                foreach ($orphanedIds as $reviewFormId) {
                    DB::table('review_form_settings')->where('review_form_id', '=', $reviewFormId)->delete();
                }
            }

            if (Schema::hasTable('review_form_elements')) {
                // Clean orphaned review_form_elements entries
                $orphanedIds = DB::table('review_form_elements AS rfe')->leftJoin('review_forms AS rf', 'rf.review_form_id', '=', 'rfe.review_form_id')->whereNull('rf.review_form_id')->distinct()->pluck('rfe.review_form_id');
                foreach ($orphanedIds as $reviewFormId) {
                    DB::table('review_form_elements')->where('review_form_id', '=', $reviewFormId)->delete();
                }
                // Clean orphaned review_form_element_settings entries
                $orphanedIds = DB::table('review_form_element_settings AS rfes')->leftJoin('review_form_elements AS rfe', 'rfes.review_form_element_id', '=', 'rfe.review_form_element_id')->whereNull('rfe.review_form_element_id')->distinct()->pluck('rfes.review_form_element_id');
                foreach ($orphanedIds as $reviewFormElementId) {
                    DB::table('review_form_element_settings')->where('review_form_element_id', '=', $reviewFormElementId)->delete();
                }
                // Clean orphaned review_form_responses entries by review_form_element_id
                $orphanedIds = DB::table('review_form_responses AS rfr')->leftJoin('review_form_elements AS rfe', 'rfe.review_form_element_id', '=', 'rfr.review_form_element_id')->whereNull('rfe.review_form_element_id')->distinct()->pluck('rfr.review_form_element_id');
                foreach ($orphanedIds as $reviewFormElementId) {
                    $this->_installer->log("Removing orphaned review_form_responses for missing review_form_element_id ${reviewFormElementId}");
                    DB::table('review_form_responses')->where('review_form_element_id', '=', $reviewFormElementId)->delete();
                }
                // Clean orphaned review_form_responses entries by review_id
                $orphanedIds = DB::table('review_form_responses AS rfr')->leftJoin('review_assignments AS ra', 'rfr.review_id', '=', 'ra.review_id')->whereNull('ra.review_id')->distinct()->pluck('rfr.review_id');
                foreach ($orphanedIds as $reviewId) {
                    $this->_installer->log("Removing orphaned review_form_responses for missing review_id ${reviewId}");
                    DB::table('review_form_responses')->where('review_id', '=', $reviewId)->delete();
                }
            }
            // Clean orphaned submissions by context_id
            $orphanedIds = DB::table('submissions AS s')->leftJoin($this->getContextTable() . ' AS c', 's.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('s.submission_id', 's.context_id');
            foreach ($orphanedIds as $contextId => $submissionId) {
                $this->_installer->log("Removing orphaned submission ID ${submissionId} with nonexistent context ID ${contextId}.");
                DB::table('submissions')->where('submission_id', '=', $submissionId)->delete();
            }
            // Clean orphaned submission_settings entries
            $orphanedIds = DB::table('submission_settings AS ss')->leftJoin('submissions AS s', 'ss.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->distinct()->pluck('ss.submission_id');
            foreach ($orphanedIds as $submissionId) {
                DB::table('submission_settings')->where('submission_id', '=', $submissionId)->delete();
            }
            // Clean orphaned publication_settings entries
            $orphanedIds = DB::table('publication_settings AS ps')->leftJoin('publications AS p', 'ps.publication_id', '=', 'p.publication_id')->whereNull('p.publication_id')->distinct()->pluck('ps.publication_id');
            foreach ($orphanedIds as $publicationId) {
                DB::table('publication_settings')->where('publication_id', '=', $publicationId)->delete();
            }
            // Clean orphaned authors entries by publication_id
            $orphanedIds = DB::table('authors AS a')->leftJoin('publications AS p', 'a.publication_id', '=', 'p.publication_id')->whereNull('p.publication_id')->distinct()->pluck('a.publication_id');
            foreach ($orphanedIds as $publicationId) {
                DB::table('authors')->where('publication_id', '=', $publicationId)->delete();
            }

            // Flag orphaned authors entries by user_group_id
            $result = DB::table('authors AS a')->leftJoin('user_groups AS ug', 'ug.user_group_id', '=', 'a.user_group_id')->leftJoin('publications AS p', 'p.publication_id', '=', 'a.publication_id')->whereNull('ug.user_group_id')->distinct()->select('a.author_id AS author_id, a.publication_id AS publication_id, a.user_group_id AS user_group_id, p.submission_id AS submission_id')->get();
            foreach ($result as $row) {
                $this->_installer->log("Found an orphaned authors entry with author_id {$row->author_id} for publication_id {$row->publication_id} with submission_id {$row->submission_id} and user_group_id {$row->user_group_id}.");
            }
            if ($result->count()) {
                throw new Exception('There are author records without matching user_group entries. Please correct these before upgrading.');
            }

            // Clean orphaned publication_settings entries
            $orphanedIds = DB::table('author_settings AS a_s')->leftJoin('authors AS a', 'a_s.author_id', '=', 'a.author_id')->whereNull('a.author_id')->distinct()->pluck('a_s.author_id');
            foreach ($orphanedIds as $authorId) {
                DB::table('author_settings')->where('author_id', '=', $authorId)->delete();
            }
            // Clean orphaned edit_decisions entries by editor_id
            $orphanedIds = DB::table('edit_decisions AS ed')->leftJoin('users AS u', 'u.user_id', '=', 'ed.editor_id')->whereNull('u.user_id')->distinct()->pluck('ed.editor_id');
            foreach ($orphanedIds as $editorId) {
                $this->_installer->log("Removing orphaned edit_decisions entry for missing editor_id ${editorId}");
                DB::table('edit_decisions')->where('editor_id', '=', $editorId)->delete();
            }
            // Clean orphaned submission_comments entries by submission_id
            $orphanedIds = DB::table('submission_comments AS sc')->leftJoin('submissions AS s', 's.submission_id', '=', 'sc.submission_id')->whereNull('s.submission_id')->distinct()->pluck('sc.submission_id');
            foreach ($orphanedIds as $submissionId) {
                $this->_installer->log("Removing orphaned submission_comments entry for missing submission_id ${submissionId}");
                DB::table('submission_comments')->where('submission_id', '=', $submissionId)->delete();
            }

            // Clean orphaned submission_comments entries by author_id
            $orphanedIds = DB::table('submission_comments AS sc')->leftJoin('users AS u', 'u.user_id', '=', 'sc.author_id')->whereNull('u.user_id')->distinct()->pluck('sc.author_id');
            foreach ($orphanedIds as $userId) {
                $this->_installer->log("Removing orphaned submission_comments entry for missing author_id ${userId}");
                DB::table('submission_comments')->where('author_id', '=', $userId)->delete();
            }

            // Clean orphaned subeditor_submission_group entries by context_id
            $orphanedIds = DB::table('subeditor_submission_group AS ssg')->leftJoin($this->getContextTable() . ' AS c', 'ssg.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('ssg.context_id');
            foreach ($orphanedIds as $contextId) {
                DB::table('subeditor_submission_group')->where('context_id', '=', $contextId)->delete();
            }

            // Clean orphaned subeditor_submission_group entries by user_id
            $orphanedIds = DB::table('subeditor_submission_group AS ssg')->leftJoin('users AS u', 'u.user_id', '=', 'ssg.user_id')->whereNull('u.user_id')->distinct()->pluck('ssg.user_id');
            foreach ($orphanedIds as $userId) {
                $this->_installer->log("Removing orphaned subeditor_submission_group entry for missing user_id ${userId}");
                DB::table('subeditor_submission_group')->where('user_id', '=', $userId)->delete();
            }

            // Clean orphaned submission_search_objects entries by submission_id
            $orphanedIds = DB::table('submission_search_objects AS sso')->leftJoin('submissions AS s', 's.submission_id', '=', 'sso.submission_id')->whereNull('s.submission_id')->distinct()->pluck('sso.submission_id');
            foreach ($orphanedIds as $submissionId) {
                DB::table('submission_search_objects')->where('submission_id', '=', $submissionId)->delete();
            }

            // Clean orphaned submission_search_object_keywords entries by object_id
            $orphanedIds = DB::table('submission_search_object_keywords AS ssok')->leftJoin('submission_search_objects AS sso', 'ssok.object_id', '=', 'sso.object_id')->whereNull('sso.object_id')->distinct()->pluck('ssok.object_id');
            foreach ($orphanedIds as $objectId) {
                DB::table('submission_search_object_keywords')->where('object_id', '=', $objectId)->delete();
            }

            // Clean orphaned submission_search_object_keywords entries by keyword_id
            $orphanedIds = DB::table('submission_search_object_keywords AS ssok')->leftJoin('submission_search_keyword_list AS sskl', 'ssok.keyword_id', '=', 'sskl.keyword_id')->whereNull('sskl.keyword_id')->distinct()->pluck('ssok.keyword_id');
            foreach ($orphanedIds as $keywordId) {
                DB::table('submission_search_object_keywords')->where('keyword_id', '=', $keywordId)->delete();
            }

            // Clean orphaned review_round_files entries by submission_id
            $orphanedIds = DB::table('review_round_files AS rrf')->leftJoin('submissions AS s', 's.submission_id', '=', 'rrf.submission_id')->whereNull('s.submission_id')->distinct()->pluck('rrf.submission_id');
            foreach ($orphanedIds as $submissionId) {
                DB::table('review_round_files')->where('submission_id', '=', $submissionId)->delete();
            }

            // Clean orphaned user_user_groups entries by user_id
            $orphanedIds = DB::table('user_user_groups AS uug')->leftJoin('users AS u', 'u.user_id', '=', 'uug.user_id')->whereNull('u.user_id')->distinct()->pluck('uug.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('user_user_groups')->where('user_id', '=', $userId)->delete();
            }

            // Clean orphaned user_group_stage entries by context_id
            $orphanedIds = DB::table('user_group_stage AS ugs')->leftJoin($this->getContextTable() . ' AS c', 'ugs.context_id', '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('ugs.context_id');
            foreach ($orphanedIds as $contextId) {
                DB::table('subeditor_submission_group')->where('context_id', '=', $contextId)->delete();
            }

            // Clean orphaned stage_assignments entries by user_id
            $orphanedIds = DB::table('stage_assignments AS sa')->leftJoin('users AS u', 'u.user_id', '=', 'sa.user_id')->whereNull('u.user_id')->distinct()->pluck('sa.user_id');
            foreach ($orphanedIds as $userId) {
                DB::table('stage_assignments')->where('user_id', '=', $userId)->delete();
            }

            // Clean orphaned stage_assignments entries by user_group_id
            $orphanedIds = DB::table('stage_assignments AS sa')->leftJoin('user_groups AS ug', 'ug.user_group_id', '=', 'sa.user_group_id')->whereNull('ug.user_group_id')->distinct()->pluck('sa.user_group_id');
            foreach ($orphanedIds as $userGroupId) {
                DB::table('stage_assignments')->where('user_group_id', '=', $userGroupId)->delete();
            }

            // Clean orphaned stage_assignments entries by submission_id
            $orphanedIds = DB::table('stage_assignments AS sa')->leftJoin('submissions AS s', 'sa.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->distinct()->pluck('s.submission_id');
            foreach ($orphanedIds as $submissionId) {
                DB::table('stage_assignments')->where('submission_id', '=', $submissionId)->delete();
            }
            // Clean orphaned submission_files entries by submission_id
            $orphanedIds = DB::table('submission_files AS sf')->leftJoin('submissions AS s', 'sf.submission_id', '=', 's.submission_id')->whereNull('s.submission_id')->distinct()->pluck('sf.submission_id');
            foreach ($orphanedIds as $submissionId) {
                $this->_installer->log("Removing orphaned submission_files entries for non-existent submission_id ${submissionId}.");
                DB::table('submission_files')->where('submission_id', '=', $submissionId)->delete();
            }
            // Clean orphaned submission_files entries by file_id
            $orphanedIds = DB::table('submission_files AS sf')->leftJoin('files AS f', 'sf.file_id', '=', 'f.file_id')->whereNull('f.file_id')->distinct()->pluck('sf.file_id');
            foreach ($orphanedIds as $fileId) {
                $this->_installer->log("Removing orphaned submission_files entries for non-existent file_id ${fileId}.");
                DB::table('submission_files')->where('file_id', '=', $fileId)->delete();
            }
            // Clean orphaned submission_files entries by genre_id
            $orphanedIds = DB::table('submission_files AS sf')->leftJoin('genres AS g', 'sf.genre_id', '=', 'g.genre_id')->whereNull('g.genre_id')->whereNotNull('sf.genre_id')->distinct()->pluck('sf.genre_id');
            foreach ($orphanedIds as $genreId) {
                $this->_installer->log("Nulling non-existent genre_id ${genreId} in submission_files.");
                DB::table('submission_files')->where('genre_id', '=', $genreId)->update(['genre_id' => null]);
            }
            // Clean orphaned submission_files entries by uploader_user_id
            $orphanedIds = DB::table('submission_files AS sf')->leftJoin('users AS u', 'sf.uploader_user_id', '=', 'u.user_id')->whereNull('u.user_id')->whereNotNull('sf.uploader_user_id')->distinct()->pluck('sf.uploader_user_id');
            foreach ($orphanedIds as $uploaderUserId) {
                $this->_installer->log("Nulling non-existent uploader_user_id ${uploaderUserId} in submission_files.");
                DB::table('submission_files')->where('uploader_user_id', '=', $uploaderUserId)->update(['uploader_user_id' => null]);
            }
            // Clean orphaned submission_files entries by source_submission_file_id
            $orphanedIds = DB::table('submission_files AS sf')->leftJoin('submission_files AS sfs', 'sf.source_submission_file_id', '=', 'sfs.submission_file_id')->whereNull('sfs.submission_file_id')->whereNotNull('sf.source_submission_file_id')->distinct()->pluck('sf.source_submission_file_id');
            foreach ($orphanedIds as $sourceSubmissionFileId) {
                $this->_installer->log("Nulling non-existent source_submission_file_id ${sourceSubmissionFileId} in submission_files.");
                DB::table('submission_files')->where('source_submission_file_id', '=', $sourceSubmissionFileId)->update(['source_submission_file_id' => null]);
            }
            // Clean orphaned data_object_tombstone_settings entries
            $orphanedIds = DB::table('data_object_tombstone_settings AS dots')->leftJoin('data_object_tombstones AS dot', 'dot.tombstone_id', '=', 'dots.tombstone_id')->whereNull('dot.tombstone_id')->distinct()->pluck('dots.tombstone_id');
            foreach ($orphanedIds as $tombstoneId) {
                DB::table('data_object_tombstone_settings')->where('tombstone_id', '=', $tombstoneId)->delete();
            }
            // Clean orphaned data_object_tombstone_oai_set_objects entries by tombstone_id
            $orphanedIds = DB::table('data_object_tombstone_oai_set_objects AS dotoso')->leftJoin('data_object_tombstones AS dot', 'dot.tombstone_id', '=', 'dotoso.tombstone_id')->whereNull('dot.tombstone_id')->distinct()->pluck('dotoso.tombstone_id');
            foreach ($orphanedIds as $tombstoneId) {
                DB::table('data_object_tombstone_oai_set_objects')->where('tombstone_id', '=', $tombstoneId)->delete();
            }
            // Clean orphaned category data
            $orphanedIds = DB::table($this->getContextSettingsTable() . ' AS cs')->leftJoin($this->getContextTable() . ' AS c', 'cs.' . $this->getContextKeyField(), '=', 'c.' . $this->getContextKeyField())->whereNull('c.' . $this->getContextKeyField())->distinct()->pluck('cs.' . $this->getContextKeyField());
            foreach ($orphanedIds as $contextId) {
                DB::table($this->getContextSettingsTable())->where($this->getContextKeyField(), '=', $contextId)->delete();
            }

            // Flag users that have same emails if we consider them case insensitively
            $result = DB::table('users AS a')
                ->join('users AS b', function ($join) {
                    $join->on(DB::Raw('LOWER(a.email)'), '=', DB::Raw('LOWER(b.email)'));
                    $join->on('a.user_id', '<>', 'b.user_id');
                })
                ->select('a.user_id as user_id, b.user_id as paired_user_id')
                ->get();
            foreach ($result as $row) {
                $this->_installer->log("The user with user_id {$row->user_id} and email {$row->email} collides with user_id {$row->paired_user_id} and email {$row->paired_email}.");
            }
            if ($result->count()) {
                throw new Exception('Starting with 3.4.0, email addresses are not case sensitive. Your database contains users that have same emails if considered case insensitively. These must be merged or made unique before the upgrade can be executed. Use the tools/mergeUsers.php script in the old installation directory to resolve these before running the upgrade.');
            }

            // Flag users that have same username if we consider them case insensitively
            $result = DB::table('users AS a')
                ->join('users AS b', function ($join) {
                    $join->on(DB::Raw('LOWER(a.username)'), '=', DB::Raw('LOWER(b.username)'));
                    $join->on('a.user_id', '<>', 'b.user_id');
                })
                ->select('a.user_id as user_id, b.user_id as paired_user_id')
                ->get();
            foreach ($result as $row) {
                $this->_installer->log("The user with user_id {$row->user_id} and username {$row->username} collides with user_id {$row->paired_user_id} and username {$row->username}.");
            }
            if ($result->count()) {
                throw new Exception('Starting with 3.4.0, usernames are not case sensitive. Your database contains users that have same username if considered case insensitively. These must be merged or made unique before the upgrade can be executed. Use the tools/mergeUsers.php script in the old installation directory to resolve these before running the upgrade.');
            }

            // Make sure submission checklists have locale key
            // See I7191_SubmissionChecklistMigration
            $invalidSubmissionsChecklist = DB::table($this->getContextSettingsTable())
                ->where('setting_name', 'submissionChecklist')
                ->whereNull('locale')
                ->count();
            if ($invalidSubmissionsChecklist > 0) {
                throw new \Exception('A row with setting_name="submissionChecklist" found in table ' . $this->getContextSettingsTable() . ' with null in the locale column. Remove this row or add a locale before upgrading.');
            }
            // All submission checklists should be a json-encoded array
            // See I7191_SubmissionChecklistMigration
            DB::table($this->getContextSettingsTable())
                ->where('setting_name', 'submissionChecklist')
                ->pluck('setting_value')
                ->each(function ($value) {
                    $checklist = json_decode($value);
                    if (is_null($checklist) || !is_array($checklist)) {
                        throw new \Exception('A row with setting_name="submissionChecklist" found in table ' . $this->getContextSettingsTable() . " without the expected setting_value. Expected an array encoded in JSON but found:\n\n" . $value . "\n\nFix or remove this row before upgrading.");
                    }
                });
        } catch (Throwable $e) {
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

    /**
     * Check the contexts' contact details before upgrade
     *
     * @throws Exception
     */
    protected function checkContactSetting(): void
    {
        $primaryLocale = DB::table('site')
            ->select(['primary_locale'])
            ->first()
            ->primary_locale;

        $missingContactContexts = DB::table("{$this->getContextTable()}")
            ->select([
                "{$this->getContextKeyField()}",
            ])
            ->addSelect(['title' => DB::table("{$this->getContextSettingsTable()}")
            ->select(['setting_value'])
            ->whereColumn("{$this->getContextKeyField()}", "{$this->getContextTable()}.{$this->getContextKeyField()}")
            ->where('locale', $primaryLocale)
            ->where('setting_name', 'name')
            ])
            ->whereNotIn(
                "{$this->getContextKeyField()}",
                fn ($query) => $query
                    ->select("{$this->getContextKeyField()}")
                    ->from("{$this->getContextSettingsTable()}")
                    ->whereColumn("{$this->getContextKeyField()}", "{$this->getContextTable()}.{$this->getContextKeyField()}")
                    ->whereIn('setting_name', ['contactEmail', 'contactName'])
            )
            ->get();

        if ($missingContactContexts->count() <= 0) {
            return;
        }

        throw new Exception(
            sprintf(
                'Missing contact name/email information for contexts [%s], please set those before upgrading',
                $missingContactContexts->pluck('title')->implode(',')
            )
        );
    }
}

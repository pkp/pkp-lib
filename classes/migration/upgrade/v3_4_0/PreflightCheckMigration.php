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

    protected function getEntityRelationships(): array
    {
        return [
            $this->getContextTable() => ['submissions', 'user_groups', 'categories', 'sections', 'navigation_menu_items', 'genres', 'filters', 'announcement_types', 'notifications', 'navigation_menus', 'library_files', 'email_templates', 'user_group_stage', 'subeditor_submission_group', 'plugin_settings', 'notification_subscription_settings', $this->getContextSettingsTable()],
            'submissions' => ['submission_files', 'publications', 'review_rounds', 'review_assignments', 'submission_search_objects', 'library_files', 'submission_settings', 'submission_comments', 'stage_assignments', 'review_round_files', 'edit_decisions'],
            'users' => ['submission_files', 'review_assignments', 'notifications', 'event_log', 'email_log', 'user_user_groups', 'user_settings', 'user_interests', 'temporary_files', 'submission_comments', 'subeditor_submission_group', 'stage_assignments', 'sessions', 'query_participants', 'notification_subscription_settings', 'notes', 'email_log_users', 'edit_decisions', 'access_keys'],
            'submission_files' => ['submission_files', 'publication_galleys', 'submission_file_settings', 'submission_file_revisions', 'review_round_files', 'review_files'],
            'user_groups' => ['authors', 'user_user_groups', 'user_group_stage', 'user_group_settings', 'subeditor_submission_group', 'stage_assignments'],
            'publications' => ['submissions', 'publication_galleys', 'authors', 'citations', 'publication_settings', 'publication_categories'],
            'publication_galleys' => ['publication_galley_settings'],
            'review_forms' => ['sections', 'review_form_elements', 'review_assignments', 'review_form_settings'],
            'categories' => ['categories', 'publication_categories', 'category_settings'],
            'review_rounds' => ['review_assignments', 'review_round_files', 'edit_decisions'],
            'authors' => ['publications', 'author_settings'],
            'controlled_vocab_entries' => ['user_interests', 'controlled_vocab_entry_settings'],
            'data_object_tombstones' => ['data_object_tombstone_settings', 'data_object_tombstone_oai_set_objects'],
            'files' => ['submission_files', 'submission_file_revisions'],
            'filters' => ['filters', 'filter_settings'],
            'genres' => ['submission_files', 'genre_settings'],
            'navigation_menu_item_assignments' => ['navigation_menu_item_assignments', 'navigation_menu_item_assignment_settings'],
            'announcement_types' => ['announcements', 'announcement_type_settings'],
            'review_assignments' => ['review_form_responses', 'review_files'],
            'review_form_elements' => ['review_form_responses', 'review_form_element_settings'],
            'sections' => ['publications', 'section_settings'],
            'navigation_menu_items' => ['navigation_menu_item_assignments', 'navigation_menu_item_settings'],
            'queries' => ['query_participants'],
            'navigation_menus' => ['navigation_menu_item_assignments'],
            'notifications' => ['notification_settings'],
            'event_log' => ['event_log_settings'],
            'email_templates' => ['email_templates_settings'],
            'library_files' => ['library_file_settings'],
            'email_log' => ['email_log_users'],
            'controlled_vocabs' => ['controlled_vocab_entries'],
            'submission_search_keyword_list' => ['submission_search_object_keywords'],
            'submission_search_objects' => ['submission_search_object_keywords'],
            'citations' => ['citation_settings'],
            'announcements' => ['announcement_settings'],
            'filter_groups' => ['filters']
        ];
    }
}

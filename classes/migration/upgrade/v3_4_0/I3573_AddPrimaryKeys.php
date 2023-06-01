<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I3573_AddPrimaryKeys.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I3573_AddPrimaryKeys
 *
 * @brief Add primary keys to tables that do not currently have them, to support DB replication.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class I3573_AddPrimaryKeys extends \PKP\migration\Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Rename tablename_pkey unique indexes to tablename_unique to avoid PostgreSQL collision
        foreach (static::getIndexData() as $tableName => [$oldIndexName, $columns, $newIndexName, $isUnique]) {
            // If the table does not exist, do not process it.
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Depending on whether the schema was created with ADODB or Laravel schema management, user_settings_pkey
            // will either be a constraint or an index. See https://github.com/pkp/pkp-lib/issues/7670.
            try {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($oldIndexName));
            } catch (Exception $e) {
                try {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($oldIndexName));
                } catch (Exception $e) {
                    $this->_installer->log("Failed to remove the index {$oldIndexName}, we'll assume it doesn't exist and proceed");
                }
            }

            if ($isUnique) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unique($columns, $newIndexName));
            } else {
                Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $newIndexName));
            }
        }

        // Add the autoincrement columns
        foreach (static::getKeyNames() as $tableName => $keyName) {
            // If the table does not exist, do not process it.
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // If the table already has the named column, do not process it.
            if (Schema::hasColumn($tableName, $keyName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($keyName) {
                $table->bigIncrements($keyName)->first();
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Remove the autoincrement columns
        foreach (static::getKeyNames() as $tableName => $keyName) {
            // If the table does not exist, do not process it.
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // If the table does not have the named column, do not process it.
            if (!Schema::hasColumn($tableName, $keyName)) {
                continue;
            }

            // Handle special case: notification_subscription_settings already had setting_id prior to this migration. Do not drop it.
            if ($tableName === 'notification_subscription_settings') {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($keyName) {
                $table->unsignedInteger($keyName)->change(); // Drop auto increment
                $table->dropPrimary($keyName);
                $table->dropColumn($keyName);
            });
        }

        // Rename tablename_unique indexes back to tablename_pkey
        foreach (static::getIndexData() as $tableName => [$oldIndexName, $columns, $newIndexName, $isUnique]) {
            // If the table does not exist, do not process it.
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $oldIndexName, $newIndexName, $isUnique) {
                if ($isUnique) {
                    $table->dropUnique($newIndexName);
                    $table->unique($columns, $oldIndexName);
                } else {
                    $table->dropIndex($newIndexName);
                    $table->index($columns, $oldIndexName);
                }
            });
        }
    }

    public static function getKeyNames(): array
    {
        return [
            'site' => 'site_id',
            'versions' => 'version_id',
            'oai_resumption_tokens' => 'oai_resumption_token_id',
            'review_round_files' => 'review_round_file_id',
            'user_interests' => 'user_interest_id',
            'email_templates_default_data' => 'email_templates_default_data_id',
            'subeditor_submission_group' => 'subeditor_submission_group_id',
            'scheduled_tasks' => 'scheduled_task_id',
            'announcement_settings' => 'announcement_setting_id',
            'publication_categories' => 'publication_category_id',
            'user_user_groups' => 'user_user_group_id',
            'review_form_responses' => 'review_form_response_id',
            'query_participants' => 'query_participant_id',
            'user_group_stage' => 'user_group_stage_id',
            'email_log_users' => 'email_log_user_id',
            'review_files' => 'review_file_id',
            'submission_search_object_keywords' => 'submission_search_object_keyword_id',
            'announcement_type_settings' => 'announcement_type_setting_id',
            'author_settings' => 'author_setting_id',
            'category_settings' => 'category_setting_id',
            'citation_settings' => 'citation_setting_id',
            'controlled_vocab_entry_settings' => 'controlled_vocab_entry_setting_id',
            'data_object_tombstone_settings' => 'tombstone_setting_id',
            'doi_settings' => 'doi_setting_id', // Already created with schema during upgrade
            'email_templates_settings' => 'email_template_setting_id',
            'event_log_settings' => 'event_log_setting_id',
            'filter_settings' => 'filter_setting_id',
            'genre_settings' => 'genre_setting_id',
            'institution_settings' => 'institution_setting_id', // Already created with schema during upgrade
            'library_file_settings' => 'library_file_setting_id',
            'navigation_menu_item_assignment_settings' => 'navigation_menu_item_assignment_setting_id',
            'navigation_menu_item_settings' => 'navigation_menu_item_setting_id',
            'notification_settings' => 'notification_setting_id',
            'notification_subscription_settings' => 'setting_id', // Already had this ID before upgrade
            'plugin_settings' => 'plugin_setting_id',
            'publication_settings' => 'publication_setting_id',
            'review_form_element_settings' => 'review_form_element_setting_id',
            'review_form_settings' => 'review_form_setting_id',
            'submission_file_settings' => 'submission_file_setting_id',
            'submission_settings' => 'submission_setting_id',
            'user_group_settings' => 'user_group_setting_id',
            'user_settings' => 'user_setting_id',
            'site_settings' => 'site_setting_id',
            'static_page_settings' => 'static_page_setting_id', // PLUGIN
        ];
    }

    /**
     * Get a list of index data for tables that need their indexes rebuilt/renamed.
     *
     * @return array [table_name => [old_key_name, [list, of, columns], new_key_name, is_unique]
     */
    public static function getIndexData(): array
    {
        return [
            'review_form_responses' => ['review_form_responses_pkey', ['review_form_element_id', 'review_id'], 'review_form_responses_unique', false],
            'review_round_files' => ['review_round_files_pkey', ['submission_id', 'review_round_id', 'submission_file_id'], 'review_round_files_unique', true],
            'review_files' => ['review_files_pkey', ['review_id', 'submission_file_id'], 'review_files_unique', true],
            'scheduled_tasks' => ['scheduled_tasks_pkey', ['class_name'], 'scheduled_tasks_unique', true],
            'announcement_type_settings' => ['announcement_type_settings_pkey', ['type_id', 'locale', 'setting_name'], 'announcement_type_settings_unique', true],
            'announcement_settings' => ['announcement_settings_pkey', ['announcement_id', 'locale', 'setting_name'], 'announcement_settings_unique', true],
            'category_settings' => ['category_settings_pkey', ['category_id', 'locale', 'setting_name'], 'category_settings_unique', true],
            'versions' => ['versions_pkey', ['product_type', 'product', 'major', 'minor', 'revision', 'build'], 'versions_unique', true],
            'site_settings' => ['site_settings_pkey', ['setting_name', 'locale'], 'site_settings_unique', true],
            'user_settings' => ['user_settings_pkey', ['user_id', 'locale', 'setting_name'], 'user_settings_unique', true],
            'notification_settings' => ['notification_settings_pkey', ['notification_id', 'locale', 'setting_name'], 'notification_settings_unique', true],
            'email_templates_default_data' => ['email_templates_default_data_pkey', ['email_key', 'locale'], 'email_templates_default_data_unique', true],
            'email_templates_settings' => ['email_settings_pkey', ['email_id', 'locale', 'setting_name'], 'email_templates_settings_unique', true],
            'oai_resumption_tokens' => ['oai_resumption_tokens_pkey', ['token'], 'oai_resumption_tokens_unique', true],
            'plugin_settings' => ['plugin_settings_pkey', ['plugin_name', 'context_id', 'setting_name'], 'plugin_settings_unique', true],
            'genre_settings' => ['genre_settings_pkey', ['genre_id', 'locale', 'setting_name'], 'genre_settings_unique', true],
            'library_file_settings' => ['library_file_settings_pkey', ['file_id', 'locale', 'setting_name'], 'library_file_settings_unique', true],
            'event_log_settings' => ['event_log_settings_pkey', ['log_id', 'setting_name'], 'event_log_settings_unique', true],
            'citation_settings' => ['citation_settings_pkey', ['citation_id', 'locale', 'setting_name'], 'citation_settings_unique', true],
            'filter_settings' => ['filter_settings_pkey', ['filter_id', 'locale', 'setting_name'], 'filter_settings_unique', true],
            'navigation_menu_item_settings' => ['navigation_menu_item_settings_pkey', ['navigation_menu_item_id', 'locale', 'setting_name'], 'navigation_menu_item_settings_unique', true],
            'navigation_menu_item_assignment_settings' => ['navigation_menu_item_assignment_settings_pkey', ['navigation_menu_item_assignment_id', 'locale', 'setting_name'], 'navigation_menu_item_assignment_settings_unique', true],
            'review_form_settings' => ['review_form_settings_pkey', ['review_form_id', 'locale', 'setting_name'], 'review_form_settings_unique', true],
            'review_form_element_settings' => ['review_form_element_settings_pkey', ['review_form_element_id', 'locale', 'setting_name'], 'review_form_element_settings_unique', true],
            'user_group_settings' => ['user_group_settings_pkey', ['user_group_id', 'locale', 'setting_name'], 'user_group_settings_unique', true],
            'user_user_groups' => ['user_user_groups_pkey', ['user_group_id', 'user_id'], 'user_user_groups_unique', true],
            'user_group_stage' => ['user_group_stage_pkey', ['context_id', 'user_group_id', 'stage_id'], 'user_group_stage_unique', true],
            'submission_file_settings' => ['submission_file_settings_pkey', ['submission_file_id', 'locale', 'setting_name'], 'submission_file_settings_unique', true],
            'submission_settings' => ['submission_settings_pkey', ['submission_id', 'locale', 'setting_name'], 'submission_settings_unique', true],
            'publication_settings' => ['publication_settings_pkey', ['publication_id', 'locale', 'setting_name'], 'publication_settings_unique', true],
            'author_settings' => ['author_settings_pkey', ['author_id', 'locale', 'setting_name'], 'author_settings_unique', true],
            'subeditor_submission_group' => ['section_editors_pkey', ['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_unique', true],
            'query_participants' => ['query_participants_pkey', ['query_id', 'user_id'], 'query_participants_unique', true],
            'submission_search_object_keywords' => ['submission_search_object_keywords_pkey', ['object_id', 'pos'], 'submission_search_object_keywords_unique', true],
            'data_object_tombstone_settings' => ['data_object_tombstone_settings_pkey', ['tombstone_id', 'locale', 'setting_name'], 'data_object_tombstone_settings_unique', true],
        ];
    }
}

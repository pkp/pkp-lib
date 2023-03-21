<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I3573_AddPrimaryKeys.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I3573_AddPrimaryKeys
 * @brief Add primary keys to tables that do not currently have them, to support DB replication.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class I3573_AddPrimaryKeys extends \PKP\migration\Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
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
}

<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6093_AddForeignKeys.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Describe upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I6093_AddForeignKeys extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcement_types', function (Blueprint $table) {
            // Drop the old assoc_type column and assoc-based index
            $table->dropIndex('announcement_types_assoc');
            $table->dropColumn('assoc_type');

            // Rename assoc_id to context_id and introduce foreign key constraint
            $table->renameColumn('assoc_id', 'context_id');
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');

            // Introduce new index
            $table->index(['context_id'], 'announcement_types_context_id');
        });

        Schema::table('announcement_type_settings', function (Blueprint $table) {
            $table->foreign('type_id')->references('type_id')->on('announcement_types')->onDelete('cascade');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreign('type_id')->references('type_id')->on('announcement_types')->onDelete('set null');
        });

        Schema::table('announcement_settings', function (Blueprint $table) {
            $table->foreign('announcement_id')->references('announcement_id')->on('announcements')->onDelete('cascade');
        });

        Schema::table('category_settings', function (Blueprint $table) {
            $table->dropColumn('setting_type');
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
        });
        // Permit nulls in categories.parent_id where previously 0 was used for "no parent"
        Schema::table('categories', function (Blueprint $table) {
            $table->bigInteger('parent_id')->nullable()->change();
        });
        DB::table('categories')->where('parent_id', '=', 0)->update(['parent_id' => null]);
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
            $table->foreign('parent_id')->references('category_id')->on('categories')->onDelete('set null');
        });
        Schema::table('publication_categories', function (Blueprint $table) {
            $table->foreign('category_id')->references('category_id')->on('categories')->onDelete('cascade');
            $table->foreign('publication_id')->references('publication_id')->on('publications')->onDelete('cascade');
        });
        Schema::table('item_views', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('genres', function (Blueprint $table) {
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
        });
        Schema::table('genre_settings', function (Blueprint $table) {
            $table->foreign('genre_id')->references('genre_id')->on('genres')->onDelete('cascade');
        });
        Schema::table('controlled_vocab_entries', function (Blueprint $table) {
            $table->foreign('controlled_vocab_id')->references('controlled_vocab_id')->on('controlled_vocabs')->onDelete('cascade');
        });
        Schema::table('controlled_vocab_entry_settings', function (Blueprint $table) {
            $table->foreign('controlled_vocab_entry_id', 'c_v_e_s_entry_id')->references('controlled_vocab_entry_id')->on('controlled_vocab_entries')->onDelete('cascade');
        });
        Schema::table('user_interests', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('controlled_vocab_entry_id')->references('controlled_vocab_entry_id')->on('controlled_vocab_entries')->onDelete('cascade');
        });
        Schema::table('user_settings', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('notification_subscription_settings', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('context')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
        });
        Schema::table('email_templates', function (Blueprint $table) {
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
        });
        Schema::table('email_templates_settings', function (Blueprint $table) {
            $table->foreign('email_id')->references('email_id')->on('email_templates')->onDelete('cascade');
        });
        Schema::table('library_files', function (Blueprint $table) {
            $table->foreign('context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
        });
        Schema::table('library_file_settings', function (Blueprint $table) {
            $table->foreign('file_id')->references('file_id')->on('library_files')->onDelete('cascade');
        });
        Schema::table('event_log', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('event_log_settings', function (Blueprint $table) {
            $table->foreign('log_id', 'event_log_settings_log_id')->references('log_id')->on('event_log')->onDelete('cascade');
        });
        Schema::table('email_log_users', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('email_log_id')->references('log_id')->on('email_log')->onDelete('cascade');
        });
        Schema::table('citations', function (Blueprint $table) {
            $table->foreign('publication_id', 'citations_publication')->references('publication_id')->on('publications')->onDelete('cascade');
        });
        Schema::table('citation_settings', function (Blueprint $table) {
            $table->foreign('citation_id', 'citation_settings_citation_id')->references('citation_id')->on('citations')->onDelete('cascade');
        });
        Schema::table('filters', function (Blueprint $table) {
            $table->foreign('filter_group_id')->references('filter_group_id')->on('filter_groups')->onDelete('cascade');
        });
        Schema::table('filter_settings', function (Blueprint $table) {
            $table->foreign('filter_id')->references('filter_id')->on('filters')->onDelete('cascade');
        });
        Schema::table('temporary_files', function (Blueprint $table) {
            $table->foreign('user_id', 'temporary_files_user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        Schema::table('navigation_menu_item_settings', function (Blueprint $table) {
            $table->foreign('navigation_menu_item_id', 'navigation_menu_item_settings_navigation_menu_id')->references('navigation_menu_item_id')->on('navigation_menu_items')->onDelete('cascade');
        });
        Schema::table('navigation_menu_item_assignments', function (Blueprint $table) {
            $table->foreign('navigation_menu_id')->references('navigation_menu_id')->on('navigation_menus')->onDelete('cascade');
            $table->foreign('navigation_menu_item_id')->references('navigation_menu_item_id')->on('navigation_menu_items')->onDelete('cascade');
        });
        Schema::table('navigation_menu_item_assignment_settings', function (Blueprint $table) {
            $table->foreign('navigation_menu_item_assignment_id', 'assignment_settings_navigation_menu_item_assignment_id')->references('navigation_menu_item_assignment_id')->on('navigation_menu_item_assignments')->onDelete('cascade');
        });
        Schema::table('review_form_settings', function (Blueprint $table) {
            $table->foreign('review_form_id', 'review_form_settings_review_form_id')->references('review_form_id')->on('review_forms')->onDelete('cascade');
        });
        Schema::table('review_form_settings', function (Blueprint $table) {
            $table->foreign('review_form_id', 'review_form_elements_review_form_id')->references('review_form_id')->on('review_forms')->onDelete('cascade');
        });
        Schema::table('review_form_element_settings', function (Blueprint $table) {
            $table->foreign('review_form_element_id', 'review_form_element_settings_review_form_element_id')->references('review_form_element_id')->on('review_form_elements')->onDelete('cascade');
        });
        Schema::table('review_form_responses', function (Blueprint $table) {
            $table->foreign('review_form_element_id')->references('review_form_element_id')->on('review_form_elements')->onDelete('cascade');
            $table->foreign('review_id')->references('review_id')->on('review_assignments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to removed data');
    }
}

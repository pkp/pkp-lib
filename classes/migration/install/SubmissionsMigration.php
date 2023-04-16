<?php

/**
 * @file classes/migration/install/SubmissionsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use PKP\submission\PKPSubmission;

class SubmissionsMigration extends \PKP\migration\Migration
{
    protected int $defaultStageId = WORKFLOW_STAGE_ID_SUBMISSION;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Submissions
        Schema::create('submissions', function (Blueprint $table) {
            $table->comment('All submissions submitted to the context, including incomplete, declined and unpublished submissions.');
            $table->bigInteger('submission_id')->autoIncrement();

            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id', 'submissions_context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'submissions_context_id');

            // NOTE: The foreign key relationship on publications is declared where that table is created.
            $table->bigInteger('current_publication_id')->nullable();

            $table->datetime('date_last_activity')->nullable();
            $table->datetime('date_submitted')->nullable();
            $table->datetime('last_modified')->nullable();
            $table->bigInteger('stage_id')->default($this->defaultStageId);
            $table->string('locale', 14)->nullable();

            $table->smallInteger('status')->default(PKPSubmission::STATUS_QUEUED);

            $table->string('submission_progress', 50)->default('start');
            //  Used in OMP only; should not be null there
            $table->smallInteger('work_type')->default(0)->nullable();
        });
        Schema::table('stage_assignments', function (Blueprint $table) {
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'stage_assignments_submission_id');
        });

        // Submission metadata
        Schema::create('submission_settings', function (Blueprint $table) {
            $table->comment('Localized data about submissions');
            $table->bigIncrements('submission_setting_id');
            $table->bigInteger('submission_id');
            $table->foreign('submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'submission_settings_submission_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['submission_id', 'locale', 'setting_name'], 'submission_settings_unique');
        });

        // publication metadata
        Schema::create('publication_settings', function (Blueprint $table) {
            $table->comment('More data about publications, including localized properties such as the title and abstract.');
            $table->bigIncrements('publication_setting_id');

            // The foreign key relationship on this table is defined with the publications table.
            $table->bigInteger('publication_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['publication_id', 'locale', 'setting_name'], 'publication_settings_unique');
        });
        // Add partial index (DBMS-specific)
        switch (DB::getDriverName()) {
            case 'mysql': DB::unprepared('CREATE INDEX publication_settings_name_value ON publication_settings (setting_name(50), setting_value(150))');
                break;
            case 'pgsql': DB::unprepared("CREATE INDEX publication_settings_name_value ON publication_settings (setting_name, setting_value) WHERE setting_name IN ('indexingState', 'medra::registeredDoi', 'datacite::registeredDoi', 'pub-id::publisher-id')");
                break;
        }

        // Authors for submissions.
        Schema::create('authors', function (Blueprint $table) {
            $table->comment('The authors of a publication.');
            $table->bigInteger('author_id')->autoIncrement();
            $table->string('email', 90);
            $table->smallInteger('include_in_browse')->default(1);

            // The foreign key relationship on this table is defined with the publications table.
            $table->bigInteger('publication_id');

            $table->float('seq', 8, 2)->default(0);

            $table->bigInteger('user_group_id')->nullable();
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
            $table->index(['user_group_id'], 'authors_user_group_id');
        });

        // Language dependent author metadata.
        Schema::create('author_settings', function (Blueprint $table) {
            $table->comment('More data about authors, including localized properties such as their name and affiliation.');
            $table->bigIncrements('author_setting_id');
            $table->bigInteger('author_id');
            $table->foreign('author_id', 'author_settings_author_id')->references('author_id')->on('authors')->onDelete('cascade');
            $table->index(['author_id'], 'author_settings_author_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['author_id', 'locale', 'setting_name'], 'author_settings_unique');
        });

        // Editor decisions.
        Schema::create('edit_decisions', function (Blueprint $table) {
            $table->comment('Editorial decisions recorded on a submission, such as decisions to accept or decline the submission, as well as decisions to send for review, send to copyediting, request revisions, and more.');
            $table->bigInteger('edit_decision_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'edit_decisions_submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'edit_decisions_submission_id');

            // Foreign key constraint is declared with review_rounds
            $table->bigInteger('review_round_id')->nullable();

            $table->bigInteger('stage_id')->nullable();
            $table->smallInteger('round')->nullable();

            $table->bigInteger('editor_id');
            $table->foreign('editor_id', 'edit_decisions_editor_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['editor_id'], 'edit_decisions_editor_id');

            $table->smallInteger('decision');
            $table->datetime('date_decided');
        });

        // Comments posted on submissions
        Schema::create('submission_comments', function (Blueprint $table) {
            $table->comment('Comments on a submission, e.g. peer review comments');
            $table->bigInteger('comment_id')->autoIncrement();
            $table->bigInteger('comment_type')->nullable();
            $table->bigInteger('role_id');

            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'submission_comments_submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'submission_comments_submission_id');

            $table->bigInteger('assoc_id');

            $table->bigInteger('author_id');
            $table->foreign('author_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['author_id'], 'submission_comments_author_id');

            $table->text('comment_title');
            $table->text('comments')->nullable();
            $table->datetime('date_posted')->nullable();
            $table->datetime('date_modified')->nullable();
            $table->smallInteger('viewable')->nullable();
        });

        // Assignments of sub editors to submission groups.
        Schema::create('subeditor_submission_group', function (Blueprint $table) {
            $table->comment('Subeditor assignments to e.g. sections and categories');
            $table->bigIncrements('subeditor_submission_group_id');
            $table->bigInteger('context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id', 'section_editors_context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade');
            $table->index(['context_id'], 'subeditor_submission_group_context_id');

            $table->bigInteger('assoc_id');
            $table->bigInteger('assoc_type');

            $table->bigInteger('user_id');
            $table->foreign('user_id', 'subeditor_submission_group_user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'subeditor_submission_group_user_id');

            $table->bigInteger('user_group_id');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
            $table->index(['user_group_id'], 'subeditor_submission_group_user_group_id');

            $table->index(['assoc_id', 'assoc_type'], 'subeditor_submission_group_assoc_id');
            $table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id', 'user_group_id'], 'section_editors_unique');
        });

        // queries posted on submission workflow
        Schema::create('queries', function (Blueprint $table) {
            $table->comment('Discussions, usually related to a submission, created by editors, authors and other editorial staff.');
            $table->bigInteger('query_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('stage_id');
            $table->float('seq', 8, 2)->default(0);
            $table->datetime('date_posted')->nullable();
            $table->datetime('date_modified')->nullable();
            $table->smallInteger('closed')->default(0);
            $table->index(['assoc_type', 'assoc_id'], 'queries_assoc_id');
        });

        // queries posted on submission workflow
        Schema::create('query_participants', function (Blueprint $table) {
            $table->comment('The users assigned to a discussion.');
            $table->bigIncrements('query_participant_id');
            $table->bigInteger('query_id');
            $table->foreign('query_id')->references('query_id')->on('queries')->onDelete('cascade');
            $table->index(['query_id'], 'query_participants_query_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'query_participants_user_id');

            $table->unique(['query_id', 'user_id'], 'query_participants_unique');
        });

        // List of all keywords.
        Schema::create('submission_search_keyword_list', function (Blueprint $table) {
            $table->comment('A list of all keywords used in the search index');
            $table->bigInteger('keyword_id')->autoIncrement();
            $table->string('keyword_text', 60);
            $table->unique(['keyword_text'], 'submission_search_keyword_text');
        });

        // Indexed objects.
        Schema::create('submission_search_objects', function (Blueprint $table) {
            $table->comment('A list of all search objects indexed in the search index');
            $table->bigInteger('object_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'submission_search_object_submission')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'submission_search_objects_submission_id');

            $table->integer('type')->comment('Type of item. E.g., abstract, fulltext, etc.');
            $table->bigInteger('assoc_id')->comment('Optional ID of an associated record (e.g., a file_id)')->nullable();
        });

        // Keyword occurrences for each indexed object.
        Schema::create('submission_search_object_keywords', function (Blueprint $table) {
            $table->comment('Relationships between search objects and keywords in the search index');
            $table->bigIncrements('submission_search_object_keyword_id');
            $table->bigInteger('object_id');
            $table->foreign('object_id')->references('object_id')->on('submission_search_objects')->onDelete('cascade');
            $table->index(['object_id'], 'submission_search_object_keywords_object_id');

            $table->bigInteger('keyword_id');
            $table->foreign('keyword_id', 'submission_search_object_keywords_keyword_id')->references('keyword_id')->on('submission_search_keyword_list')->onDelete('cascade');
            $table->index(['keyword_id'], 'submission_search_object_keywords_keyword_id');

            $table->integer('pos')->comment('Word position of the keyword in the object.');

            $table->unique(['object_id', 'pos'], 'submission_search_object_keywords_unique');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('submission_search_object_keywords');
        Schema::drop('submission_search_objects');
        Schema::drop('submission_search_keyword_list');
        Schema::drop('query_participants');
        Schema::drop('queries');
        Schema::drop('subeditor_submission_group');
        Schema::drop('submission_comments');
        Schema::drop('edit_decisions');
        Schema::drop('author_settings');
        Schema::drop('authors');
        Schema::drop('publication_settings');
        Schema::drop('submission_settings');
        Schema::drop('submissions');
    }
}

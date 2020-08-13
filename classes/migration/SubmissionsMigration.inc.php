<?php

/**
 * @file classes/migration/SubmissionsMigration.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class SubmissionsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// Submissions
		Capsule::schema()->create('submissions', function (Blueprint $table) {
			$table->bigInteger('submission_id')->autoIncrement();

			$table->bigInteger('context_id');
			$contextDao = Application::getContextDAO();
			$table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName);

			$table->bigInteger('current_publication_id')->nullable();
			// pkp/pkp-lib#6093 FIXME: Circular foreign key reference between submissions and publications
			// $table->foreign('current_publication_id')->references('publication_id')->on('publications');

			$table->datetime('date_last_activity')->nullable();
			$table->datetime('date_submitted')->nullable();
			$table->datetime('last_modified')->nullable();
			$table->bigInteger('stage_id')->default(1); // WORKFLOW_STAGE_ID_SUBMISSION
			$table->tinyInteger('status')->default(1); //  STATUS_QUEUED
			$table->tinyInteger('submission_progress')->default(1);
			//  Used in OMP only; should not be null there 
			$table->tinyInteger('work_type')->default(0)->nullable();

			$table->index(['context_id'], 'submissions_context_id');
		});

		// Submission metadata
		Capsule::schema()->create('submission_settings', function (Blueprint $table) {
			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->mediumText('setting_value')->nullable();

			$table->index(['submission_id'], 'submission_settings_submission_id');
			$table->unique(['submission_id', 'locale', 'setting_name'], 'submission_settings_pkey');
		});

		// Editor decisions.
		Capsule::schema()->create('edit_decisions', function (Blueprint $table) {
			$table->bigInteger('edit_decision_id')->autoIncrement();

			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->bigInteger('review_round_id');
			// pkp/pkp-lib#6093 FIXME: Can't declare foreign key relationship because 0 is used as default
			// $table->foreign('review_round_id')->references('review_round_id')->on('review_rounds');

			$table->bigInteger('stage_id')->nullable();
			$table->tinyInteger('round');

			$table->bigInteger('editor_id');
			$table->foreign('editor_id')->references('user_id')->on('users');

			$table->tinyInteger('decision');
			$table->datetime('date_decided');

			$table->index(['submission_id'], 'edit_decisions_submission_id');
			$table->index(['editor_id'], 'edit_decisions_editor_id');
		});

		// Comments posted on submissions
		Capsule::schema()->create('submission_comments', function (Blueprint $table) {
			$table->bigInteger('comment_id')->autoIncrement();
			$table->bigInteger('comment_type')->nullable();
			$table->bigInteger('role_id');

			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			// pkp/pkp-lib#6097 Can't declare foreign relationships with assoc_id columns
			$table->bigInteger('assoc_id');

			$table->bigInteger('author_id');
			$table->foreign('author_id')->references('user_id')->on('users');

			$table->text('comment_title');
			$table->text('comments')->nullable();
			$table->datetime('date_posted')->nullable();
			$table->datetime('date_modified')->nullable();
			$table->tinyInteger('viewable')->nullable();

			$table->index(['submission_id'], 'submission_comments_submission_id');
		});

		// Assignments of sub editors to submission groups.
		Capsule::schema()->create('subeditor_submission_group', function (Blueprint $table) {
			$table->bigInteger('context_id');
			$contextDao = Application::getContextDAO();
			$table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName);

			// pkp/pkp-lib#6097 Can't declare foreign relationships with assoc_id/assoc_type pairs
			$table->bigInteger('assoc_id');
			$table->bigInteger('assoc_type');

			$table->bigInteger('user_id');
			$table->foreign('user_id')->references('user_id')->on('users');

			$table->index(['context_id'], 'section_editors_context_id');
			$table->index(['assoc_id', 'assoc_type'], 'subeditor_submission_group_assoc_id');
			$table->index(['user_id'], 'subeditor_submission_group_user_id');
			$table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_pkey');
		});

		// queries posted on submission workflow
		Capsule::schema()->create('queries', function (Blueprint $table) {
			$table->bigInteger('query_id')->autoIncrement();

			// pkp/pkp-lib#6097 Can't declare foreign relationships with assoc_id/assoc_type pairs
			$table->bigInteger('assoc_type');
			$table->bigInteger('assoc_id');

			$table->tinyInteger('stage_id')->default(1); // WORKFLOW_STAGE_ID_SUBMISSION
			$table->float('seq', 8, 2)->default(0);
			$table->datetime('date_posted')->nullable();
			$table->datetime('date_modified')->nullable();
			$table->smallInteger('closed')->default(0);

			$table->index(['assoc_type', 'assoc_id'], 'queries_assoc_id');
		});

		// queries posted on submission workflow
		Capsule::schema()->create('query_participants', function (Blueprint $table) {
			$table->bigInteger('query_id');
			$table->foreign('query_id')->references('query_id')->on('queries');

			$table->bigInteger('user_id');
			$table->foreign('user_id')->references('user_id')->on('users');

			$table->unique(['query_id', 'user_id'], 'query_participants_pkey');
		});

		// List of all keywords.
		Capsule::schema()->create('submission_search_keyword_list', function (Blueprint $table) {
			$table->bigInteger('keyword_id')->autoIncrement();
			$table->string('keyword_text', 60);

			$table->unique(['keyword_text'], 'submission_search_keyword_text');
		});

		// Indexed objects.
		Capsule::schema()->create('submission_search_objects', function (Blueprint $table) {
			$table->bigInteger('object_id')->autoIncrement();

			$table->bigInteger('submission_id');
			$table->foreign('submission_id')->references('submission_id')->on('submissions');

			$table->integer('type')->comment('Type of item. E.g., abstract, fulltext, etc.');
			$table->bigInteger('assoc_id')->comment('Optional ID of an associated record (e.g., a file_id)')->nullable();
		});

		// Keyword occurrences for each indexed object.
		Capsule::schema()->create('submission_search_object_keywords', function (Blueprint $table) {
			$table->bigInteger('object_id');
			$table->foreign('object_id')->references('object_id')->on('submission_search_objects');

			$table->bigInteger('keyword_id');
			$table->foreign('keyword_id')->references('keyword_id')->on('submission_search_keyword_list');

			$table->integer('pos')->comment('Word position of the keyword in the object.');

			$table->index(['keyword_id'], 'submission_search_object_keywords_keyword_id');
			$table->unique(['object_id', 'pos'], 'submission_search_object_keywords_pkey');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('submission_search_object_keywords');
		Capsule::schema()->drop('submission_search_objects');
		Capsule::schema()->drop('submission_search_keyword_list');
		Capsule::schema()->drop('query_participants');
		Capsule::schema()->drop('queries');
		Capsule::schema()->drop('subeditor_submission_group');
		Capsule::schema()->drop('submission_comments');
		Capsule::schema()->drop('edit_decisions');
		Capsule::schema()->drop('publication_settings');
		Capsule::schema()->drop('submission_settings');
		Capsule::schema()->drop('submissions');
	}
}

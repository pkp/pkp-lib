<?php

/**
 * @file classes/migration/SubmissionsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
			$table->bigInteger('current_publication_id')->nullable();
			$table->datetime('date_last_activity')->nullable();
			$table->datetime('date_submitted')->nullable();
			$table->datetime('last_modified')->nullable();
			$table->bigInteger('stage_id')->default(WORKFLOW_STAGE_ID_SUBMISSION);
			$table->string('locale', 14)->nullable();

			import('lib.pkp.classes.submission.PKPSubmission'); // for constant
			$table->smallInteger('status')->default(STATUS_QUEUED);

			$table->smallInteger('submission_progress')->default(1);
			//  Used in OMP only; should not be null there
			$table->smallInteger('work_type')->default(0)->nullable();
			$table->index(['context_id'], 'submissions_context_id');
			$table->index(['current_publication_id'], 'submissions_publication_id');
		});

		// Submission metadata
		Capsule::schema()->create('submission_settings', function (Blueprint $table) {
			$table->bigInteger('submission_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->mediumText('setting_value')->nullable();
			$table->index(['submission_id'], 'submission_settings_submission_id');
			$table->unique(['submission_id', 'locale', 'setting_name'], 'submission_settings_pkey');
		});

		// publication metadata
		Capsule::schema()->create('publication_settings', function (Blueprint $table) {
			$table->bigInteger('publication_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->index(['publication_id'], 'publication_settings_publication_id');
			$table->unique(['publication_id', 'locale', 'setting_name'], 'publication_settings_pkey');
		});
		// Add partial index (DBMS-specific)
		switch (Capsule::connection()->getDriverName()) {
			case 'mysql': Capsule::connection()->unprepared('CREATE INDEX publication_settings_name_value ON publication_settings (setting_name(50), setting_value(150))'); break;
			case 'pgsql': Capsule::connection()->unprepared("CREATE INDEX publication_settings_name_value ON publication_settings (setting_name, setting_value) WHERE setting_name IN ('indexingState', 'medra::registeredDoi', 'datacite::registeredDoi', 'pub-id::publisher-id')"); break;
		}

		// Authors for submissions.
		Capsule::schema()->create('authors', function (Blueprint $table) {
			$table->bigInteger('author_id')->autoIncrement();
			$table->string('email', 90);
			$table->smallInteger('include_in_browse')->default(1);
			$table->bigInteger('publication_id');
			$table->float('seq', 8, 2)->default(0);
			$table->bigInteger('user_group_id')->nullable();
			$table->index(['publication_id'], 'authors_publication_id');
		});

		// Language dependent author metadata.
		Capsule::schema()->create('author_settings', function (Blueprint $table) {
			$table->bigInteger('author_id');
			$table->string('locale', 14)->default('');
			$table->string('setting_name', 255);
			$table->text('setting_value')->nullable();
			$table->index(['author_id'], 'author_settings_author_id');
			$table->unique(['author_id', 'locale', 'setting_name'], 'author_settings_pkey');
		});

		// Editor decisions.
		Capsule::schema()->create('edit_decisions', function (Blueprint $table) {
			$table->bigInteger('edit_decision_id')->autoIncrement();
			$table->bigInteger('submission_id');
			$table->bigInteger('review_round_id');
			$table->bigInteger('stage_id')->nullable();
			$table->smallInteger('round');
			$table->bigInteger('editor_id');
			$table->smallInteger('decision');
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
			$table->bigInteger('assoc_id');
			$table->bigInteger('author_id');
			$table->text('comment_title');
			$table->text('comments')->nullable();
			$table->datetime('date_posted')->nullable();
			$table->datetime('date_modified')->nullable();
			$table->smallInteger('viewable')->nullable();
			$table->index(['submission_id'], 'submission_comments_submission_id');
		});

		// Assignments of sub editors to submission groups.
		Capsule::schema()->create('subeditor_submission_group', function (Blueprint $table) {
			$table->bigInteger('context_id');
			$table->bigInteger('assoc_id');
			$table->bigInteger('assoc_type');
			$table->bigInteger('user_id');
			$table->index(['context_id'], 'section_editors_context_id');
			$table->index(['assoc_id', 'assoc_type'], 'subeditor_submission_group_assoc_id');
			$table->index(['user_id'], 'subeditor_submission_group_user_id');
			$table->unique(['context_id', 'assoc_id', 'assoc_type', 'user_id'], 'section_editors_pkey');
		});

		// queries posted on submission workflow
		Capsule::schema()->create('queries', function (Blueprint $table) {
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
		Capsule::schema()->create('query_participants', function (Blueprint $table) {
			$table->bigInteger('query_id');
			$table->bigInteger('user_id');
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
			$table->integer('type')->comment('Type of item. E.g., abstract, fulltext, etc.');
			$table->bigInteger('assoc_id')->comment('Optional ID of an associated record (e.g., a file_id)')->nullable();
			$table->index(['submission_id'], 'submission_search_object_submission');
		});

		// Keyword occurrences for each indexed object.
		Capsule::schema()->create('submission_search_object_keywords', function (Blueprint $table) {
			$table->bigInteger('object_id');
			$table->bigInteger('keyword_id');
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
		Capsule::schema()->drop('author_settings');
		Capsule::schema()->drop('authors');
		Capsule::schema()->drop('publication_settings');
		Capsule::schema()->drop('submission_settings');
		Capsule::schema()->drop('submissions');
	}
}

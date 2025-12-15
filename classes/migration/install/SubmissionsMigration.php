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

use APP\core\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
use PKP\author\creditRole\CreditRoleDegree;
use PKP\core\Core;
use PKP\editorialTask\enums\EditorialTaskDueInterval;
use PKP\editorialTask\enums\EditorialTaskType;
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
            $table->string('locale', 28)->nullable();

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

            $table->string('locale', 28)->default('');
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

            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['publication_id', 'locale', 'setting_name'], 'publication_settings_unique');
        });
        // Add partial index (DBMS-specific)
        match (DB::getDriverName()) {
            'mysql', 'mariadb' =>
                DB::unprepared('CREATE INDEX publication_settings_name_value ON publication_settings (setting_name(50), setting_value(150))'),
            'pgsql' =>
                DB::unprepared("CREATE INDEX publication_settings_name_value ON publication_settings (setting_name, setting_value) WHERE setting_name IN ('indexingState', 'medra::registeredDoi', 'datacite::registeredDoi', 'pub-id::publisher-id')")
        };

        // Authors for submissions.
        Schema::create('authors', function (Blueprint $table) {
            $table->comment('The authors of a publication.');
            $table->bigInteger('author_id')->autoIncrement();
            $table->string('email', 90)->nullable();
            $table->smallInteger('include_in_browse')->default(1);

            // The foreign key relationship on this table is defined with the publications table.
            $table->bigInteger('publication_id');

            $table->float('seq')->default(0);

            $table->enum('contributor_type', ContributorType::getTypes())->default(ContributorType::PERSON->getName());
        });

        // Language dependent author metadata.
        Schema::create('author_settings', function (Blueprint $table) {
            $table->comment('More data about authors, including localized properties such as their name and affiliation.');
            $table->bigIncrements('author_setting_id');
            $table->bigInteger('author_id');
            $table->foreign('author_id', 'author_settings_author_id')->references('author_id')->on('authors')->onDelete('cascade');
            $table->index(['author_id'], 'author_settings_author_id');

            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['author_id', 'locale', 'setting_name'], 'author_settings_unique');
        });

        // Credit roles listing table
        Schema::create('credit_roles', function (Blueprint $table) {
            $table->comment('The list of the CRediT Roles');
            $table->bigInteger('credit_role_id')->autoIncrement();
            $table->string('credit_role_identifier', 255);
            $table->unique(['credit_role_identifier'], 'credit_role_identifier_unique');
        });

        // Load en json, and fill table with values
        $creditRoles = json_decode(file_get_contents(Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/creditRoles/translations/en.json') ?: '', true);
        if (!$creditRoles) {
            throw new \Exception(PKP_LIB_PATH . '/lib/creditRoles/translations/en.json not found');
        }
        $creditRolesData = Arr::map(array_keys($creditRoles['translations'] ?? []), fn (string $role): array => ['credit_role_identifier' => $role]);
        DB::table('credit_roles')
            ->insert($creditRolesData);

        // Contributor roles
        Schema::create('contributor_roles', function (Blueprint $table) {
            $table->comment('The list of the contributor roles');
            $table->bigInteger('contributor_role_id')->autoIncrement();
            $table->bigInteger('context_id');
            $table->enum('contributor_role_identifier', ContributorRoleIdentifier::getRoles());
        });

        // Contributor role settings
        Schema::create('contributor_role_settings', function (Blueprint $table) {
            $table->comment('Contributor role settings');
            $table->bigInteger('contributor_role_setting_id')->autoIncrement();
            $table->bigInteger('contributor_role_id');
            $table->string('setting_name', 255);
            $table->string('setting_value', 255)->nullable();
            $table->string('locale', 28);
            $table->unique(['contributor_role_id', 'setting_name', 'locale'], 'contributor_role_id_setting_name_locale_unique');
            $table->foreign('contributor_role_id', 'contributor_role_id_settings_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
        });

        // Credit and contributor roles
        Schema::create('credit_contributor_roles', function (Blueprint $table) {
            $table->comment('The CRediT Roles and the degrees of contributors, and contributor roles');
            $table->bigInteger('credit_contributor_role_id')->autoIncrement();
            $table->bigInteger('contributor_id');
            $table->bigInteger('credit_role_id')->nullable();
            $table->enum('credit_degree', CreditRoleDegree::getDegrees())->nullable();
            $table->bigInteger('contributor_role_id')->nullable();
            $table->foreign('contributor_id', 'contributor_id_author_id_foreign')->references('author_id')->on('authors')->onDelete('cascade');
            $table->foreign('credit_role_id', 'credit_role_id_foreign')->references('credit_role_id')->on('credit_roles')->onDelete('cascade');
            $table->foreign('contributor_role_id', 'contributor_role_id_foreign')->references('contributor_role_id')->on('contributor_roles')->onDelete('cascade');
            $table->unique(['contributor_id', 'credit_role_id'], 'contributor_id_credit_role_id_unique');
            $table->unique(['contributor_id', 'contributor_role_id'], 'contributor_id_contributor_role_id_unique');
        });

        // Add contstraint to only allow either credit or contributor role per row
        DB::statement('
            ALTER TABLE credit_contributor_roles
            ADD CONSTRAINT check_xor_credit_contributor_role
            CHECK ((credit_role_id IS NOT NULL AND contributor_role_id IS NULL) OR (contributor_role_id IS NOT NULL AND credit_role_id IS NULL))
        ');

        // Editor decisions.
        Schema::create('edit_decisions', function (Blueprint $table) {
            $table->comment('Editorial decisions recorded on a submission, such as decisions to accept or decline the submission, as well as decisions to send for review, send to copyediting, request revisions, and more.');
            $table->bigInteger('edit_decision_id')->autoIncrement();

            $table->bigInteger('submission_id');
            $table->foreign('submission_id', 'edit_decisions_submission_id')->references('submission_id')->on('submissions')->onDelete('cascade');
            $table->index(['submission_id'], 'edit_decisions_submission_id');

            $table->bigInteger('publication_id');

            // Foreign key constraint is declared with review_rounds
            $table->bigInteger('review_round_id')->nullable();

            $table->bigInteger('stage_id')->nullable();
            $table->smallInteger('round')->nullable();

            $table->bigInteger('editor_id');
            $table->foreign('editor_id', 'edit_decisions_editor_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['editor_id'], 'edit_decisions_editor_id');

            $table->smallInteger('decision')->comment('A numeric constant indicating the decision that was taken. Possible values are listed in the Decision class.');
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

        // Tasks and discussions on submission workflow
        Schema::create('edit_tasks', function (Blueprint $table) {
            $table->comment('Editorial tasks and discussions, usually related to a submission, created by editors, authors and other editorial staff.');
            $table->bigInteger('edit_task_id')->autoIncrement();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('stage_id');
            $table->float('seq')->default(0);
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();
            $table->smallInteger('closed')->default(0);
            $table->dateTime('date_due')->nullable();
            $table->bigInteger('created_by')->nullable()->default(null);
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->bigInteger('started_by')->nullable()->default(null);
            $table->foreign('started_by')->references('user_id')->on('users');
            $table->unsignedSmallInteger('type')->default(1); // 1 - discussion, 2 - task
            $table->dateTime('date_started')->nullable();
            $table->dateTime('date_closed')->nullable();
            $table->string('title')->nullable();
            // if task was auto-created from a template, keep source template ID. manual tasks will keep this NULL.
            $table->unsignedBigInteger('edit_task_template_id')->nullable();
            // index to check if a task from this template exists and for filtering by template.
            $table->index(['edit_task_template_id'], 'edit_tasks_edit_task_template_id');
            $table->index(['assoc_type', 'assoc_id'], 'edit_tasks_assoc_id');
        });

        Schema::create('edit_task_participants', function (Blueprint $table) {
            $table->comment('The users assigned to a task or discussion.');
            $table->bigIncrements('edit_task_participant_id');
            $table->bigInteger('edit_task_id');
            $table->foreign('edit_task_id')->references('edit_task_id')->on('edit_tasks')->cascadeOnDelete();
            $table->index(['edit_task_id'], 'edit_task_participants_edit_task_id');

            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->boolean('is_responsible')->default(false);
            $table->index(['user_id'], 'edit_task_participants_user_id');
            $table->unique(['edit_task_id', 'user_id'], 'edit_task_participants_unique');
        });

        Schema::create('edit_task_settings', function (Blueprint $table) {
            $table->comment('More data about editorial tasks, including localized properties such as the name.');
            $table->unsignedBigInteger('edit_task_setting_id')->autoIncrement()->primary();
            $table->bigInteger('edit_task_id');
            $table->foreign('edit_task_id')
                ->references('edit_task_id')
                ->on('edit_tasks')
                ->cascadeOnDelete();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['edit_task_id', 'locale', 'setting_name'], 'edit_task_settings_unique');
            $table->index(['edit_task_id'], 'edit_task_settings_edit_task_id');
        });

        Schema::create('edit_task_templates', function (Blueprint $table) {
            $table->comment('Represents templates for the editorial tasks.');
            $table->unsignedBigInteger('edit_task_template_id')->autoIncrement()->primary();
            $table->unsignedSmallInteger('stage_id');
            $table->string('title', 255); // template title

            // templates are journal/context scoped
            $table->bigInteger('context_id')->comment('Journal/press ID for scoping templates');
            $contextDao = Application::getContextDAO();
            $table->foreign('context_id', 'edit_task_templates_context_fk')
                ->references($contextDao->primaryKeyColumn)
                ->on($contextDao->tableName)
                ->onDelete('cascade');
            $table->index(['context_id'], 'edit_task_templates_context_id_idx');

            $table->boolean('include')->default(false);

            $table->enum('due_interval', array_column(EditorialTaskDueInterval::cases(), 'value'))
                ->nullable()
                ->comment('Interval after which the task is due, from the time it is created.');
            $table->enum('type', array_column(EditorialTaskType::cases(), 'value'))->default(EditorialTaskType::DISCUSSION);
            $table->text('description')->nullable();
            $table->boolean('restrict_to_user_groups')->default(false)
                ->comment('Whether the template is restricted to user groups defined in the many to many relationship.');

            $table->timestamps();
        });

        Schema::create('edit_task_template_settings', function (Blueprint $table) {
            $table->comment('includes additional and multilingual data about the editorial task templates.');
            $table->id('edit_task_template_setting_id');
            $table->unsignedBigInteger('edit_task_template_id');
            $table->foreign('edit_task_template_id')
                ->references('edit_task_template_id')
                ->on('edit_task_templates')
                ->cascadeOnDelete();
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->unique(['edit_task_template_id', 'locale', 'setting_name'], 'edit_task__template_settings_unique');
            $table->index(['edit_task_template_id'], 'edit_task_template_settings_edit_task_id');
        });

        Schema::create('edit_task_template_user_groups', function (Blueprint $table) {
            $table->comment('Links task templates to user groups.');
            $table->unsignedBigInteger('edit_task_template_id');
            $table->bigInteger('user_group_id');

            $table->primary(['edit_task_template_id', 'user_group_id'], 'ett_ug_pk');

            $table->foreign('edit_task_template_id', 'ett_ug_template_fk')
                ->references('edit_task_template_id')->on('edit_task_templates')
                ->onDelete('cascade');

            $table->foreign('user_group_id', 'ett_ug_user_group_fk')
                ->references('user_group_id')->on('user_groups')
                ->onDelete('cascade');

            $table->index(['user_group_id'], 'ett_ug_user_group_idx');
        });


    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('edit_task_template_user_groups');
        Schema::drop('edit_task_template_settings');
        Schema::drop('edit_task_templates');
        Schema::drop('edit_task_participants');
        Schema::drop('edit_task_settings');
        Schema::drop('edit_tasks');
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

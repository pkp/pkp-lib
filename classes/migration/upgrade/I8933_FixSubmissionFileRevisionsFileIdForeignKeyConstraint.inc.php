<?php
/**
 * @file classes/migration/upgrade/I8933_FixSubmissionFileRevisionsFileIdForeignKeyConstraint.inc.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8933_FixSubmissionFileRevisionsFileIdForeignKeyConstraint
 * @brief Cascade delete and update for `fileId` in submission_file_revisions table.
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class I8933_FixSubmissionFileRevisionsFileIdForeignKeyConstraint extends Migration
{
	/**
	 * Run the migration.
	 */
	public function up()
	{
		Capsule::schema()->table('submission_file_revisions', function (Blueprint $table) {
			$table->dropForeign(['file_id']);
			$table->foreign('file_id')->references('file_id')->on('files')->onDelete('cascade')->onUpdate('cascade');
		});
	}

	/**
	 * Reverse the migration.
	 */
	public function down()
	{
		Capsule::schema()->table('submission_file_revisions', function (Blueprint $table) {
			$table->dropForeign(['file_id']);
			$table->foreign('file_id')->references('file_id')->on('files');
		});
	}
}

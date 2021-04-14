<?php

/**
 * @file classes/migration/upgrade/3_4_0/I6759_RenameVariables.inc.php
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class I6759_RenameVariables extends Migration {
	/**
	 * Run the migrations.
	 * @return void
	 */
	public function up() {

		// pkp/pkp-lib#6759 rename folders
		$this->_renameFolders();

		// pkp/pkp-lib#6759 rename values
		$this->_updateValuesWithReference();

		// pkp/pkp-lib#6759 rename tables
		$this->_updateTablesWithReference();


	}

	/**
	 * Reverse the downgrades
	 * @return void
	 */
	public function down() {
		throw new PKP\install\DowngradeNotSupportedException();
	}


	/**
	 * @return void
	 * @brief rename tables pkp/pkp-lib#6759
	 */
	private function _updateTablesWithReference() {

		Schema::rename('journals', 'servers');
		Schema::table('servers', function (Blueprint $table) {
			$table->dropIndex('journals_path');
			$table->renameColumn('journal_id', 'server_id');
			$table->float('seq', 8, 2)->comment('Used to order lists of servers')->change();
			$table->tinyInteger('enabled')->comment('Controls whether or not the server is considered "live" and will appear on the website. (Note that disabled servers may still be accessible, but only if the user knows the URL.)')->change();
			$table->unique(['path'], 'servers_path');
		});

		Schema::rename('journal_settings', 'server_settings');
		Schema::table('server_settings', function (Blueprint $table) {
			$table->dropIndex('journal_settings_journal_id');
			$table->dropIndex('journal_settings_pkey');
			$table->renameColumn('journal_id', 'server_id');
			$table->index(['server_id'], 'server_settings_server_id');
			$table->unique(['server_id', 'locale', 'setting_name'], 'server_settings_pkey');
		});

		Schema::table('sections', function (Blueprint $table) {
			$table->dropIndex('sections_journal_id');
			$table->renameColumn('journal_id', 'server_id');
			$table->index(['server_id'], 'sections_server_id');
		});

		Schema::table('submission_tombstones', function (Blueprint $table) {
			$table->dropIndex('submission_tombstones_journal_id');
			$table->renameColumn('journal_id', 'server_id');
			$table->index(['server_id'], 'submission_tombstones_server_id');
		});

	}


	/**
	 * @return void
	 * @brief rename values pkp/pkp-lib#6759
	 */
	private function _updateValuesWithReference() {
		DB::statement("UPDATE filters SET class_name = 'plugins.importexport.crossref.filter.PreprintCrossrefXmlFilter', display_name = 'Crossref XML preprint export' WHERE class_name = 'plugins.importexport.crossref.filter.ArticleCrossrefXmlFilter'");
		DB::statement("UPDATE filters SET class_name = 'plugins.metadata.dc11.filter.Dc11SchemaPreprintAdapter' WHERE class_name = 'plugins.metadata.dc11.filter.Dc11SchemaArticleAdapter'");
		DB::statement("UPDATE filter_groups SET symbolic = 'preprint=>dc11', display_name = 'plugins.metadata.dc11.preprintAdapter.displayName', description = 'plugins.metadata.dc11.preprintAdapter.description', input_type = 'class::classes.submission.Submission', output_type = 'metadata::plugins.metadata.dc11.schema.Dc11Schema(PREPRINT)' WHERE symbolic = 'article=>dc11'");
		DB::statement("UPDATE filter_groups SET symbolic = 'preprint=>crossref-xml', input_type = 'class::classes.submission.Submission[]', output_type = 'xml::schema(https://www.crossref.org/schemas/crossref4.4.0.xsd)' WHERE symbolic = 'article=>crossref-xml'");
		DB::statement("UPDATE files SET path = REPLACE(path,'journals/','contexts/')");
		DB::statement("UPDATE files SET path = REPLACE(path,'/articles/','/submissions/')");
	}


	/**
	 * @return void
	 * @brief rename journals folder to contexts in files and public pkp/pkp-lib#6759
	 */
	private function _renameFolders() {
		$filesDir = Config::getVar('files', 'files_dir');

		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll(true)->toArray();
		foreach ($contexts as $context) {

			// Move library files folder to journals folder
			$oldFolder = $filesDir . '/contexts/' . $context->getId() . '/library/';
			if (is_dir($oldFolder)){
				$newFolder = $filesDir . '/journals/' . $context->getId() . '/library/';
				rename($oldFolder, $newFolder);
			}

			// Rename articles folder to preprints
			$oldFolder = $filesDir . '/journals/' . $context->getId() . '/articles/';
			if (is_dir($oldFolder)){
				$newFolder = $filesDir . '/journals/' . $context->getId() . '/submissions/';
				rename($oldFolder, $newFolder);
			}

		}

		// Rename the current contexts folder and store for backup
		$oldContextFolder = $filesDir . '/contexts/';
		$oldContextBackUpFolder = $filesDir . '/contextsOld/';
		rename($oldContextFolder, $oldContextBackUpFolder);

		// Rename journals folder to contexts folder in files
		rename($filesDir . '/journals/', $filesDir . '/contexts/');

		// Rename journals folder to contexts folder in public
		$publicFilesDir = Config::getVar('files', 'public_files_dir');
		rename($publicFilesDir . '/journals/', $publicFilesDir . '/contexts/');

	}

}

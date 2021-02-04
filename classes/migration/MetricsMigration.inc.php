<?php

/**
 * @file classes/migration/MetricsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetricsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class MetricsMigration extends Migration {
        /**
         * Run the migrations.
         * @return void
         */
        public function up() {
		// OLAP statistics data table.
		Capsule::schema()->create('metrics', function (Blueprint $table) {
			$table->string('load_id', 255);
			$table->bigInteger('context_id');
			$table->bigInteger('pkp_section_id')->nullable();
			$table->bigInteger('assoc_object_type')->nullable();
			$table->bigInteger('assoc_object_id')->nullable();
			$table->bigInteger('submission_id')->nullable();
			$table->bigInteger('representation_id')->nullable();
			$table->bigInteger('assoc_type');
			$table->bigInteger('assoc_id');
			$table->string('day', 8)->nullable();
			$table->string('month', 6)->nullable();
			$table->smallInteger('file_type')->nullable();
			$table->string('country_id', 2)->nullable();
			$table->string('region', 2)->nullable();
			$table->string('city', 255)->nullable();
			$table->string('metric_type', 255);
			$table->integer('metric');
			$table->index(['load_id'], 'metrics_load_id');
			$table->index(['metric_type', 'context_id'], 'metrics_metric_type_context_id');
			$table->index(['metric_type', 'submission_id', 'assoc_type'], 'metrics_metric_type_submission_id_assoc_type');
			$table->index(['metric_type', 'context_id', 'assoc_type', 'assoc_id'], 'metrics_metric_type_submission_id_assoc');
		});
	}

	/**
	 * Reverse the migration.
	 * @return void
	 */
	public function down() {
		Capsule::schema()->drop('metrics');
	}
}

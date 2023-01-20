<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7725_DecisionConstantsUpdate.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7725_DecisionConstantsUpdate
 * @brief Editorial decision constant sync up accross all application
 *
 * @see https://github.com/pkp/pkp-lib/issues/7725
 */

namespace PKP\migration\upgrade\v3_4_0;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use Throwable;

abstract class I7725_DecisionConstantsUpdate extends Migration
{
    /**
     * Get the decisions constants mappings
     *
     */
    abstract public function getDecisionMappings(): array;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->configureUpdatedAtColumn();

        try {
            DB::beginTransaction();

            collect($this->getDecisionMappings())
                ->each(
                    fn ($decisionMapping) => DB::table('edit_decisions')
                        ->when(
                            isset($decisionMapping['stage_id']) && !empty($decisionMapping['stage_id']),
                            fn ($query) => $query->whereIn('stage_id', $decisionMapping['stage_id'])
                        )
                        ->where('decision', $decisionMapping['current_value'])
                        ->whereNull('updated_at')
                        ->update([
                            'decision' => $decisionMapping['updated_value'],
                            'updated_at' => Carbon::now(),
                        ])
                );

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            $this->_installer->log($exception->__toString());
        }

        $this->removeUpdatedAtColumn();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->configureUpdatedAtColumn();

        try {
            DB::beginTransaction();

            collect($this->getDecisionMappings())
                ->each(
                    fn ($decisionMapping) => DB::table('edit_decisions')
                        ->when(
                            isset($decisionMapping['stage_id']) && !empty($decisionMapping['stage_id']),
                            fn ($query) => $query->whereIn('stage_id', $decisionMapping['stage_id'])
                        )
                        ->where('decision', $decisionMapping['updated_value'])
                        ->whereNull('updated_at')
                        ->update([
                            'decision' => $decisionMapping['current_value'],
                            'updated_at' => Carbon::now(),
                        ])
                );

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            $this->_installer->log($exception->__toString());
        }

        $this->removeUpdatedAtColumn();
    }

    /**
     * Set the temporary updated_at column with NULL value
     */
    protected function configureUpdatedAtColumn(): void
    {
        if (!Schema::hasColumn('edit_decisions', 'updated_at')) {
            Schema::table('edit_decisions', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });

            return;
        }

        DB::table('edit_decisions')->update(['updated_at' => null]);

        return;
    }

    /**
     * Drop the temporary updated_at column
     */
    protected function removeUpdatedAtColumn(): void
    {
        if (Schema::hasColumn('edit_decisions', 'updated_at')) {
            Schema::table('edit_decisions', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
}

<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10362_EventLogEditorNames.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10362_EventLogEditorNames
 *
 * @brief Adds missing editorName settings to the event log.
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\facades\Repo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10362_EventLogEditorNames extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $idsToUpdate = DB::table('event_log as e')
            ->select('e.log_id', 'e.user_id')
            ->leftJoin('event_log_settings as es', function (JoinClause $join) {
                $join->on('es.log_id', '=', 'e.log_id')
                    ->where('es.setting_name', '=', 'editorName');
            })
            ->whereNull('es.log_id')
            ->whereIn('e.event_type', [
                0x30000003, // PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_DECISION
                0x30000004 // PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_RECOMMENDATION
            ])
            ->orderBy('e.log_id')
            ->pluck('e.user_id', 'e.log_id');

        $inserts = [];

        if ($idsToUpdate) {
            foreach ($idsToUpdate as $eventLogId => $userId) {
                $editorName = Repo::user()->get($userId)?->getFullName();

                if ($editorName) {
                    $inserts[] = [
                        'log_id' => $eventLogId,
                        'locale' => '',
                        'setting_name' => 'editorName',
                        'setting_value' => $editorName
                    ];
                }
            }
        }

        if ($inserts) {
            foreach (array_chunk($inserts, 1000) as $chunk) {
                DB::table('event_log_settings')->insert($chunk);
            }
        }
    }

    /**
     * Reverse the migration.
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

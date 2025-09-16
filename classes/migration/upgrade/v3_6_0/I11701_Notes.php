<?php

/**
 * @file I11702_Notes.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11325_UserComments
 *
 * @brief Migration to add table structures for user comments.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use stdClass;

class I11701_Notes extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('edit_tasks', function (Blueprint $table) {
            $table->bigInteger('started_by')->nullable()->default(null);
            $table->foreign('started_by')->references('user_id')->on('users')->cascadeOnDelete();
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('is_headnote')->default(false);
        });

        /**
         * Get all headnotes for each task or discussion and move titles to the edit_tasks table
         * according to the code, note titles are used only in headnotes associated with queries
         * it should be safe to transfer them and remove
         */
        DB::table('notes as n')->where(
            'n.date_created',
            fn (Builder $query) => $query
                ->selectRaw('MIN(nt.date_created)')
                ->from('notes as nt')
                ->whereColumn('nt.assoc_id', 'n.assoc_id')
                ->whereColumn('nt.assoc_type', 'n.assoc_type')
        )->orderBy('n.note_id')->each(function (stdClass $note) {
            DB::table('notes')->where('note_id', $note->note_id)->update([
                'is_headnote' => true,
            ]);

            if (!$note->title) {
                return;
            }

            if ($note->assoc_type != 0x010000a) { // ASSOC_TYPE_QUERY
                trigger_error("Tried to migrate the title of the note with assoc_type {$note->assoc_type}", E_USER_WARNING);
                return;
            }

            DB::table('edit_tasks')
                ->where('edit_task_id', '=', $note->assoc_id)
                ->update([
                    'title' => $note->title,
                ]);
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

<?php

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I4142_AddMessageIdToNotesAndReviewAssignments extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('notes', 'message_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->string('message_id', 255)->nullable();
                $table->index(['message_id'], 'notes_message_id');
            });
        }

        if (!Schema::hasColumn('review_assignments', 'message_id')) {
            Schema::table('review_assignments', function (Blueprint $table) {
                $table->string('message_id', 255)->nullable();
                $table->index(['message_id'], 'review_assignments_message_id');
            });
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

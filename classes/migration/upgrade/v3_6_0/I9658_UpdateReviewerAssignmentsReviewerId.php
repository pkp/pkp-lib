<?php

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9658_UpdateReviewerAssignmentsReviewerId extends Migration
{

    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->bigInteger('reviewer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->bigInteger('reviewer_id')->nullable(false)->change();
            $table->foreign('reviewer_id')->references('user_id')->on('users');
        });
    }
}

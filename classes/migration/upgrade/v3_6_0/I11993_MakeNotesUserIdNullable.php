<?php

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I11993_MakeNotesUserIdNullable extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
        });

    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable(false)->change();
        });
    }
}

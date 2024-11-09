<?php

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I10403_EmailTemplateRoleAccess extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('email_template_user_group_access', function (Blueprint $table) {
            $table->bigInteger('email_template_user_group_access_id')->autoIncrement();
            $table->string('email_key', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('user_group_id')->nullable();

            $table->foreign('context_id')->references('journal_id')->on('journals')->onDelete('cascade');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('email_template_user_group_access');
    }
}

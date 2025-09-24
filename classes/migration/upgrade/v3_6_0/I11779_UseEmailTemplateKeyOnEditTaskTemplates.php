<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11779_UseEmailTemplateKeyOnEditTaskTemplates.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.

 * @class I11779_UseEmailTemplateKeyOnEditTaskTemplates.php
 * @brief Switch task templates to reference email templates by key
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I11779_UseEmailTemplateKeyOnEditTaskTemplates extends Migration
{
    public function up(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('edit_task_templates', 'email_template_key')) {
                $table->string('email_template_key', 255)->nullable()->after('include');
            }
            // new index for key lookups in a context
            $table->index(['context_id', 'email_template_key'], 'edit_task_templates_context_email_key_idx');
        });

        // backfill key from existing email_template_id (where possible)
        DB::table('edit_task_templates')
            ->whereNotNull('email_template_id')
            ->orderBy('edit_task_template_id')
            ->chunkById(500, function ($rows) {
                $emailIds = collect($rows)->pluck('email_template_id')->unique()->values();
                if ($emailIds->isEmpty()) {
                    return;
                }

                // email_id => email_key
                $map = DB::table('email_templates')
                    ->whereIn('email_id', $emailIds)
                    ->pluck('email_key', 'email_id');

                foreach ($rows as $row) {
                    $key = $map[$row->email_template_id] ?? null;
                    if ($key) {
                        DB::table('edit_task_templates')
                            ->where('edit_task_template_id', $row->edit_task_template_id)
                            ->update(['email_template_key' => $key]);
                    }
                }
            }, 'edit_task_template_id');

        // drop FK and old column
        Schema::table('edit_task_templates', function (Blueprint $table) {
            try { $table->dropForeign(['email_template_id']); } catch (\Throwable $e) {}
        });
        Schema::table('edit_task_templates', function (Blueprint $table) {
            if (Schema::hasColumn('edit_task_templates', 'email_template_id')) {
                $table->dropColumn('email_template_id');
            }
        });
    }

    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}

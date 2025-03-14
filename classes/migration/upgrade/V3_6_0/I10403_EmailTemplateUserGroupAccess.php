<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I10403_EmailTemplateUserGroupAccess.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10403_EmailTemplateUserGroupAccess
 *
 * @brief Adds the email_template_user_group_access table to allow email template access restriction
 */

namespace PKP\migration\upgrade\V3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\emailTemplate\EmailTemplateAccessGroup;
use PKP\migration\Migration;

class I10403_EmailTemplateUserGroupAccess extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contextDao = \APP\core\Application::getContextDAO();
        Schema::create('email_template_user_group_access', function (Blueprint $table) use ($contextDao) {
            $table->bigInteger('email_template_user_group_access_id')->autoIncrement()->comment('Primary key');
            $table->string('email_key', 255)->comment("The email template's unique key");
            $table->bigInteger('context_id')->comment('The ID of the context for which the user group assignment is defined.');
            $table->bigInteger('user_group_id')->nullable()->comment('The user group ID. A null value indicates that the email template is accessible to all user groups.');

            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade')->onUpdate('cascade');
        });

        $contextIds = app()->get('context')->getIds();

        if (!empty($contextIds)) {
            $defaultTemplateKeys = DB::table('email_templates_default_data')->distinct()->select('email_key')->pluck('email_key')->all();
            $alternateTemplates = DB::table('email_templates')->select(['context_id', 'email_key'])->get()->toArray();

            $data = [];

            // Record any existing default templates as unrestricted for existing Contexts
            foreach ($defaultTemplateKeys as $defaultTemplateKey) {
                foreach ($contextIds as $contextId) {
                    $data[$defaultTemplateKey] =
                        ['email_key' => $defaultTemplateKey,
                            'context_id' => $contextId,
                            'user_group_id' => null
                        ];
                }
            }

            // For any existing alternate template, register it as unrestricted within its assigned context
            foreach ($alternateTemplates as $template) {
                foreach ($contextIds as $contextId) {
                    if ($contextId === $template->context_id) {
                        $data[$template->email_key] = [
                            'email_key' => $template->email_key,
                            'context_id' => $contextId,
                            'user_group_id' => null
                        ];

                        break;
                    }
                }
            }

            EmailTemplateAccessGroup::insert(array_values($data));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('email_template_user_group_access');
    }
}

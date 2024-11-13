<?php

namespace PKP\migration\upgrade\V3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\context\Context;
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
            $table->string('email_key', 255)->comment("The email template's unique key.");
            $table->bigInteger('context_id')->comment('Identifier for the context for which the user group assignment occurs.');
            $table->bigInteger('user_group_id')->nullable()->comment('The user group ID.');

            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName)->onDelete('cascade')->onDelete('cascade');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade')->onDelete('cascade');
        });

        $contextIds = array_map(fn (Context $context) => $context->getId(), $contextDao->getAll()->toArray());

        if (!empty($contextIds)) {
            DB::table('email_template_user_group_access')->select('*')->get();

            $defaultTemplateKeys = DB::table('email_templates_default_data')->select()->pluck('email_key')->all();
            $alternateTemplates = DB::table('email_templates')->select(['context_id', 'email_key'])->get()->toArray();

            $data = [];

            // Record any existing default templates as unrestricted for existing Contexts
            foreach ($defaultTemplateKeys as $defaultTemplateKey) {
                foreach ($contextIds as $contextId) {
                    $data[] = [
                        'email_key' => $defaultTemplateKey,
                        'context_id' => $contextId,
                        'user_group_id' => null
                    ];
                }
            }

            // For any existing alternate template, register it as unrestricted within its assigned context
            foreach ($alternateTemplates as $template) {
                foreach ($contextIds as $contextId) {
                    if ($contextId === $template->context_id) {
                        $data[] = [
                            'email_key' => $template->email_key,
                            'context_id' => $contextId,
                            'user_group_id' => null
                        ];
                    }
                }
            }

            EmailTemplateAccessGroup::insert($data);
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

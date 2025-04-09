<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I11125_UpdateEmailTemplateVariables.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11125_UpdateEmailTemplateVariables
 *
 * @brief Migration to update Email Template variable names
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I11125_UpdateEmailTemplateVariables extends \PKP\migration\Migration
{
    public function up(): void
    {
        // Update template variables
        $this->renameTemplateVariables($this->oldToNewVariablesMap());
    }

    public function down(): void
    {
        $newToOldVariableMap = array_map(function ($variablesMap) {
            return array_flip($variablesMap);
        }, $this->oldToNewVariablesMap());

        $this->renameTemplateVariables($newToOldVariableMap);
    }

    /**
     * Replaces email template variables in templates' subject and body
     */
    protected function renameTemplateVariables(array $oldNewVariablesMap): void
    {
        foreach ($oldNewVariablesMap as $emailKey => $variablesMap) {
            $existingVariables = [];
            $replacementsVariables = [];

            foreach ($variablesMap as $key => $value) {
                $existingVariables[] = '/\{\$' . $key . '\}/';
                $replacementsVariables[] = '{$' . $value . '}';
            }

            // Default templates
            $data = DB::table('email_templates_default_data')->where('email_key', $emailKey)->get();

            $data->each(function (object $entry) use ($existingVariables, $replacementsVariables) {
                $subject = preg_replace($existingVariables, $replacementsVariables, $entry->subject);
                $body = preg_replace($existingVariables, $replacementsVariables, $entry->body);
                DB::table('email_templates_default_data')
                    ->where('email_key', $entry->{'email_key'})
                    ->where('locale', $entry->{'locale'})
                    ->update(['subject' => $subject, 'body' => $body]);
            });

            // Custom templates
            $customData = DB::table('email_templates')->where('email_key', $emailKey)->get();
            $customData->each(function (object $customEntry) use ($existingVariables, $replacementsVariables) {
                $emailSettingsRows = DB::table('email_templates_settings')->where('email_id', $customEntry->{'email_id'})->get();
                foreach ($emailSettingsRows as $emailSettingsRow) {
                    $value = preg_replace($existingVariables, $replacementsVariables, $emailSettingsRow->{'setting_value'});
                    DB::table('email_templates_settings')
                        ->where('email_id', $emailSettingsRow->{'email_id'})
                        ->where('locale', $emailSettingsRow->{'locale'})
                        ->where('setting_name', $emailSettingsRow->{'setting_name'})
                        ->update(['setting_value' => $value]);
                }
            });
        }
    }

    /**
     * @return array [email_key => [old_variable => new_variable]]
     */
    protected function oldToNewVariablesMap(): array
    {
        return [
            'COPYEDIT_REQUEST' => [
                'contextAcronym' => 'contextAcronym',
            ],
            'LAYOUT_REQUEST' => [
                'contextAcronym' => 'contextAcronym',
            ],
        ];
    }
}

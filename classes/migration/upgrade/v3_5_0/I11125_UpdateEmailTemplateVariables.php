<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11125_UpdateEmailTemplateVariables.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11125_UpdateEmailTemplateVariables
 *
 * @brief Migration to update Email Template variable names
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

abstract class I11125_UpdateEmailTemplateVariables extends \PKP\migration\Migration
{
    public function up(): void
    {
        // Update template variables
        $this->renameTemplateVariables($this->oldToNewVariablesMap());
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Replaces email template variables in templates' subject and body
     */
    protected function renameTemplateVariables(array $oldNewVariablesMap): void
    {
        foreach ($oldNewVariablesMap as $emailKey => $variablesMap) {
            $settingsTableReplaceExpression = 'setting_value';
            $defaultTableBodyReplaceExpression = 'body';
            $defaultTableSubjectReplaceExpression = 'subject';

            foreach ($variablesMap as $oldName => $newName) {
                $existingVariable = "'{\${$oldName}}'";
                $replacementsVariable = "'{\${$newName}}'";

                $settingsTableReplaceExpression = "REPLACE($settingsTableReplaceExpression, $existingVariable, $replacementsVariable)";
                $defaultTableBodyReplaceExpression = "REPLACE($defaultTableBodyReplaceExpression, $existingVariable, $replacementsVariable)";
                $defaultTableSubjectReplaceExpression = "REPLACE($defaultTableSubjectReplaceExpression, $existingVariable, $replacementsVariable)";
            }

            // Default templates SQL
            $defaultTemplatesSql = "
                UPDATE email_templates_default_data
                SET
                    subject = $defaultTableSubjectReplaceExpression,
                    body = $defaultTableBodyReplaceExpression
                WHERE email_key = ?
            ";

            // Custom templates SQL
            $customTemplatesSql = "
                UPDATE email_templates_settings
                SET setting_value = $settingsTableReplaceExpression
                WHERE email_id IN (
                    SELECT email_id
                    FROM email_templates
                    WHERE email_key = ?
                )";

            DB::update($defaultTemplatesSql, [$emailKey]);
            DB::update($customTemplatesSql, [$emailKey]);
        }
    }

    /**
     * @return array [email_key => [old_variable => new_variable]]
     */
    abstract function oldToNewVariablesMap(): array;
}

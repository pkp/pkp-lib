<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I13283_RestoreDegradedOrcidFunctionality.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13283_RestoreDegradedOrcidFunctionality
 *
 * @brief Update outdated ORCID email template variables.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I13283_RestoreDegradedOrcidFunctionality extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->fixEmailVariableNames();
    }

    /**
     * @inheritDoc
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Updates ORCID email template variable names. Can be run multiple times.
     */
    private function fixEmailVariableNames(): void
    {
        DB::transaction(function () {
            $this->replace('ORCID_COLLECT_AUTHOR_ID', '{$principalContactSignature}', '{$siteSignature}');
            $this->replace('ORCID_REQUEST_AUTHOR_AUTHORIZATION', '{$principalContactSignature}', '{$siteSignature}');
            $this->replace('ORCID_REQUEST_UPDATE_SCOPE', '{$principalContactSignature}', '{$siteSignature}');

            $this->replace('ORCID_COLLECT_AUTHOR_ID', '{$authorName}', '{$recipientName}');
            $this->replace('ORCID_REQUEST_AUTHOR_AUTHORIZATION', '{$authorName}', '{$recipientName}');
            $this->replace('ORCID_REQUEST_UPDATE_SCOPE', '{$authorName}', '{$recipientName}');
        });
    }

    /**
     * Replace a string in the default body of an email template, for every locale,
     * and in every body that has been customized.
     */
    private function replace(string $emailKey, string $search, string $replace): void
    {
        $defaults = DB::table('email_templates_default_data')
            ->where('email_key', $emailKey)
            ->get();

        foreach ($defaults as $default) {
            $body = (string) $default->body;

            if (!$this->shouldReplace($body, $search)) {
                continue;
            }

            DB::table('email_templates_default_data')
                ->where('email_key', $emailKey)
                ->where('locale', $default->locale)
                ->update(['body' => str_replace($search, $replace, $body)]);
        }

        $emailIds = DB::table('email_templates')
            ->where('email_key', $emailKey)
            ->pluck('email_id');

        $customized = DB::table('email_templates_settings')
            ->whereIn('email_id', $emailIds)
            ->where('setting_name', 'body')
            ->get();

        foreach ($customized as $setting) {
            $body = (string) $setting->setting_value;

            if (!$this->shouldReplace($body, $search)) {
                continue;
            }

            DB::table('email_templates_settings')
                ->where('email_template_setting_id', $setting->email_template_setting_id)
                ->update(['setting_value' => str_replace($search, $replace, $body)]);
        }
    }

    /**
     * There is nothing to do if the body does not contain the string we are looking for
     * or if it already contains the result of the replacement.
     */
    private function shouldReplace(string $body, string $search): bool
    {
        if (!str_contains($body, $search)) {
            return false;
        }

        return true;
    }
}

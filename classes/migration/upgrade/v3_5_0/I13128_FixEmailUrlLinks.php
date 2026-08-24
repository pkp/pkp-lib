<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I13128_FixEmailUrlLinks.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13128_FixEmailUrlLinks
 *
 * @brief Fix several URL params into links in email templates, and fix some translation issues.
 * 
 * See pkp/pkp-lib#13128.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I13128_FixEmailUrlLinks extends Migration
{
    public function up(): void
    {
        $this->replace('USER_VALIDATE_CONTEXT', '{$activateUrl}', '<a href="{$activateUrl}">{$activateUrl}</a>', 'href="{$activateUrl}"');
        $this->replace('USER_VALIDATE_SITE', '{$activateUrl}', '<a href="{$activateUrl}">{$activateUrl}</a>', 'href="{$activateUrl}"');
        $this->replace('PASSWORD_RESET_CONFIRM', '{$passwordResetUrl}', '<a href="{$passwordResetUrl}">{$passwordResetUrl}</a>', 'href="{$passwordResetUrl}"');
        
        // The URL must be the author's one. Fix it, then link it.
        $this->replace('SUBMISSION_ACK', '{$submissionUrl}', '{$authorSubmissionUrl}');
        // These have a second link, so match on </p> instead of the href
        $this->replace('SUBMISSION_ACK', '{$authorSubmissionUrl}</p>', '<a href="{$authorSubmissionUrl}">{$authorSubmissionUrl}</a></p>', '{$authorSubmissionUrl}</a></p>');
        // These have one URL only
        $this->replace('SUBMISSION_ACK', '{$authorSubmissionUrl}', '<a href="{$authorSubmissionUrl}">{$authorSubmissionUrl}</a>', 'href="{$authorSubmissionUrl}"');

        // The anchor is missing the "=" of the href attribute
        $this->replace('COPYEDIT_REQUEST', '<a href"{$submissionUrl}">', '<a href="{$submissionUrl}">');
        
        // Plain text in many translations.
        $this->replace('COPYEDIT_REQUEST', '{$submissionUrl}<br />', '<a href="{$submissionUrl}">{$submissionUrl}</a><br />');
        $this->replace('COPYEDIT_REQUEST', '{$submissionUrl} <br />', '<a href="{$submissionUrl}">{$submissionUrl}</a> <br />');
        $this->replace('COPYEDIT_REQUEST', '{$submissionUrl}</p>', '<a href="{$submissionUrl}">{$submissionUrl}</a></p>');
        $this->replace('COPYEDIT_REQUEST', '{$submissionUrl}"<br />', '<a href="{$submissionUrl}">{$submissionUrl}</a>"<br />');
        $this->replace('COPYEDIT_REQUEST', '{$contextUrl}<br />', '<a href="{$contextUrl}">{$contextUrl}</a><br />');
        $this->replace('COPYEDIT_REQUEST', '{$contextUrl} <br />', '<a href="{$contextUrl}">{$contextUrl}</a> <br />');

        // In lt the quotes of this anchor became backslashes, so it has no usable href
        $this->replace(
            'COPYEDIT_REQUEST',
            '<a href\\{$submissionUrl}\\>',
            '<a href="{$submissionUrl}">',
            '',
            'lt'
        );

        $this->replace(
            'COPYEDIT_REQUEST',
            '\\{$submissionTitle}\\',
            '"{$submissionTitle}"',
            '',
            'lt'
        );
        
        // Only sl has no opening brace.
        $this->replace(
            'COPYEDIT_REQUEST',
            'Povezava do prispevka: $submissionUrl}',
            'Povezava do prispevka: <a href="{$submissionUrl}">{$submissionUrl}</a>',
            '',
            'sl'
        );
    }

    public function down(): void
    {
        $this->replace('USER_VALIDATE_CONTEXT', '<a href="{$activateUrl}">{$activateUrl}</a>', '{$activateUrl}');
        $this->replace('USER_VALIDATE_SITE', '<a href="{$activateUrl}">{$activateUrl}</a>', '{$activateUrl}');
        $this->replace('PASSWORD_RESET_CONFIRM', '<a href="{$passwordResetUrl}">{$passwordResetUrl}</a>', '{$passwordResetUrl}');
        $this->replace('SUBMISSION_ACK', '<a href="{$authorSubmissionUrl}">{$authorSubmissionUrl}</a></p>', '{$authorSubmissionUrl}</p>');
        $this->replace('SUBMISSION_ACK', '<a href="{$authorSubmissionUrl}">{$authorSubmissionUrl}</a>', '{$authorSubmissionUrl}');
        $this->replace(
            'COPYEDIT_REQUEST',
            'Povezava do prispevka: <a href="{$submissionUrl}">{$submissionUrl}</a>',
            'Povezava do prispevka: $submissionUrl}',
            '',
            'sl'
        );
        $this->replace('COPYEDIT_REQUEST', '<a href="{$contextUrl}">{$contextUrl}</a>', '{$contextUrl}');
        $this->replace('COPYEDIT_REQUEST', '<a href="{$submissionUrl}">{$submissionUrl}</a>', '{$submissionUrl}');
        // The broken anchors are not restored because we cannot tell which translations had it.
    }

    /**
     * Replace a string in the default body of an email template, for every locale,
     * and in every body that has been customized.
     *
     * Bodies already containing $skipIf are left alone, so that running the
     * migration twice does not wrap a URL that is already a link.
     *
     * $locale limits the change to one locale, for fixes that suit only one translation.
     */
    protected function replace(string $emailKey, string $search, string $replace, string $skipIf = '', ?string $locale = null): void
    {
        $defaults = DB::table('email_templates_default_data')
            ->where('email_key', $emailKey)
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->get();

        foreach ($defaults as $default) {
            $body = (string) $default->body;

            if (!$this->shouldReplace($body, $search, $skipIf)) {
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
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))
            ->get();

        foreach ($customized as $setting) {
            $body = (string) $setting->setting_value;

            if (!$this->shouldReplace($body, $search, $skipIf)) {
                continue;
            }

            DB::table('email_templates_settings')
                ->where('email_template_setting_id', $setting->email_template_setting_id)
                ->update(['setting_value' => str_replace($search, $replace, $body)]);
        }
    }

    /**
     * There is nothing to do if the body does not contain the string we are looking
     * for, or if it already contains the result of the replacement.
     */
    private function shouldReplace(string $body, string $search, string $skipIf): bool
    {
        if (!str_contains($body, $search)) {
            return false;
        }

        if ($skipIf !== '' && str_contains($body, $skipIf)) {
            return false;
        }

        return true;
    }
}

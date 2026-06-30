<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I12792_FixInvitationEmailButtons.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12792_FixInvitationEmailButtons
 *
 * @brief Rewrites the Accept/Decline buttons in the USER_ROLE_ASSIGNMENT_INVITATION
 *        email body (both the default body and any per-context customized override)
 *        to use a bulletproof table+bgcolor structure so they render correctly in
 *        classic Outlook (Word rendering engine). See pkp/pkp-lib#12792.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12792_FixInvitationEmailButtons extends Migration
{
    private const EMAIL_KEY = 'USER_ROLE_ASSIGNMENT_INVITATION';

    public function up(): void
    {
        // 1) Default body — one row per locale in email_templates_default_data
        DB::table('email_templates_default_data')
            ->where('email_key', self::EMAIL_KEY)
            ->get()
            ->each(function ($row) {
                $new = $this->rewriteOrWarn($row->body, "default body, locale={$row->locale}");
                if ($new !== null) {
                    DB::table('email_templates_default_data')
                        ->where('email_key', self::EMAIL_KEY)
                        ->where('locale', $row->locale)
                        ->update(['body' => $new]);
                }
            });

        // 2) Per-context customized overrides
        DB::table('email_templates')
            ->where('email_key', self::EMAIL_KEY)
            ->pluck('email_id')
            ->each(function ($emailId) {
                DB::table('email_templates_settings')
                    ->where('email_id', $emailId)
                    ->where('setting_name', 'body')
                    ->get()
                    ->each(function ($row) use ($emailId) {
                        $new = $this->rewriteOrWarn($row->setting_value, "customized body, email_id={$emailId}, locale={$row->locale}");
                        if ($new !== null) {
                            DB::table('email_templates_settings')
                                ->where('email_id', $emailId)
                                ->where('locale', $row->locale)
                                ->where('setting_name', 'body')
                                ->update(['setting_value' => $new]);
                        }
                    });
            });
    }

    /**
     * Try to rewrite the body. Returns the new body if the legacy pattern was
     * found and replaced, or null if no change was applied. If the body shows
     * no sign of either the legacy pattern OR the post-fix markup, emits a
     * warning to the PHP error log so the admin can review the template
     * manually after upgrade (grep for the marker to find them all).
     *
     * NULL or empty bodies are skipped.
     *
     */
    private function rewriteOrWarn(?string $body, string $context): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }
        $new = $this->rewriteBody($body);
        if ($new !== $body) {
            return $new;
        }
        if (!str_contains($body, 'btn-wrap')) {
            error_log(sprintf(
                'WARNING: [pkp/pkp-lib#12792] %s — hand-edited; Outlook button fix NOT applied. Please review this template manually.',
                $context
            ));
        }
        return null;
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Match the Accept <p><a>…</a></p> block IMMEDIATELY followed by the Decline
     * one (the canonical .po order) and replace the pair with a single
     * <table> that has two rows — one per button. 
     * Localized labels and the Smarty {$...Url} variables are preserved.
     *
     * If the two buttons are not adjacent (hand-edited), the pattern
     * doesn't match and rewriteOrWarn() emits a warning instead of writing
     * something inconsistent with the .po template.
     *
     */
    private function rewriteBody(string $body): string
    {
        return (string) preg_replace_callback(
            '#<p[^>]*>\s*<a[^>]*href=([\'"])\{\$acceptUrl\}\1[^>]*>([^<]+)</a>\s*</p>'
            . '\s*'
            . '<p[^>]*>\s*<a[^>]*href=([\'"])\{\$declineUrl\}\3[^>]*>([^<]+)</a>\s*</p>#i',
            function ($m) {
                $acceptLabel  = $m[2];
                $declineLabel = $m[4];
                return "<table class='btn-wrap' role='presentation' border='0' cellpadding='0' cellspacing='10'>"
                    . "<tr><td class='btn-cell btn-accept'  bgcolor='#28a745'><a class='btn' href='{\$acceptUrl}'>{$acceptLabel}</a></td></tr>"
                    . "<tr><td class='btn-cell btn-decline' bgcolor='#dc3545'><a class='btn' href='{\$declineUrl}'>{$declineLabel}</a></td></tr>"
                    . "</table>";
            },
            $body
        );
    }
}

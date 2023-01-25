<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I5716_EmailTemplateAssignments.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5716_EmailTemplateAssignments
 *
 * @brief Refactors relationship between Mailables and Email Templates
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\mail\mailables\DiscussionProduction;

class I5716_EmailTemplateAssignments extends \PKP\migration\upgrade\v3_4_0\I5716_EmailTemplateAssignments
{
    public function up(): void
    {
        $this->setPostedAcknowledgementSetting();
        parent::up();
    }

    protected function getContextTable(): string
    {
        return 'servers';
    }

    protected function getContextSettingsTable(): string
    {
        return 'server_settings';
    }

    protected function getContextIdColumn(): string
    {
        return 'server_id';
    }

    protected function getDiscussionTemplates(): Collection
    {
        return collect([
            DiscussionProduction::getEmailTemplateKey(),
        ]);
    }

    /**
     * Set the postedAcknowledgement context setting to the correct value, depending
     * on whether or not the email template has been disabled
     */
    protected function setPostedAcknowledgementSetting(): void
    {
        DB::table($this->getContextTable())
            ->pluck($this->getContextIdColumn())
            ->each(function (int $contextId) {
                $disabled = DB::table('email_templates')
                    ->where('context_id', $contextId)
                    ->where('email_key', 'POSTED_ACK')
                    ->where('enabled', 0)
                    ->exists();
                DB::table($this->getContextSettingsTable())
                    ->insert([
                        $this->getContextIdColumn() => $contextId,
                        'setting_name' => 'postedAcknowledgement',
                        'setting_value' => $disabled ? 0 : 1,
                    ]);
            });
    }

    /**
     * OPS doesn't require any additional templates to be reassigned
     */
    protected function mapIncludedAlternateTemplates(): array
    {
        return [];
    }

    /**
     * Get a map of the EDITOR_ASSIGN_<stage> templates
     *
     * @return [email_key => alternate_to]
     */
    protected function mapEditorAssignTemplates(): Collection
    {
        return collect([
            'EDITOR_ASSIGN_PRODUCTION' => 'DISCUSSION_NOTIFICATION_PRODUCTION',
        ]);
    }
}

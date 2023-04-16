<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7592_RemoveUnusedEmailTemplates.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7592_RemoveUnusedEmailTemplates
 *
 * @brief Describe upgrade/downgrade for removing unused reviewer related email templates from the default data
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class I7592_RemoveUnusedEmailTemplates extends \PKP\migration\Migration
{
    protected ?Collection $templatesDefaultData;
    protected ?Collection $templatesDefault;

    public function up(): void
    {
        $emailKeys = [
            'REVIEW_REMIND_AUTO_ONECLICK',
            'REVIEW_RESPONSE_OVERDUE_AUTO_ONECLICK',
            'REVIEW_REMIND_ONECLICK',
            'REVIEW_REQUEST_ATTACHED',
            'REVIEW_REQUEST_ATTACHED_SUBSEQUENT',
            'REVIEW_REQUEST_ONECLICK',
            'REVIEW_REQUEST_ONECLICK_SUBSEQUENT'
        ];

        $this->templatesDefault = DB::table('email_templates_default')
            ->whereIn('email_key', $emailKeys)
            ->get();

        $this->templatesDefaultData = DB::table('email_templates_default_data')
            ->whereIn('email_key', $emailKeys)
            ->get();

        DB::table('email_templates_default')
            ->whereIn('email_key', $emailKeys)
            ->delete();

        DB::table('email_templates_default_data')
            ->whereIn('email_key', $emailKeys)
            ->delete();
    }

    public function down(): void
    {
        $this->templatesDefault->each(function (stdClass $templateDefaultRow) {
            DB::table('email_templates_default')->insert((array) $templateDefaultRow);
        });

        $this->templatesDefaultData->each(function (stdClass $templateDefaultDataRow) {
            DB::table('email_templates_default_data')->insert((array) $templateDefaultDataRow);
        });
    }
}

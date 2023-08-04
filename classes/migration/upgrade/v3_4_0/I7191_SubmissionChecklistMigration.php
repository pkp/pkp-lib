<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_SubmissionChecklistMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_SubmissionChecklistMigration
 *
 * @brief Migrate the submissionChecklist setting from an array to a HTML string
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Support\Facades\DB;
use PKP\facades\Locale;
use PKP\install\DowngradeNotSupportedException;
use stdClass;

abstract class I7191_SubmissionChecklistMigration extends \PKP\migration\Migration
{
    protected string $CONTEXT_SETTINGS_TABLE = '';
    protected string $CONTEXT_COLUMN = '';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (empty($this->CONTEXT_SETTINGS_TABLE) || empty($this->CONTEXT_COLUMN)) {
            throw new Exception('Upgrade could not be completed because required properties for the I7191_SubmissionChecklistMigration migration are undefined.');
        }

        $initialLocale = Locale::getLocale();

        DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', 'submissionChecklist')
            ->get()
            ->each(function (stdClass $row) {
                if (empty($row->setting_value)) {
                    DB::table($this->CONTEXT_SETTINGS_TABLE)
                        ->where($this->CONTEXT_COLUMN, $row->{$this->CONTEXT_COLUMN})
                        ->where('locale', $row->locale)
                        ->where('setting_name', 'submissionChecklist')
                        ->where('setting_value', $row->setting_value)
                        ->delete();
                    return;
                }
                $checklist = json_decode($row->setting_value, true);

                usort($checklist, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

                $textList = [];
                foreach ($checklist as $item) {
                    if (!isset($item['content'])) {
                        continue;
                    }
                    $textList[] = $item['content'];
                }

                Locale::setLocale($row->locale);

                $newValue = '<p>'
                    . __('submission.submit.submissionChecklistDescription')
                    . '</p>'
                    . '<ul><li>'
                    . join('</li><li>', $textList)
                    . '</li></ul>';

                DB::table($this->CONTEXT_SETTINGS_TABLE)
                    ->where($this->CONTEXT_COLUMN, $row->{$this->CONTEXT_COLUMN})
                    ->where('locale', $row->locale)
                    ->where('setting_name', 'submissionChecklist')
                    ->update([
                        'setting_value' => $newValue,
                    ]);
            });

        Locale::setLocale($initialLocale);
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

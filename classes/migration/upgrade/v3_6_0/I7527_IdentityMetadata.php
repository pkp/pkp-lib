<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I7527_IdentityMetadata.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7527_IdentityMetadata
 *
 * @brief Stamp the current context identity (name) onto already-published publications,
 *   so that later changes to the context settings do not retroactively rewrite published metadata.
 *   Applications extend this to stamp their own fields (e.g. ISSN, publisher) and their issue-level
 *   objects.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\HasContextNameHelper;
use PKP\migration\Migration;

class I7527_IdentityMetadata extends Migration
{
    use HasContextNameHelper;

    /** @var int PKPSubmission::STATUS_PUBLISHED */
    protected const STATUS_PUBLISHED = 3;

    /** @var int Settings rows inserted per query */
    protected const CHUNK = 1000;

    public function up(): void
    {
        $contextTable = $this->getContextTableName();
        $settingsTable = $this->getContextSettingsTableName();
        $idColumn = $this->getContextTableKey();

        foreach (DB::table($contextTable)->select($idColumn, 'primary_locale')->get() as $context) {
            $contextId = $context->$idColumn;

            // Localized identity values keyed by publication setting name, then locale
            $localized = [
                'contextName' => $this->getLocalizedContextSetting($settingsTable, $idColumn, $contextId, 'name'),
                // The acronym stands in for a missing abbreviation, as in metadata output
                'contextAbbreviation' => array_replace(
                    $this->getLocalizedContextSetting($settingsTable, $idColumn, $contextId, 'acronym'),
                    $this->getLocalizedContextSetting($settingsTable, $idColumn, $contextId, 'abbreviation')
                ),
            ];

            $scalars = $this->getIdentitySettings($settingsTable, $idColumn, $contextId);

            if ($context->primary_locale) {
                $scalars['contextPrimaryLocale'] = $context->primary_locale;
            }

            if (!array_filter($localized) && !$scalars) {
                continue;
            }

            $publicationIds = DB::table('publications as p')
                ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
                ->where('s.context_id', $contextId)
                ->where('p.status', self::STATUS_PUBLISHED)
                ->pluck('p.publication_id');
            $this->stamp('publication_settings', 'publication_id', $publicationIds, $localized, $scalars);

            // Let the app stamp its own objects (e.g. issues in OJS) with the same identity
            $this->stampRelatedObjects($contextId, $localized, $scalars);
        }
    }

    /**
     * Non-empty values of a localized context setting, keyed by locale.
     */
    protected function getLocalizedContextSetting(string $settingsTable, string $idColumn, int $contextId, string $settingName): array
    {
        return DB::table($settingsTable)
            ->where($idColumn, $contextId)
            ->where('setting_name', $settingName)
            ->where('setting_value', '!=', '')
            ->pluck('setting_value', 'locale')
            ->all();
    }

    /**
     * Non-localized identity values to stamp, keyed by publication setting name. Apps override
     * to add their own (e.g. ISSN, publisher).
     */
    protected function getIdentitySettings(string $settingsTable, string $idColumn, int $contextId): array
    {
        return [];
    }

    /**
     * Stamp other objects in the context that share its identity, beyond publications. No-op by
     * default; e.g. OJS stamps published issues.
     */
    protected function stampRelatedObjects(int $contextId, array $localized, array $scalars): void
    {
    }

    /**
     * Insert the identity settings for the given objects in chunks, without overwriting any value
     * already present (insertOrIgnore on the unique key).
     */
    protected function stamp(string $table, string $idColumn, Collection $ids, array $localized, array $scalars): void
    {
        $rows = [];
        foreach ($ids as $id) {
            foreach ($localized as $name => $values) {
                foreach ($values as $locale => $value) {
                    $rows[] = [$idColumn => $id, 'locale' => $locale, 'setting_name' => $name, 'setting_value' => $value];
                }
            }
            foreach ($scalars as $name => $value) {
                $rows[] = [$idColumn => $id, 'locale' => '', 'setting_name' => $name, 'setting_value' => $value];
            }
            if (count($rows) >= self::CHUNK) {
                DB::table($table)->insertOrIgnore($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table($table)->insertOrIgnore($rows);
        }
    }

    /**
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

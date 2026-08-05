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

use APP\core\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7527_IdentityMetadata extends Migration
{
    /** @var int PKPSubmission::STATUS_PUBLISHED */
    protected const STATUS_PUBLISHED = 3;

    /** @var int Settings rows inserted per query */
    protected const CHUNK = 1000;

    public function up(): void
    {
        $contextDao = Application::getContextDAO();
        $contextTable = $contextDao->tableName;
        $settingsTable = $contextDao->settingsTableName;
        $idColumn = $contextDao->primaryKeyColumn;

        foreach (DB::table($contextTable)->select($idColumn, 'primary_locale')->get() as $context) {
            $contextId = $context->$idColumn;

            // Localized context name, keyed by locale
            $names = DB::table($settingsTable)
                ->where($idColumn, $contextId)
                ->where('setting_name', 'name')
                ->where('setting_value', '!=', '')
                ->pluck('setting_value', 'locale')
                ->all();

            $scalars = $this->getIdentitySettings($settingsTable, $idColumn, $contextId);

            if ($context->primary_locale) {
                $scalars['contextPrimaryLocale'] = $context->primary_locale;
            }

            if (!$names && !$scalars) {
                continue;
            }

            $publicationIds = DB::table('publications as p')
                ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
                ->where('s.context_id', $contextId)
                ->where('p.status', self::STATUS_PUBLISHED)
                ->pluck('p.publication_id');
            $this->stamp('publication_settings', 'publication_id', $publicationIds, $names, $scalars);

            // Let the app stamp its own objects (e.g. issues in OJS) with the same identity
            $this->stampRelatedObjects($contextId, $names, $scalars);
        }
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
    protected function stampRelatedObjects(int $contextId, array $names, array $scalars): void
    {
    }

    /**
     * Insert the identity settings for the given objects in chunks, without overwriting any value
     * already present (insertOrIgnore on the unique key).
     */
    protected function stamp(string $table, string $idColumn, Collection $ids, array $names, array $scalars): void
    {
        $rows = [];
        foreach ($ids as $id) {
            foreach ($names as $locale => $value) {
                $rows[] = [$idColumn => $id, 'locale' => $locale, 'setting_name' => 'contextName', 'setting_value' => $value];
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

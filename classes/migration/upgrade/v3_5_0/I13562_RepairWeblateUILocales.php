<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I13562_RepairWeblateUILocales.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13562_RepairWeblateUILocales
 *
 * @brief Repair default submission locales and submission locales that I9707_WeblateUILocales
 *   left behind or overwrote, because it migrated supportedDefaultSubmissionLocale based on the first context only.
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\facades\Locale;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I13562_RepairWeblateUILocales extends Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    /** Unique index columns per table and locale column */
    protected array $uniqueLocaleIndexes = [];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $affectedLocales = I9707_WeblateUILocales::getAffectedLocales();
        // e.g. bs and pt_PT are still valid in 3.5, so they cannot be remapped everywhere
        $validOldLocales = array_intersect_key($affectedLocales, Locale::getWeblateLocaleNames());
        $invalidOldLocales = array_diff_key($affectedLocales, $validOldLocales);

        $this->remapInvalidLocales($invalidOldLocales);
        // The broken default submission locales are needed to identify the affected submissions, so they are written last
        $contexts = $this->getContextSubmissionLocales();
        $this->remapSubmissionLocales($validOldLocales, $contexts);
        $this->repairDefaultSubmissionLocales($contexts);
    }

    /**
     * Remap old locales that are invalid in 3.5 in all locale and primary_locale columns.
     */
    protected function remapInvalidLocales(array $localeMap): void
    {
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            $tableName = $table['name'];
            // ROR language codes, not locales
            if ($tableName === 'ror_settings') {
                continue;
            }
            $columnNames = collect(Schema::getColumns($tableName))
                ->pluck('name')
                ->intersect(['primary_locale', 'locale']);

            foreach ($columnNames as $columnName) {
                $rows = DB::table($tableName)->whereIn($columnName, array_keys($localeMap));
                if (!$rows->exists()) {
                    continue;
                }

                $conflicts = $this->remapRows($tableName, $columnName, $rows, $localeMap, false);
                if ($conflicts) {
                    $this->_installer->log("Removed {$conflicts} rows from {$tableName} with an old locale in {$columnName}, because the rows with the new locale already exist with a different value.");
                }
            }
        }
    }

    /**
     * Get the submission locales, the default submission locale and, if broken, its repaired value per context.
     *
     * @return array<int, array{submissionLocales: string[], default: ?string, repairedDefault: ?string}>
     */
    protected function getContextSubmissionLocales(): array
    {
        $affectedLocales = I9707_WeblateUILocales::getAffectedLocales();
        $contextIdColumn = $this->getContextIdColumn();

        return DB::table($this->getContextTable())
            ->get([$contextIdColumn, 'primary_locale'])
            ->mapWithKeys(function (object $context) use ($affectedLocales, $contextIdColumn) {
                $settings = DB::table($this->getContextSettingsTable())
                    ->where($contextIdColumn, '=', $context->{$contextIdColumn})
                    ->whereIn('setting_name', ['supportedDefaultSubmissionLocale', 'supportedSubmissionLocales'])
                    ->pluck('setting_value', 'setting_name');

                $submissionLocales = json_decode($settings->get('supportedSubmissionLocales') ?? '', true) ?: [];
                $default = $settings->get('supportedDefaultSubmissionLocale');

                // I9425_SeparateUIAndSubmissionLocales set the default submission locale to the context primary locale
                $repairedDefault = $default === null || !$submissionLocales || in_array($default, $submissionLocales)
                    ? null
                    : collect([$affectedLocales[$default] ?? null, $context->primary_locale, $submissionLocales[0]])
                        ->first(fn (?string $locale) => in_array($locale, $submissionLocales));

                return [$context->{$contextIdColumn} => compact('submissionLocales', 'default', 'repairedDefault')];
            })
            ->all();
    }

    /**
     * Remap submissions created with a broken default submission locale after the upgrade to 3.5.
     *
     * Only single-language contexts are affected, because there the wizard does not ask for the submission locale.
     */
    protected function remapSubmissionLocales(array $validOldLocales, array $contexts): void
    {
        $since = $this->getFirstUpgradeTo350Date();
        if ($since === null) {
            return;
        }

        DB::table('submissions')
            ->where('date_submitted', '>=', $since)
            ->get(['submission_id', 'context_id', 'locale'])
            ->each(function (object $submission) use ($validOldLocales, $contexts) {
                $context = $contexts[$submission->context_id] ?? null;
                if (!$context || !$submission->locale || in_array($submission->locale, $context['submissionLocales'])) {
                    return;
                }

                $newLocale = match (true) {
                    $context['repairedDefault'] !== null && $submission->locale === $context['default'] => $context['repairedDefault'],
                    in_array($validOldLocales[$submission->locale] ?? null, $context['submissionLocales']) => $validOldLocales[$submission->locale],
                    default => null,
                };

                if ($newLocale === null) {
                    $this->_installer->log("Submission {$submission->submission_id}: the submission language {$submission->locale} is not one of the supported submission languages, maybe because of a wrong default submission language after the upgrade to 3.5 (see pkp/pkp-lib#13562). Please check its submission language.");
                    return;
                }

                $hasConflicts = $this->remapSubmissionLocale($submission->submission_id, $submission->locale, $newLocale);
                $this->_installer->log(
                    "Submission {$submission->submission_id}: the submission language was changed from {$submission->locale} to {$newLocale}, because it was probably set from a wrong default submission language after the upgrade to 3.5 (see pkp/pkp-lib#13562)."
                    . ($hasConflicts ? " Some metadata exists in both {$submission->locale} and {$newLocale} with different content, so the {$submission->locale} values were kept. Please check the submission metadata." : '')
                );
            });
    }

    /**
     * Remap all data of the submission from the old to the new locale.
     *
     * @return bool Whether conflicting values were kept in the old locale
     */
    protected function remapSubmissionLocale(int $submissionId, string $oldLocale, string $newLocale): bool
    {
        return DB::transaction(function () use ($submissionId, $oldLocale, $newLocale) {
            $conflicts = 0;
            foreach ($this->getSubmissionLocaleTables() as $tableName => $scope) {
                $rows = $scope(DB::table($tableName), $submissionId)->where('locale', '=', $oldLocale);
                $conflicts += $this->remapRows($tableName, 'locale', $rows, [$oldLocale => $newLocale], true);
            }
            return $conflicts > 0;
        });
    }

    /**
     * Get the tables containing submission data in the submission locale,
     * with a function restricting a query on that table to a submission.
     *
     * @return array<string, callable(Builder, int): Builder>
     */
    protected function getSubmissionLocaleTables(): array
    {
        $authorIds = fn (int $submissionId): Builder => DB::table('authors')
            ->select('author_id')
            ->whereIn('publication_id', $this->getPublicationIds($submissionId));

        return [
            'submissions' => fn (Builder $query, int $submissionId) => $query->where('submission_id', '=', $submissionId),
            'submission_settings' => fn (Builder $query, int $submissionId) => $query->where('submission_id', '=', $submissionId),
            'publication_settings' => fn (Builder $query, int $submissionId) => $query->whereIn('publication_id', $this->getPublicationIds($submissionId)),
            'author_settings' => fn (Builder $query, int $submissionId) => $query->whereIn('author_id', $authorIds($submissionId)),
            'author_affiliation_settings' => fn (Builder $query, int $submissionId) => $query->whereIn(
                'author_affiliation_id',
                DB::table('author_affiliations')->select('author_affiliation_id')->whereIn('author_id', $authorIds($submissionId))
            ),
            'citation_settings' => fn (Builder $query, int $submissionId) => $query->whereIn(
                'citation_id',
                DB::table('citations')->select('citation_id')->whereIn('publication_id', $this->getPublicationIds($submissionId))
            ),
            'controlled_vocab_entry_settings' => fn (Builder $query, int $submissionId) => $query->whereIn(
                'controlled_vocab_entry_id',
                DB::table('controlled_vocab_entries AS cve')
                    ->join('controlled_vocabs AS cv', 'cv.controlled_vocab_id', '=', 'cve.controlled_vocab_id')
                    ->select('cve.controlled_vocab_entry_id')
                    ->where('cv.assoc_type', '=', Application::ASSOC_TYPE_PUBLICATION)
                    ->whereIn('cv.assoc_id', $this->getPublicationIds($submissionId))
            ),
            'submission_file_settings' => fn (Builder $query, int $submissionId) => $query->whereIn(
                'submission_file_id',
                DB::table('submission_files')->select('submission_file_id')->where('submission_id', '=', $submissionId)
            ),
            'reviewer_suggestion_settings' => fn (Builder $query, int $submissionId) => $query->whereIn(
                'reviewer_suggestion_id',
                DB::table('reviewer_suggestions')->select('reviewer_suggestion_id')->where('submission_id', '=', $submissionId)
            ),
        ];
    }

    protected function getPublicationIds(int $submissionId): Builder
    {
        return DB::table('publications')->select('publication_id')->where('submission_id', '=', $submissionId);
    }

    /**
     * Get the date of the first upgrade to 3.5, or null if the installation was not upgraded from an earlier version.
     */
    protected function getFirstUpgradeTo350Date(): ?string
    {
        $versions = DB::table('versions')
            ->where('product_type', '=', 'core')
            ->where('product', '=', Application::get()->getName())
            ->get(['major', 'minor', 'date_installed']);

        $upgraded = $versions->contains(fn (object $version) => $version->major < 3 || ($version->major == 3 && $version->minor < 5));
        return $upgraded
            ? $versions->filter(fn (object $version) => $version->major == 3 && $version->minor == 5)->min('date_installed')
            : null;
    }

    /**
     * Write the repaired default submission locales.
     */
    protected function repairDefaultSubmissionLocales(array $contexts): void
    {
        foreach ($contexts as $contextId => $context) {
            if ($context['repairedDefault'] === null) {
                continue;
            }
            DB::table($this->getContextSettingsTable())
                ->where($this->getContextIdColumn(), '=', $contextId)
                ->where('setting_name', '=', 'supportedDefaultSubmissionLocale')
                ->update(['setting_value' => $context['repairedDefault']]);
        }
    }

    /**
     * Remap the locale of the rows.
     *
     * If a row with the new locale already exists, the empty one of both is removed, or the old one if both are equal.
     * Otherwise the old row is a conflict: it is removed, or, if $keepConflicts, kept with the old locale.
     *
     * @return int Number of conflicting rows
     */
    protected function remapRows(string $tableName, string $columnName, Builder $rows, array $localeMap, bool $keepConflicts): int
    {
        $conflicts = [];
        foreach ($this->getUniqueLocaleIndexes($tableName, $columnName) as $columns) {
            $valueColumns = $this->getValueColumns($tableName, $columns);
            foreach ((clone $rows)->get([...$columns, ...$valueColumns]) as $oldRow) {
                $newRow = fn (): Builder => $this->whereRow(DB::table($tableName), $columns, $oldRow, [$columnName => $localeMap[$oldRow->{$columnName}]]);
                $newValues = $newRow()->first($valueColumns);
                if ($newValues === null) {
                    continue;
                }

                $oldValues = Arr::only((array) $oldRow, $valueColumns);
                $newValues = (array) $newValues;
                if ($this->isEmpty($newValues)) {
                    $newRow()->delete();
                } elseif ($this->isEmpty($oldValues) || $oldValues == $newValues) {
                    $this->whereRow(DB::table($tableName), $columns, $oldRow)->delete();
                } else {
                    $conflicts[] = [$columns, $oldRow];
                    if (!$keepConflicts) {
                        $this->whereRow(DB::table($tableName), $columns, $oldRow)->delete();
                    }
                }
            }
        }

        (clone $rows)
            ->when(
                $keepConflicts && $conflicts,
                fn (Builder $query) => $query->whereNot(function (Builder $query) use ($conflicts) {
                    foreach ($conflicts as [$columns, $row]) {
                        $query->orWhere(fn (Builder $query) => $this->whereRow($query, $columns, $row));
                    }
                })
            )
            ->update([
                $columnName => count($localeMap) === 1
                    ? reset($localeMap)
                    : I9707_WeblateUILocales::getLocaleMappingExpression($columnName)
            ]);

        return count($conflicts);
    }

    /**
     * Get the columns of the unique indexes that contain the locale column.
     */
    protected function getUniqueLocaleIndexes(string $tableName, string $columnName): array
    {
        return $this->uniqueLocaleIndexes[$tableName][$columnName] ??= collect(Schema::getIndexes($tableName))
            ->filter(fn (array $index) => $index['unique'] && in_array($columnName, $index['columns']))
            ->pluck('columns')
            ->all();
    }

    /**
     * Get the columns holding the data, i.e. all except the unique index columns, auto-increment IDs and setting types.
     */
    protected function getValueColumns(string $tableName, array $indexColumns): array
    {
        return collect(Schema::getColumns($tableName))
            ->reject(fn (array $column) => $column['auto_increment'] || in_array($column['name'], [...$indexColumns, 'setting_type']))
            ->pluck('name')
            ->all();
    }

    protected function isEmpty(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '');
    }

    /**
     * Restrict the query to the row with the same unique index values, optionally with some values replaced.
     */
    protected function whereRow(Builder $query, array $columns, object $row, array $replacements = []): Builder
    {
        foreach ($columns as $column) {
            $value = array_key_exists($column, $replacements) ? $replacements[$column] : $row->{$column};
            $value === null ? $query->whereNull($column) : $query->where($column, '=', $value);
        }
        return $query;
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreflightCheckMigration
 *
 * @brief Check for common problems early in the upgrade process.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use APP\migration\upgrade\v3_4_0\MergeLocalesMigration;
use APP\statistics\StatisticsHelper;
use Exception;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\db\DAORegistry;
use Throwable;

abstract class PreflightCheckMigration extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextKeyField(): string;

    /** @var array<string,callable[]> Key = table name, value = list of cleanup processors */
    protected $tableProcessors = [];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $this->checkUsageStatsLogs();
            // It's needed to clear the duplicated settings before looking for duplicated localized data to avoid false positives
            $this->clearDuplicatedUserSettings();
            $this->checkLocaleConflicts();
            $this->checkUniqueEmailAndUsername();
            $this->checkForeignKeySupport();
            $this->checkSubmissionChecklist();
            $this->checkContactSetting();
            $this->dropForeignKeys();
            $this->clearOrphanedEntities();
            // Extra checks done after the entities are clean to avoid flagging problems over data that's supposed to be gone
            $this->checkSubmissionLocale();
            $this->checkAuthorsMissingUserGroup();
        } catch (Throwable $e) {
            if ($fallbackVersion = $this->setFallbackVersion()) {
                $this->_installer->log("A pre-flight check failed. The software was successfully upgraded to {$fallbackVersion} but could not be upgraded further (to " . $this->_installer->newVersion->getVersionString() . '). Check and correct the error, then try again.');
            }
            throw $e;
        }
    }

    /**
     * Rollback the migrations.
     */
    public function down(): void
    {
        if ($fallbackVersion = $this->setFallbackVersion()) {
            $this->_installer->log("An upgrade step failed! Fallback set to {$fallbackVersion}. Check and correct the error and try the upgrade again. We recommend restoring from backup, though you may be able to continue without doing so.");
            // Prevent further downgrade migrations from executing.
            $this->_installer->migrations = [];
        }
    }

    /**
     * Store the fallback version in the database, permitting resumption of partial upgrades.
     *
     * @return ?string Fallback version, if one was identified
     */
    protected function setFallbackVersion(): ?string
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var \PKP\site\VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            return $fallbackVersion;
        }
        return null;
    }

    /**
     * Check the contexts' contact details before upgrade
     * @see https://github.com/pkp/pkp-lib/issues/8183
     *
     * @throws Exception
     */
    protected function checkContactSetting(): void
    {
        $missingContactContexts = DB::table($this->getContextTable() . ' AS contexts')
            ->select('contexts.path AS path')
            ->leftJoin($this->getContextSettingsTable() . ' AS email_join', function ($join) {
                $join->on("contexts.{$this->getContextKeyField()}", '=', "email_join.{$this->getContextKeyField()}")
                    ->where('email_join.setting_name', '=', 'contactEmail');
            })
            ->leftJoin($this->getContextSettingsTable() . ' AS name_join', function ($join) {
                $join->on("contexts.{$this->getContextKeyField()}", '=', "name_join.{$this->getContextKeyField()}")
                    ->where('name_join.setting_name', '=', 'contactName');
            })
            ->whereNull("email_join.{$this->getContextKeyField()}")
            ->orWhereNull("name_join.{$this->getContextKeyField()}")
            ->get();

        if ($missingContactContexts->count() <= 0) {
            return;
        }

        throw new Exception(
            sprintf(
                'Contact name or email is missing for context(s) with path(s) [%s]. Please set those before upgrading.',
                $missingContactContexts->pluck('path')->implode(',')
            )
        );
    }

    /**
     * Ensures locale conflicts won't happen at a later stage of the migration
     * @see https://github.com/pkp/pkp-lib/issues/8598
     *
     * @throws Exception
     */
    protected function checkLocaleConflicts(): void
    {
        // email_templates_default_data keys should not conflict after locale migration
        // See MergeLocalesMigration (#8598)
        $affectedLocales = MergeLocalesMigration::getAffectedLocales();

        $conflictingEmailKeys = collect();
        $exceptionMessage = '';
        foreach ($affectedLocales as $localeSource => $localeTarget) {
            $conflictingEmailKeys = DB::table('email_templates_default_data')
                ->select('email_key', DB::raw('count(*) as count'))
                ->where('locale', $localeSource)
                ->orWhere('locale', $localeTarget)
                ->groupBy('email_key')
                ->havingRaw('count(*) >= 2')
                ->get();

            if (!$conflictingEmailKeys->isEmpty()) {
                foreach ($conflictingEmailKeys as $conflictingEmailKey) {
                    $exceptionMessage .= 'A row with email_key="' . $conflictingEmailKey->email_key . '" found in table email_templates_default_data which will conflict with other rows specific to the locale key "' . $localeTarget . '" after the migration. Please review this row before upgrading. Consider keeping only the ' . $localeSource . ' locale in the installation' . PHP_EOL;
                }
            }
        }

        if (!empty($exceptionMessage)) {
            throw new Exception($exceptionMessage);
        }

        // _settings tables locales should not conflict after locale migration
        // See MergeLocalesMigration (#8598)
        $conflictingSettings = collect();
        $settingsExceptionMessage = '';
        foreach (MergeLocalesMigration::getSettingsTables() as $tableName => [$entityIdColumnName, $primaryKeyColumnName]) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'locale')) {
                continue;
            }

            foreach ($affectedLocales as $localeSource => $localeTarget) {
                $conflictingSettings = DB::table($tableName)
                    ->select('setting_name', DB::raw('COUNT(*)'))
                    ->when($entityIdColumnName, fn (Builder $query) => $query->addSelect($entityIdColumnName))
                    ->where('locale', $localeSource)
                    ->orWhere('locale', $localeTarget)
                    ->when(
                        $entityIdColumnName,
                        fn (Builder $query) => $query->groupBy($entityIdColumnName, 'setting_name'),
                        fn (Builder $query) => $query->groupBy('setting_name')
                    )
                    ->havingRaw('COUNT(*) >= 2')
                    ->get();

                if (!$conflictingSettings->isEmpty()) {
                    foreach ($conflictingSettings as $conflictingSetting) {
                        $settingsExceptionMessage .= 'A row with "' . $entityIdColumnName . '"="' . $conflictingSetting->{$entityIdColumnName} . '" and "setting_name"="' . $conflictingSetting->setting_name . '" found in table "' . $tableName . '" which will conflict with other rows specific to the locale key "' . $localeTarget . '" after the migration. Please review this row before upgrading.' . PHP_EOL;
                    }
                }
            }
        }

        if (!empty($settingsExceptionMessage)) {
            throw new Exception($settingsExceptionMessage);
        }
    }

    /**
     * Ensures all logs have been processed before upgrading/modifying its structure
     *
     * @throws Exception
     */
    protected function checkUsageStatsLogs(): void
    {
        $usageStatsDir = StatisticsHelper::getUsageStatsDirPath();
        // check if there are usage stats log files older than yesterday
        foreach (glob($usageStatsDir . '/usageEventLogs/*') as $usageStatsLogFile) {
            $lastModified = date('Ymd', filemtime($usageStatsLogFile));
            $yesterday = date('Ymd', strtotime('-1 days'));
            if ($yesterday > $lastModified) {
                throw new Exception("There are unprocessed log files from more than 1 day ago in the directory {$usageStatsDir}/usageEventLogs/. This happens when the scheduled task to process usage stats logs is not being run daily. All logs in this directory older than {$yesterday} must be processed or removed before the upgrade can continue.");
            }
        }
        // check if there are old usage stats log files there that were not successfully processed
        if (
            count(glob($usageStatsDir . '/processing/*')) !== 0 ||
            count(glob($usageStatsDir . '/reject/*')) !== 0 ||
            count(glob($usageStatsDir . '/stage/*')) !== 0
        ) {
            throw new Exception("There are one or more log files that were unable to finish processing. This happens when the scheduled task to process usage stats logs encounters a failure of some kind. These logs must be repaired and reprocessed or removed before the upgrade can continue. The logs can be found in the folders reject, processing and stage in {$usageStatsDir}.");
        }
    }

    /**
     * Ensures usernames and emails are unique in a case-insensitive way (PostgreSQL)
     * MySQL has been ignored from this check as it defaults to case-insensitive collations
     *
     * @throws Exception
     */
    protected function checkUniqueEmailAndUsername(): void
    {
        if (!(DB::connection() instanceof PostgresConnection)) {
            return;
        }

        // Flag users that have same emails if we consider them case insensitively.
        // By default, MySQL/MariaDB use case-insensitive collation, so they are not generally affected.
        $result = DB::table('users AS a')
            ->join('users AS b', function ($join) {
                $join->on(DB::Raw('LOWER(a.email)'), '=', DB::Raw('LOWER(b.email)'));
                $join->on('a.user_id', '<>', 'b.user_id');
            })
            ->select('a.user_id as user_id', 'b.user_id as paired_user_id')
            ->get();
        foreach ($result as $row) {
            $this->_installer->log("The user with user_id {$row->user_id} and email {$row->email} collides with user_id {$row->paired_user_id} and email {$row->paired_email}.");
        }
        if ($result->count()) {
            throw new Exception('Starting with 3.4.0, email addresses are not case sensitive. Your database contains users that have same emails if considered case insensitively. These must be merged or made unique before the upgrade can be executed. Use the tools/mergeUsers.php script in the old installation directory to resolve these before running the upgrade.');
        }

        // Flag users that have same username if we consider them case insensitively
        // By default, MySQL/MariaDB use case-insensitive collation, so they are not generally affected.
        $result = DB::table('users AS a')
            ->join('users AS b', function ($join) {
                $join->on(DB::Raw('LOWER(a.username)'), '=', DB::Raw('LOWER(b.username)'));
                $join->on('a.user_id', '<>', 'b.user_id');
            })
            ->select('a.user_id as user_id', 'b.user_id as paired_user_id')
            ->get();
        foreach ($result as $row) {
            $this->_installer->log("The user with user_id {$row->user_id} and username {$row->username} collides with user_id {$row->paired_user_id} and username {$row->username}.");
        }
        if ($result->count()) {
            throw new Exception('Starting with 3.4.0, usernames are not case sensitive. Your database contains users that have same username if considered case insensitively. These must be merged or made unique before the upgrade can be executed. Use the tools/mergeUsers.php script in the old installation directory to resolve these before running the upgrade.');
        }
    }

    /**
     * Apply some validations to the submission checklist and attempts to auto-fix a small issue within the JSON data
     * @see https://github.com/pkp/pkp-lib/issues/7191
     * @see About the fix attempt: https://github.com/pkp/pkp-lib/issues/8929#issuecomment-1519867805
     * @throws Exception
     */
    protected function checkSubmissionChecklist(): void
    {
        // Make sure submission checklists have locale key
        // See I7191_SubmissionChecklistMigration
        $invalidSubmissionsChecklist = DB::table($this->getContextSettingsTable())
            ->where('setting_name', 'submissionChecklist')
            ->whereNull('locale')
            ->count();
        if ($invalidSubmissionsChecklist > 0) {
            throw new Exception('A row with setting_name="submissionChecklist" found in table ' . $this->getContextSettingsTable() . ' with null in the locale column. Remove this row or add a locale before upgrading.');
        }
        // All submission checklists should be a json-encoded array
        // See I7191_SubmissionChecklistMigration
        DB::table($this->getContextSettingsTable())
            ->where('setting_name', 'submissionChecklist')
            ->get(['setting_name', 'setting_value', 'locale', $this->getContextKeyField()])
            ->each(function ($row) {
                try {
                    $checklist = json_decode((string) $row->setting_value, null, 512, JSON_THROW_ON_ERROR);
                    if (is_array($checklist)) {
                        return;
                    }
                    // Attempts to fix the JSON (see https://github.com/pkp/pkp-lib/issues/8929#issuecomment-1519867805) before failing the upgrade
                    if (is_object($checklist)) {
                        DB::table($this->getContextSettingsTable())
                            ->where('setting_name', $row->setting_name)
                            ->where('locale', $row->locale)
                            ->where($this->getContextKeyField(), $row->{$this->getContextKeyField()})
                            ->update(['setting_value' => json_encode(array_values((array) $checklist), JSON_UNESCAPED_UNICODE)]);
                        return;
                    }
                    throw new Exception('Unexpected type');
                } catch (Exception) {
                    throw new Exception('A row with setting_name="submissionChecklist" found in table ' . $this->getContextSettingsTable() . " without the expected setting_value. Expected an array encoded in JSON but found:\n\n{$row->setting_value}\n\nFix or remove this row before upgrading.");
                }
            });
    }

    /**
     * Checks whether the database is ready for the introduction of foreign keys (MySQL only)
     * @see https://github.com/pkp/pkp-lib/issues/6732
     *
     * @throws Exception
     */
    protected function checkForeignKeySupport(): void
    {
        // Check if database engine supports foreign key constraints
        if (!(DB::connection() instanceof MySqlConnection)) {
            return;
        }
        $defaultEngine = DB::scalar('SELECT ENGINE FROM INFORMATION_SCHEMA.ENGINES WHERE SUPPORT = "DEFAULT"');
        if (strtolower($defaultEngine) !== 'innodb') {
            throw new Exception(
                'A default database engine ' . $defaultEngine . ' isn\'t supported, expecting InnoDB. ' .
                'Please change the default database engine to InnoDB to run the upgrade.'
            );
        }

        $result = DB::select(
            'SELECT t.table_name, t.engine AS table_engine
            FROM information_schema.tables AS t
            WHERE t.table_schema = :databaseName AND LOWER(t.engine) <> "innodb"',
            ['databaseName' => DB::connection()->getDatabaseName()]
        );

        if (count($result) > 0) {
            $tableNames = data_get($result, '*.TABLE_NAME');
            throw new Exception(
                'Storage engine that doesn\'t support foreign key constraints detected in one or more tables: ' .
                implode(', ', $tableNames) . '. Change to InnoDB before running the upgrade.'
            );
        }
    }

    /**
     * Checks if the submission.locale field is filled, due to a bug, a fix will be attempted (retrieve the submission locale from its related publication entity)
     *
     * @see https://github.com/pkp/pkp-lib/issues/7190
     */
    protected function checkSubmissionLocale(): void
    {
        // First check if we still have the publications.locale field before attempting the fix
        if (!Schema::hasColumn('publications', 'locale')) {
            return;
        }
        $rows = DB::table('submissions AS s')->join('publications AS p', 'p.publication_id', '=', 's.current_publication_id')
            ->whereNull('s.locale')
            ->whereNotNull('p.locale')
            ->get(['s.submission_id', 'p.locale']);
        foreach ($rows as $row) {
            $this->_installer->log("Updating locale of submission {$row->submission_id} to {$row->locale}.");
            DB::table('submissions')->where('submission_id', '=', $row->submission_id)->update(['locale' => $row->locale]);
        }

        if ($count = DB::table('submissions AS s')->whereNull('locale')->count()) {
            throw new Exception("There are {$count} submission records with null in the locale column. Please correct these before upgrading.");
        }
    }

    /**
     * Checks if there are authors missing an user_group relationship
     */
    protected function checkAuthorsMissingUserGroup(): void
    {
        // Flag orphaned authors entries by user_group_id
        $result = DB::table('authors AS a')->leftJoin('user_groups AS ug', 'ug.user_group_id', '=', 'a.user_group_id')->leftJoin('publications AS p', 'p.publication_id', '=', 'a.publication_id')->whereNull('ug.user_group_id')->select('a.author_id AS author_id', 'a.publication_id AS publication_id', 'a.user_group_id AS user_group_id', 'p.submission_id AS submission_id')->get();
        foreach ($result as $row) {
            $this->_installer->log("Found an orphaned author entry with author_id {$row->author_id} for publication_id {$row->publication_id} with submission_id {$row->submission_id} and user_group_id {$row->user_group_id}.");
        }
        if ($result->count()) {
            throw new Exception('There are author records without matching user_group entries. Please correct these before upgrading.');
        }
    }

    /**
     * Clears orphaned entities before introducing foreign keys
     * - The cleanup is executed based on the relationship dependencies between the entities
     * - Some recovery might be attempted before cleaning/dropping data
     * - Rows with required, but invalid foreign keys (null/bad values) will be deleted
     * - Rows with nullable/optional foreign keys will be inspected on a case-by-case basis (if possible they will be nulled, otherwise removed)
     * - Consideration is given to bidirectional/direct dependencies and exceptional cases (e.g. submission.current_publication_id, which is nullable, but required)
     * @see https://github.com/pkp/pkp-lib/issues/6093
     *
     * @throws Exception
     */
    protected function clearOrphanedEntities(): void
    {
        $this->buildOrphanedEntityProcessor();
        // Sort the tables by the number of dependent entities
        uksort(
            $this->tableProcessors,
            fn(string $a, string $b) => count($this->getEntityRelationships()[$b] ?? []) <=> count($this->getEntityRelationships()[$a] ?? [])
        );
        // Start the processing
        foreach (array_keys($this->tableProcessors) as $table) {
            $this->processTable($table);
        }
    }

    /**
     * Executes the processors for the given table
     * If changes happened (updated/deleted entries), the processors for its dependent tables will be triggered recursively
     */
    protected function processTable(string $tableName): void
    {
        $affectedRows = array_reduce($this->tableProcessors[$tableName] ?? [], fn(int $affectedRows, callable $processor): int => $affectedRows += $processor(), 0);
        if (!$affectedRows) {
            return;
        }
        foreach ($this->getEntityRelationships()[$tableName] ?? [] as $dependentTable) {
            $this->processTable($dependentTable);
        }
    }

    /**
     * This method must retrieve a relationship map, which is specific for each application
     * The format is ['parent_table' => ['child_table1', 'child_table2']]
     *
     * @return array<string,string[]>
     */
    abstract protected function getEntityRelationships(): array;

    /**
     * Builds the array with all tables that require cleanup and their respective cleanup code
     */
    protected function buildOrphanedEntityProcessor(): void
    {
        $this->addTableProcessor('submissions', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context_id->context_table.context_id current_publication_id->publications.publication_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('submissions', 'context_id', $this->getContextTable(), $this->getContextKeyField());

            // Attempts to recover the field submissions.current_publication_id before discarding the entry
            $rows = DB::table('submissions AS s')
                ->leftJoin('publications AS p', 'p.publication_id', '=', 's.current_publication_id')
                ->join(
                    'publications AS last',
                    fn(JoinClause $q) => $q->where(
                        fn(Builder $q) => $q->from('publications AS p2')
                            ->whereColumn('p2.submission_id', '=', 's.submission_id')
                            ->orderByDesc('p2.publication_id')
                            ->limit(1)
                            ->select('p2.publication_id'),
                        '=',
                        DB::raw('last.publication_id')
                    )
                )
                ->whereNull('p.publication_id')
                ->pluck('s.submission_id', 'last.publication_id');
            foreach ($rows as $publicationId => $submissionId) {
                $this->_installer->log("The current publication ID ({$publicationId}) for the submission ID {$submissionId} is invalid, the publication ID {$publicationId} will replace it");
                $affectedRows += DB::table('submissions')->where('submission_id', '=', $submissionId)->update(['current_publication_id' => $publicationId]);
            }

            // The current_publication_id is nullable, but it's in fact required by the software, so we delete orphan entries instead of nulling them
            $affectedRows += $this->deleteRequiredReference('submissions', 'current_publication_id', 'publications', 'publication_id');
            return $affectedRows;
        });

        $this->addTableProcessor('submission_files', function (): int {
            $affectedRows = 0;
            // Depends directly on ~5 entities: file_id->files.file_id genre_id->genres.genre_id source_submission_file_id->submission_files.submission_file_id submission_id->submissions.submission_id uploader_user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('submission_files', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->deleteRequiredReference('submission_files', 'file_id', 'files', 'file_id');
            $affectedRows += $this->cleanOptionalReference('submission_files', 'uploader_user_id', 'users', 'user_id');
            $affectedRows += $this->cleanOptionalReference('submission_files', 'source_submission_file_id', 'submission_files', 'submission_file_id');
            $affectedRows += $this->cleanOptionalReference('submission_files', 'genre_id', 'genres', 'genre_id');
            return $affectedRows;
        });

        $this->addTableProcessor('publications', function (): int {
            $affectedRows = 0;
            // Depends directly on ~4 entities: primary_contact_id->authors.author_id doi_id->dois.doi_id(not found in previous version) section_id->sections.section_id submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('publications', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->cleanOptionalReference('publications', 'primary_contact_id', 'authors', 'author_id');
            return $affectedRows;
        });

        $this->addTableProcessor('categories', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context_id->context_table.context_id parent_id->categories.category_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('categories', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            $affectedRows += $this->deleteOptionalReference('categories', 'parent_id', 'categories', 'category_id', $this->ignoreZero('parent_id'));
            return $affectedRows;
        });

        $this->addTableProcessor('review_rounds', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('review_rounds', 'submission_id', 'submissions', 'submission_id');
            return $affectedRows;
        });

        $this->addTableProcessor('announcement_types', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: context_id->context_table.context_id
            // Deprecated/moved field (not found on previous software version)
            // $affectedRows += $this->deleteRequiredReference('announcement_types', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            // Clean orphaned assoc_type/assoc_id data in announcement_types
            $orphanedIds = DB::table('announcement_types AS at')
                ->leftJoin($this->getContextTable() . ' AS c', 'at.assoc_id', '=', 'c.' . $this->getContextKeyField())
                ->whereNull('c.' . $this->getContextKeyField())
                ->orWhere('at.assoc_type', '<>', Application::get()->getContextAssocType())
                ->distinct()
                ->pluck('at.type_id');
            foreach ($orphanedIds as $typeId) {
                $this->_installer->log("Removing orphaned announcement type ID {$typeId} with no matching context ID.");
                $affectedRows += DB::table('announcement_types')->where('type_id', '=', $typeId)->delete();
            }
            return $affectedRows;
        });

        $this->addTableProcessor('authors', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: publication_id->publications.publication_id user_group_id->user_groups.user_group_id
            $affectedRows += $this->deleteRequiredReference('authors', 'publication_id', 'publications', 'publication_id');
            // This cleanup is required, but an extra validation will happen at the method checkAuthorsMissingUserGroup()
            // $affectedRows += $this->cleanOptionalReference('authors', 'user_group_id', 'user_groups', 'user_group_id');
            return $affectedRows;
        });

        $this->addTableProcessor('controlled_vocab_entries', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: controlled_vocab_id->controlled_vocabs.controlled_vocab_id
            $affectedRows += $this->deleteRequiredReference('controlled_vocab_entries', 'controlled_vocab_id', 'controlled_vocabs', 'controlled_vocab_id');
            return $affectedRows;
        });

        $this->addTableProcessor('filters', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: context_id->context_table.context_id filter_group_id->filter_groups.filter_group_id parent_filter_id->filters.filter_id
            $affectedRows += $this->deleteRequiredReference('filters', 'filter_group_id', 'filter_groups', 'filter_group_id');
            return $affectedRows;
        });

        $this->addTableProcessor('genres', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: context_id->context_table.context_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('genres', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('navigation_menu_item_assignments', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: navigation_menu_id->navigation_menus.navigation_menu_id navigation_menu_item_id->navigation_menu_items.navigation_menu_item_id parent_id->navigation_menu_item_assignments.navigation_menu_item_assignment_id
            $affectedRows += $this->deleteRequiredReference('navigation_menu_item_assignments', 'navigation_menu_item_id', 'navigation_menu_items', 'navigation_menu_item_id');
            $affectedRows += $this->deleteRequiredReference('navigation_menu_item_assignments', 'navigation_menu_id', 'navigation_menus', 'navigation_menu_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_assignments', function (): int {
            $affectedRows = 0;
            // Depends directly on ~4 entities: reviewer_id->users.user_id review_form_id->review_forms.review_form_id review_round_id->review_rounds.review_round_id submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('review_assignments', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->deleteRequiredReference('review_assignments', 'review_round_id', 'review_rounds', 'review_round_id');
            $affectedRows += $this->deleteRequiredReference('review_assignments', 'reviewer_id', 'users', 'user_id');
            $affectedRows += $this->cleanOptionalReference('review_assignments', 'review_form_id', 'review_forms', 'review_form_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_form_elements', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: review_form_id->review_forms.review_form_id
            $affectedRows += $this->deleteRequiredReference('review_form_elements', 'review_form_id', 'review_forms', 'review_form_id');
            return $affectedRows;
        });

        $this->addTableProcessor('announcements', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: type_id->announcement_types.type_id
            $affectedRows += $this->cleanOptionalReference('announcements', 'type_id', 'announcement_types', 'type_id');
            return $affectedRows;
        });

        $this->addTableProcessor('citations', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: publication_id->publications.publication_id
            $affectedRows += $this->deleteRequiredReference('citations', 'publication_id', 'publications', 'publication_id');
            return $affectedRows;
        });

        $this->addTableProcessor('email_templates', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: context_id->context_table.context_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('email_templates', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('event_log', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteOptionalReference('event_log', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('library_files', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context_id->context_table.context_id submission_id->submissions.submission_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('library_files', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            $affectedRows += $this->deleteOptionalReference('library_files', 'submission_id', 'submissions', 'submission_id', $this->ignoreZero('submission_id'));
            return $affectedRows;
        });

        $this->addTableProcessor('notifications', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context_id->context_table.context_id user_id->users.user_id
            $affectedRows += $this->deleteOptionalReference('notifications', 'user_id', 'users', 'user_id', $this->ignoreZero('user_id'));
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteOptionalReference('notifications', 'context_id', $this->getContextTable(), $this->getContextKeyField(), $this->ignoreZero('context_id'));
            return $affectedRows;
        });

        $this->addTableProcessor('submission_search_objects', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('submission_search_objects', 'submission_id', 'submissions', 'submission_id');
            return $affectedRows;
        });

        $this->addTableProcessor('access_keys', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('access_keys', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('announcement_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: announcement_id->announcements.announcement_id
            $affectedRows += $this->deleteRequiredReference('announcement_settings', 'announcement_id', 'announcements', 'announcement_id');
            return $affectedRows;
        });

        $this->addTableProcessor('announcement_type_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: type_id->announcement_types.type_id
            $affectedRows += $this->deleteRequiredReference('announcement_type_settings', 'type_id', 'announcement_types', 'type_id');
            return $affectedRows;
        });

        $this->addTableProcessor('author_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: author_id->authors.author_id
            $affectedRows += $this->deleteRequiredReference('author_settings', 'author_id', 'authors', 'author_id');
            return $affectedRows;
        });

        $this->addTableProcessor('category_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: category_id->categories.category_id
            $affectedRows += $this->deleteRequiredReference('category_settings', 'category_id', 'categories', 'category_id');
            return $affectedRows;
        });

        $this->addTableProcessor('citation_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: citation_id->citations.citation_id
            $affectedRows += $this->deleteRequiredReference('citation_settings', 'citation_id', 'citations', 'citation_id');
            return $affectedRows;
        });

        $this->addTableProcessor('controlled_vocab_entry_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: controlled_vocab_entry_id->controlled_vocab_entries.controlled_vocab_entry_id
            $affectedRows += $this->deleteRequiredReference('controlled_vocab_entry_settings', 'controlled_vocab_entry_id', 'controlled_vocab_entries', 'controlled_vocab_entry_id');
            return $affectedRows;
        });

        $this->addTableProcessor('data_object_tombstone_oai_set_objects', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: tombstone_id->data_object_tombstones.tombstone_id
            $affectedRows += $this->deleteRequiredReference('data_object_tombstone_oai_set_objects', 'tombstone_id', 'data_object_tombstones', 'tombstone_id');
            return $affectedRows;
        });

        $this->addTableProcessor('data_object_tombstone_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: tombstone_id->data_object_tombstones.tombstone_id
            $affectedRows += $this->deleteRequiredReference('data_object_tombstone_settings', 'tombstone_id', 'data_object_tombstones', 'tombstone_id');
            return $affectedRows;
        });

        $this->addTableProcessor('edit_decisions', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: editor_id->users.user_id review_round_id->review_rounds.review_round_id submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('edit_decisions', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->deleteRequiredReference('edit_decisions', 'editor_id', 'users', 'user_id');
            $affectedRows += $this->deleteOptionalReference('edit_decisions', 'review_round_id', 'review_rounds', 'review_round_id', $this->ignoreZero('review_round_id'));
            return $affectedRows;
        });

        $this->addTableProcessor('email_log_users', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: email_log_id->email_log.log_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('email_log_users', 'user_id', 'users', 'user_id');
            $affectedRows += $this->deleteRequiredReference('email_log_users', 'email_log_id', 'email_log', 'log_id');
            return $affectedRows;
        });

        $this->addTableProcessor('email_templates_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: email_id->email_templates.email_id
            $affectedRows += $this->deleteRequiredReference('email_templates_settings', 'email_id', 'email_templates', 'email_id');
            return $affectedRows;
        });

        $this->addTableProcessor('event_log_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: log_id->event_log.log_id
            $affectedRows += $this->deleteRequiredReference('event_log_settings', 'log_id', 'event_log', 'log_id');
            return $affectedRows;
        });

        $this->addTableProcessor('filter_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: filter_id->filters.filter_id
            $affectedRows += $this->deleteRequiredReference('filter_settings', 'filter_id', 'filters', 'filter_id');
            return $affectedRows;
        });

        $this->addTableProcessor('genre_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: genre_id->genres.genre_id
            $affectedRows += $this->deleteRequiredReference('genre_settings', 'genre_id', 'genres', 'genre_id');
            return $affectedRows;
        });

        $this->addTableProcessor($this->getContextSettingsTable(), function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: context_id->context_table.context_id
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference($this->getContextSettingsTable(), $this->getContextKeyField(), $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('library_file_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: file_id->library_files.file_id
            $affectedRows += $this->deleteRequiredReference('library_file_settings', 'file_id', 'library_files', 'file_id');
            return $affectedRows;
        });

        $this->addTableProcessor('navigation_menu_item_assignment_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: navigation_menu_item_assignment_id->navigation_menu_item_assignments.navigation_menu_item_assignment_id
            $affectedRows += $this->deleteRequiredReference('navigation_menu_item_assignment_settings', 'navigation_menu_item_assignment_id', 'navigation_menu_item_assignments', 'navigation_menu_item_assignment_id');
            return $affectedRows;
        });

        $this->addTableProcessor('navigation_menu_item_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: navigation_menu_item_id->navigation_menu_items.navigation_menu_item_id
            $affectedRows += $this->deleteRequiredReference('navigation_menu_item_settings', 'navigation_menu_item_id', 'navigation_menu_items', 'navigation_menu_item_id');
            return $affectedRows;
        });

        $this->addTableProcessor('notes', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('notes', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('notification_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: notification_id->notifications.notification_id
            $affectedRows += $this->deleteRequiredReference('notification_settings', 'notification_id', 'notifications', 'notification_id');
            return $affectedRows;
        });

        $this->addTableProcessor('notification_subscription_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context->context_table.context_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('notification_subscription_settings', 'user_id', 'users', 'user_id');
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('notification_subscription_settings', 'context', $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('publication_categories', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: category_id->categories.category_id publication_id->publications.publication_id
            $affectedRows += $this->deleteRequiredReference('publication_categories', 'publication_id', 'publications', 'publication_id');
            $affectedRows += $this->deleteRequiredReference('publication_categories', 'category_id', 'categories', 'category_id');
            return $affectedRows;
        });

        $this->addTableProcessor('publication_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: publication_id->publications.publication_id
            $affectedRows += $this->deleteRequiredReference('publication_settings', 'publication_id', 'publications', 'publication_id');
            return $affectedRows;
        });

        $this->addTableProcessor('query_participants', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: query_id->queries.query_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('query_participants', 'user_id', 'users', 'user_id');
            $affectedRows += $this->deleteRequiredReference('query_participants', 'query_id', 'queries', 'query_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_files', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: review_id->review_assignments.review_id submission_file_id->submission_files.submission_file_id
            $affectedRows += $this->deleteRequiredReference('review_files', 'submission_file_id', 'submission_files', 'submission_file_id');
            $affectedRows += $this->deleteRequiredReference('review_files', 'review_id', 'review_assignments', 'review_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_form_element_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: review_form_element_id->review_form_elements.review_form_element_id
            $affectedRows += $this->deleteRequiredReference('review_form_element_settings', 'review_form_element_id', 'review_form_elements', 'review_form_element_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_form_responses', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: review_form_element_id->review_form_elements.review_form_element_id review_id->review_assignments.review_id
            $affectedRows += $this->deleteRequiredReference('review_form_responses', 'review_id', 'review_assignments', 'review_id');
            $affectedRows += $this->deleteRequiredReference('review_form_responses', 'review_form_element_id', 'review_form_elements', 'review_form_element_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_form_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: review_form_id->review_forms.review_form_id
            $affectedRows += $this->deleteRequiredReference('review_form_settings', 'review_form_id', 'review_forms', 'review_form_id');
            return $affectedRows;
        });

        $this->addTableProcessor('review_round_files', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: review_round_id->review_rounds.review_round_id submission_file_id->submission_files.submission_file_id submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('review_round_files', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->deleteRequiredReference('review_round_files', 'submission_file_id', 'submission_files', 'submission_file_id');
            $affectedRows += $this->deleteRequiredReference('review_round_files', 'review_round_id', 'review_rounds', 'review_round_id');
            return $affectedRows;
        });

        $this->addTableProcessor('sessions', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteOptionalReference('sessions', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('stage_assignments', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: submission_id->submissions.submission_id user_group_id->user_groups.user_group_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('stage_assignments', 'user_id', 'users', 'user_id');
            $affectedRows += $this->deleteRequiredReference('stage_assignments', 'user_group_id', 'user_groups', 'user_group_id');
            $affectedRows += $this->deleteRequiredReference('stage_assignments', 'submission_id', 'submissions', 'submission_id');
            return $affectedRows;
        });

        $this->addTableProcessor('subeditor_submission_group', function (): int {
            $affectedRows = 0;
            // Depends directly on ~3 entities: context_id->context_table.context_id user_group_id->user_groups.user_group_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('subeditor_submission_group', 'user_id', 'users', 'user_id');
            // Deprecated/moved field (not found on previous software version)
            // $affectedRows += $this->deleteRequiredReference('subeditor_submission_group', 'user_group_id', 'user_groups', 'user_group_id');
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('subeditor_submission_group', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('submission_comments', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: author_id->users.user_id submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('submission_comments', 'submission_id', 'submissions', 'submission_id');
            $affectedRows += $this->deleteRequiredReference('submission_comments', 'author_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('submission_file_revisions', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: file_id->files.file_id submission_file_id->submission_files.submission_file_id
            $affectedRows += $this->deleteRequiredReference('submission_file_revisions', 'submission_file_id', 'submission_files', 'submission_file_id');
            $affectedRows += $this->deleteRequiredReference('submission_file_revisions', 'file_id', 'files', 'file_id');
            return $affectedRows;
        });

        $this->addTableProcessor('submission_file_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: submission_file_id->submission_files.submission_file_id
            $affectedRows += $this->deleteRequiredReference('submission_file_settings', 'submission_file_id', 'submission_files', 'submission_file_id');
            return $affectedRows;
        });

        $this->addTableProcessor('submission_search_object_keywords', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: keyword_id->submission_search_keyword_list.keyword_id object_id->submission_search_objects.object_id
            $affectedRows += $this->deleteRequiredReference('submission_search_object_keywords', 'object_id', 'submission_search_objects', 'object_id');
            $affectedRows += $this->deleteRequiredReference('submission_search_object_keywords', 'keyword_id', 'submission_search_keyword_list', 'keyword_id');
            return $affectedRows;
        });

        $this->addTableProcessor('submission_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: submission_id->submissions.submission_id
            $affectedRows += $this->deleteRequiredReference('submission_settings', 'submission_id', 'submissions', 'submission_id');
            return $affectedRows;
        });

        $this->addTableProcessor('temporary_files', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('temporary_files', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('user_group_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_group_id->user_groups.user_group_id
            $affectedRows += $this->deleteRequiredReference('user_group_settings', 'user_group_id', 'user_groups', 'user_group_id');
            return $affectedRows;
        });

        $this->addTableProcessor('user_group_stage', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: context_id->context_table.context_id user_group_id->user_groups.user_group_id
            $affectedRows += $this->deleteRequiredReference('user_group_stage', 'user_group_id', 'user_groups', 'user_group_id');
            // Custom field (not found in at least one of the softwares)
            $affectedRows += $this->deleteRequiredReference('user_group_stage', 'context_id', $this->getContextTable(), $this->getContextKeyField());
            return $affectedRows;
        });

        $this->addTableProcessor('user_interests', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: controlled_vocab_entry_id->controlled_vocab_entries.controlled_vocab_entry_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('user_interests', 'user_id', 'users', 'user_id');
            $affectedRows += $this->deleteRequiredReference('user_interests', 'controlled_vocab_entry_id', 'controlled_vocab_entries', 'controlled_vocab_entry_id');
            return $affectedRows;
        });

        $this->addTableProcessor('user_settings', function (): int {
            $affectedRows = 0;
            // Depends directly on ~1 entities: user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('user_settings', 'user_id', 'users', 'user_id');
            return $affectedRows;
        });

        $this->addTableProcessor('user_user_groups', function (): int {
            $affectedRows = 0;
            // Depends directly on ~2 entities: user_group_id->user_groups.user_group_id user_id->users.user_id
            $affectedRows += $this->deleteRequiredReference('user_user_groups', 'user_id', 'users', 'user_id');
            $affectedRows += $this->deleteRequiredReference('user_user_groups', 'user_group_id', 'user_groups', 'user_group_id');
            return $affectedRows;
        });
    }

    /**
     * Delete rows from the source table where the foreign key field contains either invalid values or NULL
     * Used for NOT NULL/required relationships
     * @param $filter callable(Builder): Builder
     */
    protected function deleteRequiredReference(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn, ?callable $filter = null): int
    {
        if (!$this->validateColumns($sourceTable, $sourceColumn, $referenceTable, $referenceColumn)) {
            return 0;
        }

        $filter ??= fn(Builder $q) => $q;
        $ids = $filter(
            DB::table("{$sourceTable} AS s")
                ->leftJoin("{$referenceTable} AS r", "s.{$sourceColumn}", '=', "r.{$referenceColumn}")
                ->whereNull("r.{$referenceColumn}")
                ->distinct()
        )
            ->pluck("s.{$sourceColumn}");

        if (!$ids->count()) {
            return 0;
        }

        $removed = 0;
        $this->_installer->log("Removing orphaned entries from \"{$sourceTable}\" with an invalid value for the required column \"{$sourceColumn}\". The following IDs do not exist at the reference table \"{$referenceTable}\":\n{$ids->join(', ')}");
        foreach ($ids->chunk(1000) as $chunkedIds) {
            $removed += DB::table($sourceTable)
                ->whereIn($sourceColumn, $chunkedIds)
                ->orWhereNull($sourceColumn)
                ->delete();
        }
        $this->_installer->log("{$removed} entries removed");
        return $removed;
    }

    /**
     * Resets optional/nullable foreign key fields from the source table to NULL when the field contains invalid values
     * Used for NULLABLE relationships
     * @param $filter callable(Builder): Builder
     */
    protected function cleanOptionalReference(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn, ?callable $filter = null): int
    {
        if (!$this->validateColumns($sourceTable, $sourceColumn, $referenceTable, $referenceColumn)) {
            return 0;
        }

        $filter ??= fn(Builder $q) => $q;
        $ids = $filter(
            DB::table("{$sourceTable} AS s")
                ->leftJoin("{$referenceTable} AS r", "s.{$sourceColumn}", '=', "r.{$referenceColumn}")
                ->whereNotNull("s.{$sourceColumn}")
                ->whereNull("r.{$referenceColumn}")
                ->distinct()
        )
            ->pluck("s.{$sourceColumn}");

        if (!$ids->count()) {
            return 0;
        }

        $updated = 0;
        $this->_installer->log("Cleaning orphaned entries from \"{$sourceTable}\" with an invalid value for the column \"{$sourceColumn}\". The following IDs do not exist at the reference table \"{$referenceTable}\" and will be reset to NULL:\n{$ids->join(', ')}");
        foreach ($ids->chunk(1000) as $chunkedIds) {
            $updated += DB::table($sourceTable)
                ->whereIn($sourceColumn, $chunkedIds)
                ->update([$sourceColumn => null]);
        }
        $this->_installer->log("{$updated} entries updated");
        return $updated;
    }

    /**
     * Deletes rows from the source table where the foreign key field contains invalid values
     * Used for NULLABLE relationships, where the source record lose the meaning without its relationship
     * @param $filter callable(Builder): Builder
     */
    protected function deleteOptionalReference(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn, ?callable $filter = null): int
    {
        if (!$this->validateColumns($sourceTable, $sourceColumn, $referenceTable, $referenceColumn)) {
            return 0;
        }

        $filter ??= fn(Builder $q) => $q;
        $ids = $filter(
            DB::table("{$sourceTable} AS s")
                ->leftJoin("{$referenceTable} AS r", "s.{$sourceColumn}", '=', "r.{$referenceColumn}")
                ->whereNotNull("s.{$sourceColumn}")
                ->whereNull("r.{$referenceColumn}")
                ->distinct()
        )
            ->pluck("s.{$sourceColumn}");

        if (!$ids->count()) {
            return 0;
        }
        $this->_installer->log("Removing orphaned entries from \"{$sourceTable}\" with an invalid value for the column \"{$sourceColumn}\". The following IDs do not exist at the reference table \"{$referenceTable}\":\n{$ids->join(', ')}");
        $removed = 0;
        foreach ($ids->chunk(1000) as $chunkedIds) {
            $removed += DB::table($sourceTable)
                ->whereIn($sourceColumn, $chunkedIds)
                ->delete();
        }
        $this->_installer->log("{$removed} entries removed");
        return $removed;
    }

    /**
     * Adds a table processor to the list and defines an optional processing priority (higher values are processed first)
     */
    protected function addTableProcessor(string $table, callable $processor): void
    {
        if (!Schema::hasTable($table)) {
            $this->_installer->log("Skipped cleanup of nonexistent table {$table}");
            return;
        }
        $this->tableProcessors[$table][] = $processor;
    }

    /**
     * Helper to ignore foreign keys with 0, which will be handled by the migrations which add foreign keys
     *
     * @return callable(Builder $q): Builder
     */
    protected function ignoreZero(string $sourceColumn): callable
    {
        return fn(Builder $q) => $q->where("s.{$sourceColumn}", '!=', 0);
    }

    /**
     * Check the existence of required columns
     */
    protected function validateColumns(string $sourceTable, string $sourceColumn, string $referenceTable, string $referenceColumn): bool
    {
        if (!Schema::hasColumn($sourceTable, $sourceColumn)) {
            $this->_installer->log("Cleanup on the field \"{$sourceTable}.{$sourceColumn}\" was skipped, the field was not found");
            return false;
        }
        if (!Schema::hasColumn($referenceTable, $referenceColumn)) {
            $this->_installer->log("Cleanup on the field \"{$sourceTable}.{$sourceColumn}\" was skipped, the reference field \"{$referenceTable}.{$referenceColumn}\" was not found");
            return false;
        }
        return true;
    }

    /**
     * Clears duplicated user_settings
     * This method used to be a migration, it has been incorporated at the pre-flight to avoid issues with the checks introduced by the MergeLocalesMigration
     * Given that it operates on duplicated entries, it should be ok to run it several times
     * @see https://github.com/pkp/pkp-lib/issues/7167
     */
    protected function clearDuplicatedUserSettings(): void
    {
        // Locates and removes duplicated user_settings
        // The latest code stores settings using assoc_id = 0 and assoc_type = 0. Which means entries using null or anything else are outdated.
        // Note: Old versions (e.g. OJS <= 2.x) made use of these fields to store some settings, but they have been removed years ago, which means they are safe to be discarded.
        if (DB::connection() instanceof PostgresConnection) {
            DB::unprepared(
                "DELETE FROM user_settings s
                USING user_settings duplicated
                -- Attempts to find a better fitting record among the duplicates (preference is given to the smaller assoc_id/assoc_type values)
                LEFT JOIN user_settings best
                    ON best.setting_name = duplicated.setting_name
                    AND best.user_id = duplicated.user_id
                    AND best.locale = duplicated.locale
                    AND (
                        COALESCE(best.assoc_id, 999999) < COALESCE(duplicated.assoc_id, 999999)
                        OR (
                            COALESCE(best.assoc_id, 999999) = COALESCE(duplicated.assoc_id, 999999)
                            AND COALESCE(best.assoc_type, 999999) < COALESCE(duplicated.assoc_type, 999999)
                        )
                    )
                -- Locates all duplicated settings (same key fields, except the assoc_type/assoc_id)
                WHERE s.setting_name = duplicated.setting_name
                    AND s.user_id = duplicated.user_id
                    AND s.locale = duplicated.locale
                    AND (
                        COALESCE(s.assoc_type, -999999) <> COALESCE(duplicated.assoc_type, -999999)
                        OR COALESCE(s.assoc_id, -999999) <> COALESCE(duplicated.assoc_id, -999999)
                    )
                    -- Ensures a better record was found (if not found, it means the current duplicated record is the best and shouldn't be removed)
                    AND best.user_id IS NOT NULL"
            );
            return;
        }

        DB::unprepared(
            "DELETE s
            FROM user_settings s
            -- Locates all duplicated settings (same key fields, except the assoc_type/assoc_id)
            INNER JOIN user_settings duplicated
                ON s.setting_name = duplicated.setting_name
                AND s.user_id = duplicated.user_id
                AND s.locale = duplicated.locale
                AND (
                    COALESCE(s.assoc_type, -999999) <> COALESCE(duplicated.assoc_type, -999999)
                    OR COALESCE(s.assoc_id, -999999) <> COALESCE(duplicated.assoc_id, -999999)
                )
            -- Attempts to find a better fitting record among the duplicates (preference is given to the smaller assoc_id/assoc_type values)
            LEFT JOIN user_settings best
                ON best.setting_name = duplicated.setting_name
                AND best.user_id = duplicated.user_id
                AND best.locale = duplicated.locale
                AND (
                    COALESCE(best.assoc_id, 999999) < COALESCE(duplicated.assoc_id, 999999)
                    OR (
                        COALESCE(best.assoc_id, 999999) = COALESCE(duplicated.assoc_id, 999999)
                        AND COALESCE(best.assoc_type, 999999) < COALESCE(duplicated.assoc_type, 999999)
                    )
                )
            -- Ensures a better record was found (if not found, it means the current duplicated record is the best and shouldn't be removed)
            WHERE best.user_id IS NOT NULL"
        );
    }

    /**
     * Drops existing foreign keys that might have a "ON DELETE RESTRICT" rule, which would break the cleanup
     * The removed foreign keys will be re-added later on by the migration I6093_AddForeignKeys
     */
    protected function dropForeignKeys(): void
    {
        if (DB::getDoctrineSchemaManager()->introspectTable('submission_files')->hasForeignKey('submission_files_file_id_foreign')) {
            Schema::table('submission_files', fn (Blueprint $table) => $table->dropForeign('submission_files_file_id_foreign'));
        }
        Schema::table('submission_file_revisions', function (Blueprint $table) {
            foreach (['submission_file_revisions_submission_file_id_foreign', 'submission_file_revisions_file_id_foreign'] as $foreignKeyName) {
                if (DB::getDoctrineSchemaManager()->introspectTable('submission_file_revisions')->hasForeignKey($foreignKeyName)) {
                    $table->dropForeign($foreignKeyName);
                }
            }
        });
        if (DB::getDoctrineSchemaManager()->introspectTable('review_files')->hasForeignKey('review_files_submission_file_id_foreign')) {
            Schema::table('review_files', fn (Blueprint $table) => $table->dropForeign('review_files_submission_file_id_foreign'));
        }
        if (DB::getDoctrineSchemaManager()->introspectTable('review_round_files')->hasForeignKey('review_round_files_submission_file_id_foreign')) {
            Schema::table('review_round_files', fn (Blueprint $table) => $table->dropForeign('review_round_files_submission_file_id_foreign'));
        }
    }
}

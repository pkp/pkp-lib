<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PKPI7014_DoiMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPI7014_DoiMigration
 *
 * @brief Migrate DOI related fields to the new structures
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class PKPI7014_DoiMigration extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        // DOIs
        Schema::create('dois', function (Blueprint $table) {
            $table->bigInteger('doi_id')->autoIncrement();

            $table->bigInteger('context_id');
            $table->foreign('context_id')->references($this->getContextIdColumn())->on($this->getContextTable());
            $table->index(['context_id'], 'dois_context_id');

            $table->string('doi');
            $table->smallInteger('status')->default(1);
        });

        // Settings
        Schema::create('doi_settings', function (Blueprint $table) {
            $table->bigIncrements('doi_setting_id');
            $table->bigInteger('doi_id');
            $table->foreign('doi_id')->references('doi_id')->on('dois')->cascadeOnDelete();
            $table->index(['doi_id'], 'doi_settings_doi_id');

            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->unique(['doi_id', 'locale', 'setting_name'], 'doi_settings_unique');
        });

        // Add doiId to publication
        Schema::table('publications', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'publications_doi_id');
        });
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

    protected function migrateExistingDataUp(): void
    {
        $this->_migrateDoiSettingsToContext();
        $this->_transferDefaultPatterns();
        $this->_migratePublicationDoisUp();
    }

    /**
     * Move DOI settings from plugin_settings to Context (Journal/Press/Server) settings
     */
    protected function _migrateDoiSettingsToContext(): void
    {
        // Get plugin_based settings
        $q = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'doipubidplugin')
            ->select(['context_id','setting_name', 'setting_value']);
        $results = $q->get();

        $data = new \stdClass();
        $data->enabledDois = [];
        $data->doiCreationTime = [];
        $data->enabledDoiTypes = [];
        $data->doiPrefix = [];
        $data->doiSuffixType = [];
        $data->doiPublicationSuffixPattern = [];
        $data->doiRepresentationSuffixPattern = [];

        $data = $this->addSuffixPatternsData($data);

        // Map to context-based settings
        $results->reduce(function ($carry, $item) {
            switch ($item->setting_name) {
                case 'enabled':
                    $carry->enabledDois[] = [
                        $this->getContextIdColumn() => $item->context_id,
                        'setting_name' => 'enableDois',
                        'setting_value' => (int) $item->setting_value,
                    ];
                    $carry->doiCreationTime[] = [
                        $this->getContextIdColumn() => $item->context_id,
                        'setting_name' => 'doiCreationTime',
                        'setting_value' => 'copyEditCreationTime',
                    ];
                    return $carry;
                case 'enablePublicationDoi':
                    if (!isset($carry->enabledDoiTypes[$item->context_id])) {
                        $carry->enabledDoiTypes[$item->context_id] = [
                            $this->getContextIdColumn() => $item->context_id,
                            'setting_name' => 'enabledDoiTypes',
                            'setting_value' => [],
                        ];
                    }

                    if ($item->setting_value === '1') {
                        array_push($carry->enabledDoiTypes[$item->context_id]['setting_value'], 'publication');
                    }
                    return $carry;
                case 'enableRepresentationDoi':
                    if (!isset($carry->enabledDoiTypes[$item->context_id])) {
                        $carry->enabledDoiTypes[$item->context_id] = [
                            $this->getContextIdColumn() => $item->context_id,
                            'setting_name' => 'enabledDoiTypes',
                            'setting_value' => [],
                        ];
                    }

                    if ($item->setting_value === '1') {
                        array_push($carry->enabledDoiTypes[$item->context_id]['setting_value'], 'representation');
                    }
                    return $carry;
                case 'doiSuffix':
                    $value = '';
                    switch ($item->setting_value) {
                        case 'default':
                            $value = 'default';
                            break;
                        case 'pattern':
                            $value = 'customPattern';
                            break;
                        case 'customId':
                            $value = 'customId';
                            break;
                    }
                    $carry->doiSuffixType[] = [
                        $this->getContextIdColumn() => $item->context_id,
                        'setting_name' => 'doiSuffixType',
                        'setting_value' => $value,
                    ];
                    return $carry;
                case 'doiPrefix':
                case 'doiPublicationSuffixPattern':
                case 'doiRepresentationSuffixPattern':
                    $carry->{$item->setting_name}[] = [
                        $this->getContextIdColumn() => $item->context_id,
                        'setting_name' => $item->setting_name,
                        'setting_value' => $item->setting_value,
                    ];
                    return $carry;
                default:
                    $carry = $this->insertSuffixPatternsData($carry, $item);
                    $carry = $this->insertEnabledDoiTypes($carry, $item);
                    return $carry;
            }
        }, $data);

        // Prepare insert statements
        $insertData = [];
        foreach ($data->enabledDois as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiCreationTime as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->enabledDoiTypes as $item) {
            $item['setting_value'] = json_encode($item['setting_value']);
            array_push($insertData, $item);
        }
        foreach ($data->doiPrefix as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiSuffixType as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiPublicationSuffixPattern as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiRepresentationSuffixPattern as $item) {
            array_push($insertData, $item);
        }

        $insertData = $this->prepareSuffixPatternsForInsert($data, $insertData);

        DB::table($this->getContextSettingsTable())->insert($insertData);

        // Add minimum required DOI settings to context if DOI plugin not previously enabled
        $missingDoiSettingsInsertStatement = DB::table($this->getContextTable())
            ->select($this->getContextIdColumn())
            ->whereNotIn($this->getContextIdColumn(), function (Builder $q) {
                $q->select($this->getContextIdColumn())
                    ->from($this->getContextSettingsTable())
                    ->where('setting_name', '=', 'enableDois');
            })
            ->get()
            ->reduce(function ($carry, $item) {
                $carry[] = [
                    $this->getContextIdColumn() => $item->{$this->getContextIdColumn()},
                    'setting_name' => 'enableDois',
                    'setting_value' => 0,
                ];
                $carry[] = [
                    $this->getContextIdColumn() => $item->{$this->getContextIdColumn()},
                    'setting_name' => 'doiCreationTime',
                    'setting_value' => 'copyEditCreationTime'
                ];
                $carry[] = [
                    $this->getContextIdColumn() => $item->{$this->getContextIdColumn()},
                    'setting_name' => 'useDefaultDoiSuffix',
                    'setting_value' => 1
                ];
                return $carry;
            }, []);

        DB::table($this->getContextSettingsTable())->insert($missingDoiSettingsInsertStatement);

        // Cleanup old DOI plugin settings
        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'doipubidplugin')
            ->delete();
        DB::table('versions')
            ->where('product_type', '=', 'plugins.pubIds')
            ->where('product', '=', 'doi')
            ->delete();
    }

    /**
     * Copies default patterns to custom pattern fields if no custom patterns have been supplied
     */
    private function _transferDefaultPatterns(): void
    {
        // Collect list of all possible custom suffix types for each context
        $suffixPatternStatuses = DB::table($this->getContextTable())
            ->select($this->getContextIdColumn())
            ->get()
            ->reduce(function ($carry, $item) {
                $carry[$item->{$this->getContextIdColumn()}] = [];
                foreach ($this->getSuffixPatternNames() as $suffixPatternName) {
                    $carry[$item->{$this->getContextIdColumn()}][$suffixPatternName] = false;
                }
                return $carry;
            }, []);

        // Flag those suffix types for each context that are already stored in the database
        DB::table($this->getContextSettingsTable())
            ->whereIn('setting_name', function (Builder $q) {
                $q->select('setting_name')
                    ->from($this->getContextSettingsTable())
                    ->whereIn('setting_name', $this->getSuffixPatternNames())
                    // We don't want to flag null suffix patterns as existing, otherwise the default
                    // pattern would not be added
                    ->whereNotNull('setting_value');
            })
            ->get()
            ->each(function ($item) use (&$suffixPatternStatuses) {
                $suffixPatternStatuses[$item->{$this->getContextIdColumn()}][$item->setting_name] = true;
            });

        // Prepare insert statement to add default suffix patterns as custom patterns
        // where no previous custom pattern existed
        $insertStatements = [];
        foreach ($suffixPatternStatuses as $contextId => $suffixPatternStatus) {
            foreach ($suffixPatternStatus as $suffixPattern => $status) {
                if ($status) {
                    continue;
                }
                $insertStatements[] = [
                    $this->getContextIdColumn() => $contextId,
                    'setting_name' => $suffixPattern,
                    'setting_value' => $this->getSuffixPatternValue($suffixPattern),
                ];
            }
        }

        // Finally, save custom patterns to DB
        if (!empty($insertStatements)) {
            DB::table($this->getContextSettingsTable())->upsert($insertStatements, [$this->getContextIdColumn(), 'locale', 'setting_name'], ['setting_value']);
        }
    }

    /**
     * Move publication DOIs from publication_settings table to DOI objects
     */
    private function _migratePublicationDoisUp(): void
    {
        $q = DB::table('submissions', 's')
            ->select(['s.context_id', 'p.publication_id', 'p.doi_id', 'pss.setting_name', 'pss.setting_value'])
            ->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
            ->leftJoin('publication_settings as pss', 'pss.publication_id', '=', 'p.publication_id')
            ->where('pss.setting_name', '=', 'pub-id::doi');

        $q->chunkById(1000, function ($items) {
            foreach ($items as $item) {
                // Double-check to ensure a DOI object does not already exist for publication
                if ($item->doi_id === null) {
                    $doiId = $this->_addDoi($item->context_id, $item->setting_value);

                    // Add association to newly created DOI to publication
                    DB::table('publications')
                        ->where('publication_id', '=', $item->publication_id)
                        ->update(['doi_id' => $doiId]);
                } else {
                    // Otherwise, update existing DOI object
                    $this->_updateDoi($item->doi_id, $item->context_id, $item->setting_value);
                }
            }
        }, 'p.publication_id', 'publication_id');

        // Remove pub-id::doi settings entry
        DB::table('publication_settings')
            ->where('setting_name', '=', 'pub-id::doi')
            ->delete();
    }

    /**
     * Creates a new DOI object for a given context ID and DOI
     */
    protected function _addDoi(string $contextId, string $doi): int
    {
        return DB::table('dois')
            ->insertGetId(
                [
                    'context_id' => $contextId,
                    'doi' => $doi,
                ]
            );
    }

    /**
     * Update the context ID and doi for a given DOI object
     */
    protected function _updateDoi(int $doiId, string $contextId, string $doi): int
    {
        return DB::table('dois')
            ->where('doi_id', '=', $doiId)
            ->update(
                [
                    'context_id' => $contextId,
                    'doi' => $doi
                ]
            );
    }

    /**
     * Gets app-specific context table name, e.g. journals
     */
    abstract protected function getContextTable(): string;

    /**
     * Gets app-specific context_id column, e.g. journal_id
     */
    abstract protected function getContextIdColumn(): string;

    /**
     * Gets app-specific context settings table, e.g. journal_settings
     */
    abstract protected function getContextSettingsTable(): string;

    /**
     * Adds app-specific suffix patterns to data collector stdClass
     */
    abstract protected function addSuffixPatternsData(\stdClass $data): \stdClass;

    /**
     * Adds suffix pattern settings from DB into reducer's data
     */
    abstract protected function insertSuffixPatternsData(\stdClass $carry, \stdClass $item): \stdClass;

    /**
     * Adds insert-ready statements for all applicable suffix pattern items
     */
    abstract protected function prepareSuffixPatternsForInsert(\stdClass $processedData, array $insertData): array;

    /**
     * Add app-specific enabled DOI types for insert into DB
     */
    abstract protected function insertEnabledDoiTypes(\stdClass $carry, \stdClass $item): \stdClass;

    /**
     * Get an array with the keys for each suffix pattern type
     */
    abstract protected function getSuffixPatternNames(): array;

    /**
     * Returns the default pattern for the given suffix pattern type
     */
    abstract protected function getSuffixPatternValue(string $suffixPatternName): string;
}

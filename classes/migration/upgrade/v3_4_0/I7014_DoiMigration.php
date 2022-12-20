<?php

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\doi\Doi;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\upgrade\v3_4_0\PKPI7014_DoiMigration;

class I7014_DoiMigration extends PKPI7014_DoiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        parent::up();

        // Add doiId to galley
        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'publication_galleys_doi_id');
        });

        $this->migrateExistingDataUp();
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
        parent::migrateExistingDataUp();
        // Find all existing DOIs, move to new DOI objects and add foreign key for pub object
        $this->_migrateGalleyDoisUp();
        $this->_migrateCrossrefSettingsToContext();
    }

    /**
     * Transfer over batch_id, failedMsg and settings, filter info for importExport to generic plugin transfer
     */
    private function _migrateCrossrefSettingsToContext()
    {
        // ===== Filters ===== //
        DB::table('filters')
            ->where('class_name', '=', 'plugins.importexport.crossref.filter.PreprintCrossrefXmlFilter')
            ->update(['class_name' => 'plugins.generic.crossref.filter.PreprintCrossrefXmlFilter']);

        // ===== Submissions Statuses & Settings ===== //
        // 1. Get submissions with Crossref info
        $publicationDois = DB::table('publications', 'p')
            ->select(['p.submission_id', 'p.doi_id'])
            ->whereNotNull('p.doi_id')
            ->get()
            ->map(function ($item) {
                return [
                    'submission_id' => $item->submission_id,
                    'doi_id' => $item->doi_id
                ];
            });
        $galleyDois = DB::table('publication_galleys', 'pg')
            ->leftJoin('publications as p', 'pg.publication_id', '=', 'p.publication_id')
            ->select(['p.submission_id', 'pg.doi_id'])
            ->whereNotNull('pg.doi_id')
            ->get()
            ->map(function ($item) {
                return [
                    'submission_id' => $item->submission_id,
                    'doi_id' => $item->doi_id
                ];
            });

        // 2. Get all DOIs possibly associated with submission (publications and galleys)
        $doisBySubmission = $publicationDois->concat($galleyDois)
            ->mapToGroups(function ($item) {
                return [$item['submission_id'] => $item['doi_id']];
            })
            ->map(function ($item) {
                return ['doiIds' => $item->all()];
            })->toArray();
        // 3. Apply batchId and failedMsg to all DOIs
        DB::table('submissions', 's')
            ->leftJoin('submission_settings as ss', 's.submission_id', '=', 'ss.submission_id')
            ->whereIn('ss.setting_name', ['crossref::registeredDoi', 'crossref::status', 'crossref::batchId', 'crossref::failedMsg'])
            ->select(['ss.submission_id', 'ss.setting_name', 'ss.setting_value'])
            ->get()
            ->each(function ($item) use (&$doisBySubmission) {
                if (!isset($doisBySubmission[$item->submission_id])) {
                    return;
                }
                switch ($item->setting_name) {
                    case 'crossref::registeredDoi':
                        $doisBySubmission[$item->submission_id]['crossref::registeredDoi'] = $item->setting_value;
                        return;
                    case 'crossref::status':
                        $status = Doi::STATUS_ERROR;
                        $registrationAgency = null;
                        if (in_array($item->setting_value, ['found', 'registered', 'markedRegistered'])) {
                            $status = Doi::STATUS_REGISTERED;
                            if ($item->setting_value === 'registered') {
                                $registrationAgency = 'CrossRefExportPlugin';
                            }
                        }
                        $doisBySubmission[$item->submission_id]['status'] = $status;
                        if ($registrationAgency !== null) {
                            $doisBySubmission[$item->submission_id]['registrationAgency'] = $registrationAgency;
                        }
                        return;
                    case 'crossref::batchId':
                        $doisBySubmission[$item->submission_id]['crossrefplugin_batchId'] = $item->setting_value;
                        return;
                    case 'crossref::failedMsg':
                        $doisBySubmission[$item->submission_id]['crossrefplugin_failedMsg'] = $item->setting_value;
                        return;
                }
            });
        // 4. Apply status to all DOIs
        $doiSettingInserts = [];
        $doiStatusUpdates = [];
        foreach ($doisBySubmission as $item) {
            $doiIds = $item['doiIds'];
            foreach ($doiIds as $doiId) {
                // Settings
                if (isset($item['crossrefplugin_batchId'])) {
                    $doiSettingInserts[] = [
                        'doi_id' => $doiId,
                        'setting_name' => 'crossrefplugin_batchId',
                        'setting_value' => $item['crossrefplugin_batchId'],
                    ];
                }
                if (isset($item['crossrefplugin_failedMsg'])) {
                    $doiSettingInserts[] = [
                        'doi_id' => $doiId,
                        'setting_name' => 'crossrefplugin_failedMsg',
                        'setting_value' => $item['crossrefplugin_failedMsg'],
                    ];
                }

                if (isset($item['registrationAgency'])) {
                    $doiSettingInserts[] = [
                        'doi_id' => $doiId,
                        'setting_name' => 'registrationAgency',
                        'setting_value' => $item['registrationAgency'],
                    ];
                }

                // Status
                if (isset($item['status'])) {
                    $doiStatusUpdates[$doiId] = ['status' => $item['status']];
                } elseif (isset($item['crossref::registeredDoi'])) {
                    $doiStatusUpdates[$doiId] = ['status' => Doi::STATUS_REGISTERED];
                }
            }
        }

        foreach (array_chunk($doiSettingInserts, 100) as $chunkedInserts) {
            DB::table('doi_settings')
                ->insert($chunkedInserts);
        }
        foreach ($doiStatusUpdates as $doiId => $doiStatusUpdate) {
            DB::table('dois')
                ->where('doi_id', '=', $doiId)
                ->update($doiStatusUpdate);
        }

        // 5. Clean up old settings
        DB::table('submission_settings')
            ->whereIn('setting_name', ['crossref::registeredDoi', 'crossref::status', 'crossref::batchId'])
            ->delete();

        // ===== General cleanup ===== //

        // If any Crossref settings are configured, assume plugin is in use and enable
        $contextsWithPluginEnabled = DB::table('servers')
            ->whereIn('server_id', function (Builder $q) {
                $q->select('context_id')
                    ->from('plugin_settings')
                    ->where('plugin_name', '=', 'crossrefexportplugin');
            })
            ->select(['server_id'])
            ->get();
        $contextsWithPluginEnabled->each(function ($item) {
            DB::table('plugin_settings')
                ->insert(
                    [
                        'plugin_name' => 'crossrefplugin',
                        'context_id' => $item->server_id,
                        'setting_name' => 'enabled',
                        'setting_value' => 1,
                        'setting_type' => 'bool'
                    ]
                );
        });

        // Enable automatic DOI deposit if configured
        $contextsWithAutomaticDeposit = DB::table('servers')
            ->whereIn('server_id', function (Builder $q) {
                $q->select(['context_id'])
                    ->from('plugin_settings')
                    ->where('plugin_name', '=', 'crossrefexportplugin')
                    ->where('setting_name', '=', 'automaticRegistration')
                    ->where('setting_value', '=', 1) ;
            })
            ->select(['server_id'])
            ->get();
        $contextsWithAutomaticDeposit->each(function ($item) {
            DB::table('journal_settings')
                ->insert(
                    [
                        'server_id' => $item->server_id,
                        'setting_name' => 'automaticDoiDeposit',
                        'setting_value' => 1
                    ]
                );
        });

        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'crossrefexportplugin')
            ->where('setting_name', '=', 'automaticRegistration')
            ->delete();

        // Update no-longer-in-use version for importExport plugin
        DB::table('versions')
            ->where('product_type', '=', 'plugins.importexport')
            ->where('product', '=', 'crossref')
            ->delete();

        // Remove scheduled task
        DB::table('scheduled_tasks')
            ->where('class_name', '=', 'plugins.importexport.crossref.CrossrefInfoSender')
            ->delete();
    }

    /**
     * Move galley DOIs from publication_galley_settings table to DOI objects
     */
    private function _migrateGalleyDoisUp(): void
    {
        $q = DB::table('submissions', 's')
            ->select(['s.context_id', 'pg.galley_id', 'pg.doi_id', 'pgss.setting_name', 'pgss.setting_value'])
            ->leftJoin('publications as p', 'p.submission_id', '=', 's.submission_id')
            ->leftJoin('publication_galleys as pg', 'pg.publication_id', '=', 'p.publication_id')
            ->leftJoin('publication_galley_settings as pgss', 'pgss.galley_id', '=', 'pg.galley_id')
            ->where('pgss.setting_name', '=', 'pub-id::doi');

        $q->chunkById(1000, function ($items) {
            foreach ($items as $item) {
                // Double-check to ensure a DOI object does not already exist for galley
                if ($item->doi_id === null) {
                    $doiId = $this->_addDoi($item->context_id, $item->setting_value);

                    // Add association to newly created DOI to galley
                    DB::table('publication_galleys')
                        ->where('galley_id', '=', $item->galley_id)
                        ->update(['doi_id' => $doiId]);
                } else {
                    // Otherwise update existing DOI object
                    $this->_updateDoi($item->doi_id, $item->context_id, $item->setting_value);
                }
            }
        }, 'pg.galley_id', 'galley_id');
    }

    /**
     * Gets app-specific context table name, e.g. journals
     */
    protected function getContextTable(): string
    {
        return 'servers';
    }

    /**
     * Gets app-specific context_id column, e.g. journal_id
     */
    protected function getContextIdColumn(): string
    {
        return 'server_id';
    }

    /**
     * Gets app-specific context settings table, e.g. journal_settings
     */
    protected function getContextSettingsTable(): string
    {
        return 'server_settings';
    }

    /**
     * Adds app-specific suffix patterns to data collector stdClass
     */
    protected function addSuffixPatternsData(\stdClass $data): \stdClass
    {
        // OPS does not add any additional publication types,
        // so we only need to return the unmodified data.
        return $data;
    }

    /**
     * Adds suffix pattern settings from DB into reducer's data
     */
    protected function insertSuffixPatternsData(\stdClass $carry, \stdClass $item): \stdClass
    {
        // OPS does not add any additional publication types,
        // so we only need to return the passed in carry object.
        return $carry;
    }

    /**
     * Adds insert-ready statements for all applicable suffix pattern items
     */
    protected function prepareSuffixPatternsForInsert(\stdClass $processedData, array $insertData): array
    {
        // OPS does not add any additional publication types,
        // so we only need to return the passed in insert data array.
        return $insertData;
    }

    /**
     * Add app-specific enabled DOI types for insert into DB
     */
    protected function insertEnabledDoiTypes(\stdClass $carry, \stdClass $item): \stdClass
    {
        // OPS does not add any additional publication types,
        // so we only need to return the passed in carry object.
        return $carry;
    }

    /**
     * Get an array with the keys for each suffix pattern type
     */
    protected function getSuffixPatternNames(): array
    {
        return ['doiPublicationSuffixPattern', 'doiRepresentationSuffixPattern'];
    }

    /**
     * Returns the default pattern for the given suffix pattern type
     */
    protected function getSuffixPatternValue(string $suffixPatternName): string
    {
        $pattern = '';
        switch ($suffixPatternName) {
            case 'doiPublicationSuffixPattern':
                $pattern = '%j.%a';
                break;
            case 'doiRepresentationSuffixPattern':
                $pattern = '%j.%a.g%g';
                break;
        }

        return $pattern;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\migration\upgrade\v3_4_0\I7014_DoiMigration', '\I7014_DoiMigration');
}

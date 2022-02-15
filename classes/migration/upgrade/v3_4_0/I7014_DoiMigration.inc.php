<?php

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\doi\Doi;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\install\DoiMigration;
use PKP\migration\Migration;

class I7014_DoiMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DOIs
        $doiMigrations = new DoiMigration($this->_installer, $this->_attributes);
        $doiMigrations->up();

        // Context ID foreign key needs to be added on an app-specific basis
        Schema::table('dois', function (Blueprint $table) {
            $table->foreign('context_id')->references('server_id')->on('servers');
        });

        // Add doiId to publication
        Schema::table('publications', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
        });

        // Add doiId to galley
        Schema::table('publication_galleys', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
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

    private function migrateExistingDataUp()
    {
        // Find all existing DOIs, move to new DOI objects and add foreign key for pub object
        $this->_migrateDoiSettingsToContext();
        $this->_migratePublicationDoisUp();
        $this->_migrateGalleyDoisUp();
        $this->_migrateCrossrefSettingsToContext();
    }

    /**
     * Move DOI settings from plugin_settings to Context (Journal) settings
     */
    private function _migrateDoiSettingsToContext()
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
        $data->customDoiSuffixType = [];
        $data->doiPublicationSuffixPattern = [];
        $data->doiRepresentationSuffixPattern = [];

        // Map to context-based settings
        $results->reduce(function ($carry, $item) {
            switch ($item->setting_name) {
                case 'enabled':
                    $carry->enabledDois[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => 'enableDois',
                        'setting_value' => (int) $item->setting_value,
                    ];
                    $carry->doiCreationTime[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => 'doiCreationTime',
                        'setting_value' => 'copyEditCreationTime',
                    ];
                    return $carry;
                case 'enablePublicationDoi':
                    if (!isset($carry->enabledDoiTypes[$item->context_id])) {
                        $carry->enabledDoiTypes[$item->context_id] = [
                            'server_id' => $item->context_id,
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
                            'server_id' => $item->context_id,
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
                            $value = 'defaultPattern';
                            break;
                        case 'pattern':
                            $value = 'customPattern';
                            break;
                        case 'customId':
                            $value = 'customId';
                            break;
                    }
                    $carry->customDoiSuffixType[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => 'customDoiSuffixType',
                        'setting_value' => $value,
                    ];
                    return $carry;
                case 'doiPrefix':
                    $carry->doiPrefix[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => $item->setting_name,
                        'setting_value' => $item->setting_value,
                    ];
                    return $carry;
                case 'doiPublicationSuffixPattern':
                    $carry->doiPublicationSuffixPattern[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => $item->setting_name,
                        'setting_value' => $item->setting_value,
                    ];
                    return $carry;
                case 'doiRepresentationSuffixPattern':
                    $carry->doiRepresentationSuffixPattern[] = [
                        'server_id' => $item->context_id,
                        'setting_name' => $item->setting_name,
                        'setting_value' => $item->setting_value,
                    ];
                    return $carry;
                default:
                    return $carry;
            }
        }, $data);

        // Prepare insert statements
        $insertData = [];
        foreach ($data->enabledDois as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->enabledDoiTypes as $item) {
            $item['setting_value'] = json_encode($item['setting_value']);
            array_push($insertData, $item);
        }
        foreach ($data->doiPrefix as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->customDoiSuffixType as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiPublicationSuffixPattern as $item) {
            array_push($insertData, $item);
        }
        foreach ($data->doiRepresentationSuffixPattern as $item) {
            array_push($insertData, $item);
        }

        DB::table('server_settings')->insert($insertData);

        // Add minimum required DOI settings to context if DOI plugin not previously enabled
        $missingDoiSettingsInsertStatement = DB::table('servers')
            ->select('server_id')
            ->whereNotIn('server_id', function (Builder $q) {
                $q->select('server_id')
                    ->from('server_settings')
                    ->where('setting_name', '=', 'enableDois');
            })
            ->get()
            ->reduce(function ($carry, $item) {
                $carry[] = [
                    'server_id' => $item->server_id,
                    'setting_name' => 'enableDois',
                    'setting_value' => 0,
                ];
                $carry[] = [
                    'server_id' => $item->server_id,
                    'setting_name' => 'doiCreationTime',
                    'setting_value' => 'copyEditCreationTime'
                ];
                return $carry;
            }, []);

        DB::table('server_settings')->insert($missingDoiSettingsInsertStatement);

        // Cleanup old DOI plugin settings
        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'doipubidplugin')
            ->delete();
        DB::table('versions')
            ->where('product_type', '=', 'plugins.pubIds')
            ->where('product', '=', 'doi')
            ->update(['current' => 0]);
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
                        if (in_array($item->setting_value, ['found', 'registered', 'markedRegistered'])) {
                            $status = Doi::STATUS_REGISTERED;
                        }
                        $doisBySubmission[$item->submission_id]['status'] = $status;
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
        }, 'publication_id');

        // Remove pub-id::doi settings entry
        DB::table('publication_settings')
            ->where('setting_name', '=', 'pub-id::doi')
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
        }, 'galley_id');
    }

    /**
     * Creates a new DOI object for a given context ID and DOI
     *
     */
    private function _addDoi(string $contextId, string $doi): int
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
     *
     */
    private function _updateDoi(int $doiId, string $contextId, string $doi): int
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\migration\upgrade\v3_4_0\I7014_DoiMigration', '\I7014_DoiMigration');
}

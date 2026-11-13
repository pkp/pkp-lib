<?php

/**
 * @file tools/repairRorAffiliations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepairRorAffiliationsTool
 *
 * @ingroup tools
 *
 * @brief Repair author affiliations wrongly migrated by the 3.5.0 upgrade, which derived
 *  RORs from affiliation names and ignored the ROR plugin's rorId (pkp/pkp-lib#13274).
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

use APP\core\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\submission\PKPSubmission;

class RepairRorAffiliationsTool extends \PKP\cliTool\CommandLineTool
{
    private const CHUNK_SIZE = 1000;

    // the full ROR dump has ~147k records (incl. inactive ones) and only grows; fewer means an incomplete import
    private const MIN_ROR_COUNT = 100000;

    private bool $commit = false;
    /** @var int[] author ids not to repair */
    private array $exclude = [];
    private string $outDir;
    private string $stamp;

    public function __construct($argv = [])
    {
        parent::__construct($argv);

        // the files directory, as the application root is usually the web root and the reports hold unpublished data
        $this->outDir = rtrim(Config::getVar('files', 'files_dir'), '/');
        foreach ($this->argv as $arg) {
            if ($arg === '--commit') {
                $this->commit = true;
            } elseif (str_starts_with($arg, '--exclude=')) {
                $ids = array_map('trim', explode(',', substr($arg, strlen('--exclude='))));
                if (in_array(false, array_map('ctype_digit', $ids), true)) {
                    fwrite(STDERR, "--exclude expects a comma-separated list of author ids.\n");
                    exit(1);
                }
                $this->exclude = array_map('intval', $ids);
            } else {
                $this->exitWithUsageMessage();
            }
        }

        if (!is_dir($this->outDir) || !is_writable($this->outDir)) {
            fwrite(STDERR, "The files directory {$this->outDir} (files_dir in config.inc.php) is not writable; run the tool as the user that owns it.\n");
            exit(1);
        }
        $this->stamp = date('Ymd-His');
    }

    public function usage()
    {
        echo "Repair author affiliations wrongly migrated to RORs by the 3.5.0 upgrade.\n"
            . "Only authors whose affiliations are unchanged since the upgrade are repaired;\n"
            . "the others that may still need a fix are listed for manual review.\n"
            . "Run it on 3.5, before upgrading to 3.6. The CSV and SQL files are written to the files directory (files_dir).\n\n"
            . "Usage: {$this->scriptName} [--commit] [--exclude=<author ids>]\n"
            . "  (no option)  write a CSV report of the affected authors and change nothing\n"
            . "  --commit     also apply the repair and write an SQL file that reverts it\n"
            . "  --exclude    comma-separated author ids not to repair, e.g. --exclude=12,345\n";
    }

    public function execute(): void
    {
        // from 3.6 on, rorId is also an organization contributor's own ROR ID, which this tool would take for the ROR plugin's
        $version = Application::get()->getCurrentVersion();
        if ($version->getMajor() !== 3 || $version->getMinor() !== 5) {
            fwrite(STDERR, 'This tool only runs on 3.5; this installation is ' . $version->getVersionString() . ".\n");
            exit(1);
        }

        if (!Schema::hasTable('author_affiliations') || !Schema::hasTable('rors')) {
            fwrite(STDERR, "The author_affiliations or rors table does not exist; is this a 3.5 installation?\n");
            exit(1);
        }

        // both recomputing the old name matches and verifying plugin rorIds need the complete registry
        $rorCount = DB::table('rors')->count();
        if ($rorCount < self::MIN_ROR_COUNT) {
            fwrite(STDERR, "The rors table (the ROR registry cache) has only {$rorCount} entries, so it is incomplete. "
                . "Run the scheduled task UpdateRorRegistryDataset first, then run this tool again.\n");
            exit(1);
        }

        // what the buggy upgrade matched: author affiliation strings equal to a ROR name,
        // compared in SQL so that the database collation behaves exactly as it did then
        $nameMatches = DB::table('author_settings as aus')
            ->join('ror_settings as rs', 'rs.setting_value', '=', 'aus.setting_value')
            ->join('rors as r', 'r.ror_id', '=', 'rs.ror_id')
            ->where('aus.setting_name', '=', 'affiliation')
            ->whereNotNull('aus.setting_value')
            ->where('rs.setting_name', '=', 'name')
            ->select(['aus.author_id', 'r.ror'])
            ->distinct()
            ->get()
            ->groupBy('author_id')
            ->map(fn (Collection $rows) => $rows->pluck('ror')->all());

        $authorIds = DB::table('author_settings')
            ->whereIn('setting_name', ['affiliation', 'rorId'])
            ->whereNotNull('setting_value')
            ->distinct()
            ->orderBy('author_id')
            ->pluck('author_id')
            ->all();

        $csvFile = "{$this->outDir}/repairRorAffiliations-{$this->stamp}.csv";
        $csv = $this->openPrivate($csvFile);
        fputcsv($csv, ['action', 'review_reason', 'author_id', 'publication_id', 'submission_id', 'published', 'legacy_affiliation', 'legacy_ror_id', 'current_affiliations', 'repaired_affiliations']);

        $toRepair = [];
        $counts = ['repair' => 0, 'review' => 0, 'excluded' => 0];
        $repairableIds = [];
        foreach (array_chunk($authorIds, self::CHUNK_SIZE) as $authorIdChunk) {
            foreach ($this->classify($authorIdChunk, $nameMatches) as $result) {
                if ($result['action'] === 'repair') {
                    $repairableIds[] = $result['authorId'];
                    if (in_array($result['authorId'], $this->exclude)) {
                        $result['action'] = 'excluded';
                    }
                }
                $counts[$result['action']]++;
                fputcsv($csv, [
                    $result['action'],
                    $result['reason'],
                    $result['authorId'],
                    $result['publicationId'],
                    $result['submissionId'],
                    $result['published'] ? 'yes' : 'no',
                    $this->toJson((object) $result['legacyAffiliation']),
                    $result['legacyRorId'],
                    $this->toJson($this->namesAsObjects($result['current'])),
                    $result['action'] === 'review' ? '' : $this->toJson($this->namesAsObjects($result['repaired'])),
                ]);
                if ($result['action'] === 'repair') {
                    $toRepair[$result['authorId']] = $result['repaired'];
                }
            }
        }
        fclose($csv);

        echo 'Checked ' . count($authorIds) . " authors with legacy affiliation data.\n"
            . "To repair: {$counts['repair']}, excluded: {$counts['excluded']}, to review manually: {$counts['review']}.\n"
            . "Report: {$csvFile}\n";

        $unknownExcludes = array_diff($this->exclude, $repairableIds);
        if (!empty($unknownExcludes)) {
            echo 'Note: these excluded author ids would not be repaired anyway: ' . implode(',', $unknownExcludes) . "\n";
        }

        if (!$this->commit || empty($toRepair)) {
            if (!empty($toRepair)) {
                echo "Nothing was changed. Run again with --commit to apply the repair.\n";
            }
            return;
        }

        $rollbackFile = "{$this->outDir}/repairRorAffiliations-{$this->stamp}-rollback.sql";
        $this->writeRollback($rollbackFile, array_keys($toRepair));
        echo "Rollback SQL: {$rollbackFile}\n";

        $this->repair($toRepair);
        echo "Repaired {$counts['repair']} authors. Metadata already deposited or harvested "
            . "(e.g. Crossref, DataCite, OAI) for published ones is only corrected after it is redeposited.\n";
    }

    /**
     * @param int[] $authorIds
     * @param Collection<int,string[]> $nameMatches
     */
    private function classify(array $authorIds, Collection $nameMatches): array
    {
        $legacy = DB::table('author_settings')
            ->whereIn('author_id', $authorIds)
            ->whereIn('setting_name', ['affiliation', 'rorId'])
            ->whereNotNull('setting_value')
            ->get(['author_id', 'locale', 'setting_name', 'setting_value'])
            ->groupBy('author_id');

        $current = $this->loadAffiliations($authorIds);

        $publications = DB::table('authors as a')
            ->join('publications as p', 'p.publication_id', '=', 'a.publication_id')
            ->whereIn('a.author_id', $authorIds)
            ->get(['a.author_id', 'p.publication_id', 'p.submission_id', 'p.status'])
            ->keyBy('author_id');

        $plugInRors = $legacy->map(fn (Collection $rows) => $this->normalizeRor($rows->firstWhere('setting_name', 'rorId')?->setting_value));
        $knownRors = DB::table('rors')
            ->whereIn('ror', $plugInRors->filter()->unique()->values()->all())
            ->pluck('ror', 'ror')
            ->all();

        $results = [];
        foreach ($authorIds as $authorId) {
            $legacyRows = $legacy->get($authorId, collect());
            $affiliationRows = $legacyRows->where('setting_name', 'affiliation');
            $matchedRors = $nameMatches->get($authorId, []);
            $plugInRor = $plugInRors->get($authorId);

            $legacyNames = $affiliationRows->pluck('setting_value', 'locale')->all();
            $buggyShapes = [
                // names only: no name matched, or the ROR registry download failed during the upgrade
                [['ror' => null, 'name' => $legacyNames]],
                // 3.5.0-0 and 3.5.0-1 split a non-ROR affiliation into one per locale (pkp/pkp-lib#11675)
                array_map(fn ($locale, $name) => ['ror' => null, 'name' => [$locale => $name]], array_keys($legacyNames), $legacyNames),
            ];
            if ($matchedRors) {
                $buggyShapes[] = array_map(fn ($ror) => ['ror' => $ror, 'name' => []], $matchedRors);
            }

            // the same rules as I7135_CreateNewRorRegistryCacheTables::migrateAffiliations()
            $ror = $plugInRor;
            $rorIsKnown = $ror !== null && isset($knownRors[$ror]);
            if ($ror !== null && !$rorIsKnown) {
                $ror = null;
            }
            $repaired = [['ror' => $ror, 'name' => $rorIsKnown ? [] : $legacyNames]];

            $buggyShapes = array_map(fn (array $shape) => $this->canonical($shape), $buggyShapes);
            $repaired = $this->canonical($repaired);
            $currentRows = $current->get($authorId, []);
            $currentCanonical = $this->canonical($currentRows);

            if ($currentCanonical === $repaired) {
                continue;
            }

            if (in_array($currentCanonical, $buggyShapes, true)) {
                $action = 'repair';
                $reasons = [];
            } else {
                // edited since the upgrade, or a ROR name changed since the upgrade's registry:
                // only report it if a name-derived ROR is still there or the plugin ROR is missing
                $currentRors = array_column($currentCanonical, 'ror');
                $guessed = array_diff($matchedRors, [$plugInRor]);
                $stillGuessed = array_intersect($guessed, $currentRors);
                $plugInRorMissing = $rorIsKnown && !in_array($plugInRor, $currentRors, true);
                // derived from a name ROR has changed since the upgrade, or chosen later by hand
                $unexplained = array_diff(array_filter($currentRors), $matchedRors, [$plugInRor]);
                if (empty($stillGuessed) && !$plugInRorMissing && empty($unexplained)) {
                    continue;
                }
                $action = 'review';
                $reasons = array_filter([
                    !empty($stillGuessed) ? 'name-derived ROR still present' : null,
                    $plugInRorMissing ? 'ROR plugin ROR missing' : null,
                    !empty($unexplained) ? 'ROR not explained by the legacy data' : null,
                ]);
            }

            $publication = $publications->get($authorId);
            $results[] = [
                'action' => $action,
                'reason' => implode('; ', $reasons),
                'authorId' => $authorId,
                'publicationId' => $publication?->publication_id,
                'submissionId' => $publication?->submission_id,
                'published' => $publication && (int) $publication->status === PKPSubmission::STATUS_PUBLISHED,
                'legacyAffiliation' => $legacyNames,
                'legacyRorId' => $legacyRows->firstWhere('setting_name', 'rorId')?->setting_value,
                'current' => $currentCanonical,
                'repaired' => $repaired,
            ];
        }
        return $results;
    }

    /**
     * @param int[] $authorIds
     *
     * @return Collection<int,array> author_id => [['id' => ..., 'ror' => ..., 'name' => [locale => value]], ...]
     */
    private function loadAffiliations(array $authorIds): Collection
    {
        $affiliations = DB::table('author_affiliations')
            ->whereIn('author_id', $authorIds)
            ->orderBy('author_affiliation_id')
            ->get(['author_affiliation_id', 'author_id', 'ror']);

        $names = DB::table('author_affiliation_settings')
            ->whereIn('author_affiliation_id', $affiliations->pluck('author_affiliation_id')->all())
            ->where('setting_name', '=', 'name')
            ->get(['author_affiliation_id', 'locale', 'setting_value'])
            ->groupBy('author_affiliation_id');

        return $affiliations
            ->groupBy('author_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'id' => $row->author_affiliation_id,
                'ror' => $row->ror,
                'name' => $names->get($row->author_affiliation_id, collect())->pluck('setting_value', 'locale')->all(),
            ])->all());
    }

    /**
     * Order-independent form for comparison: blank names and empty rows are dropped.
     */
    private function canonical(array $affiliations): array
    {
        $result = [];
        foreach ($affiliations as $affiliation) {
            $name = array_filter($affiliation['name'], fn ($value) => trim((string) $value) !== '');
            ksort($name);
            if ($affiliation['ror'] === null && empty($name)) {
                continue;
            }
            $result[] = ['ror' => $affiliation['ror'], 'name' => $name];
        }
        usort($result, fn ($a, $b) => [$a['ror'], json_encode($a['name'])] <=> [$b['ror'], json_encode($b['name'])]);
        return $result;
    }

    private function toJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Encode empty name lists as {} rather than [].
     */
    private function namesAsObjects(array $affiliations): array
    {
        return array_map(fn ($affiliation) => ['ror' => $affiliation['ror'], 'name' => (object) $affiliation['name']], $affiliations);
    }

    /**
     * @param array<int,array> $toRepair author_id => canonical affiliations
     */
    private function repair(array $toRepair): void
    {
        DB::transaction(function () use ($toRepair) {
            foreach (array_chunk(array_keys($toRepair), self::CHUNK_SIZE) as $authorIdChunk) {
                $affiliationIds = DB::table('author_affiliations')->whereIn('author_id', $authorIdChunk)->pluck('author_affiliation_id')->all();
                DB::table('author_affiliation_settings')->whereIn('author_affiliation_id', $affiliationIds)->delete();
                DB::table('author_affiliations')->whereIn('author_id', $authorIdChunk)->delete();

                foreach ($authorIdChunk as $authorId) {
                    foreach ($toRepair[$authorId] as $affiliation) {
                        $affiliationId = DB::table('author_affiliations')->insertGetId(
                            ['author_id' => $authorId, 'ror' => $affiliation['ror']],
                            'author_affiliation_id'
                        );
                        $settings = [];
                        foreach ($affiliation['name'] as $locale => $name) {
                            $settings[] = ['author_affiliation_id' => $affiliationId, 'locale' => $locale, 'setting_name' => 'name', 'setting_value' => $name];
                        }
                        DB::table('author_affiliation_settings')->insert($settings);
                    }
                }
            }
        });
    }

    /**
     * Write SQL restoring the current affiliations, including their ids, of the given authors.
     *
     * @param int[] $authorIds
     */
    private function writeRollback(string $file, array $authorIds): void
    {
        $pdo = DB::getPdo();
        $quote = fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value);

        $sql = $this->openPrivate($file);
        fwrite($sql, "-- Reverts repairRorAffiliations.php run {$this->stamp}\nSTART TRANSACTION;\n");
        foreach (array_chunk($authorIds, self::CHUNK_SIZE) as $authorIdChunk) {
            $idList = implode(',', array_map('intval', $authorIdChunk));
            fwrite($sql, "DELETE FROM author_affiliation_settings WHERE author_affiliation_id IN (SELECT author_affiliation_id FROM author_affiliations WHERE author_id IN ({$idList}));\n");
            fwrite($sql, "DELETE FROM author_affiliations WHERE author_id IN ({$idList});\n");

            $affiliations = DB::table('author_affiliations')->whereIn('author_id', $authorIdChunk)->orderBy('author_affiliation_id')->get();
            foreach ($affiliations as $row) {
                fwrite($sql, "INSERT INTO author_affiliations (author_affiliation_id, author_id, ror) VALUES ({$row->author_affiliation_id}, {$row->author_id}, {$quote($row->ror)});\n");
            }
            $settings = DB::table('author_affiliation_settings')
                ->whereIn('author_affiliation_id', $affiliations->pluck('author_affiliation_id')->all())
                ->orderBy('author_affiliation_setting_id')
                ->get();
            foreach ($settings as $row) {
                fwrite($sql, "INSERT INTO author_affiliation_settings (author_affiliation_setting_id, author_affiliation_id, locale, setting_name, setting_value) VALUES ({$row->author_affiliation_setting_id}, {$row->author_affiliation_id}, {$quote($row->locale)}, {$quote($row->setting_name)}, {$quote($row->setting_value)});\n");
            }
        }
        fwrite($sql, "COMMIT;\n");
        fclose($sql);
    }

    /**
     * Open a new file for writing, readable only by its owner.
     *
     * @return resource
     */
    private function openPrivate(string $file)
    {
        $umask = umask(0077);
        $handle = fopen($file, 'x');
        umask($umask);
        if ($handle === false) {
            fwrite(STDERR, "Could not create {$file}.\n");
            exit(1);
        }
        return $handle;
    }

    /**
     * Mirrors I7135_CreateNewRorRegistryCacheTables::normalizeRor().
     */
    private function normalizeRor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = strtolower(rtrim(trim($value), '/'));
        $value = preg_replace('#^http://#', 'https://', $value);
        if (preg_match('#^0[a-hj-km-np-tv-z0-9]{6}\d{2}$#', $value)) {
            $value = 'https://ror.org/' . $value;
        }
        return preg_match('#^https://ror\.org/0[a-hj-km-np-tv-z0-9]{6}\d{2}$#', $value) ? $value : null;
    }
}

$tool = new RepairRorAffiliationsTool($argv ?? []);
$tool->execute();

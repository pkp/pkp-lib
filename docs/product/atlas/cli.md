# Atlas — cli (command-line tools)

- **Modality**: cli
- **Sweep date**: 2026-07-26
- **Trees swept**:
  - Shared: `/Users/jarda/git/pkp/pkp-main/ojs-main/lib/pkp/tools/` — swept once; `diff -rq` confirmed the `lib/pkp/tools/` dirs in ojs-main, omp-main and ops-main working trees are byte-identical. Working-tree submodule SHA in all three checkouts: `ad4606f93ef153d018e3c4cce751698258c9bbcd` (note: task brief said `9db481cf4d`, which matches none of the recorded gitlinks — ojs-main HEAD records `9a91dc2ee9`, omp/ops record `ad4606f93e`; ojs-main's `lib/pkp` is locally modified. Recorded here mechanically, no judgment.)
  - App-side: `/Users/jarda/git/pkp/pkp-main/ojs-main/tools/`, `/Users/jarda/git/pkp/pkp-main/omp-main/tools/`, `/Users/jarda/git/pkp/pkp-main/ops-main/tools/`
- **Globs used**: `lib/pkp/tools/*.php` (22 files), `tools/*.php` per app (ojs 9, omp 7, ops 7). Console/command dirs checked mechanically: no `lib/pkp/cli/`, `lib/pkp/console/` or `lib/pkp/commands/` dir exists; CLI base classes live in `lib/pkp/classes/cliTool/` (CommandInterface, CommandLineTool, ConvertLogFileTool, InstallTool, MergeUsersTool, UpgradeTool + traits) — base classes, not entry points, so not atomized.
- Same-named app tools are one atom with a combined apps field (descriptions identical across apps except the object noun — noted in-line).
- **Maintainer ruling (2026-07-27)**: the cli modality is **out-of-scope for test coverage by default** — CLI atoms never seed features or canonical scenarios. They are claimable by specs only as reference ("CLI-only" operational counterpart of a UI feature, analogous to the API-only rule). Atoms no spec claims are satisfied under the atom-claim invariant as out-of-scope with this reason; no per-atom marking needed.

| ID | Apps | Pointer | Description |
|---|---|---|---|
| CLI-001 | ojs omp ops | `lib/pkp/tools/appKey.php` (`CommandAppKey`) | Generate/validate the app key in `config.inc.php`. Verbs: `generate`, `validate`, `configure`, `ciphers`, `usage` |
| CLI-002 | ojs omp ops | `lib/pkp/tools/buildSwagger.php` (`buildSwagger`) | Compile a complete `swagger.json` file for hosting API documentation |
| CLI-003 | ojs omp ops | `lib/pkp/tools/constants.php` (`constants`) | Get the value of application constants |
| CLI-004 | ojs omp ops | `lib/pkp/tools/convertApacheAccessLogFile.php` (`ConvertApacheAccessLogFile`) | Copy, prepare and convert an Apache access log file into the new format needed for stats reprocessing |
| CLI-005 | ojs omp ops | `lib/pkp/tools/convertUsageStatsLogFile.php` (`ConvertUsageStatsLogFile`) | Convert an old usage stats log file (releases < 3.4) into the new format |
| CLI-006 | ojs omp ops | `lib/pkp/tools/events.php` (`commandEvents`) | List all events registered on the system. Verbs: `list`, `cache`, `clear`, `usage` |
| CLI-007 | ojs omp ops | `lib/pkp/tools/generateTestGeoMetrics.php` (`generateTestGeoMetrics`) | Generate example Geo metric data |
| CLI-008 | ojs omp ops | `lib/pkp/tools/generateTestMetrics.php` (`generateTestMetrics`) | Generate example metric data |
| CLI-009 | ojs omp ops | `lib/pkp/tools/getHooks.php` (`getHooks`) | Compile documentation on hooks |
| CLI-010 | ojs omp ops | `lib/pkp/tools/installEmailTemplate.php` (`installEmailTemplate`) | Install email templates from PO files into the database (args: emailKey, locales) |
| CLI-011 | ojs omp ops | `lib/pkp/tools/installPluginVersion.php` (`InstallPluginVersionTool`) | Install a plugin version descriptor |
| CLI-012 | ojs omp ops | `lib/pkp/tools/jobs.php` (`commandJobs`) | List, iterate and purge queued jobs on the database. Verbs: `list`, `purge`, `test`, `total`, `help`, `run`, `work`, `failed`, `restart`, `usage` |
| CLI-013 | ojs omp ops | `lib/pkp/tools/markLocaleKeyFuzzy.php` (`MarkLocaleKeyFuzzy`) | Mark a locale key as fuzzy across all locales except en |
| CLI-014 | ojs omp ops | `lib/pkp/tools/migration.php` (`migrationTool`) | Run a single fully-qualified database migration class (no class-docblock brief; usage: `migration.php \fully\qualified\migration\Name [up|down]`) |
| CLI-015 | ojs omp ops | `lib/pkp/tools/moveLocaleKeysToLib.php` (`MoveLocaleKeysToLib`) | Move a locale key from an application's locale files to the pkp-lib locale files |
| CLI-016 | ojs omp ops | `lib/pkp/tools/parseCitations.php` (`CitationsParsingTool`) | Parse existing citations. Verbs: `all`, `context`, `submission` |
| CLI-017 | ojs omp ops | `lib/pkp/tools/plugins.php` (`PluginsTool`) | Get information about installed/available plugins. Verbs: `list`, `info` |
| CLI-018 | ojs omp ops | `lib/pkp/tools/removeLocaleKey.php` (`RemoveLocaleKey`) | Remove a locale key from all locale files |
| CLI-019 | ojs omp ops | `lib/pkp/tools/replaceVariableInLocaleKey.php` (`ReplaceVariableInLocaleKey`) | Replace a `{$variable}` in a specific locale key across all locales |
| CLI-020 | ojs omp ops | `lib/pkp/tools/reprocessUsageStatsMonth.php` (`reprocessUsageStatsMonth`) | Reprocess the usage stats log files for a month |
| CLI-021 | ojs omp ops | `lib/pkp/tools/scheduler.php` (`commandScheduler`) | List and run scheduled tasks. Verbs: `list`, `run`, `test`, `work`, `usage` |
| CLI-022 | ojs omp ops | `lib/pkp/tools/setVersionTool.php` (`SetVersionTool`) | Set a version number for each publication |
| CLI-023 | ojs omp ops | `tools/bootstrap.php` | Application-specific configuration common to all CLI tools (bootstrap include required by the other tools; not itself a runnable tool) |
| CLI-024 | ojs omp | `tools/cleanReviewerInterests.php` (`ReviewerInterestsDeletionTool`) | Remove user interests that are not referenced by any user accounts. Verbs: `--show`, `--remove` |
| CLI-025 | ojs ops | `tools/deleteSubmissions.php` (`SubmissionDeletionTool`) | Delete submissions (usage: `submission_id [...]`) |
| CLI-026 | ojs omp ops | `tools/importExport.php` (`importExport`) | Perform import/export tasks by dispatching to `importexport`-category plugins. Verbs: `list`, `[pluginName] usage`, `[pluginName] [params...]` |
| CLI-027 | ojs omp ops | `tools/install.php` (`OJSInstallTool` / `OMPInstallTool` / `OPSInstallTool`) | CLI tool for installing the application (interactive; extends `PKP\cliTool\InstallTool`) |
| CLI-028 | ojs omp ops | `tools/mergeUsers.php` (`mergeUsers`) | Merge two user accounts (extends `PKP\cliTool\MergeUsersTool`) |
| CLI-029 | ojs omp ops | `tools/rebuildSearchIndex.php` (`rebuildSearchIndex`) | Rebuild the keyword search database (article in OJS / monograph in OMP / preprint in OPS; usage: `[options] [journal_path]`) |
| CLI-030 | ojs | `tools/resolveAgencyDuplicates.php` (`resolveAgencyDuplicates`) | Resolve DOI registration agency duplication pre-3.4 (agencies: crossref, ...). Verbs: `test`, `resolve [agency_name] --force` (no file docblock) |
| CLI-031 | ojs omp ops | `tools/upgrade.php` (`upgradeTool`) | CLI tool for upgrading the application (extends `PKP\cliTool\UpgradeTool`). Verbs: `check`, `latest`, `upgrade`, `download` |

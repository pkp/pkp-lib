<?php

/**
 * @file classes/cliTool/StampIdentityMetadataTool.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StampIdentityMetadataTool
 *
 * @ingroup tools
 *
 * @brief Base for the CLI tools that re-stamp the context identity metadata onto published
 *   publications, from the context's current settings and the values given as options.
 *   The values to stamp are always shown and must be confirmed before anything is changed.
 */

namespace PKP\cliTool;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\core\DataObject;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use Throwable;

abstract class StampIdentityMetadataTool extends CommandLineTool
{
    protected int $contextId = 0;
    protected string $command = '';

    /** @var string[] Command parameters: IDs or years */
    protected array $parameters = [];

    /** @var array<string,string> Options as given, name => value */
    protected array $options = [];

    protected Context $context;

    /** @var int[] Years given to the 'year' command */
    protected array $years = [];

    /** @var array<string,array<string,string>> Localized overrides, field => [locale => value] */
    protected array $localizedOverrides = [];

    /** @var array<string,string> Overrides of single-value fields, field => value */
    protected array $scalarOverrides = [];

    /**
     * The default identity values: they apply to every field not given as an option.
     * Taken from the context's current settings. Only read, for the preview and the checks.
     */
    protected DataObject $defaultIdentity;

    /** @var array<string,array{stamped:int,unchanged:int}> Counts per object type */
    protected array $counts = [];

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        $positional = [];
        foreach ($this->argv as $arg) {
            if (!str_starts_with($arg, '--')) {
                $positional[] = $arg;
                continue;
            }
            if (!str_contains($arg, '=')) {
                $this->exitWithError("Option {$arg} needs a value, e.g. {$arg}=<value>. An empty value clears the field.");
            }
            [$name, $value] = explode('=', substr($arg, 2), 2);
            $this->options[$name] = trim($value);
        }

        if (count($positional) < 2) {
            $this->usage();
            exit(1);
        }
        $this->contextId = (int) array_shift($positional);
        $this->command = array_shift($positional);
        $this->parameters = $positional;
    }

    /**
     * Get the name of the context type used in messages, e.g. 'journal'.
     */
    abstract protected function getContextNoun(): string;

    /**
     * Get the word used for published content in messages, e.g. 'posted' for preprints.
     */
    protected function getPublishedWord(): string
    {
        return 'published';
    }

    /**
     * Get the options for localized fields, option prefix => field details.
     * An option is given per locale, e.g. --name-en.
     *
     * @return array<string,array{field:string,label:string,description:string}>
     */
    protected function getLocalizedOptions(): array
    {
        $noun = $this->getContextNoun();
        return [
            'name' => ['field' => 'contextName', 'label' => 'Name', 'description' => ucfirst($noun) . ' name'],
            'abbreviation' => ['field' => 'contextAbbreviation', 'label' => 'Abbreviation', 'description' => 'Abbreviation, as used in citations and DOI deposits'],
        ];
    }

    /**
     * Get the options for single-value fields, option => field details.
     *
     * @return array<string,array{field:string,label:string,description:string}>
     */
    protected function getScalarOptions(): array
    {
        return [
            'primary-locale' => ['field' => 'contextPrimaryLocale', 'label' => 'Primary locale', 'description' => 'Primary locale, selecting the name used where only one is allowed'],
            'publisher-location' => ['field' => 'publisherLocation', 'label' => 'Publisher location', 'description' => 'Publisher location, e.g. Berlin'],
        ];
    }

    /**
     * Get the supported commands.
     */
    protected function getCommands(): array
    {
        return ['publication_id', 'submission_id', 'year', 'all'];
    }

    /**
     * Get the command synopses for the usage text.
     */
    protected function getCommandUsage(): array
    {
        return [
            'publication_id <id> [<id> ...]',
            'submission_id <id> [<id> ...]',
            'year <year_or_range> [<year_or_range> ...]',
            'all',
        ];
    }

    /**
     * Get the explanations of the commands for the usage text.
     */
    protected function getCommandNotes(): array
    {
        $published = $this->getPublishedWord();
        return [
            "'publication_id' stamps the given {$published} publications, e.g. a single version.",
            "'submission_id' stamps all {$published} publications (versions) of the given submissions.",
            "'year' stamps the publications {$published} in the given years. Year ranges are YYYY-YYYY, e.g. 2010-2020.",
            "'all' stamps all {$published} publications.",
        ];
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        $noun = $this->getContextNoun();
        $published = $this->getPublishedWord();
        echo "Re-stamps the {$noun} identity metadata onto {$published} content, from the {$noun}'s current settings\n"
            . "and the values given as options. Content not {$published} yet is skipped: it is stamped when it is {$published}.\n"
            . "The values to stamp are shown and must be confirmed, so the tool must be run interactively.\n"
            . "Make a database backup before running it.\n\n"
            . "Usage:\n";
        foreach ($this->getCommandUsage() as $synopsis) {
            echo "\t{$this->scriptName} <context_id> {$synopsis} [options]\n";
        }
        echo "\n" . implode("\n", $this->getCommandNotes()) . "\n\n"
            . "Options (fields not given are stamped with the {$noun}'s current value; an empty value clears the field):\n";
        foreach ($this->getLocalizedOptions() as $prefix => $option) {
            printf("\t%-32s%s, per locale\n", "--{$prefix}-<locale>=<value>", $option['description']);
        }
        foreach ($this->getScalarOptions() as $name => $option) {
            printf("\t%-32s%s\n", "--{$name}=<value>", $option['description']);
        }
    }

    /**
     * Validate the input, show the values to stamp and, once confirmed, stamp them.
     */
    public function execute()
    {
        $this->validate();
        $this->printPreview();
        $this->confirm();
        $this->runCommand();
        $this->printSummary();
    }

    /**
     * Check all input before anything is changed, and stop at the first error.
     */
    protected function validate(): void
    {
        $context = Application::getContextDAO()->getById($this->contextId);
        if (!$context) {
            $this->exitWithError("Unknown context ID {$this->contextId}.");
        }
        $this->context = $context;

        if (!in_array($this->command, $this->getCommands())) {
            $this->exitWithError("Unknown command '{$this->command}'. Expected one of: " . implode(', ', $this->getCommands()) . '.');
        }

        match ($this->command) {
            'all' => empty($this->parameters) || $this->exitWithError("'all' takes no parameters."),
            'year' => $this->years = $this->parseYears($this->parameters),
            default => $this->validateIds(),
        };

        $this->prepareDefaultIdentity();
        $this->parseOptions();
    }

    /**
     * Check the IDs given to an ID command.
     */
    protected function validateIds(): void
    {
        if (empty($this->parameters)) {
            $this->exitWithError("No IDs given to '{$this->command}'.");
        }
        foreach ($this->parameters as $id) {
            if (!ctype_digit($id)) {
                $this->exitWithError("Invalid ID '{$id}'.");
            }
            if ($error = $this->getIdError((int) $id)) {
                $this->exitWithError($error);
            }
        }
    }

    /**
     * Check one ID given to an ID command.
     *
     * @return ?string An error message, or null if the ID can be stamped
     */
    protected function getIdError(int $id): ?string
    {
        if ($this->command === 'publication_id') {
            $publication = Repo::publication()->get($id);
            if (!$publication || !Repo::submission()->get($publication->getData('submissionId'), $this->contextId)) {
                return "Unknown publication {$id}, or it does not belong to context {$this->contextId}.";
            }
            if ($publication->getData('status') !== Publication::STATUS_PUBLISHED) {
                return "Publication {$id} is not {$this->getPublishedWord()}.";
            }
            return null;
        }
        if (!Repo::submission()->get($id, $this->contextId)) {
            return "Unknown submission {$id}, or it does not belong to context {$this->contextId}.";
        }
        if ($this->getPublishedPublications([$id])->isEmpty()) {
            return "Submission {$id} has no {$this->getPublishedWord()} publication.";
        }
        return null;
    }

    /**
     * Parse years and year ranges (e.g. '2010', '2015-2020') into a list of years.
     */
    protected function parseYears(array $params): array
    {
        if (empty($params)) {
            $this->exitWithError("No years given to 'year'.");
        }
        $years = [];
        foreach ($params as $param) {
            if (!preg_match('/^(\d{4})(?:-(\d{4}))?$/', $param, $matches)) {
                $this->exitWithError("Invalid year or year range '{$param}'. Use YYYY or YYYY-YYYY.");
            }
            $start = (int) $matches[1];
            $end = (int) ($matches[2] ?? $start);
            if ($start > $end) {
                $this->exitWithError("Invalid year range '{$param}': the start is after the end.");
            }
            foreach (range($start, $end) as $year) {
                $years[$year] = true;
            }
        }
        return array_keys($years);
    }

    /**
     * Parse the options into overrides, rejecting unknown options and invalid locales.
     */
    protected function parseOptions(): void
    {
        $localizedOptions = $this->getLocalizedOptions();
        $scalarOptions = $this->getScalarOptions();

        foreach ($this->options as $name => $value) {
            if (isset($scalarOptions[$name])) {
                $this->scalarOverrides[$scalarOptions[$name]['field']] = $value;
                continue;
            }
            [$prefix, $locale] = array_pad(explode('-', $name, 2), 2, null);
            if (!isset($localizedOptions[$prefix]) || $locale === null) {
                $this->exitWithError("Unknown option --{$name}. Run the tool without parameters to see the options.");
            }
            // Any valid locale is accepted, not only the current ones: the historical identity may
            // be in a language the context no longer supports
            if (!Locale::isLocaleValid($locale)) {
                $this->exitWithError("Invalid locale '{$locale}' in --{$name}.");
            }
            $this->localizedOverrides[$localizedOptions[$prefix]['field']][$locale] = $value;
        }

        if (array_key_exists('contextPrimaryLocale', $this->scalarOverrides) && !Locale::isLocaleValid($this->scalarOverrides['contextPrimaryLocale'])) {
            $this->exitWithError("Invalid locale '{$this->scalarOverrides['contextPrimaryLocale']}' in --primary-locale.");
        }

        // Without a name nothing counts as stamped, and all fields would fall back to the current values
        $result = clone $this->defaultIdentity;
        $this->applyOverrides($result);
        if (!array_filter((array) $result->getData('contextName'))) {
            $this->exitWithError('The name would be empty in all locales. A stamped identity needs a name: give it with --name-<locale>=<value>.');
        }
    }

    /**
     * Set the default identity values: the context's current identity, as stamped onto a new,
     * unsaved publication.
     */
    protected function prepareDefaultIdentity(): void
    {
        $publication = Repo::publication()->newDataObject();
        $publication->stampContextIdentity($this->context);
        $this->defaultIdentity = $publication;
    }

    /**
     * Show the values to stamp, with any warnings.
     */
    protected function printPreview(): void
    {
        $noun = $this->getContextNoun();
        echo ucfirst($noun) . " identity to stamp (given = from the options, default = from the {$noun}'s current settings):\n";

        $result = clone $this->defaultIdentity;
        $this->applyOverrides($result);

        foreach ($this->getLocalizedOptions() as $option) {
            $field = $option['field'];
            $locales = array_unique(array_merge(
                array_keys(array_filter((array) $this->defaultIdentity->getData($field))),
                array_keys($this->localizedOverrides[$field] ?? [])
            ));
            sort($locales);
            if (empty($locales)) {
                $this->printPreviewRow($option['label'], null, false);
            }
            foreach ($locales as $locale) {
                $given = array_key_exists($locale, $this->localizedOverrides[$field] ?? []);
                $this->printPreviewRow("{$option['label']} ({$locale})", $result->getData($field, $locale), $given);
            }
        }
        foreach ($this->getScalarOptions() as $option) {
            $given = array_key_exists($option['field'], $this->scalarOverrides);
            $this->printPreviewRow($option['label'], $result->getData($option['field']), $given);
        }

        foreach ($this->getPreviewWarnings($result) as $warning) {
            echo "Warning: {$warning}\n";
        }
        echo "\nCommand: {$this->command}" . ($this->parameters ? ' ' . implode(' ', $this->parameters) : '') . "\n"
            . "Make sure you have a database backup before continuing.\n";
    }

    /**
     * Print one line of the preview.
     */
    protected function printPreviewRow(string $label, ?string $value, bool $given): void
    {
        $marker = $given ? ($value === null || $value === '' ? 'given, cleared' : 'given') : 'default';
        printf("  %-28s%-50s[%s]\n", $label . ':', $value === null || $value === '' ? '(none)' : $value, $marker);
    }

    /**
     * Get the warnings shown in the preview.
     *
     * @param DataObject $result The default identity with the overrides applied
     */
    protected function getPreviewWarnings(DataObject $result): array
    {
        $warnings = [];
        foreach ($this->getLocalizedOptions() as $prefix => $option) {
            $field = $option['field'];
            if (empty($this->localizedOverrides[$field])) {
                continue;
            }
            $notGiven = array_diff(
                array_keys(array_filter((array) $this->defaultIdentity->getData($field))),
                array_keys($this->localizedOverrides[$field])
            );
            if ($notGiven) {
                $warnings[] = "{$option['label']} is not given for " . implode(', ', $notGiven) . ', which keeps the default value. '
                    . "Give it with --{$prefix}-<locale>=, or clear it with an empty value.";
            }
        }
        $primaryLocale = $result->getData('contextPrimaryLocale');
        if ($primaryLocale && empty($result->getData('contextName', $primaryLocale))) {
            $warnings[] = "There is no name in the primary locale {$primaryLocale}.";
        }
        return $warnings;
    }

    /**
     * Ask for confirmation. Only an interactive run can be confirmed.
     */
    protected function confirm(): void
    {
        if (!stream_isatty(STDIN)) {
            $this->exitWithError('This tool must be run interactively: it asks for confirmation before changing published metadata.');
        }
        echo 'Continue? [y/N] ';
        if (strtolower(trim((string) fgets(STDIN))) !== 'y') {
            echo "Aborted. Nothing was changed.\n";
            exit(0);
        }
    }

    /**
     * Run the given command.
     */
    protected function runCommand(): void
    {
        match ($this->command) {
            'all' => $this->stampAll(),
            'year' => $this->stampByYears($this->years),
            'publication_id' => $this->stampPublications(array_map('intval', $this->parameters)),
            'submission_id' => $this->stampSubmissions(array_map('intval', $this->parameters)),
        };
    }

    /**
     * Stamp the given publications.
     */
    protected function stampPublications(array $publicationIds): void
    {
        foreach ($publicationIds as $publicationId) {
            $this->stampPublication(Repo::publication()->get($publicationId));
        }
    }

    /**
     * Stamp the published publications of the given submissions.
     */
    protected function stampSubmissions(array $submissionIds): void
    {
        foreach ($this->getPublishedPublications($submissionIds) as $publication) {
            $this->stampPublication($publication);
        }
    }

    /**
     * Stamp all published publications.
     */
    protected function stampAll(): void
    {
        foreach ($this->getPublishedPublications() as $publication) {
            if ($this->includePublication($publication)) {
                $this->stampPublication($publication);
            }
        }
    }

    /**
     * Stamp the publications published in the given years.
     */
    protected function stampByYears(array $years): void
    {
        foreach ($this->getPublishedPublications() as $publication) {
            $datePublished = $publication->getData('datePublished');
            if ($datePublished && in_array((int) date('Y', strtotime($datePublished)), $years) && $this->includePublication($publication)) {
                $this->stampPublication($publication);
            }
        }
    }

    /**
     * Whether 'year' and 'all' stamp a publication directly, e.g. not when it is stamped with its issue.
     */
    protected function includePublication(Publication $publication): bool
    {
        return true;
    }

    /**
     * Get the published publications of the context, optionally of the given submissions only.
     */
    protected function getPublishedPublications(?array $submissionIds = null): LazyCollection
    {
        $collector = Repo::publication()->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Publication::STATUS_PUBLISHED]);
        if ($submissionIds !== null) {
            $collector->filterBySubmissionIds($submissionIds);
        }
        return $collector->getMany();
    }

    /**
     * Stamp a publication. If its identity changed, mark its DOIs stale and let export plugins
     * mark their deposit status stale.
     *
     * @hook Publication::identityRestamped [[$publication, $context]]
     */
    protected function stampPublication(Publication $publication): void
    {
        $this->stampObject(
            $publication,
            'publication',
            "publication {$publication->getId()} (submission {$publication->getData('submissionId')})",
            fn () => $publication->stampContextIdentity($this->context),
            function () use ($publication) {
                Repo::publication()->edit($publication, []);
                Repo::doi()->markStale(Repo::doi()->getDoisForPublication($publication));
                Hook::call('Publication::identityRestamped', [$publication, $this->context]);
            }
        );
    }

    /**
     * Stamp an object, apply the overrides and save it if its identity changed. If anything
     * fails, report the object and stop: a re-run skips the objects already stamped.
     *
     * @param string $type Object type, for the summary
     * @param string $label Object description, for error messages
     * @param callable $stamp Stamps the context identity
     * @param callable $save Saves the object and handles the consequences of the change
     */
    protected function stampObject(DataObject $object, string $type, string $label, callable $stamp, callable $save): void
    {
        $this->counts[$type] ??= ['stamped' => 0, 'unchanged' => 0];
        try {
            $before = $this->getIdentity($object);
            $stamp();
            $this->applyOverrides($object);
            if ($this->getIdentity($object) === $before) {
                $this->counts[$type]['unchanged']++;
                return;
            }
            $this->markRemovedLocales($object, $before);
            $save();
            $this->counts[$type]['stamped']++;
        } catch (Throwable $e) {
            printf("Error while stamping %s: %s\n", $label, $e->getMessage());
            $this->printSummary();
            echo "The objects stamped so far are saved. Fix the cause and run the same command again.\n";
            exit(1);
        }
    }

    /**
     * Apply the overrides to an object.
     */
    protected function applyOverrides(DataObject $object): void
    {
        foreach ($this->localizedOverrides as $field => $values) {
            foreach ($values as $locale => $value) {
                $object->setData($field, $value === '' ? null : $value, $locale);
            }
        }
        foreach ($this->scalarOverrides as $field => $value) {
            $object->setData($field, $value === '' ? null : $value);
        }
    }

    /**
     * Set the locales an object's localized identity fields no longer have to null, so that saving
     * deletes their rows: a locale missing from the data is left in the database.
     *
     * @param array $before The object's identity before stamping, see getIdentity()
     */
    protected function markRemovedLocales(DataObject $object, array $before): void
    {
        foreach (array_column($this->getLocalizedOptions(), 'field') as $field) {
            $values = (array) $object->getData($field);
            $removed = array_diff_key((array) $before[$field], array_filter($values));
            if ($removed) {
                $object->setData($field, array_fill_keys(array_keys($removed), null) + $values);
            }
        }
    }

    /**
     * Get the identity fields of an object, normalized for comparison.
     */
    protected function getIdentity(DataObject $object): array
    {
        $identity = [];
        $fields = array_merge(
            array_column($this->getLocalizedOptions(), 'field'),
            array_column($this->getScalarOptions(), 'field')
        );
        foreach ($fields as $field) {
            $value = $object->getData($field);
            if (is_array($value)) {
                $value = array_filter($value, fn ($v) => $v !== null && $v !== '');
                ksort($value);
            }
            $identity[$field] = $value === '' || $value === [] ? null : $value;
        }
        return $identity;
    }

    /**
     * Print how many objects were stamped and left unchanged.
     */
    protected function printSummary(): void
    {
        foreach ($this->counts as $type => $count) {
            printf("%s: %d stamped, %d unchanged.\n", ucfirst($type) . 's', $count['stamped'], $count['unchanged']);
        }
    }

    /**
     * Print an error and stop.
     */
    protected function exitWithError(string $message): never
    {
        echo "Error: {$message}\n";
        exit(1);
    }
}

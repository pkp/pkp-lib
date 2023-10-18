<?php

namespace PKP\cliTool;

use PKP\cliTool\CommandLineTool;

abstract class ResolveAgencyDuplicatesTool extends CommandLineTool
{
    protected string|null $command = null;
    protected string|null $agency_name = null;
    protected bool $forceFlag = false;
    /**
     * List of potential agencies to choose from along with related fields for resolution.
     *
     * Array shape should look like:
     * `['agency_name' => [
     *      'status' => 'agency_name::status',
     *      'additionalFields': [...],
     *      ]
     * ]`
     */
    protected array $agencies = [];

    public function __construct($argv = [])
    {
        parent::__construct($argv);

        $forceFlagIndex = array_search('--force', $this->argv);
        if ($forceFlagIndex) {
            $this->forceFlag = true;
            array_splice($this->argv, $forceFlagIndex, 1);
        }

        if (sizeof($this->argv) == 0) {
            $this->exitWithUsageMessage();
        }
        $this->command = array_shift($this->argv);
        $this->agency_name = array_shift($this->argv);

        if (
            $this->command === 'resolve' &&
            ($this->agency_name === null || !in_array($this->agency_name, array_keys($this->agencies)))
        ) {
            $this->exitWithUsageMessage();
        }
    }

    public function usage()
    {
        $agencies = implode(', ', array_keys($this->agencies));
        echo "Script to resolve DOI registration agency duplication pre-3.4.\n"
            . "NB: If a conflict exists for a submission, the corresponding publication objects (galleys, etc.) will also be cleaned up.\n\n"
            . "Usage:\n"
            . "{$this->scriptName} resolve [agency_name] --force : Remove conflicting DOI registration info, keeping agency_name.\n"
            . "{$this->scriptName} test : Returns list of conflicting items\n\n"
            . "Options:\n"
            . "agency_name      One of: {$agencies}.\n"
            . "--force          Force resolve operation. Will not delete data without it.\n";
    }

    public function execute(): void
    {
        switch ($this->command) {
            case 'resolve':
                $this->resolve();
                break;
            case 'test':
                $this->test();
                break;
            default:
                $this->exitWithUsageMessage();
                break;
        }
    }

    protected function print(string $message): void
    {
        echo $message . PHP_EOL;
    }

    private function resolve(): void
    {
        if (!$this->forceFlag) {
            $this->print("Warning! This is a destructive operation. Ensure you have a database backup and rerun this command with the `--force` flag.");
            exit(0);
        }

        $agencies = array_filter($this->agencies, fn ($key) => $key !== $this->agency_name, ARRAY_FILTER_USE_KEY);
        $this->print('Removing duplicate registration info for ' . implode(', ', array_keys($agencies)) . '...');

        $agencyFields = array_reduce($agencies, fn($carry, $item) => array_merge($carry, [$item['status']], $item['additionalFields']), []);
        $this->handleResolution($agencyFields);
    }

    /**
     * Print out IDs for publication objects to be deleted
     */
    abstract protected function test(): void;

    /**
     * Handle the actual removal of duplicates from all publication objects
     *
     * @param array $agencyFields All setting_name entries to check for when removing table entries
     * @return void
     */
    abstract protected function handleResolution(array $agencyFields): void;
}

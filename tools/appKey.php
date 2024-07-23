<?php

/**
 * @file tools/appKey.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommandAppKey
 *
 * @brief CLI tool to generate/validate app key
 */

namespace PKP\tools;

use Throwable;
use PKP\core\PKPAppKey;
use PKP\cliTool\CommandLineTool;
use PKP\cliTool\traits\HasCommandInterface;
use PKP\cliTool\traits\HasParameterList;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\InvalidArgumentException as CommandInvalidArgumentException;

define('APP_ROOT', dirname(__FILE__, 4));
require_once APP_ROOT . '/tools/bootstrap.php';

class CommandAppKey extends CommandLineTool
{
    use HasParameterList;
    use HasCommandInterface;

    protected const AVAILABLE_OPTIONS = [
        'validate'  => 'admin.cli.tool.appKey.options.validate.description',
        'generate'  => 'admin.cli.tool.appKey.options.generate.description',
        'configure' => 'admin.cli.tool.appKey.options.configure.description',
        'usage'     => 'admin.cli.tool.appKey.options.usage.description',
    ];

    /**
     * Which option will be call?
     */
    protected ?string $option;

    /**
     * Constructor
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        array_shift($argv); // Shift the tool name off the top

        $this->setParameterList($argv);

        if (!isset($this->getParameterList()[0])) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.empty.option'),
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->option = $this->getParameterList()[0];

        $this->setCommandInterface();
    }

    /**
     * Parse and execute the command
     */
    public function execute()
    {
        if (!isset(self::AVAILABLE_OPTIONS[$this->option])) {
            throw new CommandNotFoundException(
                __('admin.cli.tool.jobs.option.doesnt.exists', ['option' => $this->option]),
                array_keys(self::AVAILABLE_OPTIONS)
            );
        }

        $this->{$this->option}();
    }

    /**
     * Print command usage information.
     */
    public function usage(): void
    {
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.usage.title') . '</comment>');
        $this->getCommandInterface()->line(__('admin.cli.tool.usage.parameters') . PHP_EOL);
        $this->getCommandInterface()->line('<comment>' . __('admin.cli.tool.available.commands', ['namespace' => 'appKey']) . '</comment>');

        $this->printCommandList(self::AVAILABLE_OPTIONS);
    }

    /**
     * Write the `app_key` variable in the `config.inc.php` file if missing
     */
    public function configure(): void
    {
        $output = $this->getCommandInterface()->getOutput();

        if (PKPAppKey::hasKeyVariable()) {
            $output->error(__('admin.cli.tool.appKey.error.alreadyKeyVariableExists'));
            return;
        }

        try {
            PKPAppKey::writeAppKeyVariableToConfig()
                ? $output->success(__('admin.cli.tool.appKeyVariable.success.writtenToConfig'))
                : $output->error(__('admin.cli.tool.appKeyVariable.error.writtenToConfig'));
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
        }
    }

    /**
     * Generate the app key and write in the config file
     */
    public function generate(): void
    {
        $output = $this->getCommandInterface()->getOutput();

        try {
            // configure `app_key` variable if not already configured
            if (!PKPAppKey::hasKeyVariable()) {
                $this->configure();
            }

            $appKey = PKPAppKey::generate();
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return;
        }

        if ($this->hasFlagSet('--show')) {
            $output->info(__('admin.cli.tool.appKey.show', ['appKey' => $appKey]));
            return;
        }

        if (!PKPAppKey::hasKeyVariable()) {
            $output->error(__('admin.cli.tool.appKey.error.missingKeyVariable'));
            return;
        }

        if ((PKPAppKey::hasKey() && PKPAppKey::validate(PKPAppKey::getKey())) && !$this->hasFlagSet('--force')) {
            $output->warning(__('admin.cli.tool.appKey.warning.replaceValidKey'));
            return;
        }

        try {
            PKPAppKey::writeAppKeyToConfig($appKey);
            $output->success(__('admin.cli.tool.appKey.success.writtenToConfig'));
        } catch (Throwable $exception) {
            $this->getCommandInterface()->getOutput()->error($exception->getMessage());    
        } finally {
            return;
        }
    }

    /**
     * Validate the app key from config file
     */
    public function validate(): void
    {
        $output = $this->getCommandInterface()->getOutput();

        if (!PKPAppKey::hasKeyVariable()) {
            $output->error(__('admin.cli.tool.appKey.error.missingKeyVariable'));
            return;
        }

        if (!PKPAppKey::hasKey()) {
            $output->error(__('admin.cli.tool.appKey.error.missingAppKey'));
            return;
        }

        if (!PKPAppKey::validate(PKPAppKey::getKey())) {
            $output->error(__('admin.cli.tool.appKey.error.InvalidAppKey', [
                'ciphers' => implode(', ', array_keys(PKPAppKey::SUPPORTED_CIPHERS))
            ]));
            return;
        }

        $output->success(__('admin.cli.tool.appKey.success.valid'));
    }
}

try {
    $tool = new CommandAppKey($argv ?? []);
    $tool->execute();
} catch (\Throwable $exception) {
    $output = new \PKP\cliTool\CommandInterface;

    if ($exception instanceof CommandInvalidArgumentException) {
        $output->errorBlock([$exception->getMessage()]);

        return;
    }

    if ($exception instanceof CommandNotFoundException) {
        $alternatives = $exception->getAlternatives();

        $message = __('admin.cli.tool.appKey.mean.those') . PHP_EOL . implode(PHP_EOL, $alternatives);

        $output->errorBlock([$exception->getMessage(), $message]);

        return;
    }

    throw $exception;
}

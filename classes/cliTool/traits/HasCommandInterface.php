<?php

/**
 * @file classes/cliTool/traits/HasCommandInterface.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait HasCommandInterface
 *
 * @brief A helper trait for CLI tools that provide functionality to read/write on CLI interface
 */

namespace PKP\cliTool\traits;

use PKP\cliTool\CommandInterface;
use Symfony\Component\Console\Helper\Helper;

trait HasCommandInterface
{
    /**
     * CLI interface, this object should extends InteractsWithIO
     */
    protected ?CommandInterface $commandInterface = null;

    /**
     * Set the command interface
     */
    public function setCommandInterface(?CommandInterface $commandInterface = null): self
    {
        $this->commandInterface = $commandInterface ?? new CommandInterface;

        return $this;
    }

    /**
     * Get the command interface
     */
    public function getCommandInterface(): CommandInterface
    {
        return $this->commandInterface;
    }

    /**
     * Print given options in a pretty way.
     */
    protected function printCommandList(array $options, bool $shouldTranslate = true): void
    {
        $width = (int)collect(array_keys($options))
            ->map(fn($command) => Helper::width($command))
            ->sort()
            ->last() + 2;

        foreach ($options as $commandName => $description) {
            $spacingWidth = $width - Helper::width($commandName);
            $this->getCommandInterface()->line(
                sprintf(
                    '  <info>%s</info>%s%s',
                    $commandName,
                    str_repeat(' ', $spacingWidth),
                    $shouldTranslate ? __($description) : $description
                )
            );
        }
    }
}

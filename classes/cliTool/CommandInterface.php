<?php

/**
 * @file classes/cliTool/CommandInterface.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait CommandInterface
 *
 * @brief Class to provide IO interface on CLI
 */

namespace PKP\cliTool;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

class CommandInterface
{
    use InteractsWithIO;

    public function __construct()
    {
        $output = new OutputStyle(
            new StringInput(''),
            new StreamOutput(fopen('php://stdout', 'w'))
        );

        $this->setOutput($output);
    }

    public function errorBlock(array $messages = [], ?string $title = null): void
    {
        $this->getOutput()->block(
            $messages,
            $title,
            'fg=white;bg=red',
            ' ',
            true
        );
    }
}

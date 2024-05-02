<?php

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

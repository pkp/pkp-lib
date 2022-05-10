<?php

declare(strict_types=1);

/**
 * @file classes/core/PKPScheduler.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPScheduler
 * @ingroup core
 *
 * @brief Bootstraps the scheduler tasks
 */

namespace PKP\core;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class PKPScheduler
{
    public function __construct(
        public Schedule $scheduleBag
    ) {
    }

    public function run(): void
    {
        $scheduler = new ScheduleRunCommand();
        $scheduler->setLaravel(PKPContainer::getInstance());

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $scheduler->setInput($input);
        $scheduler->setOutput(new OutputStyle($input, $output));

        $scheduler->handle(
            $this->scheduleBag,
            app(Dispatcher::class),
            app(ExceptionHandler::class)
        );

        echo $output->fetch();
    }
}

<?php

namespace PKP\plugins\interfaces;

use APP\scheduler\Scheduler;

interface HasTaskScheduler
{
    public function registerSchedules(?Scheduler $scheduler = null): void;
}
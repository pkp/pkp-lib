<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Providers/JobServiceProvider.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobServiceProvider
 * @ingroup support
 *
 * @brief Service Provider for Jobs Domain
 */

namespace PKP\Domains\Jobs\Providers;

use Illuminate\Support\ServiceProvider;

use PKP\Domains\Jobs\Interfaces\JobRepositoryInterface;
use PKP\Domains\Jobs\Repositories\Job as Repository;

class JobServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            JobRepositoryInterface::class,
            Repository::class
        );
    }
}

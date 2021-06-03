<?php

/**
 * @file classes/facade/Repo.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repo
 *
 * @brief This facade provides access to all Repositories in the application.
 *
 * A Repo contains all the methods needed to interact with an entity, such
 * as CRUD operations as well as utility methods to check status, locate items
 * and perform bulk actions.
 *
 * A Repository is a wrapper around an entity's DAO where additional business
 * logic can be performed. Use the Repository to coordinate actions across the
 * application, such as firing events, writing activity logs, or refreshing
 * cached data. The Repository should hand off data to the DAO to perform
 * basic crud operations.
 */

namespace PKP\facades;

use Illuminate\Support\Facades\App;

class Repo
{
    public static function announcement(): \PKP\announcement\Repository
    {
        return App::make(\PKP\announcement\Repository::class);
    }
}

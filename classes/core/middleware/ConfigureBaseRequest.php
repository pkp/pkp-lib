<?php

declare(strict_types=1);

/**
 * @file classes/core/middleware/ConfigureBaseRequest.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConfigureBaseRequest
 * @ingroup core_middleware
 *
 * @brief Middleware to add some extra values into the Request object
 */

namespace PKP\core\middleware;

use Closure;
use Illuminate\Http\Request;
use PKP\config\Config;

class ConfigureBaseRequest
{
    /**
     * Add few configurations on base Request object
     */
    public function handle(Request $request, Closure $next)
    {
        $isPathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;

        $request->attributes->add(['isPathInfoEnabled' => $isPathInfoEnabled]);

        return $next($request);
    }
}

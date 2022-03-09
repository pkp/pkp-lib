<?php

declare(strict_types=1);

namespace PKP\core\Controllers\Middlewares;

use Closure;
use Illuminate\Http\Request;
use PKP\config\Config;

class ConfigureBaseRequest
{
    /**
     * Add few configurations on BaseRequest
     */
    public function handle(Request $request, Closure $next)
    {
        $isPathInfoEnabled = Config::getVar('general', 'disable_path_info') ? false : true;

        $request->attributes->add(['isPathInfoEnabled' => $isPathInfoEnabled]);

        return $next($request);
    }
}

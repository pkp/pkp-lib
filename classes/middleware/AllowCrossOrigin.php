<?php

declare(strict_types=1);

namespace PKP\middleware;

use Closure;
use Illuminate\Http\Request;

class AllowCrossOrigin
{
    /**
     * 
     * 
     * @param \Illuminate\Http\Request  $request
     * @param Closure                   $next
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Access-Control-Allow-Origin', '*');
        $request->headers->set('Access-Control-Allow-Methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        $request->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $next($request);
    }
}
<?php

declare(strict_types=1);

namespace PKP\middlewares;

use Closure;
use Illuminate\Http\Request;

class RemoveTrailingSlash
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
        $uriPath = $request->path();

        if ($uriPath === '/' || substr($uriPath, -1) !== '/') {
            return $next($request);
        }

        $uriPath = substr($uriPath, 0, -1);

        $newRequest = Request::create(
            $uriPath, 
            $request->method(), 
            $request->query->all(), 
            $request->cookies->all(), 
            $request->allFiles(), 
            $request->server->all(),
            $request->getContent()
        );

        return $next($newRequest);
    }
}
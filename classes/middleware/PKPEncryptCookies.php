<?php

/**
 * @file classes/middleware/PKPEncryptCookies.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEncryptCookies
 *
 * @brief Encrypt/Decrypt the cookie if cookie entryption is enabled
 */

namespace PKP\middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class PKPEncryptCookies extends \Illuminate\Cookie\Middleware\EncryptCookies
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next)
    {
        $config = app()->get('config')->get('session');
        
        if (!$config['cookie_encryption']) {
            return $next($request);
        }

        return $this->encrypt($next($this->decrypt($request)));
    }
    
    /**
     * Encrypt the cookies on an outgoing response.
     *
     * Overridden to widen visibility from protected to public,
     * allowing PKPSessionGuard::updateSessionCookieToResponse() to call it directly.
     */
    public function encrypt(Response $response)
    {
        return parent::encrypt($response);
    }
}
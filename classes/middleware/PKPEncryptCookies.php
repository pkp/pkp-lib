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
use Illuminate\Cookie\CookieValuePrefix;
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
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function encrypt(Response $response)
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($this->isDisabled($cookie->getName())) {
                continue;
            }

            $response->headers->setCookie($this->duplicate(
                $cookie,
                $this->encrypter->encrypt(
                    CookieValuePrefix::create(
                        $cookie->getName(),
                        $this->encrypter->getKey()
                    ) . $cookie->getValue(),
                    static::serialized($cookie->getName())
                )
            ));
        }

        return $response;
    }
}
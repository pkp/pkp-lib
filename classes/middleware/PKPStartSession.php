<?php

/**
 * @file classes/middleware/PKPStartSession.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStartSession
 *
 * @ingroup middleware
 *
 * @brief 
 */

namespace PKP\middleware;

use APP\template\TemplateManager;
use Illuminate\Contracts\Session\Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class PKPStartSession extends \Illuminate\Session\Middleware\StartSession
{
    /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response   $response
     * @param  \Illuminate\Contracts\Session\Session        $session
     * 
     * @return void
     */
    protected function addCookieToResponse(Response $response, Session $session)
    {
        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            $cookie = new Cookie(
                $session->getName(), 
                $session->getId(), 
                $this->getCookieExpirationDate(),
                $config['path'], 
                $config['domain'], 
                $config['secure'] ?? false,
                $config['http_only'] ?? true, 
                false, 
                $config['same_site'] ?? null
            );

            $response->headers->setCookie($cookie);
            
            $templateManager = TemplateManager::getManager();
            $templateManager->addCookie($session->getName(), $cookie->__toString(), false, $response->getStatusCode());
        }
    }

    /**
     * Update the session cookie to the application response.
     * 
     * @param  \Illuminate\Contracts\Session\Session|null   $session
     * @param  \Symfony\Component\HttpFoundation\Response   $response
     * 
     * @return void
     */
    public function updateCookieToResponse(Session $session, Response $response = null): void
    {
        $response ??= app()->get(\Illuminate\Http\Response::class); /** @var \Illuminate\Http\Response $response */
        $request = app()->get('request'); /** @var \Illuminate\Http\Request $request */

        $session->save();
        $request->setLaravelSession(
            $this->startSession($request, $session)
        );

        $templateManager = TemplateManager::getManager();
        $templateManager->clearCookie($session->getName());
        $response->headers->removeCookie($session->getName());
        // $request->cookies->set($session->getName(), $session->getId());
        // $request->server->set('HTTP_COOKIE', $session->getName().'='.$session->getId());
        // $request->headers->set('cookie', [0 => $session->getName().'='.$session->getId()]);

        $this->addCookieToResponse($response, $session);
    }
}

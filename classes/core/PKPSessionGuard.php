<?php

/**
 * @file classes/core/PKPSessionGuard.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSessionGuard
 *
 * @brief Core session guard to handle session management actions
 */

namespace PKP\core;

use Carbon\Carbon;
use PKP\user\User;
use APP\facades\Repo;
use DateTimeInterface;
use PKP\core\Registry;
use APP\core\Application;
use PKP\security\Validation;
use InvalidArgumentException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Contracts\Session\Session;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class PKPSessionGuard extends SessionGuard
{
    /**
     * @copydoc \Illuminate\Auth\SessionGuard::$user
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|\PKP\user\User|null
     */
    protected $user;

    /**
     * @copydoc \Illuminate\Auth\SessionGuard::$provider
     *
     * @var \Illuminate\Contracts\Auth\UserProvider|\PKP\core\PKPUserProvider
     */
    protected $provider;

    /**
     * Retrieves whether the session is disabled
     */
    public static function isSessionDisable(): bool
    {
        return defined('SESSION_DISABLE_INIT');
    }

    /**
     * Disable the session
     */
    public static function disableSession(): void
    {
        if (!defined('SESSION_DISABLE_INIT')) {
            define('SESSION_DISABLE_INIT', true);
        }
    }

    /**
     * update the current user without firing any events or changes
     */
    public function updateUser(AuthenticatableContract|User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @copydoc \Illuminate\Auth\SessionGuard::setUser(AuthenticatableContract $user)
     */
    public function setUser(AuthenticatableContract|User $user)
    {
        Registry::set('user', $user);

        return parent::setUser($user);
    }

    /**
     * Set the user id in session
     */
    public function setUserId(int $userId): static
    {
        $this->session->put('userId', $userId);

        return $this;
    }

    /**
     * Retrieve the user id from the current session
     */
    public function getUserId(): ?int
    {
        return $this->session->get('userId');
    }

    /**
     * Sign In as different user
     */
    public function signInAs(AuthenticatableContract|User $user): void
    {
        $auth = app()->get('auth'); /** @var \PKP\core\PKPAuthManager $auth */

        $this->session->put([
            'signedInAs' => $this->getUserId(),
            'password_hash_'.$auth->getDefaultDriver() => $user->getPassword(),
        ]);

        $this
            ->setUserDataToSession($user)
            ->updateUser($user)
            ->updateSession($user->getId());
    }

    /**
     * Sign Out from previously sign in as different user
     */
    public function signOutAs(AuthenticatableContract|User $user): void
    {
        $auth = app()->get('auth'); /** @var \PKP\core\PKPAuthManager $auth */

        $this->session->forget('signedInAs');

        $this->session->put('password_hash_'.$auth->getDefaultDriver(), $user->getPassword());

        $this
            ->setUserDataToSession($user)
            ->updateUser($user)
            ->updateSession($user->getId());
    }

    /**
     * Set the user data to session
     */
    public function setUserDataToSession(AuthenticatableContract|User $user): self
    {
        $this->setUserId($user->getId());
        $this->session->put('username', $user->getUsername());
        $this->session->put('email',    $user->getEmail());

        return $this;
    }

    /**
     * @copydoc \Illuminate\Auth\SessionGuard::updateSession($id)
     */
    public function updateSession($id)
    {
        parent::updateSession($id);

        $this->updateLaravelSession($this->getSession());

        $this->updateSessionCookieToResponse($this->getSession());
    }

    /**
     * Update the session instance to laravel request singleton object
     */
    public function updateLaravelSession(Session $session): void
    {
        $request = app()->get('request'); /** @var \Illuminate\Http\Request $request */
        $request->setLaravelSession($session);
    }

    /**
     * Get the session store used by the guard.
     */
    public function setSession(Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Update session cookie based in the response
     */
    public function updateSessionCookieToResponse(?Session $session = null): void
    {
        $session ??= $this->getSession();
        $headerCookies = [];

        $config = app()->get("config")["session"];

        /** @var \Illuminate\Http\Response $response */
        $response = app()->get(\Illuminate\Http\Response::class);

        // clear out the previous session and remember me cookies
        foreach ([$session->getName(), $this->getRecallerName()] as $cookieName) {
            $response->headers->removeCookie($cookieName, $config['path'], $config['domain']);
            $response->headers->clearCookie($cookieName, $config['path'], $config['domain']);
        }

        $cookie = new Cookie(
            name: $session->getName(),
            value: $session->getId(),
            expire: $this->getCookieExpirationDate($config),
            path: $config['path'],
            domain: $config['domain'],
            secure: $config['secure'] ?? false,
            httpOnly: $config['http_only'] ?? true,
            raw: false,
            sameSite: $config['same_site'] ?? null
        );

        $headerCookies[] = $session->getName().'='.$session->getId();
        $response->headers->setCookie($cookie);

        // Set remember me cookie
        $cookieJar = $this->getCookieJar(); /** @var \Illuminate\Cookie\CookieJar $cookieJar */
        if ( ($rememberCookie = $cookieJar->queued($this->getRecallerName())) ) {
            $response->headers->setCookie($rememberCookie);
            $headerCookies[] = $rememberCookie->getName() . '=' . $rememberCookie->getValue();
        }

        // update response header cookie values in formar [name=value]
        $response->headers->set('cookie', $headerCookies);

        if ($config['cookie_encryption']) {
            $pkpEncryptCookies = app()->make(\PKP\middleware\PKPEncryptCookies::class); /** @var \PKP\middleware\PKPEncryptCookies $pkpEncryptCookies */
            $pkpEncryptCookies->encrypt($response);
        }
    }

    /**
     * Send the cookie headers
     */
    public function sendCookies(): void
    {
        if (headers_sent()) {
            return;
        }

        $response = app()->get(\Illuminate\Http\Response::class); /** @var \Illuminate\Http\Response $response */

        foreach ($response->headers->getCookies() as $cookie) {
            header('Set-Cookie: '.$cookie, false, $response->getStatusCode() ?? 0);
        }
    }

    /**
     * Invalidate/Remove/Delete other user session by user auth identifier name (e.g. user_id)
     *
     * @param int       $userId                 The user id for which session data need to be removed
     * @param string    $excludableSessionId    The session id which should be kept
     */
    public function invalidateOtherSessions(int $userId, ?string $excludableSessionId = null): void
    {
        DB::table('sessions')
            ->where($this->provider->createUserInstance()->getAuthIdentifierName(), $userId)
            ->when($excludableSessionId, fn ($query) => $query->where('id', '<>', $excludableSessionId))
            ->delete();
    }

    /**
     * Remove/Delete all sessions
     * Calling this method result in all users being logged out from the system
     */
    public function removeAllSession(): void
    {
        DB::table('sessions')->delete();
    }

    /**
     * @copydoc \Illuminate\Auth\SessionGuard::rehashUserPasswordForDeviceLogout
     */
    protected function rehashUserPasswordForDeviceLogout($password)
    {
        $rehash = null;

        if (! Validation::verifyPassword($this->user->getUsername(), $password, $this->user->getPassword(), $rehash)) {
            throw new InvalidArgumentException('The given password does not match the current password.');
        }

        return tap($this->user, function(&$user) use ($password, $rehash) {
            $rehash ??= Validation::encryptCredentials($user->getUsername(), $password);
            $user->setPassword($rehash);

            $auth = app()->get('auth'); /** @var \PKP\core\PKPAuthManager $auth */
            Application::get()->getRequest()->getSession()->put([
                'password_hash_' . $auth->getDefaultDriver() => $rehash,
            ]);

            Repo::user()->edit($user);
        });
    }

    /**
     * Get the cookie lifetime in seconds.
     */
    protected function getCookieExpirationDate(array $config): DateTimeInterface|int
    {
        return $config['expire_on_close'] ? 0 : Date::instance(
            Carbon::now()->addRealMinutes($config['lifetime'])
        );
    }
}

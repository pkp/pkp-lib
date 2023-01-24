<?php

/**
 * @file classes/session/SessionManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SessionManager
 * @ingroup session
 *
 * @brief Implements PHP methods for a custom session storage handler (see http://php.net/session).
 */

namespace PKP\session;

use APP\core\Application;
use Carbon\Carbon;
use PKP\config\Config;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use SessionHandlerInterface;

class SessionManager implements SessionHandlerInterface
{
    /** The DAO for accessing Session objects */
    private SessionDao $sessionDao;

    /** The Session associated with the current request */
    private ?Session $userSession = null;

    private PKPRequest $request;

    /**
     * Constructor.
     * Initialize session configuration and set PHP session handlers.
     * Attempts to rejoin a user's session if it exists, or create a new session otherwise.
     *
     */
    private function __construct(SessionDAO $sessionDao)
    {
        $this->sessionDao = $sessionDao;
        $this->request = Application::get()->getRequest();

        $this->configure();
        $this->start();

        // If there's a session assigned to the session ID
        if ($this->userSession) {
            // Validates it and refresh
            if ($this->isValid($this->userSession)) {
                $this->refresh();
                return;
            }
            // When invalid, regenerates the session ID without destroying the failed session (perhaps it belongs to another user)
            session_regenerate_id();
        }

        $this->createSession();
    }

    /**
     * Return an instance of the session manager.
     *
     */
    public static function getManager(): SessionManager
    {
        // Reference required
        $instance = & Registry::get('sessionManager', true, null);
        return $instance ??= new SessionManager(DAORegistry::getDAO('SessionDAO'));
    }

    /**
     * Invalidate given user's all sessions or except for the given session id
     *
     * @param int $userId The target user id for whom to invalidate sessions
     *
     */
    public function invalidateSessions(int $userId, string $excludableSessionId = null): bool
    {
        $this->getSessionDao()->deleteUserSessions($userId, $excludableSessionId);

        return true;
    }

    /**
     * Get the Session DAO instance associated with the current request
     *
     */
    public function getSessionDao(): SessionDao
    {
        return $this->sessionDao;
    }

    /**
     * Get the session associated with the current request.
     */
    public function getUserSession(): Session
    {
        return $this->userSession;
    }

    /**
     * Open a session.
     * Does nothing; only here to satisfy PHP session handler requirements.
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Close a session.
     * Does nothing; only here to satisfy PHP session handler requirements.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Read session data from database.
     */
    public function read(string $sessionId): string
    {
        $this->userSession ??= $this->sessionDao->getSession($sessionId);
        return $this->userSession?->getSessionData() ?? '';
    }

    /**
     * Save session data to database.
     */
    public function write(string $sessionId, string $data): bool
    {
        if ($this->userSession) {
            $this->userSession->setSessionData($data);
            $this->sessionDao->updateObject($this->userSession);
        }
        return true;
    }

    /**
     * Destroy (delete) a session.
     */
    public function destroy(string $sessionId): bool
    {
        $this->sessionDao->deleteById($sessionId);
        return true;
    }

    /**
     * Garbage collect unused session data.
     *
     * @todo Use $lifetime instead of assuming 24 hours?
     *
     * @param int $lifetime the number of seconds after which data will be seen as "garbage" and cleaned up
     */
    public function gc(int $lifetime): bool
    {
        $sessionLifetimeInDays = max(0, Config::getVar('general', 'session_lifetime'));
        $lastUsedRemember = $sessionLifetimeInDays ? Carbon::now()->subDays($sessionLifetimeInDays)->getTimestamp() : 0;
        $this->sessionDao->deleteByLastUsed(Carbon::now()->subDay()->getTimestamp(), $lastUsedRemember);
        return true;
    }

    /**
     * Regenerate the session ID for the current user session.
     * This is useful to guard against the "session fixation" form of hijacking
     * by changing the user's session ID after they have logged in (in case the
     * original session ID had been pre-populated).
     */
    public function regenerateSessionId(): bool
    {
        // Indirectly calls $this->destroy() with the old session ID
        if (!session_regenerate_id(true)) {
            return false;
        }

        $this->userSession->setId(session_id());
        $this->sessionDao->insertObject($this->userSession);

        return true;
    }

    /**
     * Change the lifetime of the current session cookie.
     *
     */
    public function updateSessionLifetime(int $expireTime = 0): bool
    {
        $options = session_get_cookie_params();
        unset($options['lifetime']);
        $options['expires'] = $expireTime;
        return setcookie(session_name(), session_id(), $options);
    }

    /**
     * Retrieves whether the session initialization is disabled
     */
    public static function isDisabled(): bool
    {
        return defined('SESSION_DISABLE_INIT');
    }

    /**
     * Prevents the session initialization
     */
    public static function disable(): void
    {
        // Constant kept for backwards compatibility
        if (!defined('SESSION_DISABLE_INIT')) {
            define('SESSION_DISABLE_INIT', true);
        }
    }

    /**
     * Retrieves whether the user has a session ID
    */
    public static function hasSession(): bool
    {
        // If the session isn't disabled and a cookie is present or a session was started in the current request
        return !SessionManager::isDisabled() && (isset($_COOKIE[Config::getVar('general', 'session_cookie_name')]) || !!session_id());
    }

    private function configure(): void
    {
        $domain = $this->request->getServerHost(includePort: false);

        // Configure PHP session parameters
        ini_set('session.name', Config::getVar('general', 'session_cookie_name'));
        ini_set('session.cookie_lifetime', 0);
        ini_set('session.cookie_path', Config::getVar('general', 'session_cookie_path', $this->request->getBasePath() . '/'));
        ini_set('session.cookie_domain', $domain);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', Config::getVar('general', 'same_site', 'Lax'));
        ini_set('session.cookie_secure', Config::getVar('security', 'force_ssl'));
        ini_set('session.use_trans_sid', 0);
        ini_set('session.serialize_handler', 'php');
        ini_set('session.use_cookies', 1);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_maxlifetime', 60 * 60);
        ini_set('session.cache_limiter', 'none');

        session_set_save_handler($this, true);
    }

    /**
     * Starts the session
     *
     * In case there are many session cookies, it attempts to find the best one and clear the remaining, considering the issues below:
     * Empty domains: Old applications (e.g. OJS 2.x) didn't store the domain, therefore users revising the application after a migration might be affected, we'll drop it.
     * Subdomains mixed with parent domains: Users visiting "journal.sfu.ca", then "www.journal.sfu.ca" might end up with N+1 session cookies, this is problematic, we'll try to drop the excess.
     * Domain cookies belonging to different paths or expired sessions: They will be kept (until the user clears his cookies) and probably trigger the extra checks.
     */
    private function start(): void
    {
        $sessionIds = collect($this->getSessionIds());
        // Standard flow with a single session ID
        if ($sessionIds->count() < 2) {
            session_start();
            return;
        }

        $requestDomain = $this->request->getServerHost(includePort: false);
        /** @var \Illuminate\Support\Collection<Session> */
        $sessions = $sessionIds
            // Attempts to map the ID to an active session
            ->map(fn (string $sessionId) => $this->sessionDao->getSession($sessionId))
            // Only sessions with valid domains (empty domains are also accepted)
            ->filter(fn (?Session $session) => $session && str_ends_with(strtolower($requestDomain), strtolower($session->getDomain())));

        /** @var ?Session */
        $bestSession = $sessions->reduce(function (?Session $best, Session $current): ?Session {
            // Skip invalid sessions
            if (!$this->isValid($current)) {
                return $best;
            }
            // Give priority to logged in sessions
            if ($current->getUserId() && !$best?->getUserId()) {
                return $current;
            }
            // Give priority to the session which was used most recently
            return $current->getSecondsLastUsed() > (int) $best?->getSecondsLastUsed() ? $current : $best;
        });

        /** @var \Illuminate\Support\Collection<string> */
        $domains = $sessions->map(fn (Session $session) => $session->getDomain() ?: $requestDomain)->unique();
        // Prefers the parent domain (smaller length) to define the session, fallbacks to the request domain
        $bestDomain = $domains->reduce(fn (?string $best, string $current) => $best && strlen($best) <= strlen($current) ? $best : $current) ?: $requestDomain;

        // Ensures the session domain isn't empty
        $bestSession?->setDomain($bestDomain);
        // Updates the domain setting while the session is closed
        ini_set('session.cookie_domain', $bestDomain);

        // Seed the session with the proper ID
        session_id($bestSession?->getId() ?? session_create_id());
        session_start();

        // The session cookies must be dropped **after** the session is started, otherwise PHP will not send the headers to clear them
        $this->clearDiscardedSessions($domains->toArray(), $bestDomain);
        // Ensures the domain is updated (data will be saved once the session gets closed)
        $this->userSession?->setDomain($bestDomain);
        $this->updateSessionLifetime();
    }

    /**
     * Clears discarded session cookies
     *
     * @param string[] $domains
     */
    private function clearDiscardedSessions(array $domains, string $bestDomain): void
    {
        // Includes non-specified/empty domain (cleanup deprecated domainless cookie)
        $domains[] = '';
        $requestDomain = $this->request->getServerHost(includePort: false);
        // Includes the request domain if it's not the domain used by the session
        if ($requestDomain !== $bestDomain) {
            $domains[] = $requestDomain;
        }

        // Drops only the cookies (the session data will be cleared by the garbage collector, if we attempt to drop them here we may affect other users)
        foreach (array_unique($domains) as $domain) {
            setcookie(session_name(), '', ['domain' => $domain, 'path' => ini_get('session.cookie_path')]);
        }
    }

    /**
     * Retrieves whether the given session is valid
     */
    private function isValid(Session $session): bool
    {
        // Same IP address (if IP validation is enabled)
        return (!Config::getVar('security', 'session_check_ip') || $session->getIpAddress() === $this->request->getRemoteAddr())
            // Same user agent
            && $session->getUserAgent() === substr($this->request->getUserAgent(), 0, 255)
            // Compatible domain
            && (!$session->getDomain() || str_ends_with(strtolower($this->request->getServerHost(includePort: false)), strtolower($session->getDomain())));
    }

    /**
     * Refreshes the session expiration
     */
    private function refresh(): void
    {
        // Update existing session's timestamp; will be saved when write is called
        $this->userSession->setSecondsLastUsed(time());
        if (!$this->userSession->getRemember()) {
            return;
        }
        // Update session timestamp for remembered sessions so it doesn't expire in the middle of a browser session
        $lifetime = max(0, Config::getVar('general', 'session_lifetime'));
        $this->userSession->setRemember((bool) $lifetime);
        $this->updateSessionLifetime($lifetime ? Carbon::now()->addDays($lifetime)->getTimestamp() : 0);
    }

    /**
     * Creates a new session
     */
    private function createSession(): void
    {
        $now = time();

        $this->userSession = $this->sessionDao->newDataObject();
        $this->userSession->setId(session_id());
        $this->userSession->setIpAddress($this->request->getRemoteAddr());
        $this->userSession->setUserAgent($this->request->getUserAgent());
        $this->userSession->setSecondsCreated($now);
        $this->userSession->setSecondsLastUsed($now);
        $this->userSession->setDomain(ini_get('session.cookie_domain'));
        $this->userSession->setSessionData('');

        $this->sessionDao->insertObject($this->userSession);
    }

    /**
     * Retrieve session IDs sent by the browser
     *
     * @return string[]
     */
    private function getSessionIds(): array
    {
        $ids = [];
        foreach (explode('; ', $_SERVER['HTTP_COOKIE'] ?? '') as $cookie) {
            $nameValue = explode('=', $cookie, 2);
            $value = trim(urldecode($nameValue[1] ?? ''));
            if ($nameValue[0] === session_name() && strlen($value)) {
                $ids[$value] = 0;
            }
        }
        return array_keys($ids);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\session\SessionManager', '\SessionManager');
}

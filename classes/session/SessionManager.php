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
use Stringy\Stringy;

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

        // Initialize the session. This calls SessionManager::read() and
        // sets $this->userSession if a session is present.
        session_start();

        // Check if the session is tied to the parent domain
        $domain = $this->userSession ? $this->userSession->getDomain() : null;
        if ($domain && $domain != $request->getServerHost(null, false)) {
            // if current host contains . and the session domain (is a subdomain of the session domain), adjust the session's domain parameter to the parent
            if (strtolower(substr($request->getServerHost(null, false), -1 - strlen($domain))) === '.' . strtolower($domain)) {
                ini_set('session.cookie_domain', $domain);
            }
        }

        if (!$this->userSession || !$this->isValid($this->userSession)) {
            if ($this->userSession) {
                // Destroy old session
                session_destroy();
            }

            $this->createSession();
        } else {
            $this->refresh();
        }
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
     * Resubmit the session cookie.
     *
     */
    public function updateSessionCookie($sessionId = false, int $expireTime = 0): bool
    {
        $domain = ini_get('session.cookie_domain');
        // Specific domains must contain at least one '.' (e.g. Chrome)
        if (strpos($domain, '.') === false) {
            $domain = false;
        }

        // Clear cookies with no domain #8921
        if ($domain) {
            setcookie(session_name(), '', 0, ini_get('session.cookie_path'), false);
        }

        $cookieParams = session_get_cookie_params();
        unset($cookieParams['lifetime']);
        $cookieParams['expires'] = $expireTime;
        return setcookie(
            session_name(),
            $sessionId === false ? session_id() : $sessionId,
            $cookieParams
        );
    }

    /**
     * Regenerate the session ID for the current user session.
     * This is useful to guard against the "session fixation" form of hijacking
     * by changing the user's session ID after they have logged in (in case the
     * original session ID had been pre-populated).
     */
    public function regenerateSessionId(): bool
    {
        $success = false;
        $currentSessionId = session_id();

        if (session_regenerate_id(true) && isset($this->userSession)) {
            // Delete old session and insert new session
            $this->sessionDao->deleteById($currentSessionId);
            $this->userSession->setId(session_id());
            $this->sessionDao->insertObject($this->userSession);
            $this->updateSessionCookie(); // TODO: this might not be needed on >= 4.3.3
            $success = true;
        }

        return $success;
    }

    /**
     * Change the lifetime of the current session cookie.
     *
     */
    public function updateSessionLifetime(int $expireTime = 0): bool
    {
        return $this->updateSessionCookie(false, $expireTime);
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
     * Retrieves whether the given session is valid
     */
    private function isValid(Session $session): bool
    {
        // Same IP address (if IP validation is enabled)
        return (!Config::getVar('security', 'session_check_ip') || $session->getIpAddress() === $this->request->getRemoteAddr())
            // Same user agent
            && $session->getUserAgent() === substr($this->request->getUserAgent(), 0, 255)
            // Compatible domain
            && (!$session->getDomain() || (Stringy::create($this->request->getServerHost(includePort: false))->endsWith($session->getDomain(), false)));
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\session\SessionManager', '\SessionManager');
}

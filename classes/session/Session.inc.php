<?php

/**
 * @defgroup session Session
 * Implements session concerns such as the session manager, session objects, etc.
 */

/**
 * @file classes/session/Session.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Session
 * @ingroup session
 *
 * @see SessionDAO
 *
 * @brief Maintains user state information from one request to the next.
 */

namespace PKP\session;

use PKP\config\Config;
use PKP\db\DAORegistry;

class Session extends \PKP\core\DataObject
{
    /** The User object associated with this session */
    public $user;


    /**
     * Get a session variable's value.
     *
     * @param $key string
     */
    public function getSessionVar($key)
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Get a session variable's value.
     *
     * @param $key string
     * @param $value mixed
     */
    public function setSessionVar($key, $value)
    {
        $_SESSION[$key] = $value;
        return $value;
    }

    /**
     * Unset (delete) a session variable.
     *
     * @param $key string
     */
    public function unsetSessionVar($key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    //
    // Get/set methods
    //

    /**
     * Get user ID (0 if anonymous user).
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * Set user ID.
     *
     * @param $userId int
     */
    public function setUserId($userId)
    {
        if (!isset($userId) || empty($userId)) {
            $this->user = null;
            $userId = null;
        } elseif ($userId != $this->getData('userId')) {
            $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
            $this->user = $userDao->getById($userId);
            if (!isset($this->user)) {
                $userId = null;
            }
        }
        $this->setData('userId', $userId);
    }

    /**
     * Get IP address.
     *
     * @return string
     */
    public function getIpAddress()
    {
        return $this->getData('ipAddress');
    }

    /**
     * Set IP address.
     *
     * @param $ipAddress string
     */
    public function setIpAddress($ipAddress)
    {
        $this->setData('ipAddress', $ipAddress);
    }

    /**
     * Get user agent.
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->getData('userAgent');
    }

    /**
     * Set user agent.
     *
     * @param $userAgent string
     */
    public function setUserAgent($userAgent)
    {
        $this->setData('userAgent', $userAgent);
    }

    /**
     * Get time (in seconds) since session was created.
     *
     * @return int
     */
    public function getSecondsCreated()
    {
        return $this->getData('created');
    }

    /**
     * Set time (in seconds) since session was created.
     *
     * @param $created int
     */
    public function setSecondsCreated($created)
    {
        $this->setData('created', $created);
    }

    /**
     * Get time (in seconds) since session was last used.
     *
     * @return int
     */
    public function getSecondsLastUsed()
    {
        return $this->getData('lastUsed');
    }

    /**
     * Set time (in seconds) since session was last used.
     *
     * @param $lastUsed int
     */
    public function setSecondsLastUsed($lastUsed)
    {
        $this->setData('lastUsed', $lastUsed);
    }

    /**
     * Check if session is to be saved across browser sessions.
     *
     * @return boolean
     */
    public function getRemember()
    {
        return $this->getData('remember');
    }

    /**
     * Set whether session is to be saved across browser sessions.
     *
     * @param $remember boolean
     */
    public function setRemember($remember)
    {
        $this->setData('remember', $remember);
    }

    /**
     * Get all session parameters.
     *
     * @return array
     */
    public function getSessionData()
    {
        return $this->getData('data');
    }

    /**
     * Set session parameters.
     *
     * @param $data array
     */
    public function setSessionData($data)
    {
        $this->setData('data', $data);
    }

    /**
     * Get the domain with which the session is registered
     *
     * @return array
     */
    public function getDomain()
    {
        return $this->getData('domain');
    }

    /**
     * Set the domain with which the session is registered
     *
     * @param $data array
     */
    public function setDomain($data)
    {
        $this->setData('domain', $data);
    }

    /**
     * Get user associated with this session (null if anonymous user).
     *
     * @return User
     */
    public function &getUser()
    {
        return $this->user;
    }

    /**
     * Get a usable CSRF token (generating if necessary).
     *
     * @return string
     */
    public function getCSRFToken()
    {
        $csrf = $this->getSessionVar('csrf');
        if (!is_array($csrf) || time() > $csrf['timestamp'] + (60 * 60)) { // 1 hour token expiry
            // Generate random data
            if (function_exists('openssl_random_pseudo_bytes')) {
                $data = openssl_random_pseudo_bytes(128);
            } elseif (function_exists('random_bytes')) {
                $data = random_bytes(128);
            } else {
                $data = sha1(mt_rand());
            }

            // Hash the data
            $token = null;
            $salt = Config::getVar('security', 'salt');
            $algos = hash_algos();
            foreach (['sha256', 'sha1', 'md5'] as $algo) {
                if (in_array($algo, $algos)) {
                    $token = hash_hmac($algo, $data, $salt);
                }
            }
            if (!$token) {
                $token = md5($data . $salt);
            }

            $csrf = $this->setSessionVar('csrf', [
                'timestamp' => time(),
                'token' => $token,
            ]);
        } else {
            // Extend timeout of CSRF token
            $csrf['timestamp'] = time();
            $this->setSessionVar('csrf', $csrf);
        }
        return $csrf['token'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\session\Session', '\Session');
}

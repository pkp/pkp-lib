<?php

/**
 * @file classes/security/Validation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Validation
 *
 * @ingroup security
 *
 * @brief Class providing user validation/authentication operations.
 */

namespace PKP\security;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PKP\config\Config;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\site\Site;
use PKP\site\SiteDAO;
use PKP\user\User;
use PKP\security\Role;
use Illuminate\Support\Facades\DB;


class Validation
{
    public const ADMINISTRATION_PROHIBITED = 0;
    public const ADMINISTRATION_PARTIAL = 1;
    public const ADMINISTRATION_FULL = 2;

    /**
     * Authenticate user credentials and mark the user as logged in in the current session.
     *
     * @param string $username
     * @param string $password unencrypted password
     * @param string $reason reference to string to receive the reason an account was disabled; null otherwise
     * @param bool $remember remember a user's session past the current browser session
     *
     * @return ?User the User associated with the login credentials, or false if the credentials are invalid
     */
    public static function login($username, $password, &$reason, $remember = false)
    {
        $reason = null;

        return Auth::attempt(['username' => $username, 'password' => $password], $remember)
            ? static::registerUserSession(Auth::user(), $reason)
            : false;
    }

    /**
     * Verify if the input password is correct
     *
     * @param string $username the string username
     * @param string $password the plaintext password
     * @param string $hash the password hash from the database
     * @param string &$rehash if password needs rehash, this variable is used
     *
     * @return bool
     */
    public static function verifyPassword($username, $password, $hash, &$rehash)
    {
        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            // update to new hashing algorithm
            $oldHash = self::encryptCredentials($username, $password, false, true);

            if ($oldHash === $hash) {
                // update hash
                $rehash = self::encryptCredentials($username, $password);

                return true;
            }
        }

        return password_verify($password, $hash);
    }

    /**
     * Mark the user as logged in in the current session.
     *
     * @param User      $user       user to register in the session
     * @param string    $reason     reference to string to receive the reason an account
     *                              was disabled; null otherwise
     *
     * @return mixed                User or boolean the User associated with the login credentials,
     *                              or false if the credentials are invalid
     */
    public static function registerUserSession($user, &$reason)
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($user->getDisabled()) { // The user has been disabled.
            $reason = $user->getDisabledReason();
            if ($reason === null) {
                $reason = '';
            }
            return false;
        }

        $request = Application::get()->getRequest();
        $request->getSessionGuard()->setUserDataToSession($user)->updateSession($user->getId());

        $user->setDateLastLogin(Core::getCurrentDate());
        Repo::user()->edit($user);

        return $user;
    }

    /**
     * Mark the user as logged out in the current session.
     *
     * @return bool
     */
    public static function logout()
    {
        $request = Application::get()->getRequest();
        $session = $request->getSession();
        $user = Auth::user(); /** @var \PKP\user\User $user */

        Auth::logout();
        $session->invalidate();
        $session->regenerateToken();

        $session->put('username', $user->getUsername());
        $session->put('email', $user->getEmail());

        $request->getSessionGuard()->updateSession(null);

        return true;
    }

    /**
     * Redirect to the login page, appending the current URL as the source.
     *
     * @param string $message Optional name of locale key to add to login page
     */
    public static function redirectLogin($message = null)
    {
        $args = [];

        if (isset($_SERVER['REQUEST_URI'])) {
            $args['source'] = $_SERVER['REQUEST_URI'];
        }
        if ($message !== null) {
            $args['loginMessage'] = $message;
        }

        $request = Application::get()->getRequest();
        $request->redirect(null, 'login', null, null, $args);
    }

    /**
     * Check if a user's credentials are valid.
     *
     * @param string $username username
     * @param string $password unencrypted password
     *
     * @return bool
     */
    public static function checkCredentials($username, $password)
    {
        $user = Repo::user()->getByUsername($username, false);
        if (!$user) {
            return false;
        }

        // Validate against user database
        $rehash = null;
        if (!self::verifyPassword($username, $password, $user->getPassword(), $rehash)) {
            return false;
        }

        if (!empty($rehash)) {
            // update to new hashing algorithm
            $user->setPassword($rehash);

            // save new password hash to database
            Repo::user()->edit($user);
        }

        return true;
    }

    /**
     * Check if a user is authorized to access the specified role in the specified context.
     *
     * @param int $roleId
     * @param int $contextId optional (e.g., for global site admin role), the ID of the context
     *
     * @return bool
     */
    public static function isAuthorized($roleId, ?int $contextId = Application::SITE_CONTEXT_ID)
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        $user = Auth::user(); /** @var \PKP\user\User $user */
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        return $roleDao->userHasRole($contextId, $user->getId(), $roleId);
    }

    /**
     * Encrypt user passwords for database storage.
     * The username is used as a unique salt to make dictionary
     * attacks against a compromised database more difficult.
     *
     * @param string $username username (kept for backwards compatibility)
     * @param string $password unencrypted password
     * @param string $encryption optional encryption algorithm to use, defaulting to the value from the site configuration
     * @param bool $legacy if true, use legacy hashing technique for backwards compatibility
     *
     * @return string encrypted password
     */
    public static function encryptCredentials($username, $password, $encryption = false, $legacy = false)
    {
        if ($legacy) {
            $valueToEncrypt = $username . $password;

            if ($encryption == false) {
                $encryption = Config::getVar('security', 'encryption');
            }

            switch ($encryption) {
                case 'sha1':
                    if (function_exists('sha1')) {
                        return sha1($valueToEncrypt);
                    }
                    // no break
                case 'md5':
                default:
                    return md5($valueToEncrypt);
            }
        } else {
            return password_hash($password, PASSWORD_BCRYPT);
        }
    }

    /**
     * Generate a random password.
     * Assumes the random number generator has already been seeded.
     *
     * @param int $length the length of the password to generate (default is site minimum)
     *
     * @return string
     */
    public static function generatePassword($length = null)
    {
        if (!$length) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite(); /** @var Site $site */
            $length = $site->getMinPasswordLength();
        }
        $letters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '23456789';

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= random_int(1, 4) == 4 ? $numbers[random_int(0, strlen($numbers) - 1)] : $letters[random_int(0, strlen($letters) - 1)];
        }
        return $password;
    }

    /**
     * Generate a hash value to use for confirmation to reset a password.
     *
     * @param int $userId
     * @param int $expiry timestamp when hash expires, defaults to CURRENT_TIME + RESET_SECONDS
     *
     * @return string (boolean false if user is invalid)
     */
    public static function generatePasswordResetHash($userId, $expiry = null)
    {
        if (($user = Repo::user()->get($userId)) == null) {
            // No such user
            return false;
        }
        // create hash payload
        $salt = Config::getVar('security', 'salt');

        if (empty($expiry)) {
            $expires = (int) Config::getVar('security', 'reset_seconds', 7200);
            $expiry = time() + $expires;
        }

        // use last login time to ensure the hash changes when they log in
        $data = $user->getUsername() . $user->getPassword() . $user->getDateLastLogin() . $expiry;

        // generate hash and append expiry timestamp
        $algos = hash_algos();

        foreach (['sha256', 'sha1', 'md5'] as $algo) {
            if (in_array($algo, $algos)) {
                return hash_hmac($algo, $data, $salt) . ':' . $expiry;
            }
        }

        // fallback to MD5
        return md5($data . $salt) . ':' . $expiry;
    }

    /**
     * Check if provided password reset hash is valid.
     *
     * @param int $userId
     * @param string $hash
     *
     * @return bool
     */
    public static function verifyPasswordResetHash($userId, $hash)
    {
        // append ":" to ensure the explode results in at least 2 elements
        [, $expiry] = explode(':', $hash . ':');

        if (empty($expiry) || ((int) $expiry < time())) {
            // expired
            return false;
        }

        return ($hash === self::generatePasswordResetHash($userId, $expiry));
    }

    /**
     * Suggest a username given the first and last names.
     *
     * @param string $givenName
     * @param string $familyName
     *
     * @return string
     */
    public static function suggestUsername($givenName, $familyName = null)
    {
        $name = $givenName;
        if (!empty($familyName)) {
            $initial = Str::substr($givenName, 0, 1);
            $name = $initial . $familyName;
        }

        $suggestion = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-zA-Z0-9_-]/', '');
        for ($i = ''; Repo::user()->getByUsername($suggestion . $i, true); $i++);
        return $suggestion . $i;
    }

    /**
     * Check if the user is logged in.
     */
    public static function isLoggedIn(): bool
    {
        return (bool) Application::get()->getRequest()->getSessionGuard()->getUserId();
    }

    /**
     * Check if the user is logged in as a different user. Returns the original user ID or null
     */
    public static function loggedInAs(): ?int
    {
        return Application::get()->getRequest()->getSession()->get('signedInAs') ?: null;
    }

    /**
     * Check if the user is logged in as a different user.
     *
     *
     * @deprecated 3.4
     */
    public static function isLoggedInAs(): bool
    {
        return (bool) static::loggedInAs();
    }

    /**
     * Shortcut for checking authorization as site admin.
     *
     * @return bool
     */
    public static function isSiteAdmin()
    {
        return self::isAuthorized(Role::ROLE_ID_SITE_ADMIN);
    }

    /**
     * Check whether a user is allowed to administer another user.
     *
     * @param int $administeredUserId User ID of user to potentially administer
     * @param int $administratorUserId User ID of user who wants to do the administrating
     *
     * @return bool True IFF the administration operation is permitted
     *
     * @deprecated 3.4 Use the method getAdministrationLevel and checked against the ADMINISTRATION_* constants
     */
    public static function canAdminister($administeredUserId, $administratorUserId)
    {
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

        // You can administer yourself
        if ($administeredUserId == $administratorUserId) {
            return true;
        }

        // You cannot administer administrators
        if ($roleDao->userHasRole(\PKP\core\PKPApplication::SITE_CONTEXT_ID, $administeredUserId, Role::ROLE_ID_SITE_ADMIN)) {
            return false;
        }

        // Otherwise, administrators can administer everyone
        if ($roleDao->userHasRole(\PKP\core\PKPApplication::SITE_CONTEXT_ID, $administratorUserId, Role::ROLE_ID_SITE_ADMIN)) {
            return true;
        }

        // Check for administered user group assignments in other contexts
        // that the administrator user doesn't have a manager role in.
        $userGroups = Repo::userGroup()->userUserGroups($administeredUserId);
        foreach ($userGroups as $userGroup) {
            if ($userGroup->getContextId() != \PKP\core\PKPApplication::SITE_CONTEXT_ID && !$roleDao->userHasRole($userGroup->getContextId(), $administratorUserId, Role::ROLE_ID_MANAGER)) {
                // Found an assignment: disqualified.
                return false;
            }
        }

        // Make sure the administering user has a manager role somewhere
        $foundManagerRole = false;
        $roles = $roleDao->getByUserId($administratorUserId);
        foreach ($roles as $role) {
            if ($role->getRoleId() == Role::ROLE_ID_MANAGER) {
                $foundManagerRole = true;
            }
        }
        if (!$foundManagerRole) {
            return false;
        }

        // There were no conflicting roles. Permit administration.
        return true;
    }

    /**
     * Get the user's administration level
     *
     * @param int   $administeredUserId     User ID of user to potentially administer
     * @param int   $administratorUserId    User ID of user who wants to do the administrating
     * @param int   $contextId              The journal/context Id
     *
     * @return int The authorized administration level
     */
    public static function getAdministrationLevel(int $administeredUserId, int $administratorUserId, ?int $contextId = null): int
    {
        if ($administeredUserId === $administratorUserId) {
            return self::ADMINISTRATION_FULL;
        }

        // check if the administered user is a site admin.. (cannot be administered)
        $isAdministeredSiteAdmin = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([\PKP\core\PKPApplication::SITE_CONTEXT_ID])
            ->filterByRoleIds([Role::ROLE_ID_SITE_ADMIN])
            ->filterByUserIds([$administeredUserId])
            ->getCount() > 0;

        if ($isAdministeredSiteAdmin) {
            return self::ADMINISTRATION_PROHIBITED;
        }

        // check if the administrator is a site admin.. (can administer all users)
        $isAdministratorSiteAdmin = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([\PKP\core\PKPApplication::SITE_CONTEXT_ID])
            ->filterByRoleIds([Role::ROLE_ID_SITE_ADMIN])
            ->filterByUserIds([$administratorUserId])
            ->getCount() > 0;

        if ($isAdministratorSiteAdmin) {
            return self::ADMINISTRATION_FULL;
        }

        // ensure administrator have a manager role somewhere
        $hasManagerRole = Repo::userGroup()
            ->getCollector()
            ->filterByUserIds([$administratorUserId])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER])
            ->getCount() > 0;

        if (!$hasManagerRole) {
            return self::ADMINISTRATION_PROHIBITED;
        }

        // optimize query to check for unmanaged contexts
        $unmanagedContextsExist = \Illuminate\Support\Facades\DB::table('user_user_groups as uug_administered')
            ->join('user_groups as ug_administered', 'uug_administered.user_group_id', '=', 'ug_administered.user_group_id')
            ->where('uug_administered.user_id', $administeredUserId)
            ->whereNotIn('ug_administered.context_id', function ($query) use ($administratorUserId) {
                $query->select('ug_administrator.context_id')
                    ->from('user_user_groups as uug_administrator')
                    ->join('user_groups as ug_administrator', 'uug_administrator.user_group_id', '=', 'ug_administrator.user_group_id')
                    ->where('uug_administrator.user_id', $administratorUserId)
                    ->where('ug_administrator.role_id', Role::ROLE_ID_MANAGER);
            })
            ->exists();

        if ($unmanagedContextsExist) {
            // check if partial administration is allowed in the current context
            $isManagerInCurrentContext = $contextId !== null &&
                Repo::userGroup()
                    ->getCollector()
                    ->filterByContextIds([$contextId])
                    ->filterByUserIds([$administratorUserId])
                    ->filterByRoleIds([Role::ROLE_ID_MANAGER])
                    ->getCount() > 0;

            if ($isManagerInCurrentContext) {
                return self::ADMINISTRATION_PARTIAL;
            }

            return self::ADMINISTRATION_PROHIBITED;
        }

        // all contexts are managed by the administrator.. permit full administration
        return self::ADMINISTRATION_FULL;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\Validation', '\Validation');
}

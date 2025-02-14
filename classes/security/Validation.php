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
use PKP\userGroup\UserGroup;
use PKP\security\Role;
use PKP\core\PKPApplication;

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
        // You can administer yourself
        if ($administeredUserId == $administratorUserId) {
            return true;
        }

        $siteContextId = \PKP\core\PKPApplication::SITE_CONTEXT_ID;

        // check if administered user is site admin
        $isAdministeredUserSiteAdmin = UserGroup::query()
            ->withContextIds($siteContextId)
            ->withRoleIds(Role::ROLE_ID_SITE_ADMIN)
            ->whereHas('userUserGroups', function ($query) use ($administeredUserId) {
                $query->withUserId($administeredUserId)
                      ->withActive();
            })
            ->exists();
    
        if ($isAdministeredUserSiteAdmin) {
            return false;
        }
    
        // check if administrator user is site admin
        $isAdministratorUserSiteAdmin = UserGroup::query()
            ->withContextIds($siteContextId)
            ->withRoleIds(Role::ROLE_ID_SITE_ADMIN)
            ->whereHas('userUserGroups', function ($query) use ($administratorUserId) {
                $query->withUserId($administratorUserId)
                      ->withActive();
            })
            ->exists();
    
        if ($isAdministratorUserSiteAdmin) {
            return true;
        }
    
        // Get contexts where administered user has roles
        $administeredUserContexts = UserGroup::query()
            ->whereHas('userUserGroups', function ($query) use ($administeredUserId) {
                $query->withUserId($administeredUserId)
                      ->withActive();
            })
            ->get()
            ->map(fn ($userGroup) => $userGroup->contextId)
            ->unique()
            ->values()
            ->toArray();
    
        // get contexts where administrator user has manager role
        $administratorManagerContexts = UserGroup::query()
            ->withRoleIds(Role::ROLE_ID_MANAGER)
            ->whereHas('userUserGroups', function ($query) use ($administratorUserId) {
                $query->withUserId($administratorUserId)
                      ->withActive();
            })
            ->get()
            ->map(fn ($userGroup) => $userGroup->contextId)
            ->unique()
            ->values()
            ->toArray();
    
        // check for conflicting contexts
        $conflictingContexts = array_diff($administeredUserContexts, $administratorManagerContexts);
    
        if (!empty($conflictingContexts)) {
            // found conflicting contexts: disqualified
            return false;
        }

        // Make sure the administering user has a manager role somewhere
        if (empty($administratorManagerContexts)) {
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
    public static function getAdministrationLevel(
        int $administeredUserId,
        int $administratorUserId,
        ?int $contextId = null
    ): int {

        if ($administeredUserId === $administratorUserId) {
            return self::ADMINISTRATION_FULL;
        }

        $siteContextId = PKPApplication::SITE_CONTEXT_ID;

        // single query to fetch user groups assigned to either user
        $allUserGroups = UserGroup::query()
            ->whereHas('userUserGroups', fn($q) =>
                $q->withActive()->withUserIds([$administratorUserId, $administeredUserId])
            )
            ->with(['userUserGroups' => fn($q) =>
                $q->withActive()->withUserIds([$administratorUserId, $administeredUserId])
            ])
            ->get();

        $administratorMap = [];
        $administeredMap = [];

        foreach ($allUserGroups as $userGroup) {
            $roleId = $userGroup->roleId;
            $userGroupContextId = $userGroup->contextId ?? PKPApplication::SITE_CONTEXT_ID;

            // then each user assignment row
            foreach ($userGroup->userUserGroups as $uug) {
                if ($uug->userId === $administratorUserId) {
                    $administratorMap[$userGroupContextId][] = $roleId;
                } elseif ($uug->userId === $administeredUserId) {
                    $administeredMap[$userGroupContextId][] = $roleId;
                }
            }
        }

        if (
            isset($administeredMap[$siteContextId]) &&
            in_array(Role::ROLE_ID_SITE_ADMIN, $administeredMap[$siteContextId], true)
        ) {
            return self::ADMINISTRATION_PROHIBITED;
        }

        // if administrator user is site admin => FULL
        if (
            isset($administratorMap[$siteContextId]) &&
            in_array(Role::ROLE_ID_SITE_ADMIN, $administratorMap[$siteContextId], true)
        ) {
            return self::ADMINISTRATION_FULL;
        }

        // gather manager contexts for the administrator
        $administratorManagerContexts = [];
        foreach ($administratorMap as $ctx => $roles) {
            if (in_array(Role::ROLE_ID_MANAGER, $roles, true)) {
                $administratorManagerContexts[] = $ctx;
            }
        }

        if (empty($administratorManagerContexts)) {
            return self::ADMINISTRATION_PROHIBITED;
        }

        $administeredUserContexts = array_keys($administeredMap);
        $conflictingContexts = array_diff($administeredUserContexts, $administratorManagerContexts);
        if (!empty($conflictingContexts)) {
            if ($contextId !== null && in_array($contextId, $administratorManagerContexts, true)) {
                return self::ADMINISTRATION_PARTIAL;
            }
            return self::ADMINISTRATION_PROHIBITED;
        }

        return self::ADMINISTRATION_FULL;
    }

    /**
     * Determine if the current user can "Log In As" the target user.
     *
     * By default, we do a cross-journal check (contextId = null)
     * to enforce "Log In As" only in a single journal context, pass $contextId.
     */
    public static function canUserLoginAs(
        int $targetUserId,
        int $currentUserId,
        ?int $contextId = null
    ): bool {
        // prevent self-login
        if ($targetUserId === $currentUserId) {
            return false;
        }
        return self::getAdministrationLevel($targetUserId, $currentUserId, $contextId) === self::ADMINISTRATION_FULL;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\Validation', '\Validation');
}

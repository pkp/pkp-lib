<?php
/**
 * @defgroup user User
 * Implements data objects and DAOs concerned with managing user accounts.
 */

/**
 * @file classes/user/User.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class User
 *
 * @ingroup user
 *
 * @brief Basic class describing users existing in the system.
 */

namespace PKP\user;

use APP\facades\Repo;
use Illuminate\Contracts\Auth\Authenticatable;
use PKP\db\DAORegistry;
use PKP\identity\Identity;
use PKP\security\RoleDAO;

class User extends Identity implements Authenticatable
{
    /** @var array Roles assigned to this user grouped by context */
    protected $_roles = [];

    //
    // Get/set methods
    //

    /**
     * Get username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->getData('userName');
    }

    /**
     * Set username.
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->setData('userName', $username);
    }

    /**
     * Get implicit auth ID string.
     *
     * @return string
     */
    public function getAuthStr()
    {
        return $this->getData('authStr');
    }

    /**
     * Set Shib ID string for this user.
     *
     * @param string $authStr
     */
    public function setAuthStr($authStr)
    {
        $this->setData('authStr', $authStr);
    }

    /**
     * Get localized user signature.
     */
    public function getLocalizedSignature()
    {
        return $this->getLocalizedData('signature');
    }

    /**
     * Get email signature.
     *
     * @param string $locale
     *
     * @return string|array<string,string>
     */
    public function getSignature($locale)
    {
        return $this->getData('signature', $locale);
    }

    /**
     * Set signature.
     *
     * @param string $signature
     * @param string $locale
     */
    public function setSignature($signature, $locale)
    {
        $this->setData('signature', $signature, $locale);
    }

    /**
     * Get password (encrypted).
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->getData('password');
    }

    /**
     * Set password (assumed to be already encrypted).
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->setData('password', $password);
    }

    /**
     * Get phone number.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->getData('phone');
    }

    /**
     * Set phone number.
     *
     * @param string $phone
     */
    public function setPhone($phone)
    {
        $this->setData('phone', $phone);
    }

    /**
     * Get mailing address.
     *
     * @return string
     */
    public function getMailingAddress()
    {
        return $this->getData('mailingAddress');
    }

    /**
     * Set mailing address.
     *
     * @param string $mailingAddress
     */
    public function setMailingAddress($mailingAddress)
    {
        $this->setData('mailingAddress', $mailingAddress);
    }

    /**
     * Get billing address.
     *
     * @return string
     */
    public function getBillingAddress()
    {
        return $this->getData('billingAddress');
    }

    /**
     * Set billing address.
     *
     * @param string $billingAddress
     */
    public function setBillingAddress($billingAddress)
    {
        $this->setData('billingAddress', $billingAddress);
    }

    /**
     * Get the user's interests displayed as a comma-separated string
     *
     * @return string
     */
    public function getInterestString()
    {
        return Repo::userInterest()->getInterestsString($this);
    }

    /**
     * Get user gossip.
     *
     * @return string
     */
    public function getGossip()
    {
        return $this->getData('gossip');
    }

    /**
     * Set user gossip.
     *
     * @param string $gossip
     */
    public function setGossip($gossip)
    {
        $this->setData('gossip', $gossip);
    }

    /**
     * Get user's working languages.
     *
     * @return array
     */
    public function getLocales()
    {
        $locales = $this->getData('locales');
        return $locales ?? [];
    }

    /**
     * Set user's working languages.
     *
     * @param array $locales
     */
    public function setLocales($locales)
    {
        $this->setData('locales', $locales);
    }

    /**
     * Get date user last sent an email.
     *
     * @return string (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateLastEmail()
    {
        return $this->getData('dateLastEmail');
    }

    /**
     * Set date user last sent an email.
     *
     * @param string $dateLastEmail (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateLastEmail($dateLastEmail)
    {
        $this->setData('dateLastEmail', $dateLastEmail);
    }

    /**
     * Get date user registered with the site.
     *
     * @return string (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateRegistered()
    {
        return $this->getData('dateRegistered');
    }

    /**
     * Set date user registered with the site.
     *
     * @param string $dateRegistered (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateRegistered($dateRegistered)
    {
        $this->setData('dateRegistered', $dateRegistered);
    }

    /**
     * Get date user email was validated with the site.
     *
     * @return string (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateValidated()
    {
        return $this->getData('dateValidated');
    }

    /**
     * Set date user email was validated with the site.
     *
     * @param string $dateValidated (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateValidated($dateValidated)
    {
        $this->setData('dateValidated', $dateValidated);
    }

    /**
     * Get date user last logged in to the site.
     *
     * @return string
     */
    public function getDateLastLogin()
    {
        return $this->getData('dateLastLogin');
    }

    /**
     * Set date user last logged in to the site.
     *
     * @param string $dateLastLogin
     */
    public function setDateLastLogin($dateLastLogin)
    {
        $this->setData('dateLastLogin', $dateLastLogin);
    }

    /**
     * Check if user must change their password on their next login.
     *
     * @return bool
     */
    public function getMustChangePassword()
    {
        return $this->getData('mustChangePassword');
    }

    /**
     * Set whether or not user must change their password on their next login.
     *
     * @param bool $mustChangePassword
     */
    public function setMustChangePassword($mustChangePassword)
    {
        $this->setData('mustChangePassword', $mustChangePassword);
    }

    /**
     * Check if user is disabled.
     *
     * @return bool
     */
    public function getDisabled()
    {
        return $this->getData('disabled');
    }

    /**
     * Set whether or not user is disabled.
     *
     * @param bool $disabled
     */
    public function setDisabled($disabled)
    {
        $this->setData('disabled', $disabled);
    }

    /**
     * Get the reason the user was disabled.
     *
     * @return string
     */
    public function getDisabledReason()
    {
        return $this->getData('disabledReason');
    }

    /**
     * Set the reason the user is disabled.
     *
     * @param string $reasonDisabled
     */
    public function setDisabledReason($reasonDisabled)
    {
        $this->setData('disabledReason', $reasonDisabled);
    }

    /**
     * Get the inline help display status for this user.
     *
     * @return int
     */
    public function getInlineHelp()
    {
        return $this->getData('inlineHelp');
    }

    /**
     * Set the inline help display status for this user.
     *
     * @param int $inlineHelp
     */
    public function setInlineHelp($inlineHelp)
    {
        $this->setData('inlineHelp', $inlineHelp);
    }

    /**
     * Check if this user has a role in a context
     *
     * @param int|array $roles Role(s) to check for
     * @param ?int $contextId The context to check for roles in.
     *
     * @return bool
     */
    public function hasRole($roles, ?int $contextId)
    {
        $contextRoles = $this->getRoles($contextId);

        if (empty($contextRoles)) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        foreach ($contextRoles as $contextRole) {
            if (in_array((int) $contextRole->getId(), $roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get this user's roles in a context
     *
     * @param ?int $contextId The context to retrieve roles in.
     * @param bool $noCache Force the roles to be retrieved from the database
     *
     * @return array
     */
    public function getRoles(?int $contextId, $noCache = false)
    {
        $contextId = (int) $contextId;
        if ($noCache || empty($this->_roles[$contextId])) {
            $userRolesDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $userRolesDao */
            $this->setRoles($userRolesDao->getByUserId($this->getId(), $contextId), $contextId);
        }

        return $this->_roles[$contextId] ?? [];
    }

    /**
     * Set this user's roles in a context
     *
     * @param array $roles The roles to assign this user
     * @param ?int $contextId The context to assign these roles
     */
    public function setRoles($roles, ?int $contextId)
    {
        $contextId = (int) $contextId;
        $this->_roles[$contextId] = $roles;
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getAuthIdentifierName
     */
    public function getAuthIdentifierName()
    {
        return 'user_id';
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getAuthIdentifier
     */
    public function getAuthIdentifier()
    {
        return $this->getId();
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getAuthPassword
     */
    public function getAuthPassword()
    {
        return $this->getPassword();
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getAuthPasswordName
     */
    public function getAuthPasswordName()
    {
        return 'password';
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getRememberToken
     */
    public function getRememberToken()
    {
        return $this->getData('rememberToken');
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::setRememberToken
     */
    public function setRememberToken($value)
    {
        return $this->setData('rememberToken', $value);
    }

    /**
     * @copydoc \Illuminate\Contracts\Auth\Authenticatable::getRememberTokenName
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\User', '\User');
}

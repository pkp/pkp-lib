<?php
/**
 * @defgroup user User
 * Implements data objects and DAOs concerned with managing user accounts.
 */

/**
 * @file classes/user/User.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class User
 * @ingroup user
 *
 * @brief Basic class describing users existing in the system.
 */

namespace PKP\user;

use PKP\db\DAORegistry;

use PKP\identity\Identity;

class User extends Identity
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
     * @return string
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
        $interestManager = new InterestManager();
        return $interestManager->getInterestsString($this);
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
     * @return datestamp (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateLastEmail()
    {
        return $this->getData('dateLastEmail');
    }

    /**
     * Set date user last sent an email.
     *
     * @param datestamp $dateLastEmail (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateLastEmail($dateLastEmail)
    {
        $this->setData('dateLastEmail', $dateLastEmail);
    }

    /**
     * Get date user registered with the site.
     *
     * @return datestamp (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateRegistered()
    {
        return $this->getData('dateRegistered');
    }

    /**
     * Set date user registered with the site.
     *
     * @param datestamp $dateRegistered (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateRegistered($dateRegistered)
    {
        $this->setData('dateRegistered', $dateRegistered);
    }

    /**
     * Get date user email was validated with the site.
     *
     * @return datestamp (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateValidated()
    {
        return $this->getData('dateValidated');
    }

    /**
     * Set date user email was validated with the site.
     *
     * @param datestamp $dateValidated (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateValidated($dateValidated)
    {
        $this->setData('dateValidated', $dateValidated);
    }

    /**
     * Get date user last logged in to the site.
     *
     * @return datestamp
     */
    public function getDateLastLogin()
    {
        return $this->getData('dateLastLogin');
    }

    /**
     * Set date user last logged in to the site.
     *
     * @param datestamp $dateLastLogin
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
        return $this->getData('disabled_reason');
    }

    /**
     * Set the reason the user is disabled.
     *
     * @param string $reasonDisabled
     */
    public function setDisabledReason($reasonDisabled)
    {
        $this->setData('disabled_reason', $reasonDisabled);
    }

    /**
     * Get ID of authentication source for this user.
     *
     * @return int
     */
    public function getAuthId()
    {
        return $this->getData('authId');
    }

    /**
     * Set ID of authentication source for this user.
     *
     * @param int $authId
     */
    public function setAuthId($authId)
    {
        $this->setData('authId', $authId);
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

    public function getContactSignature()
    {
        $signature = htmlspecialchars($this->getFullName());
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
        if ($a = $this->getLocalizedAffiliation()) {
            $signature .= '<br/>' . htmlspecialchars($a);
        }
        if ($p = $this->getPhone()) {
            $signature .= '<br/>' . __('user.phone') . ' ' . htmlspecialchars($p);
        }
        $signature .= '<br/>' . htmlspecialchars($this->getEmail());
        return $signature;
    }

    /**
     * Check if this user has a role in a context
     *
     * @param int|array $roles Role(s) to check for
     * @param int $contextId The context to check for roles in.
     *
     * @return bool
     */
    public function hasRole($roles, $contextId)
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
     * @param int $contextId The context to retrieve roles in.
     * @param bool $noCache Force the roles to be retrieved from the database
     *
     * @return array
     */
    public function getRoles($contextId, $noCache = false)
    {
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
     * @param int $contextId The context to assign these roles
     */
    public function setRoles($roles, $contextId)
    {
        $this->_roles[$contextId] = $roles;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\User', '\User');
}

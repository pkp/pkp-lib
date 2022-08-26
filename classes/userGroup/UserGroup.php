<?php

/**
 * @file classes/userGroup/UserGroup.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\UserGroup
 *
 * @see DAO
 *
 * @brief UserGroup metadata class.
 */

namespace PKP\userGroup;

use PKP\facades\Locale;
use PKP\db\DAORegistry;

class UserGroup extends \PKP\core\DataObject
{
    /**
     * Get the role ID
     *
     * @return int ROLE_ID_...
     */
    public function getRoleId()
    {
        return $this->getData('roleId');
    }

    /**
     * Set the role ID
     *
     * @param int $roleId ROLE_ID_...
     */
    public function setRoleId($roleId)
    {
        $this->setData('roleId', $roleId);
    }

    /**
     * Get the role path
     *
     * @return string Role path
     */
    public function getPath()
    {
        return $this->getData('path');
    }

    /**
     * Set the role path
     * $param $path string
     */
    public function setPath($path)
    {
        $this->setData('path', $path);
    }

    /**
     * Get the context ID
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set the context ID
     *
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the default flag
     *
     * @return bool
     */
    public function getDefault()
    {
        return $this->getData('isDefault');
    }

    /**
     * Set the default flag
     *
     * @param bool $isDefault
     */
    public function setDefault($isDefault)
    {
        $this->setData('isDefault', $isDefault);
    }

    /**
     * Get the "show title" flag (whether or not the title of the role
     * should be included in the list of submission contributor names)
     *
     * @return bool
     */
    public function getShowTitle()
    {
        return $this->getData('showTitle');
    }

    /**
     * Set the "show title" flag
     *
     * @param bool $showTitle
     */
    public function setShowTitle($showTitle)
    {
        $this->setData('showTitle', $showTitle);
    }

    /**
     * Get the "permit self-registration" flag (whether or not users may
     * self-register for this role, i.e. in the case of external
     * reviewers, or whether it should be prohibited, in the case of
     * internal reviewers).
     *
     * @return bool True IFF user self-registration is permitted
     */
    public function getPermitSelfRegistration()
    {
        return $this->getData('permitSelfRegistration');
    }

    /**
     * Set the "permit self-registration" flag
     */
    public function setPermitSelfRegistration(bool $permitSelfRegistration)
    {
        $this->setData('permitSelfRegistration', $permitSelfRegistration);
    }

    /**
     * Get the recommendOnly option (whether or not the manager or the sub-editor role
     * can only recommend or also make decisions in the submission review)
     *
     * @return bool
     */
    public function getRecommendOnly()
    {
        return $this->getData('recommendOnly');
    }

    /**
     * Set the recommendOnly option (whether or not the manager or the sub-editor role
     * can only recommend or also make decisions in the submission review)
     *
     * @param bool $recommendOnly
     */
    public function setRecommendOnly($recommendOnly)
    {
        $this->setData('recommendOnly', $recommendOnly);
    }

    /**
     * Get the localized role name
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get localized user group name, or array of localized names if $locale is null
     *
     * @param string|null $locale
     *
     * @return string|array|null localized name or array of localized names or null
     */
    public function getName($locale)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set user group name
     *
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the localized abbreviation
     *
     * @return string
     */
    public function getLocalizedAbbrev()
    {
        return $this->getLocalizedData('abbrev');
    }

    /**
     * Get localized user group abbreviation, or array of localized abbreviations if $locale is null
     *
     * @param string|null $locale
     *
     * @return string|array|null localized abbreviation or array of localized abbreviations or null
     */
    public function getAbbrev($locale)
    {
        return $this->getData('abbrev', $locale);
    }

    /**
     * Set user group abbreviation
     *
     * @param string $abbrev
     * @param string $locale
     */
    public function setAbbrev($abbrev, $locale)
    {
        $this->setData('abbrev', $abbrev, $locale);
    }

    /**
     * Getter for permitMetadataEdit attribute.
     *
     * @return bool
     */
    public function getPermitMetadataEdit()
    {
        return $this->getData('permitMetadataEdit');
    }

    /**
     * Setter for permitMetadataEdit attribute.
     */
    public function setPermitMetadataEdit(bool $permitMetadataEdit)
    {
        $this->setData('permitMetadataEdit', $permitMetadataEdit);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\userGroup\UserGroup', '\UserGroup');
}
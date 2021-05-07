<?php

/**
 * @file classes/announcement/AnnouncementType.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementType
 * @ingroup announcement
 *
 * @see AnnouncementTypeDAO, AnnouncementTypeForm
 *
 * @brief Basic class describing an announcement type.
 */

namespace PKP\announcement;

class AnnouncementType extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //
    /**
     * Get assoc ID for this annoucement.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set assoc ID for this annoucement.
     *
     * @param $assocId int
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get assoc type for this annoucement.
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set assoc Type for this annoucement.
     *
     * @param $assocType int
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get the type of the announcement type.
     *
     * @return string
     */
    public function getLocalizedTypeName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get the type of the announcement type.
     *
     * @param $locale string
     *
     * @return string
     */
    public function getName($locale)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set the type of the announcement type.
     *
     * @param $name string
     * @param $locale string
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\AnnouncementType', '\AnnouncementType');
}

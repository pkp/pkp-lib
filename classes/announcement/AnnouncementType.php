<?php

/**
 * @file classes/announcement/AnnouncementType.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementType
 *
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
     * Get context ID for this announcement.
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set context ID for this announcement.
     *
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
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
     * @param string $locale
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
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\AnnouncementType', '\AnnouncementType');
}

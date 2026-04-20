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
    /**
     * Get the context ID.
     */
    public function getContextId(): ?int
    {
        return $this->getData('contextId');
    }

    /**
     * Set the context ID.
     */
    public function setContextId(?int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the localized name.
     */
    public function getLocalizedTypeName(): string
    {
        return $this->getLocalizedData('name') ?? '';
    }

    /**
     * Get the name.
     */
    public function getName(?string $locale): array|string|null
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set the name.
     */
    public function setName(array|string|null $name, ?string $locale): void
    {
        $this->setData('name', $name, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\AnnouncementType', '\AnnouncementType');
}

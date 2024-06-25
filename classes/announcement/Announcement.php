<?php

/**
 * @defgroup announcement Announcement
 * Implements announcements that can be presented to website visitors.
 */

/**
 * @file classes/announcement/Announcement.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Announcement
 *
 * @ingroup announcement
 *
 * @see DAO
 *
 * @brief Basic class describing a announcement.
 */

namespace PKP\announcement;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use PKP\db\DAORegistry;

class Announcement extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //
    /**
     * Get assoc ID for this announcement.
     */
    public function getAssocId(): ?int
    {
        return $this->getData('assocId');
    }

    /**
     * Set assoc ID for this announcement.
     */
    public function setAssocId(?int $assocId): void
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get assoc type for this announcement.
     */
    public function getAssocType(): ?int
    {
        return $this->getData('assocType');
    }

    /**
     * Set assoc type for this announcement.
     */
    public function setAssocType(?int $assocType): void
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get the announcement type of the announcement.
     */
    public function getTypeId(): ?int
    {
        return $this->getData('typeId');
    }

    /**
     * Set the announcement type of the announcement.
     */
    public function setTypeId(?int $typeId): void
    {
        $this->setData('typeId', $typeId);
    }

    /**
     * Get the announcement type name of the announcement.
     */
    public function getAnnouncementTypeName(): string
    {
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        return $this->getData('typeId') ? $announcementTypeDao->getById($this->getData('typeId'))?->getLocalizedTypeName() ?? '' : '';
    }

    /**
     * Get localized announcement title
     */
    public function getLocalizedTitle(): string
    {
        return $this->getLocalizedData('title') ?? '';
    }

    /**
     * Get full localized announcement title including type name
     */
    public function getLocalizedTitleFull(): string
    {
        $typeName = $this->getAnnouncementTypeName();
        if (!empty($typeName)) {
            return $typeName . ': ' . $this->getLocalizedTitle();
        } else {
            return $this->getLocalizedTitle();
        }
    }

    /**
     * Get announcement title.
     */
    public function getTitle(?string $locale): array|string|null
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set announcement title.
     */
    public function setTitle(array|string|null $title, ?string $locale): void
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get localized short description
     */
    public function getLocalizedDescriptionShort(): string
    {
        return $this->getLocalizedData('descriptionShort') ?? '';
    }

    /**
     * Get announcement brief description.
     */
    public function getDescriptionShort(?string $locale): array|string|null
    {
        return $this->getData('descriptionShort', $locale);
    }

    /**
     * Set announcement brief description.
     */
    public function setDescriptionShort(array|string|null $descriptionShort, ?string $locale): void
    {
        $this->setData('descriptionShort', $descriptionShort, $locale);
    }

    /**
     * Get localized full description
     */
    public function getLocalizedDescription(): string
    {
        return $this->getLocalizedData('description') ?? '';
    }

    /**
     * Get announcement description.
     */
    public function getDescription(?string $locale): array|string|null
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set announcement description.
     */
    public function setDescription(array|string|null $description, ?string $locale): void
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get announcement expiration date.
     *
     * @return ?string Format (YYYY-MM-DD)
     */
    public function getDateExpire(): ?string
    {
        return $this->getData('dateExpire');
    }

    /**
     * Set announcement expiration date.
     *
     * @param ?string $dateExpire Format YYYY-MM-DD HH:MM:SS
     */
    public function setDateExpire(?string $dateExpire): void
    {
        $this->setData('dateExpire', $dateExpire);
    }

    /**
     * Get announcement posted date.
     *
     * @return string Format YYYY-MM-DD
     */
    public function getDatePosted(): string
    {
        return $this->getData('datePosted') ? date('Y-m-d', strtotime($this->getData('datePosted'))) : '';
    }

    /**
     * Get announcement posted datetime.
     *
     * @return string Format YYYY-MM-DD HH:MM:SS
     */
    public function getDatetimePosted(): string
    {
        return $this->getData('datePosted') ?? '';
    }

    /**
     * Set announcement posted date.
     *
     * @param string $datePosted Format YYYY-MM-DD
     */
    public function setDatePosted(string $datePosted): void
    {
        $this->setData('datePosted', $datePosted);
    }

    /**
     * Set announcement posted datetime.
     *
     * @param string $datetimePosted Format YYYY-MM-DD HH:MM:SS
     */
    public function setDatetimePosted(string $datetimePosted): void
    {
        $this->setData('datePosted', $datetimePosted);
    }

    /**
     * Get the featured image data
     */
    public function getImage(): ?array
    {
        return $this->getData('image');
    }

    /**
     * Set the featured image data
     */
    public function setImage(array $image): void
    {
        $this->setData('image', $image);
    }

    /**
     * Get the full URL to the image
     *
     * @param bool $withTimestamp Pass true to include a query argument with a timestamp
     *     of the date the image was uploaded in order to workaround cache bugs in browsers
     */
    public function getImageUrl(bool $withTimestamp = true): string
    {
        $image = $this->getImage();
        if (!$image) {
            return '';
        }

        $filename = $image['uploadName'];
        if ($withTimestamp) {
            $filename .= '?'. strtotime($image['dateUploaded']);
        }

        $publicFileManager = new PublicFileManager();

        return join('/', [
            Application::get()->getRequest()->getBaseUrl(),
            $this->getAssocId()
                ? $publicFileManager->getContextFilesPath($this->getAssocId())
                : $publicFileManager->getSiteFilesPath(),
            Repo::announcement()->getImageSubdirectory(),
            $filename
        ]);
    }

    /**
     * Get the alt text for the image
     */
    public function getImageAltText(): string
    {
        return $this->getImage()['altText'] ?? '';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\announcement\Announcement', '\Announcement');
}

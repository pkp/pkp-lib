<?php
/**
 * @file classes/highlight/Highlight.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Highlight
 *
 * @ingroup highlight
 *
 * @see DAO
 *
 * @brief The Highlight class implements the abstract data model of a context or site-level highlight.
 */

namespace PKP\highlight;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use PKP\core\DataObject;

class Highlight extends DataObject
{
    public function getContextId(): ?int
    {
        return $this->getData('contextId');
    }

    public function setContextId(?int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    public function getLocalizedDescription(?string $locale = null): ?string
    {
        return $this->getLocalizedData('description', $locale);
    }

    /**
     * @param string[] $description
     */
    public function setDescription(array $description): void
    {
        $this->setData('description', $description);
    }

    public function setLocalizedDescription(string $description, string $locale): void
    {
        $this->setData('description', $description, $locale);
    }

    public function getImage(): ?array
    {
        return $this->getData('image');
    }

    public function setImage($image): void
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
            $filename .= '?' . strtotime($image['dateUploaded']);
        }

        $publicFileManager = new PublicFileManager();

        return join('/', [
            Application::get()->getRequest()->getBaseUrl(),
            $this->getContextId()
                ? $publicFileManager->getContextFilesPath($this->getContextId())
                : $publicFileManager->getSiteFilesPath(),
            Repo::highlight()->getImageSubdirectory(),
            $filename
        ]);
    }

    /**
     * Get the alt text for the image
     */
    public function getImageAltText(): string
    {
        $image = $this->getImage();

        if (!$image || !$image['altText']) {
            return '';
        }

        return $image['altText'];
    }

    public function getSequence(): ?int
    {
        return $this->getData('sequence');
    }

    public function setSequence($sequence): void
    {
        $this->setData('sequence', $sequence);
    }

    public function getLocalizedTitle(?string $locale = null): string
    {
        return $this->getLocalizedData('title', $locale);
    }

    /**
     * @param string[] $title
     */
    public function setTitle(array $title): void
    {
        $this->setData('title', $title);
    }

    public function setLocalizedTitle(string $title, string $locale): void
    {
        $this->setData('title', $title, $locale);
    }

    public function getUrl(): ?string
    {
        return $this->getData('url');
    }

    public function setUrl(string $url): void
    {
        $this->setData('url', $url);
    }

    public function getLocalizedUrlText(?string $locale = null): string
    {
        return $this->getLocalizedData('urlText', $locale);
    }

    /**
     * @param string[] $urlText
     */
    public function setUrlText(array $urlText): void
    {
        $this->setData('urlText', $urlText);
    }

    public function setLocalizedUrlText(string $urlText, string $locale): void
    {
        $this->setData('urlText', $urlText, $locale);
    }
}

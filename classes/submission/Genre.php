<?php

/**
 * @file classes/submission/Genre.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Genre
 *
 * @ingroup submission
 *
 * @see GenreDAO
 *
 * @brief Basic class describing a genre.
 */

namespace PKP\submission;

use PKP\db\DAORegistry;

class Genre extends \PKP\core\DataObject
{
    public const GENRE_CATEGORY_DOCUMENT = 1;
    public const GENRE_CATEGORY_ARTWORK = 2;
    public const GENRE_CATEGORY_SUPPLEMENTARY = 3;

    /**
     * Get ID of context.
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of context.
     *
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get sequence of genre.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of genre.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get key of genre.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->getData('key');
    }

    /**
     * Set key of genre.
     *
     * @param string $key
     */
    public function setKey($key)
    {
        $this->setData('key', $key);
    }

    /**
     * Get enabled status of genre.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set enabled status of genre.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Set the name of the genre
     *
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the name of the genre
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
     * Get the localized name of the genre
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get context file category (e.g. artwork or document)
     *
     * @return int GENRE_CATEGORY_...
     */
    public function getCategory()
    {
        return $this->getData('category');
    }

    /**
     * Set context file category (e.g. artwork or document)
     *
     * @param int $category GENRE_CATEGORY_...
     */
    public function setCategory($category)
    {
        $this->setData('category', $category);
    }

    /**
     * Get dependent flag
     *
     * @return bool
     */
    public function getDependent()
    {
        return $this->getData('dependent');
    }

    /**
     * Set dependent flag
     *
     * @param bool $dependent
     */
    public function setDependent($dependent)
    {
        $this->setData('dependent', $dependent);
    }

    /**
     * Get supplementary flag
     *
     * @return bool
     */
    public function getSupplementary()
    {
        return $this->getData('supplementary');
    }

    /**
     * Set supplementary flag
     *
     * @param bool $supplementary
     */
    public function setSupplementary($supplementary)
    {
        $this->setData('supplementary', $supplementary);
    }

    /**
     * Get whether this file is required for new submissions
     */
    public function getRequired(): bool
    {
        return (bool) $this->getData('required');
    }

    /**
     * Set whether this file is required for new submissions
     */
    public function setRequired(bool $required): void
    {
        $this->setData('required', $required);
    }

    /**
     * Is this a default genre.
     *
     * @return bool
     */
    public function isDefault()
    {
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $defaultKeys = $genreDao->getDefaultKeys();
        return in_array($this->getKey(), $defaultKeys);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\Genre', '\Genre');
    foreach (['GENRE_CATEGORY_DOCUMENT', 'GENRE_CATEGORY_ARTWORK', 'GENRE_CATEGORY_SUPPLEMENTARY'] as $constantName) {
        define($constantName, constant('\Genre::' . $constantName));
    }
}

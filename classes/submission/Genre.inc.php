<?php

/**
 * @file classes/submission/Genre.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Genre
 * @ingroup submission
 *
 * @see GenreDAO
 *
 * @brief Basic class describing a genre.
 */

define('GENRE_CATEGORY_DOCUMENT', 1);
define('GENRE_CATEGORY_ARTWORK', 2);
define('GENRE_CATEGORY_SUPPLEMENTARY', 3);

class Genre extends \PKP\core\DataObject
{
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
     * @param $contextId int
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
     * @param $sequence float
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
     * @param $key string
     */
    public function setKey($key)
    {
        $this->setData('key', $key);
    }

    /**
     * Get enabled status of genre.
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set enabled status of genre.
     *
     * @param $enabled boolean
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Set the name of the genre
     *
     * @param $name string
     * @param $locale string
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the name of the genre
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
     * @param $category int GENRE_CATEGORY_...
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
     * @param $dependent bool
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
     * @param $supplementary bool
     */
    public function setSupplementary($supplementary)
    {
        $this->setData('supplementary', $supplementary);
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

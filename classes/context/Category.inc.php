<?php

/**
 * @file classes/context/Category.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Category
 * @ingroup context
 *
 * @see CategoryDAO
 *
 * @brief Describes basic Category properties.
 */

namespace PKP\context;

class Category extends \PKP\core\DataObject
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
        return $this->setData('contextId', $contextId);
    }

    /**
     * Get ID of parent category.
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->getData('parentId');
    }

    /**
     * Set ID of parent category.
     *
     * @param $parentId int
     */
    public function setParentId($parentId)
    {
        return $this->setData('parentId', $parentId);
    }

    /**
     * Get sequence of category.
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of category.
     *
     * @param $sequence int
     */
    public function setSequence($sequence)
    {
        return $this->setData('sequence', $sequence);
    }

    /**
     * Get category path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getData('path');
    }

    /**
     * Set category path.
     *
     * @param $path string
     */
    public function setPath($path)
    {
        return $this->setData('path', $path);
    }

    /**
     * Get localized title of the category.
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get title of category.
     *
     * @param $locale string
     *
     * @return string
     */
    public function getTitle($locale)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set title of category.
     *
     * @param $title string
     * @param $locale string
     */
    public function setTitle($title, $locale)
    {
        return $this->setData('title', $title, $locale);
    }

    /**
     * Get localized description of the category.
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get description of category.
     *
     * @param $locale string
     *
     * @return string
     */
    public function getDescription($locale)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set description of category.
     *
     * @param $description string
     * @param $locale string
     */
    public function setDescription($description, $locale)
    {
        return $this->setData('description', $description, $locale);
    }

    /**
     * Get the image.
     *
     * @return array
     */
    public function getImage()
    {
        return $this->getData('image');
    }

    /**
     * Set the image.
     *
     * @param $image array
     */
    public function setImage($image)
    {
        return $this->setData('image', $image);
    }

    /**
     * Get the option how the books in this category should be sorted,
     * in the form: concat(sortBy, sortDir).
     *
     * @return string
     */
    public function getSortOption()
    {
        return $this->getData('sortOption');
    }

    /**
     * Set the option how the books in this categpry should be sorted,
     * in the form: concat(sortBy, sortDir).
     *
     * @param $sortOption string
     */
    public function setSortOption($sortOption)
    {
        return $this->setData('sortOption', $sortOption);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\Category', '\Category');
}

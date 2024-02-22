<?php

/**
 * @file classes/category/Category.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Category
 *
 * @brief Describes basic Category properties.
 */

namespace PKP\category;

class Category extends \PKP\core\DataObject
{
    /**
     * Get ID of context.
     */
    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of context.
     */
    public function setContextId(int $contextId)
    {
        return $this->setData('contextId', $contextId);
    }

    /**
     * Get ID of parent category.
     */
    public function getParentId(): ?int
    {
        return $this->getData('parentId');
    }

    /**
     * Set ID of parent category.
     */
    public function setParentId(?int $parentId)
    {
        return $this->setData('parentId', $parentId);
    }

    /**
     * Get sequence of category.
     */
    public function getSequence(): float
    {
        return (float) $this->getData('sequence');
    }

    /**
     * Set sequence of category.
     */
    public function setSequence(float $sequence)
    {
        return $this->setData('sequence', $sequence);
    }

    /**
     * Get category path.
     */
    public function getPath(): string
    {
        return $this->getData('path');
    }

    /**
     * Set category path.
     */
    public function setPath(string $path)
    {
        return $this->setData('path', $path);
    }

    /**
     * Get localized title of the category.
     */
    public function getLocalizedTitle(): string
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get title of category.
     */
    public function getTitle(?string $locale = null)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set title of category.
     */
    public function setTitle($title, ?string $locale)
    {
        return $this->setData('title', $title, $locale);
    }

    /**
     * Get localized description of the category.
     */
    public function getLocalizedDescription(): ?string
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get description of category.
     */
    public function getDescription(?string $locale)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set description of category.
     */
    public function setDescription($description, ?string $locale)
    {
        return $this->setData('description', $description, $locale);
    }

    /**
     * Get the image.
     */
    public function getImage(): ?array
    {
        return $this->getData('image');
    }

    /**
     * Set the image.
     */
    public function setImage(?array $image)
    {
        return $this->setData('image', $image);
    }

    /**
     * Get the option how the books in this category should be sorted,
     * in the form: concat(sortBy, sortDir).
     */
    public function getSortOption(): ?string
    {
        return $this->getData('sortOption');
    }

    /**
     * Set the option how the books in this category should be sorted,
     * in the form: concat(sortBy, sortDir).
     */
    public function setSortOption(?string $sortOption)
    {
        return $this->setData('sortOption', $sortOption);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\category\Category', '\Category');
}

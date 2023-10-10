<?php

/**
 * @defgroup reviewForm Review Form
 * Implements review forms, which are forms that can be created and customized
 * by the manager and presented to the reviewer in order to assess submissions.
 */

/**
 * @file classes/reviewForm/ReviewForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewForm
 *
 * @ingroup reviewForm
 *
 * @see ReviewerFormDAO
 *
 * @brief Basic class describing a review form.
 *
 */

namespace PKP\reviewForm;

class ReviewForm extends \PKP\core\DataObject
{
    /**
     * Get localized title.
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get localized description.
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    //
    // Get/set methods
    //

    /**
     * Get the number of completed reviews for this review form.
     *
     * @return int
     */
    public function getCompleteCount()
    {
        return $this->getData('completeCount');
    }

    /**
     * Set the number of complete reviews for this review form.
     *
     * @param int $completeCount
     */
    public function setCompleteCount($completeCount)
    {
        $this->setData('completeCount', $completeCount);
    }

    /**
     * Get the number of incomplete reviews for this review form.
     *
     * @return int
     */
    public function getIncompleteCount()
    {
        return $this->getData('incompleteCount');
    }

    /**
     * Set the number of incomplete reviews for this review form.
     *
     * @param int $incompleteCount
     */
    public function setIncompleteCount($incompleteCount)
    {
        $this->setData('incompleteCount', $incompleteCount);
    }

    /**
     * Get the associated type.
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set the associated type.
     *
     * @param int $assocType
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get the Id of the associated type.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set the Id of the associated type.
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get sequence of review form.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of review form.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get active flag
     *
     * @return int
     */
    public function getActive()
    {
        return $this->getData('active');
    }

    /**
     * Set active flag
     *
     * @param int $active
     */
    public function setActive($active)
    {
        $this->setData('active', $active);
    }

    /**
     * Get title.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getTitle($locale)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Set title.
     *
     * @param string $title
     * @param string $locale
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get description.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getDescription($locale)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set description.
     *
     * @param string $description
     * @param string $locale
     */
    public function setDescription($description, $locale)
    {
        $this->setData('description', $description, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\reviewForm\ReviewForm', '\ReviewForm');
}

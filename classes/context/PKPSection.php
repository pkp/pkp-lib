<?php

/**
 * @file classes/context/PKPSection.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSection
 * @ingroup context
 *
 * @brief Describes basic section properties.
 */

namespace PKP\context;

class PKPSection extends \PKP\core\DataObject
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
     * @param int $contextId
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get sequence of section.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of section.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get localized title of section.
     *
     * @return string
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get title of section.
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
     * Set title of section.
     *
     * @param string $title
     * @param string $locale
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Return boolean indicating whether or not submissions are restricted to [sub]Editors.
     *
     * @return bool
     */
    public function getEditorRestricted()
    {
        return $this->getData('editorRestricted');
    }

    /**
     * Set whether or not submissions are restricted to [sub]Editors.
     *
     * @param bool $editorRestricted
     */
    public function setEditorRestricted($editorRestricted)
    {
        $this->setData('editorRestricted', $editorRestricted);
    }

    /**
     * Get ID of primary review form.
     *
     * @return int
     */
    public function getReviewFormId()
    {
        return $this->getData('reviewFormId');
    }

    /**
     * Set ID of primary review form.
     *
     * @param int $reviewFormId
     */
    public function setReviewFormId($reviewFormId)
    {
        $this->setData('reviewFormId', $reviewFormId);
    }

    /**
     * Get localized section policy.
     *
     * @return string
     */
    public function getLocalizedPolicy()
    {
        return $this->getLocalizedData('policy');
    }

    /**
     * Get policy.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getPolicy($locale)
    {
        return $this->getData('policy', $locale);
    }

    /**
     * Set policy.
     *
     * @param string $policy
     * @param string $locale
     */
    public function setPolicy($policy, $locale)
    {
        return $this->setData('policy', $policy, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\PKPSection', '\PKPSection');
}

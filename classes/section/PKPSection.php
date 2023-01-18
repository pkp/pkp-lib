<?php

/**
 * @file classes/section/Section.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Section
 * @ingroup section
 *
 * @see DAO
 *
 * @brief Basic class describing a section.
*/

namespace PKP\section;

use PKP\security\Role;

class PKPSection extends \PKP\core\DataObject
{
    /**
     * What user roles are allowed to submit to sections
     * that have restricted submissions to editors
     *
     * @return int[] One or more ROLE_ID_* constants
     */
    public static function getEditorRestrictedRoles(): array
    {
        return [
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR
        ];
    }

    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    public function setContextId(int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    public function getSequence(): float
    {
        return $this->getData('sequence');
    }

    public function setSequence(float $sequence): void
    {
        $this->setData('sequence', $sequence);
    }

    /* Because title is requied, there must be at least one title */
    public function getLocalizedTitle(): string
    {
        return $this->getLocalizedData('title');
    }

    public function getTitle(?string $locale): string|array
    {
        return $this->getData('title', $locale);
    }

    public function setTitle(string|array $title, string $locale = null): void
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Return boolean indicating whether or not submissions are restricted to [sub]Editors.
     */
    public function getEditorRestricted(): bool
    {
        return $this->getData('editorRestricted');
    }

    /**
     * Set whether or not submissions are restricted to [sub]Editors.
     */
    public function setEditorRestricted(bool $editorRestricted): void
    {
        $this->setData('editorRestricted', $editorRestricted);
    }

    /**
     * Return boolean indicating if section should be inactivated.
     */
    public function getIsInactive(): bool
    {
        return $this->getData('isInactive');
    }

    /**
     * Set if section should be inactivated.
     */
    public function setIsInactive(bool $isInactive): void
    {
        $this->setData('isInactive', $isInactive);
    }


    /** Not in OMP, but in OJS and OPS */

    /* Because abbrev is requied, there must be at least one abbrev. */
    public function getLocalizedAbbrev(): string
    {
        return $this->getLocalizedData('abbrev');
    }

    public function getAbbrev(?string $locale): string|array
    {
        return $this->getData('abbrev', $locale);
    }

    public function setAbbrev(string|array $abbrev, string $locale = null): void
    {
        $this->setData('abbrev', $abbrev, $locale);
    }

    public function getLocalizedPolicy(): ?string
    {
        return $this->getLocalizedData('policy');
    }

    public function getPolicy(?string $locale): string|array|null
    {
        return $this->getData('policy', $locale);
    }

    public function setPolicy(string|array $policy, string $locale = null): void
    {
        $this->setData('policy', $policy, $locale);
    }

    /**
     * Get ID of primary review form.
     */
    public function getReviewFormId(): ?int
    {
        return $this->getData('reviewFormId');
    }

    /**
     * Set ID of primary review form.
     */
    public function setReviewFormId(?int $reviewFormId): void
    {
        $this->setData('reviewFormId', $reviewFormId);
    }

    /**
     * Get "will/will not be indexed" setting of section.
     */
    public function getMetaIndexed(): bool
    {
        return $this->getData('metaIndexed');
    }

    /**
     * Set "will/will not be indexed" setting of section.
     */
    public function setMetaIndexed(bool $metaIndexed): void
    {
        $this->setData('metaIndexed', $metaIndexed);
    }

    /**
     * Get peer-reviewed setting of section.
     */
    public function getMetaReviewed(): bool
    {
        return $this->getData('metaReviewed');
    }

    /**
     * Set peer-reviewed setting of section.
     */
    public function setMetaReviewed(bool $metaReviewed): void
    {
        $this->setData('metaReviewed', $metaReviewed);
    }

    /**
     * Get boolean indicating whether abstracts are not required
     */
    public function getAbstractsNotRequired(): bool
    {
        return $this->getData('abstractsNotRequired');
    }

    /**
     * Set boolean indicating whether abstracts are not required
     */
    public function setAbstractsNotRequired(bool $abstractsNotRequired): void
    {
        $this->setData('abstractsNotRequired', $abstractsNotRequired);
    }

    /**
     * Return boolean indicating if title should be hidden in issue ToC.
     */
    public function getHideTitle(): bool
    {
        return $this->getData('hideTitle');
    }

    /**
     * Set if title should be hidden in issue ToC.
     */
    public function setHideTitle(bool $hideTitle): void
    {
        $this->setData('hideTitle', $hideTitle);
    }

    /**
     * Return boolean indicating if author should be hidden in issue ToC.
     */
    public function getHideAuthor(): bool
    {
        return $this->getData('hideAuthor');
    }

    /**
     * Set if author should be hidden in issue ToC.
     */
    public function setHideAuthor(bool $hideAuthor): void
    {
        $this->setData('hideAuthor', $hideAuthor);
    }
    /**
     * Get abstract word count limit.
     */
    public function getAbstractWordCount(): ?int
    {
        return $this->getData('wordCount');
    }

    /**
     * Set abstract word count limit.
     */
    public function setAbstractWordCount(int $wordCount): void
    {
        $this->setData('wordCount', $wordCount);
    }

    /**
     * Get localized string identifying type of items in this section.
     */
    public function getLocalizedIdentifyType(): ?string
    {
        return $this->getLocalizedData('identifyType');
    }

    /**
     * Get string identifying type of items in this section.
     */
    public function getIdentifyType(?string $locale): string|array|null
    {
        return $this->getData('identifyType', $locale);
    }

    /**
     * Set string identifying type of items in this section.
     */
    public function setIdentifyType(string|array $identifyType, string $locale = null): void
    {
        $this->setData('identifyType', $identifyType, $locale);
    }


    /* Not in OJS, but in OMP and OPS */

    /**
     * Get section path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getData('path');
    }

    /**
     * Set section path.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        return $this->setData('path', $path);
    }

    /**
     * Get localized series description.
     */
    public function getLocalizedDescription(): ?string
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get series description.
     */
    public function getDescription(?string $locale): string|array|null
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set series description.
     */
    public function setDescription(string|array $description, string $locale = null): void
    {
        $this->setData('description', $description, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\section\PKPSection', '\PKPSection');
}

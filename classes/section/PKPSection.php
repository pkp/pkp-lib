<?php

/**
 * @file classes/section/Section.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSection
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

    /* Because title is required, there must be at least one title */
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
     * Return boolean indicating if section is not active.
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
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\section\PKPSection', '\PKPSection');
}

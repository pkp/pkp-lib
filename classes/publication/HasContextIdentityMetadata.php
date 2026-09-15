<?php

/**
 * @file classes/publication/HasContextIdentityMetadata.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait HasContextIdentityMetadata
 *
 * @brief Resolver methods for the context identity metadata (name, abbreviation) stamped
 *   onto a publication or issue, falling back to the live context when nothing is stamped yet.
 *
 * Each getter returns the value stamped on the object. Once an identity is stamped, it is final,
 * also for fields stamped empty (e.g. no ISSN at the time). The live context value is only used
 * when no identity has been stamped yet - for example an unpublished publication shown in a
 * preview, or content imported/migrated before its identity was stamped. Output (citations, OAI,
 * DOI deposits, meta tags, etc.) should read identity through these methods rather than from the
 * context directly, so that a later change to the context settings never rewrites already-published
 * metadata. Applications extend this with their own fields (e.g. ISSN in OJS).
 */

namespace PKP\publication;

use PKP\context\Context;

trait HasContextIdentityMetadata
{
    /**
     * Whether a context identity has been stamped. Blank locale rows (e.g. from an import) do
     * not count, or the object would never be stamped.
     */
    public function hasContextIdentity(): bool
    {
        return (bool) array_filter((array) $this->getData('contextName'), fn ($name) => $name !== null && $name !== '');
    }

    /**
     * Get the stamped journal/press/server name in the current locale, falling back to the
     * live context name when no name has been stamped.
     */
    public function getLocalizedContextName(Context $context): string
    {
        return $this->getLocalizedData('contextName') ?: $context->getLocalizedName();
    }

    /**
     * Get the stamped name, preferring the given locale but falling back to any locale it was
     * stamped in (e.g. when the context's primary locale changed since), then to the live context.
     *
     * A context without any name is a broken installation, so a TypeError is preferred over empty output.
     */
    public function getContextName(string $locale, Context $context): string
    {
        return $this->getLocalizedData('contextName', $locale) ?: $context->getLocalizedName($locale);
    }

    /**
     * Get the stamped name in the primary locale that was in effect at publication time,
     * falling back to the current context primary locale for un-stamped content.
     */
    public function getPrimaryContextName(Context $context): string
    {
        $primaryLocale = $this->getData('contextPrimaryLocale') ?: $context->getPrimaryLocale();
        return $this->getContextName($primaryLocale, $context);
    }

    /**
     * Get the stamped abbreviation in the current locale, or the live context abbreviation or
     * acronym if no identity has been stamped.
     */
    public function getLocalizedContextAbbreviation(Context $context): ?string
    {
        if ($this->hasContextIdentity()) {
            return $this->getLocalizedData('contextAbbreviation') ?: null;
        }
        return $context->getLocalizedData('abbreviation') ?: $context->getLocalizedData('acronym') ?: null;
    }

    /**
     * Get the stamped abbreviation, preferring the given locale but falling back to any locale it
     * was stamped in, like getContextName(). The live context abbreviation or acronym is only used
     * if no identity has been stamped.
     */
    public function getContextAbbreviation(string $locale, Context $context): ?string
    {
        if ($this->hasContextIdentity()) {
            return $this->getLocalizedData('contextAbbreviation', $locale) ?: null;
        }
        return $context->getData('abbreviation', $locale) ?: $context->getData('acronym', $locale) ?: null;
    }

    /**
     * Get the stamped abbreviation in the primary locale that was in effect at publication time.
     */
    public function getPrimaryContextAbbreviation(Context $context): ?string
    {
        $primaryLocale = $this->getData('contextPrimaryLocale') ?: $context->getPrimaryLocale();
        return $this->getContextAbbreviation($primaryLocale, $context);
    }
}

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
 * @brief Resolver methods for the context identity metadata (name, publisher) stamped
 *   onto a publication or issue, falling back to the live context when nothing is stamped yet.
 *
 * Each getter returns the value stamped on the object, falling back to the live context value
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
     * No ?? '' guard: getLocalizedName() can theoretically return null on a context with no name
     * set in any locale, but that indicates a broken installation. A TypeError is preferable to
     * silently emitting empty strings in metadata output.
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

}

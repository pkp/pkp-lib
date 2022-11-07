<?php

/**
 * @file classes/mail/traits/Discussion.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Discussion
 * @ingroup mail_traits
 *
 * @brief trait to support Discussion email template variables
 */

namespace PKP\mail\traits;

use PKP\mail\variables\SubmissionEmailVariable;

trait Discussion
{
    use Unsubscribe {
        getRequiredVariables as getTraitRequiredVariables;
    }

    protected function addFooter(string $locale): self
    {
        $this->setupUnsubscribeFooter($locale, $this->context);
        return $this;
    }

    protected function setFooterText(string $locale, string $localeKey = null): string
    {
        if (is_null($localeKey)) {
            $localeKey = 'emails.footer.unsubscribe.discussion';
        }

        return __($localeKey, [], $locale);
    }

    /**
     * Adds email template variable class required to generate submission-related variables
     */
    protected static function getRequiredVariables(): array
    {
        return array_merge(
            static::getTraitRequiredVariables(),
            [SubmissionEmailVariable::class]
        );
    }
}

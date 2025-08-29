<?php

/**
 * @file classes/mail/traits/Discussion.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Discussion
 *
 * @ingroup mail_traits
 *
 * @brief trait to support Discussion email template variables
 */

namespace PKP\mail\traits;

use Illuminate\Mail\Mailables\Headers;
use PKP\mail\variables\SubmissionEmailVariable;

trait Discussion
{
    use Unsubscribe, CapturableReply {
        Unsubscribe::getRequiredVariables as getTraitRequiredVariables;
        Unsubscribe::headers as unsubscribeHeaders;
        CapturableReply::headers as capturableReplyHeaders;
    }

    /**
     * Merge the headers from the competing traits together.
     */
    public function headers(): Headers
    {
        $unsubscribeHeaders = $this->unsubscribeHeaders();
        $capturableReplyHeaders = $this->capturableReplyHeaders();

        // Not sure how to choose message IDs from two possible sources -- make sure we aren't in that situation.
        if ($unsubscribeHeaders->messageId !== null) {
            throw new Exception('Unable to merge message IDs!');
        }

        // Merge the headers and references provided by the two traits
        return new Headers(
            $capturableReplyHeaders->messageId,
            array_merge($unsubscribeHeaders->references, $capturableReplyHeaders->references),
            array_merge($unsubscribeHeaders->text, $capturableReplyHeaders->text)
        );
    }

    protected function addFooter(string $locale): self
    {
        $this->setupUnsubscribeFooter($locale, $this->context);
        $this->setupCapturableReply();
        return $this;
    }

    protected function setFooterText(string $locale, ?string $localeKey = null): string
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

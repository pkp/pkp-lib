<?php
/**
 * @file classes/mailable/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and edit Mailables.
 */

namespace APP\mail;

use APP\mail\mailables\PostedAcknowledgement;
use Illuminate\Support\Collection;
use PKP\context\Context;

class Repository extends \PKP\mail\Repository
{
    protected function isMailableEnabled(string $class, Context $context): bool
    {
        if ($class === PostedAcknowledgement::class) {
            return (bool) $context->getData('postedAcknowledgement');
        }
        return parent::isMailableEnabled($class, $context);
    }

    /**
     * Overrides the map from the shared library as OPS uses distinct mailables from OJS and OMP
     */
    public function map(): Collection
    {
        return collect([
            \PKP\mail\mailables\AnnouncementNotify::class,
            \PKP\mail\mailables\DecisionAcceptNotifyAuthor::class,
            \PKP\mail\mailables\DecisionInitialDeclineNotifyAuthor::class,
            \PKP\mail\mailables\DecisionNotifyOtherAuthors::class,
            \PKP\mail\mailables\DecisionRevertInitialDeclineNotifyAuthor::class,
            \PKP\mail\mailables\DiscussionProduction::class,
            \PKP\mail\mailables\PasswordResetRequested::class,
            \PKP\mail\mailables\StatisticsReportNotify::class,
            \PKP\mail\mailables\SubmissionAcknowledgement::class,
            \PKP\mail\mailables\SubmissionAcknowledgementNotAuthor::class,
            \PKP\mail\mailables\UserCreated::class,
            \PKP\mail\mailables\ValidateEmailContext::class,
            \PKP\mail\mailables\ValidateEmailSite::class,
            \PKP\mail\mailables\PublicationVersionNotify::class,
            mailables\PostedAcknowledgement::class,
            mailables\PostedNewVersionAcknowledgement::class,
            mailables\SubmissionAcknowledgementCanPost::class,
        ]);
    }
}

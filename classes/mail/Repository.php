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

namespace PKP\mail;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PKP\context\Context;
use PKP\mail\mailables\DecisionNotifyOtherAuthors;
use PKP\mail\mailables\EditReviewNotify;
use PKP\mail\mailables\ReviewCompleteNotifyEditors;
use PKP\mail\mailables\StatisticsReportNotify;
use PKP\mail\mailables\SubmissionAcknowledgement;
use PKP\mail\mailables\SubmissionAcknowledgementNotAuthor;
use PKP\mail\mailables\SubmissionNeedsEditor;
use PKP\mail\mailables\SubmissionSavedForLater;
use PKP\mail\traits\Configurable;
use PKP\plugins\Hook;

class Repository
{
    /**
     * Get a list of mailables
     *
     * @param string $searchPhrase Only include mailables with a name or description matching this search phrase
     * @param ?bool $includeDisabled Whether or not to include mailables not used in this context, based on the context settings
     *
     * @return Collection<int,string> The fully-qualified class name of each mailable
     *
     * @hook Mailer::Mailables [[$mailables, $context]]
     */
    public function getMany(
        Context $context,
        ?string $searchPhrase = null,
        ?bool $includeDisabled = false,
        ?bool $includeConfigurableOnly = false
    ): Collection {
        $mailables = $this->map();
        Hook::call('Mailer::Mailables', [$mailables, $context]);

        return $mailables
            ->filter(fn (string $class) => !$searchPhrase || $this->containsSearchPhrase($class, $searchPhrase))
            ->filter(function (string $class) use ($context, $includeDisabled) {
                return $includeDisabled || $this->isMailableEnabled($class, $context);
            })
            ->filter(function (string $class) use ($context, $includeConfigurableOnly) {
                return !$includeConfigurableOnly || $this->isMailableConfigurable($class, $context);
            });
    }

    /**
     * Simple check if mailable's name and description contains a search phrase
     * doesn't look up in associated email templates
     *
     * @param string $className The fully-qualified class name of the Mailable
     */
    protected function containsSearchPhrase(string $className, string $searchPhrase): bool
    {
        $searchPhrase = Str::lower($searchPhrase);

        /** @var Mailable $className */
        return str_contains(Str::lower($className::getName()), $searchPhrase) ||
            str_contains(Str::lower($className::getDescription()), $searchPhrase);
    }

    /**
     * Get a mailable by its email template key
     *
     * @return ?string The fully-qualified class name of the mailable
     */
    public function get(string $emailTemplateKey, Context $context): ?string
    {
        /** @var ?string $mailable */
        $mailable = $this->getMany($context)
            ->first(fn (string $mailable) => $mailable::getEmailTemplateKey() === $emailTemplateKey);

        if (!$mailable) {
            return null;
        }

        return $mailable;
    }

    /**
     * Get a summary of a mailable's properties
     */
    public function summarizeMailable(string $class): array
    {
        $dataDescriptions = $class::getDataDescriptions();
        ksort($dataDescriptions);

        return [
            '_href' => Application::get()->getRequest()->getDispatcher()->url(
                Application::get()->getRequest(),
                Application::ROUTE_API,
                Application::get()->getRequest()->getContext()->getPath(),
                'mailables/' . $class::getEmailTemplateKey(),
            ),
            'dataDescriptions' => $dataDescriptions,
            'description' => $class::getDescription(),
            'emailTemplateKey' => $class::getEmailTemplateKey(),
            'fromRoleIds' => $class::getFromRoleIds(),
            'groupIds' => $class::getGroupIds(),
            'name' => $class::getName(),
            'supportsTemplates' => $class::getSupportsTemplates(),
            'toRoleIds' => $class::getToRoleIds(),
        ];
    }

    /**
     * Get a full description of a mailable's properties, including any
     * assigned email templates
     */
    public function describeMailable(string $class, int $contextId): array
    {
        $data = $this->summarizeMailable($class);

        if (!$class::getSupportsTemplates()) {
            $data['emailTemplates'] = [];
        } else {
            $templates = Repo::emailTemplate()
                ->getCollector($contextId)
                ->alternateTo([$class::getEmailTemplateKey()])
                ->getMany();

            $defaultTemplate = Repo::emailTemplate()->getByKey($contextId, $class::getEmailTemplateKey());

            $data['emailTemplates'] = Repo::emailTemplate()
                ->getSchemaMap()
                ->summarizeMany(
                    collect(
                        array_merge(
                            [$defaultTemplate],
                            $templates->values()->toArray()
                        )
                    )
                )
                ->values();
        }


        return $data;
    }

    /**
     * Check if a mailable is enabled on this context
     */
    protected function isMailableEnabled(string $class, Context $context): bool
    {
        if ($class === StatisticsReportNotify::class) {
            return (bool) $context->getData('editorialStatsEmail');
        } elseif (in_array($class, [SubmissionAcknowledgement::class, SubmissionAcknowledgementNotAuthor::class])) {
            $setting = $context->getData('submissionAcknowledgement');
            if ($setting === Context::SUBMISSION_ACKNOWLEDGEMENT_ALL_AUTHORS) {
                return true;
            } elseif ($setting === Context::SUBMISSION_ACKNOWLEDGEMENT_OFF) {
                return false;
            } elseif ($class === SubmissionAcknowledgementNotAuthor::class) {
                return false;
            }
            return true;
        } elseif ($class === DecisionNotifyOtherAuthors::class) {
            return $context->getData('notifyAllAuthors');
        }
        return true;
    }

    /**
     * Check if mailable can be configured
     */
    protected function isMailableConfigurable(string $class, Context $context): bool
    {
        if (!in_array(Configurable::class, class_uses_recursive($class))) {
            return false;
        }

        /**
         * Mailables may not have associated email templates due to pkp/pkp-lib#9109 and pkp/pkp-lib#9217,
         * don't allow to configure them
         * FIXME remove after #9202 is resolved
         */
        if (in_array($class, [
            EditReviewNotify::class,
            ReviewCompleteNotifyEditors::class,
            SubmissionSavedForLater::class,
            SubmissionNeedsEditor::class,
        ])) {
            $template = Repo::emailTemplate()->getByKey($context->getId(), $class::getEmailTemplateKey());
            if (!$template) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the mailables used in this app
     */
    public function map(): Collection
    {
        return collect([
            mailables\AnnouncementNotify::class,
            mailables\DecisionAcceptNotifyAuthor::class,
            mailables\DecisionBackFromCopyeditingNotifyAuthor::class,
            mailables\DecisionBackFromProductionNotifyAuthor::class,
            mailables\DecisionCancelReviewRoundNotifyAuthor::class,
            mailables\DecisionDeclineNotifyAuthor::class,
            mailables\DecisionInitialDeclineNotifyAuthor::class,
            mailables\DecisionNewReviewRoundNotifyAuthor::class,
            mailables\DecisionNotifyOtherAuthors::class,
            mailables\DecisionNotifyReviewer::class,
            mailables\DecisionRequestRevisionsNotifyAuthor::class,
            mailables\DecisionResubmitNotifyAuthor::class,
            mailables\DecisionRevertDeclineNotifyAuthor::class,
            mailables\DecisionRevertInitialDeclineNotifyAuthor::class,
            mailables\DecisionSendExternalReviewNotifyAuthor::class,
            mailables\DecisionSendToProductionNotifyAuthor::class,
            mailables\DecisionSkipExternalReviewNotifyAuthor::class,
            mailables\DiscussionCopyediting::class,
            mailables\DiscussionProduction::class,
            mailables\DiscussionReview::class,
            mailables\DiscussionSubmission::class,
            mailables\EditorAssigned::class,
            mailables\EditReviewNotify::class,
            mailables\EditorialReminder::class,
            mailables\OrcidRequestAuthorAuthorization::class,
            mailables\OrcidCollectAuthorId::class,
            mailables\PasswordResetRequested::class,
            mailables\PublicationVersionNotify::class,
            mailables\RecommendationNotifyEditors::class,
            mailables\ReviewAcknowledgement::class,
            mailables\ReviewCompleteNotifyEditors::class,
            mailables\ReviewConfirm::class,
            mailables\ReviewDecline::class,
            mailables\ReviewRemind::class,
            mailables\ReviewRemindAuto::class,
            mailables\ReviewRequest::class,
            mailables\ReviewRequestSubsequent::class,
            mailables\ReviewResponseRemindAuto::class,
            mailables\ReviewerRegister::class,
            mailables\ReviewerReinstate::class,
            mailables\ReviewerResendRequest::class,
            mailables\ReviewerUnassign::class,
            mailables\RevisedVersionNotify::class,
            mailables\StatisticsReportNotify::class,
            mailables\SubmissionAcknowledgement::class,
            mailables\SubmissionAcknowledgementNotAuthor::class,
            mailables\UserCreated::class,
            mailables\ValidateEmailContext::class,
            mailables\ValidateEmailSite::class,
        ]);
    }
}

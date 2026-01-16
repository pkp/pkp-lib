<?php

/**
 * @file components/RequestReviewResponsePage.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequestReviewResponsePage
 *
 * @ingroup classes_components
 *
 * @brief A class to prepare the data object for the Request Review Round Author Response page
 */

namespace PKP\components;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use PKP\components\fileAttachers\FileStage;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\ReviewFiles;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\mail\mailables\RequestReviewRoundAuthorResponse;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\authorResponse\AuthorResponseManager;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;

class RequestReviewResponsePage
{
    private ReviewRound $reviewRound;
    private Submission $submission;
    private int $stageId;
    private Context $context;
    private array $locales;

    public function __construct(ReviewRound $reviewRound, Submission $submission, string $stageId, Context $context, array $locales)
    {
        $this->reviewRound = $reviewRound;
        $this->submission = $submission;
        $this->stageId = $stageId;
        $this->locales = $locales;
        $this->context = $context;
    }

    /*
     * Get the file attachers that can be used in the email composer.
     */
    protected function getFileAttachers(Submission $submission, Context $context): array
    {
        $attachers = [
            new Upload(
                $context,
                __('common.upload.addFile'),
                __('common.upload.addFile.description'),
                __('common.upload.addFile')
            ),
        ];

        $attachers[] = (new FileStage(
            $context,
            $submission,
            __('submission.submit.submissionFiles'),
            __('email.addAttachment.submissionFiles.submissionDescription'),
            __('email.addAttachment.submissionFiles.attach')
        ))
            ->withFileStage(
                SubmissionFile::SUBMISSION_FILE_SUBMISSION,
                __('submission.submit.submissionFiles')
            );

        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$this->reviewRound->getId()])
            ->getMany()
            ->keyBy(fn (ReviewAssignment $reviewAssignment, int $key) => $reviewAssignment->getId())
            ->sortKeys()
            ->all();

        $reviewerFiles = [];
        if (!empty($reviewAssignments)) {
            $reviewerFiles = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, array_keys($reviewAssignments))
                ->getMany();
        }

        $attachers[] = new ReviewFiles(
            __('reviewer.submission.reviewFiles'),
            __('email.addAttachment.reviewFiles.description'),
            __('email.addAttachment.reviewFiles.attach'),
            $reviewerFiles,
            $reviewAssignments,
            $context
        );

        $attachers[] = new Library($context, $submission);

        return array_map(fn ($attacher) => $attacher->getState(), $attachers);
    }
    /*
     * Get page config.
     */
    public function getConfig(): array
    {
        $request = Application::get()->getRequest();
        $reviewAuthorResponseManager = new AuthorResponseManager(
            reviewRound: $this->reviewRound,
            submission: $this->submission,
            context: $this->context,
            request: $request
        );

        $mailable = $reviewAuthorResponseManager->getMailable();
        $assignedAuthors = $this->getAssignedAuthors();

        $mailable->recipients($assignedAuthors->all())->sender($request->getUser());
        return [
            'recipients' => $assignedAuthors->keys()->all(),
            'recipientOptions' => $this->recipientOptions($assignedAuthors),
            'reviewRoundId' => $this->reviewRound->getId(),
            'stageId' => $this->stageId,
            'emailTemplatesApiUrl' => $this->getEmailTemplatesApiUrl(),
            'canChangeRecipients' => false,
            'initialTemplateKey' => RequestReviewRoundAuthorResponse::getEmailTemplateKey(),
            'locale' => $this->context->getPrimaryLocale(),
            'locales' => $this->getLocales(),
            'emailTemplates' => $this->getEmailTemplates($mailable),
            'variables' => $this->getTemplateVariables($mailable),
            'attachers' => $this->getFileAttachers($this->submission, $this->context),
            'submissionUrl' => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_PAGE,
                $this->context->getData('urlPath'),
                'dashboard',
                'editorial',
                null,
                ['workflowSubmissionId' => $this->submission->getId()]
            ),
            'submissionTitle' => $this->submission->getCurrentPublication()->getLocalizedFullTitle(),
            'submissionId' => $this->submission->getId(),
        ];
    }

    /**
     * Get the email templates to use.
     */
    public function getEmailTemplates(Mailable $mailable): array
    {
        $emailTemplates = collect();
        $request = Application::get()->getRequest();

        if ($mailable::getEmailTemplateKey()) {
            $emailTemplate = Repo::emailTemplate()->getByKey($this->context->getId(), $mailable::getEmailTemplateKey());
            if ($emailTemplate && Repo::emailTemplate()->isTemplateAccessibleToUser($request->getUser(), $emailTemplate, $this->context->getId())) {
                $emailTemplates->add($emailTemplate);
            }

            Repo::emailTemplate()
                ->getCollector($this->context->getId())
                ->alternateTo([$mailable::getEmailTemplateKey()])
                ->getMany()
                ->each(function (EmailTemplate $template) use ($request, $emailTemplates) {
                    if (Repo::emailTemplate()->isTemplateAccessibleToUser($request->getUser(), $template, $this->context->getId())) {
                        $emailTemplates->add($template);
                    }
                });
        }

        return Repo::emailTemplate()->getSchemaMap()->mapMany($emailTemplates)->all();
    }
    /**
     * Get locales formatted for use in Email Composer.
     */
    private function getLocales(): array
    {
        return array_map(
            fn ($locale) => [
                'locale' => $locale,
                'name' => Locale::getMetadata($locale)->getDisplayName(),
            ],
            $this->locales,
        );
    }


    /**
     * Get template variables for the given mailable, formatted for use in Email Composer
     *
     * @param Mailable $mailable - The mailable to extract template variables from
     */
    private function getTemplateVariables(Mailable $mailable): array
    {
        $variables = [];
        foreach ($this->locales as $locale) {
            $variables[$locale] = $this->getFormattedVariables($mailable, $locale);
        }
        return $variables;
    }

    /**
     * Get variables formatted for use in email composer
     */
    private function getFormattedVariables($mailable, string $locale): array
    {
        $data = $mailable->getData($locale);
        $descriptions = $mailable::getDataDescriptions();

        $variables = [];
        foreach ($data as $key => $value) {
            $variables[] = [
                'key' => $key,
                'value' => $value,
                'description' => $descriptions[$key] ?? '',
            ];
        }

        return $variables;
    }

    /**
     * Get Authors assigned to the submission the review round is associated with.
     */
    private function getAssignedAuthors(): Enumerable
    {
        $userIds = StageAssignment::withSubmissionIds([$this->submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$this->stageId])
            ->get()
            ->pluck('user_id')
            ->all();

        $authors = collect();
        foreach (array_unique($userIds) as $authorUserId) {
            $authors->put($authorUserId, Repo::user()->get($authorUserId));
        }

        return $authors;
    }

    /**
     * @param array $recipients - Array of users to format recipient options from.
     */
    private function recipientOptions(Enumerable $recipients): array
    {
        return $recipients->map(function ($user) {
            $names = [];
            foreach ($this->locales as $locale) {
                $names[$locale] = $user->getFullName(true, false, $locale);
            }

            return [
                'value' => $user->getId(),
                'label' => $names,
            ];
        })->values()->all();
    }

    /**
     * Get the URL for the email templates API endpoint
     */
    private function getEmailTemplatesApiUrl(): string
    {
        $request = Application::get()->getRequest();

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $this->context->getData('urlPath'),
            'emailTemplates'
        );
    }

    /**
     * Get breadcrumbs to be displayed on the page.
     */
    public function getBreadcrumb(Submission $submission, Context $context, Request $request): array
    {
        $currentPublication = $submission->getCurrentPublication();
        $dispatcher = $request->getDispatcher();
        $submissionTitle = Str::of(
            join(
                __('common.commaListSeparator'),
                [
                    $currentPublication->getShortAuthorString(),
                    $currentPublication->getLocalizedFullTitle(null, 'html'),
                ]
            )
        );

        $submissionTitle = $submissionTitle->limit(50, '...');

        return [
            [
                'id' => 'dashboard',
                'name' => __('navigation.dashboard'),
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'dashboard'
                ),
            ],
            [
                'id' => 'submission',
                'name' => (string) $submissionTitle,
                'format' => 'html',
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $context->getData('urlPath'),
                    'dashboard',
                    'editorial',
                    null,
                    ['workflowSubmissionId' => $submission->getId()]
                ),
            ],
            [
                'id' => 'requestReviewRoundAuthorResponse',
                'name' => __('editor.submission.reviewRound.requestAuthorResponse'),
            ]
        ];
    }
}

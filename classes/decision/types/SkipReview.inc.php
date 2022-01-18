<?php
/**
 * @file classes/decision/types/SkipReview.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to accept a submission, skip the review stage and send it to the copyediting stage.
 */

namespace PKP\decision\types;

use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\steps\Email;
use PKP\decision\steps\PromoteFiles;
use PKP\decision\Type;
use PKP\decision\types\traits\InSubmissionStage;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\RequestPayment;
use PKP\decision\Workflow;
use PKP\mail\mailables\DecisionSkipReviewNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class SkipReview extends Type
{
    use InSubmissionStage;
    use RequestPayment;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::SKIP_REVIEW;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_SUBMISSION;
    }

    public function getNewStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.skipReview', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.skipReview.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.skipReview.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.skipReview.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.skipReview.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        foreach ($props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case self::ACTION_PAYMENT:
                    $this->validatePaymentAction($action, $actionErrorKey, $validator, $context);
                    break;
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
            }
        }
    }

    public function callback(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::callback($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case self::ACTION_PAYMENT:
                    $this->requestPayment($submission, $editor, $context);
                    break;
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->sendAuthorEmail(
                        new DecisionSkipReviewNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }
    }

    public function getWorkflow(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Workflow
    {
        $workflow = new Workflow($this, $submission, $context);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        // Request payment if configured
        $paymentManager = Application::getPaymentManager($context);
        if ($paymentManager->publicationEnabled()) {
            $workflow->addStep($this->getPaymentForm($context));
        }

        $authors = $workflow->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionSkipReviewNotifyAuthor($context, $submission, $fakeDecision);
            $workflow->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.skipReview.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        $workflow->addStep((new PromoteFiles(
            'promoteFilesToReview',
            __('editor.submission.selectFiles'),
            __('editor.submission.decision.promoteFiles.copyediting'),
            SubmissionFile::SUBMISSION_FILE_FINAL,
            $submission,
            $this->getFileGenres($context->getId())
        ))->addFileList(
            __('submission.submit.submissionFiles'),
            Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
        ));

        return $workflow;
    }
}

<?php
/**
 * @file classes/decision/types/NewExternalReviewRound.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NewExternalReviewRound
 *
 * @brief A decision to open a new round of review in the external review stage
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\decision\Steps;
use PKP\decision\steps\Email;
use PKP\decision\steps\PromoteFiles;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\mailables\DecisionNewReviewRoundNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class NewExternalReviewRound extends DecisionType
{
    use InExternalReviewRound;
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::NEW_EXTERNAL_ROUND;
    }

    public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.newReviewRound', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.newReviewRound.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.newReviewRound.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.newReviewRound.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.newReviewRound.completedDescription', ['title' => $submission?->getCurrentPublication()?->getLocalizedFullTitle(null, 'html') ?? '']);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        if (!isset($props['actions'])) {
            return;
        }

        foreach ((array) $props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
            }
        }
    }

    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        /** @var ReviewRound $reviewRound */
        $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $this->getNewStageId($submission, $decision->getData('reviewRoundId')));
        $this->createReviewRound($submission, $this->getStageId(), $reviewRound->getRound() + 1);

        parent::runAdditionalActions($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->sendAuthorEmail(
                        new DecisionNewReviewRoundNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }
    }

    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Steps
    {
        $steps = new Steps($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor, $reviewRound);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);

        $authors = $steps->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionNewReviewRoundNotifyAuthor($context, $submission, $fakeDecision);
            $steps->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.newReviewRound.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        $steps->addStep((new PromoteFiles(
            'promoteFilesToReviewRound',
            __('editor.submission.selectFiles'),
            __('editor.submission.decision.promoteFiles.review'),
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            $submission,
            $this->getFileGenres($context->getId())
        ))->addFileList(
            __('editor.submission.revisions'),
            Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
                ->filterByReviewRoundIds([$reviewRound->getId()])
        ));

        return $steps;
    }
}

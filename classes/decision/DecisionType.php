<?php
/**
 * @file classes/decision/DecisionType.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief An interface to define an editorial decision type.
 */

namespace PKP\decision;

use APP\core\Application;
use APP\core\Request;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use PKP\validation\ValidatorFactory;

abstract class DecisionType
{
    public const ACTION_NOTIFY_AUTHORS = 'notifyAuthors';
    public const ACTION_NOTIFY_REVIEWERS = 'notifyReviewers';
    public const ACTION_PAYMENT = 'payment';
    public const ACTION_DISCUSSION = 'discussion';

    public const REVIEW_ASSIGNMENT_COMPLETED = 1;
    public const REVIEW_ASSIGNMENT_ACTIVE = 2;
    public const REVIEW_ASSIGNMENT_CONFIRMED = 3;

    /**
     * Get a label/title when this decision has been completed
     *
     * eg - Submission Accepted
     */
    abstract public function getCompletedLabel(): string;

    /**
     * Get a message for the user to confirm that this decision has been completed
     *
     * eg - The submission, {$title}, was accepted and sent to the copyediting stage.
     */
    abstract public function getCompletedMessage(Submission $submission): string;

    /**
     * Get the decision type identifier
     *
     * One of the SUBMISSION_EDITOR_DECISION_ constants
     */
    abstract public function getDecision(): int;

    /**
     * Get a localized description of this decision
     */
    abstract public function getDescription(?string $locale = null): string;

    /**
     * Get a localized label for this decision, such as Accept Submission
     */
    abstract public function getLabel(?string $locale = null): string;

    /**
     * Get the locale key to use for the log entry when this decision is taken
     */
    abstract public function getLog(): string;

    /**
     * Get the status that should be assigned to the last review round when this
     * decision is taken.
     *
     * This will be used by decisions taken in a review stage. If the value is
     * null the review round status will be recalculated after the decision is
     * recorded.
     */
    abstract public function getNewReviewRoundStatus(): ?int;

    /**
     * Get the status that should be assigned to the submission when this decision is taken.
     *
     * Null if the status should not be changed.
     */
    abstract public function getNewStatus(): ?int;

    /**
     * Get the workflow stage a submission should be promoted to, if the decision should
     * result in moving the submission to another stage
     */
    abstract public function getNewStageId(Submission $submission, ?int $reviewRoundId): ?int;

    /**
     * The decision can only be taken when the submission is in this workflow stage
     */
    abstract public function getStageId(): int;

    /**
     * Get a url to record this decision for a submission
     *
     * @throws Exception If the editorial decision is in the review stage but no review round id has been passed
     */
    public function getUrl(Request $request, Context $context, Submission $submission, int $reviewRoundId = null): string
    {
        $args = [
            'decision' => $this->getDecision(),
        ];
        if ($this->isInReview()) {
            if (!$reviewRoundId) {
                throw new Exception('Can not get URL to the ' . get_class($this) . ' decision without a review round id.');
            }
            $args['reviewRoundId'] = $reviewRoundId;
        }
        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getPath(),
            'decision',
            'record',
            $submission->getId(),
            $args
        );
    }

    /**
     * Is this decision in a review workflow stage?
     */
    public function isInReview(): bool
    {
        return in_array(
            $this->getStageId(),
            [
                WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
                WORKFLOW_STAGE_ID_EXTERNAL_REVIEW
            ]
        );
    }

    /**
     * Validate this decision
     *
     * The default decision properties will already be validated. Use
     * this method to validate data for this decision's actions, or
     * to apply any additional restrictions for this decision.
     */
    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // No validation checks are performed by default
    }

    /**
     * Run actions required to process a new editorial decision
     *
     * This callback method is fired whenever a new decision is
     * recorded. This method changes a submission's status and
     * stage id, as well as the review round status.
     *
     * Add this method to child decision types to perform additional
     * actions when the decision is recorded, such as sending emails,
     * creating a new review round, etc.
     *
     * Each decision type can support its own $actions. See the
     * decision type class to understand which $actions are
     * supported by that type.
     *
     * Typically, $actions represent a form that was completed or an
     * email that was composed while recording the decision.
     *
     * However, custom decisions are not constrained to these types
     * and may use the $actions array to configure any steps
     * necessary to record the decision.
     *
     * The $actions array is a parameter in the REST API so that any
     * actions may be sent along with the request to record a decision.
     *
     * @see Repository::add()
     *
     * @param array $actions Actions handled by the decision type
     */
    public function runAdditionalActions(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        if ($this->getNewStatus()) {
            Repo::submission()->updateStatus($submission, $this->getNewStatus());
        }

        $newStageId = $this->getNewStageId($submission, (int)$decision->getData('reviewRoundId'));

        if ($newStageId) {
            $submission->setData('stageId', $newStageId);
            Repo::submission()->dao->update($submission);

            // Create a new review round if there is not an existing round
            // when promoting to a review stage, or reset the review round
            // status if one already exists
            if (in_array($newStageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
                /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $newStageId);
                if (!is_a($reviewRound, ReviewRound::class)) {
                    $this->createReviewRound($submission, $newStageId, 1);
                } else {
                    $reviewRoundDao->updateStatus($reviewRound, null);
                }
            }
        }

        // Change review round status when a decision is taken in a review stage
        if ($reviewRoundId = $decision->getData('reviewRoundId')) {
            /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
            $reviewRound = $reviewRoundDao->getById($reviewRoundId);
            if (is_a($reviewRound, ReviewRound::class)) {
                // If the decision type doesn't specify a review round status, recalculate
                // it from scratch. In order to do this, we unset the ReviewRound's status
                // so the DAO will determine the new status
                if (is_null($this->getNewReviewRoundStatus())) {
                    $reviewRound->setData('status', null);
                }
                $reviewRoundDao->updateStatus($reviewRound, $this->getNewReviewRoundStatus());
            }
        }
    }

    /**
     * Get the workflow steps for this decision type
     *
     * Returns null if this decision type does not use a workflow.
     * In such cases the decision can be recorded but does not make
     * use of the built-in UI for making the decision
     */
    public function getSteps(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): ?Steps
    {
        return null;
    }

    /**
     * Get the assigned authors
     */
    protected function getAssignedAuthorIds(Submission $submission): array
    {
        $userIds = [];
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $result = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR, $this->getStageId());
        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $result->next()) {
            $userIds[] = (int) $stageAssignment->getUserId();
        }
        return $userIds;
    }

    /**
     * Validate the properties of an email action
     *
     * @return array Empty if no errors
     */
    protected function validateEmailAction(array $emailAction, Submission $submission, array $allowedAttachmentFileStages = []): array
    {
        $schema = (object) [
            'attachments' => (object) [
                'type' => 'array',
                'items' => (object) [
                    'type' => 'object',
                ],
            ],
            'bcc' => (object) [
                'type' => 'array',
                'items' => (object) [
                    'type' => 'string',
                    'validation' => [
                        'email_or_localhost',
                    ],
                ],
            ],
            'body' => (object) [
                'type' => 'string',
                'validation' => [
                    'required',
                ],
            ],
            'cc' => (object) [
                'type' => 'array',
                'items' => (object) [
                    'type' => 'string',
                    'validation' => [
                        'email_or_localhost',
                    ],
                ],
            ],
            'id' => (object) [
                'type' => 'string',
                'validation' => [
                    'alpha',
                    'required',
                ],
            ],
            'subject' => (object) [
                'type' => 'string',
                'validation' => [
                    'required',
                ],
            ],
            'recipients' => (object) [
                'type' => 'array',
                'items' => (object) [
                    'type' => 'integer',
                ],
            ],
        ];

        $schemaService = App::make(PKPSchemaService::class);
        $rules = [];
        foreach ($schema as $propName => $propSchema) {
            $rules = $schemaService->addPropValidationRules($rules, $propName, $propSchema);
        }

        $validator = ValidatorFactory::make(
            $emailAction,
            $rules,
        );

        if (isset($emailAction['attachments'])) {
            $validator->after(function ($validator) use ($emailAction, $submission, $allowedAttachmentFileStages) {
                if ($validator->errors()->get('attachments')) {
                    return;
                }
                foreach ($emailAction['attachments'] as $attachment) {
                    $errorMessage = __('email.attachmentNotFound', ['fileName' => $attachment['name'] ?? '']);
                    if (isset($attachment['temporaryFileId'])) {
                        $uploaderId = Application::get()->getRequest()->getUser()->getId();
                        if (!$this->validateTemporaryFileAttachment($attachment['temporaryFileId'], $uploaderId)) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } elseif (isset($attachment['submissionFileId'])) {
                        if (!$this->validateSubmissionFileAttachment((int) $attachment['submissionFileId'], $submission, $allowedAttachmentFileStages)) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } elseif (isset($attachment['libraryFileId'])) {
                        if (!$this->validateLibraryAttachment($attachment['libraryFileId'], $submission)) {
                            $validator->errors()->add('attachments', $errorMessage);
                        }
                    } else {
                        $validator->errors()->add('attachments', $errorMessage);
                    }
                }
            });
        }

        $errors = [];

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }

        return $errors;
    }

    /**
     * Validate a file attachment that has been uploaded by the user
     */
    protected function validateTemporaryFileAttachment(string $temporaryFileId, int $uploaderId): bool
    {
        $temporaryFileManager = new TemporaryFileManager();
        return (bool) $temporaryFileManager->getFile($temporaryFileId, $uploaderId);
    }

    /**
     * Validate a file attachment from a submission file
     *
     * @param array<int> $allowedFileStages SubmissionFile::SUBMISSION_FILE_*
     */
    protected function validateSubmissionFileAttachment(int $submissionFileId, Submission $submission, array $allowedFileStages): bool
    {
        $submissionFile = Repo::submissionFile()->get($submissionFileId);
        return $submissionFile
            && $submissionFile->getData('submissionId') === $submission->getId()
            && in_array($submissionFile->getData('fileStage'), $allowedFileStages);
    }

    /**
     * Validate a file attachment from a library file
     */
    protected function validateLibraryAttachment(int $libraryFileId, Submission $submission): bool
    {
        /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
        $file = $libraryFileDao->getById($libraryFileId, $submission->getData('contextId'));

        if (!$file) {
            return false;
        }

        return !$file->getSubmissionId() || $file->getSubmissionId() === $submission->getId();
    }

    /**
     * Set an error message for invalid recipients
     *
     * @param array<int> $invalidRecipientIds
     */
    protected function setRecipientError(string $actionErrorKey, array $invalidRecipientIds, Validator $validator)
    {
        $names = array_map(function ($userId) {
            $user = Repo::user()->get((int) $userId);
            return $user ? $user->getFullName() : $userId;
        }, $invalidRecipientIds);
        $validator->errors()->add(
            $actionErrorKey . '.to',
            __(
                'editor.submission.workflowDecision.invalidRecipients',
                ['names' => join(__('common.commaListSeparator'), $names)]
            )
        );
    }

    /**
     * Create a fake decision object as if a decision of this
     * type was recorded
     *
     * This decision object can be passed to a Mailable in order to
     * prepare data for email templates. The decision is not saved
     * to the database and has no `id` property.
     */
    protected function getFakeDecision(Submission $submission, User $editor, ?ReviewRound $reviewRound = null): Decision
    {
        return Repo::decision()->newDataObject([
            'dateDecided' => Core::getCurrentDate(),
            'decision' => $this->getDecision(),
            'editorId' => $editor->getId(),
            'reviewRoundId' => $reviewRound ? $reviewRound->getId() : null,
            'round' => $reviewRound ? $reviewRound->getRound() : null,
            'stageId' => $this->getStageId(),
            'submissionId' => $submission->getId(),
        ]);
    }

    /**
     * Convert a decision action to EmailData
     */
    protected function getEmailDataFromAction(array $action): EmailData
    {
        return new EmailData($action);
    }

    /**
     * Get a Mailable from a decision's action data
     *
     * Sets the sender, subject, body and attachments.
     *
     * Does NOT set the recipients.
     */
    protected function addEmailDataToMailable(Mailable $mailable, User $sender, EmailData $email): Mailable
    {
        $mailable
            ->sender($sender)
            ->bcc($email->bcc)
            ->cc($email->cc)
            ->subject($email->subject)
            ->body($email->body);

        if (!empty($email->attachments)) {
            foreach ($email->attachments as $attachment) {
                if (isset($attachment[Mailable::ATTACHMENT_TEMPORARY_FILE])) {
                    $mailable->attachTemporaryFile(
                        $attachment[Mailable::ATTACHMENT_TEMPORARY_FILE],
                        $attachment['name'],
                        $sender->getId()
                    );
                } elseif (isset($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE])) {
                    $mailable->attachSubmissionFile(
                        $attachment[Mailable::ATTACHMENT_SUBMISSION_FILE],
                        $attachment['name']
                    );
                } elseif (isset($attachment[Mailable::ATTACHMENT_LIBRARY_FILE])) {
                    $mailable->attachLibraryFile(
                        $attachment[Mailable::ATTACHMENT_LIBRARY_FILE],
                        $attachment['name']
                    );
                }
            }
        }

        return $mailable;
    }

    /**
     * Create a review round in a review stage
     */
    protected function createReviewRound(Submission $submission, int $stageId, ?int $round = 1)
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');

        $reviewRound = $reviewRoundDao->build(
            $submission->getId(),
            $stageId,
            $round,
            ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS
        );

        // Create review round status notification
        /** @var NotificationDAO $notificationDao */
        $notificationDao = DAORegistry::getDAO('NotificationDAO');
        $notificationFactory = $notificationDao->getByAssoc(
            Application::ASSOC_TYPE_REVIEW_ROUND,
            $reviewRound->getId(),
            null,
            Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS,
            $submission->getData('contextId')
        );
        if (!$notificationFactory->next()) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                Application::get()->getRequest(),
                null,
                Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS,
                $submission->getData('contextId'),
                Application::ASSOC_TYPE_REVIEW_ROUND,
                $reviewRound->getId()
            );
        }
    }

    /**
     * Helper method to get the file genres for a context
     */
    protected function getFileGenres(int $contextId): array
    {
        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        return $genreDao->getByContextId($contextId)->toArray();
    }
}

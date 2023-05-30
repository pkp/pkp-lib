<?php
/**
 * @file classes/decision/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage editorial decisions.
 */

namespace PKP\decision;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\log\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionLog;
use PKP\observers\events\DecisionAdded;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\validation\ValidatorFactory;

abstract class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService<Decision> $schemaService */
    protected $schemaService;


    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Decision
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $submissionId = null): ?Decision
    {
        return $this->dao->get($id, $submissionId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $submissionId = null): bool
    {
        return $this->dao->exists($id, $submissionId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * decisions to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a decision
     *
     * Perform validation checks on data used to add a decision. It is not
     * possible to edit a decision.
     *
     * @param array $props A key/value array with the new data to validate
     * @param Submission $submission The submission for this decision
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(array $props, DecisionType $decisionType, Submission $submission, Context $context): array
    {
        // Return early if no valid decision type exists
        if (!isset($props['decision']) || $props['decision'] !== $decisionType->getDecision()) {
            return ['decision' => [__('editor.submission.workflowDecision.typeInvalid')]];
        }

        // Return early if an invalid submission ID is passed
        if (!isset($props['submissionId']) || $props['submissionId'] !== $submission->getId()) {
            return ['submissionId' => [__('editor.submission.workflowDecision.submissionInvalid')]];
        }

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, []),
        );

        // Check required
        ValidatorFactory::required(
            $validator,
            null,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            [],
            []
        );

        $validator->after(function ($validator) use ($props, $decisionType, $submission, $context) {
            // The decision stage id must match the decision type's stage id
            // and the submission's current workflow stage
            if ($props['stageId'] !== $decisionType->getStageId()
                    || $props['stageId'] !== $submission->getData('stageId')) {
                $validator->errors()->add('decision', __('editor.submission.workflowDecision.invalidStage'));
            }

            // The editorId must match an existing editor
            if (isset($props['editorId'])) {
                $user = Repo::user()->get((int) $props['editorId']);
                if (!$user) {
                    $validator->errors()->add('editorId', __('editor.submission.workflowDecision.invalidEditor'));
                }
            }

            // A recommendation can not be made if the submission does not
            // have at least one assigned editor who can make a decision
            if ($this->isRecommendation($decisionType->getDecision())) {
                /** @var StageAssignmentDAO $stageAssignmentDao  */
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
                $assignedEditorIds = $stageAssignmentDao->getDecidingEditorIds($submission->getId(), $decisionType->getStageId());
                if (!$assignedEditorIds) {
                    $validator->errors()->add('decision', __('editor.submission.workflowDecision.requiredDecidingEditor'));
                }
            }

            // Validate the review round
            if (isset($props['reviewRoundId'])) {
                // The decision must be taken during a review stage
                if (!$decisionType->isInReview() && !$validator->errors()->get('reviewRoundId')) {
                    $validator->errors()->add('reviewRoundId', __('editor.submission.workflowDecision.invalidReviewRoundStage'));
                }

                // The review round must exist and be related to the correct submission.
                if (!$validator->errors()->get('reviewRoundId')) {
                    $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                    $reviewRound = $reviewRoundDao->getById($props['reviewRoundId']);
                    if (!$reviewRound) {
                        $validator->errors()->add('reviewRoundId', __('editor.submission.workflowDecision.invalidReviewRound'));
                    } elseif ($reviewRound->getSubmissionId() !== $submission->getId()) {
                        $validator->errors()->add('reviewRoundId', __('editor.submission.workflowDecision.invalidReviewRoundSubmission'));
                    }
                }
            } elseif ($decisionType->isInReview()) {
                $validator->errors()->add('reviewRoundId', __('editor.submission.workflowDecision.requiredReviewRound'));
            }

            // Allow the decision type to add validation checks
            $decisionType->validate($props, $submission, $context, $validator, isset($reviewRound) ? $reviewRound->getId() : null);
        });

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Decision::validate', [&$errors, $props]);

        return $errors;
    }

    /**
     * Record an editorial decision
     */
    public function add(Decision $decision): int
    {
        // Actions are handled separately from the decision object
        $actions = $decision->getData('actions') ?? [];
        $decision->unsetData('actions');

        // Set the review round automatically from the review round id
        if ($decision->getData('reviewRoundId')) {
            $decision->setData('round', $this->getRoundByReviewRoundId($decision->getData('reviewRoundId')));
        }
        $decision->setData('dateDecided', Core::getCurrentDate());
        $id = $this->dao->insert($decision);
        Hook::call('Decision::add', [$decision]);

        $decision = $this->get($id);

        $decisionType = $decision->getDecisionType();
        $submission = Repo::submission()->get($decision->getData('submissionId'));
        $editor = Repo::user()->get($decision->getData('editorId'));
        $decision = $this->get($decision->getId());
        $context = Application::get()->getRequest()->getContext();
        if (!$context || $context->getId() !== $submission->getData('contextId')) {
            $context = Services::get('context')->get($submission->getData('contextId'));
        }

        // Log the decision
        SubmissionLog::logEvent(
            $this->request,
            $submission,
            $this->isRecommendation($decisionType->getDecision())
                ? PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_RECOMMENDATION
                : PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_DECISION,
            $decisionType->getLog(),
            [
                'editorId' => $editor->getId(),
                'editorName' => $editor->getFullName(),
                'submissionId' => $decision->getData('submissionId'),
                'decision' => $decisionType->getDecision(),
            ]
        );

        // Allow the decision type to perform additional actions
        $decisionType->runAdditionalActions($decision, $submission, $editor, $context, $actions);

        try {
            event(new DecisionAdded(
                $decision,
                $decisionType,
                $submission,
                $editor,
                $context,
                $actions
            ));
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }

        $this->updateNotifications($decision, $decisionType, $submission);

        return $id;
    }

    /**
     * Delete all decisions by the submission ID
     */
    public function deleteBySubmissionId(int $submissionId)
    {
        $decisionIds = $this->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->getIds();

        foreach ($decisionIds as $decisionId) {
            $this->dao->deleteById($decisionId);
        }
    }

    /**
     * Get a decision type by the DECISION::* constant
     */
    public function getDecisionType(int $decision): ?DecisionType
    {
        $decision = $this->getDecisionTypes()->first(function (DecisionType $decisionType) use ($decision) {
            return $decisionType->getDecision() === $decision;
        });

        return $decision ?? null;
    }

    /**
     * Find the most recent revisions decision that is still active. An active
     * decision is one that is not overriden by any other decision.
     */
    public function getActivePendingRevisionsDecision(int $submissionId, int $stageId, int $decision = Decision::PENDING_REVISIONS): ?Decision
    {
        $postReviewDecisions = [Decision::SEND_TO_PRODUCTION];
        $revisionDecisions = [Decision::PENDING_REVISIONS, Decision::RESUBMIT];
        if (!in_array($decision, $revisionDecisions)) {
            return null;
        }

        $revisionsDecisions = $this->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->getMany();

        // Most recent decision first
        $revisionsDecisions = $revisionsDecisions->reverse();

        $pendingRevisionDecision = null;
        foreach ($revisionsDecisions as $revisionDecision) {
            if (in_array($revisionDecision->getData('decision'), $postReviewDecisions)) {
                // Decisions at later stages do not override the pending revisions one.
                continue;
            } elseif ($revisionDecision->getData('decision') == $decision) {
                if ($revisionDecision->getData('stageId') == $stageId) {
                    $pendingRevisionDecision = $revisionDecision;
                    // Only the last pending revisions decision is relevant.
                    break;
                } else {
                    // Both internal and external pending revisions decisions are
                    // valid at the same time. Continue to search.
                    continue;
                }
            } else {
                break;
            }
        }


        return $pendingRevisionDecision;
    }

    /**
     * Have any submission files been uploaded to the revision file stage since
     * this decision was taken?
     */
    public function revisionsUploadedSinceDecision(Decision $decision, int $submissionId): bool
    {
        $stageId = $decision->getData('stageId');
        $round = $decision->getData('round');
        $sentRevisions = false;

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getReviewRound($submissionId, $stageId, $round);

        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
            ->getMany();

        foreach ($submissionFiles as $submissionFile) {
            if ($submissionFile->getData('updatedAt') > $decision->getData('dateDecided')) {
                $sentRevisions = true;
                break;
            }
        }

        return $sentRevisions;

        return true;
    }

    /**
     * Get a list of all the decision types available
     *
     * @return Collection<int,DecisionType>
     */
    abstract public function getDecisionTypes(): Collection;

    /**
     * Get a list of the decline decision types
     *
     * @return DecisionType[]
     */
    abstract public function getDeclineDecisionTypes(): array;

    /**
     * Get a list of the decision types that a recommending user is 
     * allowed to make given a submission stage id.
     *
     * @return DecisionType[]
     */
    abstract public function getDecisionTypesMadeByRecommendingUsers(int $stageId): array;

    /**
     * Is the given decision a recommendation?
     */
    public function isRecommendation(int $decision): bool
    {
        return in_array($decision, [
            Decision::RECOMMEND_ACCEPT,
            Decision::RECOMMEND_DECLINE,
            Decision::RECOMMEND_PENDING_REVISIONS,
            Decision::RECOMMEND_RESUBMIT,
        ]);
    }

    protected function getRoundByReviewRoundId(int $reviewRoundId): int
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getById($reviewRoundId);
        return $reviewRound->getData('round');
    }

    /**
     * Update notifications controlled by the NotificationManager
     */
    protected function updateNotifications(Decision $decision, DecisionType $decisionType, Submission $submission)
    {
        $notificationMgr = new NotificationManager();

        // Update editor decision and pending revisions notifications.
        $notificationTypes = $this->getReviewNotificationTypes();
        if ($editorDecisionNotificationType = $notificationMgr->getNotificationTypeByEditorDecision($decision)) {
            array_unshift($notificationTypes, $editorDecisionNotificationType);
        }

        $authorIds = [];
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $result = $stageAssignmentDao->getBySubmissionAndRoleIds($submission->getId(), [Role::ROLE_ID_AUTHOR], $decisionType->getStageId());
        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $result->next()) {
            $authorIds[] = (int) $stageAssignment->getUserId();
        }

        $notificationMgr->updateNotification(
            Application::get()->getRequest(),
            $notificationTypes,
            $authorIds,
            Application::ASSOC_TYPE_SUBMISSION,
            $submission->getId()
        );

        // Update submission notifications
        $submissionNotificationTypes = $this->getSubmissionNotificationTypes($decision);
        if (count($submissionNotificationTypes)) {
            $notificationMgr->updateNotification(
                Application::get()->getRequest(),
                $submissionNotificationTypes,
                null,
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );
        }
    }

    /**
     * Get the notification types related to a review stage
     *
     * @return int[] One or more of the Notification::NOTIFICATION_TYPE_ constants
     */
    abstract protected function getReviewNotificationTypes(): array;

    /**
     * Get additional notifications to be updated on a submission
     *
     * @return int[] One or more of the Notification::NOTIFICATION_TYPE_ constants
     */
    protected function getSubmissionNotificationTypes(Decision $decision): array
    {
        switch ($decision->getData('decision')) {
            case Decision::ACCEPT:
                return [
                    Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                    Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS
                ];
            case Decision::SEND_TO_PRODUCTION:
                return [
                    Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                    Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                    Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
                    Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
                ];
        }
        return [];
    }
}

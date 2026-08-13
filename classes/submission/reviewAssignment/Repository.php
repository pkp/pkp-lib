<?php

/**
 * @file classes/submission/reviewAssignment/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage review assignment.
 */

namespace PKP\submission\reviewAssignment;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\models\InvitationModel;
use PKP\notification\Notification;
use PKP\plugins\Hook;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\services\PKPSchemaService;
use PKP\submission\ReviewFilesDAO;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<ReviewAssignment> $schemaService */
    protected PKPSchemaService $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): ReviewAssignment
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $submissionId = null): ?ReviewAssignment
    {
        return $this->dao->get($id, $submissionId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $submissionId = null): bool
    {
        return $this->dao->exists($id, $submissionId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a category
     *
     * Perform validation checks on data used to add or edit a review assignment.
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook ReviewAssignment::validate [&$errors, $object, $props, $allowedLocales, $primaryLocale]
     */
    public function validate(?ReviewAssignment $object, array $props, Context $context): array
    {
        $primaryLocale = $context->getData('primaryLocale');
        $allowedLocales = $context->getData('supportedFormLocales');

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check if submission exists
        if (isset($props['submissionId'])) {
            $validator->after(function ($validator) use ($props) {
                if (!$validator->errors()->get('submissionId')) {
                    $submission = Repo::submission()->get($props['submissionId']);
                    if (!$submission) {
                        $validator->errors()->add('submissionId', __('api.reviews.assignments.invalidSubmission'));
                    }
                }
            });
        }

        // Check if reviewer exists
        if (isset($props['reviewerId'])) {
            $validator->after(function ($validator) use ($props, $context) {
                if (!$validator->errors()->get('reviewerId')) {
                    $reviewer = Repo::user()->get($props['reviewerId']);
                    if (!$reviewer) {
                        $validator->errors()->add('reviewerId', __('api.reviews.assignments.invalidReviewer'));
                    }
                    $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
                    $isReviewer = $roleDao->userHasRole($context->getId(), $reviewer->getId(), Role::ROLE_ID_REVIEWER);
                    if (!$isReviewer) {
                        $validator->errors()->add('reviewerId', __('api.reviews.assignments.invalidReviewer'));
                    }
                }
            });
        }

        // Check that the rating is valid
        if (isset($props['quality'])) {
            $validator->after(function ($validator) use ($props) {
                if (!$validator->errors()->get('quality')) {
                    $quality = $props['quality'];
                    // Zero signifies no rating
                    $hasRating = $quality !== 0;

                    $isValidRating = $hasRating && $quality >= ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_POOR &&
                        $quality <= ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_GOOD;

                    if (!$isValidRating) {
                        $validator->errors()->add('quality', __('api.reviews.assignments.invalidQualityRating'));
                    }
                }
            });
        }

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::run('ReviewAssignment::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Add a new review assignment
     *
     * @hook ReviewAssignment::add [[$reviewAssignment]]
     */
    public function add(ReviewAssignment $reviewAssignment): int
    {
        $id = $this->dao->insert($reviewAssignment);
        $reviewAssignment->stampModified();
        Hook::call('ReviewAssignment::add', [$reviewAssignment]);
        $this->updateReviewRoundStatus($reviewAssignment);

        return $id;
    }

    /**
     * Edit a review assignment
     *
     * @hook ReviewAssignment::edit [[$newReviewAssignment, $reviewAssignment, $params]]
     */
    public function edit(ReviewAssignment $reviewAssignment, array $params)
    {
        $newReviewAssignment = clone $reviewAssignment;
        $newReviewAssignment->setAllData(array_merge($newReviewAssignment->_data, $params));
        $newReviewAssignment->stampModified();

        Hook::call('ReviewAssignment::edit', [$newReviewAssignment, $reviewAssignment, $params]);

        $this->dao->update($newReviewAssignment);
        $this->updateReviewRoundStatus($newReviewAssignment);
    }

    /**
     * Delete a review assignment
     *
     * @hook ReviewAssignment::delete::before [[$reviewAssignment]]
     */
    public function delete(ReviewAssignment $reviewAssignment)
    {
        Hook::call('ReviewAssignment::delete::before', [$reviewAssignment]);

        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao->deleteByReviewId($reviewAssignment->getId());

        $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
        $reviewFilesDao->revokeByReviewId($reviewAssignment->getId());

        Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())->delete();

        $this->dao->delete($reviewAssignment);

        $this->updateReviewRoundStatus($reviewAssignment);

        $accessInvitation = $this->getAccessInvitation($reviewAssignment);
        if ($accessInvitation) {
            $accessInvitation->updateStatus(InvitationStatus::CANCELLED);
        }

        Hook::call('ReviewAssignment::delete', [$reviewAssignment]);
    }

    /**
     * Delete a collection of review assignments
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $reviewAssignment) {
            $this->delete($reviewAssignment);
        }
    }

    /**
     * Delete all review assignments for a given context ID.
     */
    public function deleteByContextId(int $contextId): void
    {
        // using reviewAssignmentCollector to fetch ids of all review assignments for the context
        $reviewAssignmentCollector = $this->getCollector();
        $reviewAssignmentCollector->filterByContextIds([$contextId]);

        $this->deleteMany($reviewAssignmentCollector);

        // delete review rounds associated with this context
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao->deleteByContextId($contextId);
    }

    /**
     * Return the review methods translation keys.
     */
    public function getReviewMethodsTranslationKeys(): array
    {
        return [
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS => 'editor.submissionReview.doubleAnonymous',
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS => 'editor.submissionReview.anonymous',
            ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN => 'editor.submissionReview.open',
        ];
    }

    /**
     * Update the status of the review round an assignment is attached to. This
     * should be fired whenever a reviewer assignment is modified.
     */
    protected function updateReviewRoundStatus(ReviewAssignment $reviewAssignment): bool
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getReviewRound(
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getStageId(),
            $reviewAssignment->getRound()
        );

        // Review round may not exist if submission is being deleted
        if ($reviewRound) {
            $reviewRoundDao->updateStatus($reviewRound);
            return true;
        }

        return false;
    }

    /**
     * @copydoc DAO::getExternalReviewerIdsByCompletedYear()
     *
     * @return Collection<int,int>
     */
    public function getExternalReviewerIdsByCompletedYear(int $contextId, string $year): Collection
    {
        return $this->dao->getExternalReviewerIdsByCompletedYear($contextId, $year);
    }

    /**
     * Get the access invitation for a review assignment, if it exists.
     */
    public function getAccessInvitation(ReviewAssignment $reviewAssignment): ?ReviewerAccessInvite
    {
        $invitationModels = InvitationModel::byType(ReviewerAccessInvite::INVITATION_TYPE)
            ->byUserId($reviewAssignment->getReviewerId())
            ->stillActive()
            ->get();

        foreach ($invitationModels as $invitationModel) {
            $invitation = new ReviewerAccessInvite($invitationModel);

            if ($invitation->getPayload()->reviewAssignmentId === $reviewAssignment->getId()) {
                return $invitation;
            }
        }

        return null;
    }

    /**
     * @copydoc DAO::getExportableDOIsPeerReviewIds()
     *
     * @return array - Array of exportable peer review IDs.
     */
    public function getExportableDOIsPeerReviewIds(int $contextId, bool $doiVersioning, ?array $submissionIds = null): array
    {
        return $this->dao->getExportableDOIsPeerReviewIds($contextId, $doiVersioning, $submissionIds);
    }

    /**
     * Save a review form response for a review assignment.
     *
     * @param $reviewFormElementId - The ID of the review form element the response is being submitted for.
     * @param $reviewFormResponseValue - The submitted response value.
     */
    public function saveReviewFormResponse(ReviewAssignment $reviewAssignment, int $reviewFormElementId, mixed $reviewFormResponseValue): void
    {
        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');

        $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getId(), $reviewFormElementId);
        if (!isset($reviewFormResponse)) {
            $reviewFormResponse = new ReviewFormResponse();
        }

        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewFormElement = $reviewFormElementDao->getById($reviewFormElementId);
        $elementType = $reviewFormElement->getElementType();

        switch ($elementType) {
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA:
                $reviewFormResponse->setResponseType('string');
                $reviewFormResponse->setValue($reviewFormResponseValue);
                break;
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
                $reviewFormResponse->setResponseType('int');
                $reviewFormResponse->setValue($reviewFormResponseValue);
                break;
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
                $reviewFormResponse->setResponseType('object');
                $reviewFormResponse->setValue($reviewFormResponseValue);
                break;
        }
        if ($reviewFormResponse->getReviewFormElementId() != null && $reviewFormResponse->getReviewId() != null) {
            $reviewFormResponseDao->updateObject($reviewFormResponse);
        } else {
            $reviewFormResponse->setReviewFormElementId($reviewFormElementId);
            $reviewFormResponse->setReviewId($reviewAssignment->getId());
            $reviewFormResponseDao->insertObject($reviewFormResponse);
        }
    }

    /**
     * Formats a review form element's response for logging purposes.
     *
     * @param mixed $response - The responses submitted for the review form element.
     *
     * @return array - An assoc array containing the formatted response data:
     * [
     *     '<elementID>' => [
     *         'question' => '<string>',
     *         'answer' => '<int|string|array>',
     *         'elementType' => <int>,
     *         'possibleResponses' => [<string>], // optional
     *         'selectedResponses' => [<int>], // optional
     *     ],
     *  ]
     */
    public function formatReviewFormElementResponseForLogEntry(ReviewFormElement $reviewFormElement, mixed $response): array
    {
        $elementType = $reviewFormElement->getElementType();

        $entry = [
            'question' => $reviewFormElement->getLocalizedQuestion(),
            'elementType' => $elementType,
        ];

        switch ($reviewFormElement->getElementType()) {
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                $entry['answer'] = $possibleResponses[(int)$response] ?? null;
                break;
            case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                $labels = [];
                foreach ((array)$response as $index) {
                    if (isset($possibleResponses[(int)$index])) {
                        $labels[] = $possibleResponses[(int)$index];
                    }
                }
                $entry['answer'] = implode(', ', $labels);
                break;
            default:
                $entry['answer'] = (string)$response;
        }

        if (in_array($elementType, $reviewFormElement->getMultipleResponsesElementTypes())) {
            $entry['possibleResponses'] = $reviewFormElement->getLocalizedPossibleResponses() ?? [];
            $entry['selectedResponses'] = array_map('intval', (array)$response);
        }

        return $entry;
    }

    /**
     * Save or update a review comment for a review assignment.
     */
    public function saveReviewComment(ReviewAssignment $reviewAssignment, string $comments, bool $viewable): SubmissionComment
    {
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), true);
        /** @var ?SubmissionComment $comment */
        $comment = $submissionComments->next();

        if (!isset($comment)) {
            $comment = $submissionCommentDao->newDataObject();
        }

        $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
        $comment->setRoleId(Role::ROLE_ID_REVIEWER);
        $comment->setAssocId($reviewAssignment->getId());
        $comment->setSubmissionId($reviewAssignment->getSubmissionId());
        $comment->setAuthorId($reviewAssignment->getReviewerId());
        $comment->setComments($comments);
        $comment->setCommentTitle('');
        $comment->setViewable($viewable);
        $comment->setDatePosted(Core::getCurrentDate());

        $commentId = $comment->getId();
        // Save or update
        if ($comment->getId() != null) {
            $submissionCommentDao->updateObject($comment);
        } else {
            $commentId = $submissionCommentDao->insertObject($comment);
        }

        return $submissionCommentDao->getById($commentId);
    }
}

<?php

/**
 * @file classes/services/PKPSubmissionService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionService
 * @ingroup services
 *
 * @brief Helper class that encapsulates submission business logic
 */

namespace PKP\services;

use APP\core\Application;
use APP\core\Services;
use APP\services\queryBuilders\SubmissionQueryBuilder;
use APP\submission\Submission;

use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\plugins\HookRegistry;
use PKP\security\Role;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\PKPSubmission;
use PKP\submission\SubmissionFile;
use PKP\validation\ValidatorFactory;
use PKP\workflow\WorkflowStageDAO;

abstract class PKPSubmissionService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface
{
    public const STAGE_STATUS_SUBMISSION_UNASSIGNED = 1;

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($submissionId)
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        return $submissionDao->getById($submissionId);
    }

    /**
     * Get a submission by the urlPath of its publications
     *
     * @param string $urlPath
     * @param int $contextId
     *
     * @return Submission|null
     */
    public function getByUrlPath($urlPath, $contextId)
    {
        $qb = new \PKP\services\queryBuilders\PKPPublicationQueryBuilder();
        $firstResult = $qb->getQueryByUrlPath($urlPath, $contextId)->first();

        if (!$firstResult) {
            return null;
        }

        return $this->get($firstResult->submission_id);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getCount()
     */
    public function getCount($args = [])
    {
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getIds()
     */
    public function getIds($args = [])
    {
        return $this->getQueryBuilder($args)->getIds();
    }

    /**
     * Get a collection of Submission objects limited, filtered
     * and sorted by $args
     *
     * @param array $args
     *		@option int contextId If not supplied, CONTEXT_ID_NONE will be used and
     *			no submissions will be returned. To retrieve submissions from all
     *			contexts, use CONTEXT_ID_ALL.
     * 		@option string orderBy
     * 		@option string orderDirection
     * 		@option int assignedTo
     * 		@option int|array status
     * 		@option string searchPhrase
     * 		@option int count
     * 		@option int offset
     *
     * @return \Iterator
     */
    public function getMany($args = [])
    {
        $range = null;
        if (isset($args['count'])) {
            $range = new DBResultRange($args['count'], null, $args['offset'] ?? 0);
        }
        // Pagination is handled by the DAO, so don't pass count and offset
        // arguments to the QueryBuilder.
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        $submissionListQO = $this->getQueryBuilder($args)->getQuery();
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        $result = $submissionDao->retrieveRange($sql = $submissionListQO->toSql(), $params = $submissionListQO->getBindings(), $range);
        $queryResults = new DAOResultFactory($result, $submissionDao, '_fromRow', [], $sql, $params, $range);

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     */
    public function getMax($args = [])
    {
        // Don't accept args to limit the results
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     *
     * @return SubmissionQueryBuilder
     */
    public function getQueryBuilder($args = [])
    {
        $defaultArgs = [
            'contextId' => \PKP\core\PKPApplication::CONTEXT_ID_NONE,
            'orderBy' => 'dateSubmitted',
            'orderDirection' => 'DESC',
            'assignedTo' => [],
            'status' => null,
            'stageIds' => null,
            'searchPhrase' => null,
            'isIncomplete' => false,
            'isOverdue' => false,
            'daysInactive' => null,
        ];

        $args = array_merge($defaultArgs, $args);

        $submissionListQB = new SubmissionQueryBuilder();
        $submissionListQB
            ->filterByContext($args['contextId'])
            ->orderBy($args['orderBy'], $args['orderDirection'])
            ->assignedTo($args['assignedTo'])
            ->filterByStatus($args['status'])
            ->filterByStageIds($args['stageIds'])
            ->filterByIncomplete($args['isIncomplete'])
            ->filterByOverdue($args['isOverdue'])
            ->filterByDaysInactive($args['daysInactive'])
            ->filterByCategories($args['categoryIds'] ?? null)
            ->searchPhrase($args['searchPhrase']);

        if (isset($args['count'])) {
            $submissionListQB->limitTo($args['count']);
        }

        if (isset($args['offset'])) {
            $submissionListQB->offsetBy($args['count']);
        }

        HookRegistry::call('Submission::getMany::queryBuilder', [&$submissionListQB, $args]);

        return $submissionListQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($submission, $props, $args = null)
    {
        \AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);
        $values = [];
        $request = $args['request'];
        $dispatcher = $request->getDispatcher();

        // Retrieve the submission's context for properties that require it
        if (array_intersect(['_href', 'urlAuthorWorkflow', 'urlEditorialWorkflow'], $props)) {
            $submissionContext = $request->getContext();
            if (!$submissionContext || $submissionContext->getId() != $submission->getData('contextId')) {
                $submissionContext = Services::get('context')->get($submission->getData('contextId'));
            }
        }

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        \PKPApplication::ROUTE_API,
                        $submissionContext->getData('urlPath'),
                        'submissions/' . $submission->getId()
                    );
                    break;
                    case 'publications':
                        $values[$prop] = array_map(
                            function ($publication) use ($args, $submission, $submissionContext) {
                                return Services::get('publication')->getSummaryProperties(
                                    $publication,
                                    $args + [
                                        'submission' => $submission,
                                        'context' => $submissionContext,
                                        'currentUserReviewAssignment' => DAORegistry::getDAO('ReviewAssignmentDAO')
                                            ->getLastReviewRoundReviewAssignmentByReviewer(
                                                $submission->getId(),
                                                $args['request']->getUser()->getId()
                                            ),
                                    ]
                                );
                            },
                            $submission->getData('publications')
                        );
                        break;
                case 'reviewAssignments':
                    $values[$prop] = $this->getPropertyReviewAssignments($submission);
                    break;
                case 'reviewRounds':
                    $values[$prop] = $this->getPropertyReviewRounds($submission);
                    break;
                case 'stages':
                    $values[$prop] = $this->getPropertyStages($submission);
                    break;
                case 'statusLabel':
                    $values[$prop] = __($submission->getStatusKey());
                    break;
                case 'urlAuthorWorkflow':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        \PKPApplication::ROUTE_PAGE,
                        $submissionContext->getData('urlPath'),
                        'authorDashboard',
                        'submission',
                        $submission->getId()
                    );
                    break;
                case 'urlEditorialWorkflow':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        \PKPApplication::ROUTE_PAGE,
                        $submissionContext->getData('urlPath'),
                        'workflow',
                        'access',
                        $submission->getId()
                    );
                    break;
                case 'urlWorkflow':
                    $values[$prop] = $this->getWorkflowUrlByUserRoles($submission);
                    break;
                default:
                    $values[$prop] = $submission->getData($prop);
                    break;
            }
        }

        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_SUBMISSION, $values, $request->getContext()->getSupportedSubmissionLocales());

        HookRegistry::call('Submission::getProperties::values', [&$values, $submission, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($submission, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_SUBMISSION);

        return $this->getProperties($submission, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($submission, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_SUBMISSION);

        return $this->getProperties($submission, $props, $args);
    }

    /**
     * Returns properties for custom API endpoints used in the backend
     *
     * @param Submission $submission
     * @param array extra arguments
     *		$args['request'] PKPRequest Required
     *		$args['slimRequest'] SlimRequest
     */
    public function getBackendListProperties($submission, $args = null)
    {
        \PluginRegistry::loadCategory('pubIds', true);

        $props = [
            '_href', 'contextId', 'currentPublicationId','dateLastActivity','dateSubmitted','id',
            'lastModified','publications','reviewAssignments','reviewRounds','stageId','stages','status',
            'statusLabel','submissionProgress','urlAuthorWorkflow','urlEditorialWorkflow','urlWorkflow','urlPublished',
        ];

        HookRegistry::call('Submission::getBackendListProperties::properties', [&$props, $submission, $args]);

        return $this->getProperties($submission, $props, $args);
    }

    /**
     * Get details about the review assignments for a submission
     *
     * @todo account for extra review stage in omp
     *
     * @param $submission Submission
     */
    public function getPropertyReviewAssignments($submission)
    {
        $reviewAssignments = $this->getReviewAssignments($submission);

        $reviews = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            // @todo for now, only show reviews that haven't been
            // declined or cancelled
            if ($reviewAssignment->getDeclined()) {
                continue;
            }

            $request = \Application::get()->getRequest();
            $currentUser = $request->getUser();
            $context = $request->getContext();
            $dateFormatShort = $context->getLocalizedDateFormatShort();
            $due = is_null($reviewAssignment->getDateDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateDue()));
            $responseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateResponseDue()));

            $reviews[] = [
                'id' => (int) $reviewAssignment->getId(),
                'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
                'statusId' => (int) $reviewAssignment->getStatus(),
                'status' => __($reviewAssignment->getStatusKey()),
                'due' => $due,
                'responseDue' => $responseDue,
                'round' => (int) $reviewAssignment->getRound(),
                'roundId' => (int) $reviewAssignment->getReviewRoundId(),
            ];
        }

        return $reviews;
    }

    /**
     * Get details about the review rounds for a submission
     *
     * @todo account for extra review stage in omp
     *
     * @param $submission Submission
     *
     * @return array
     */
    public function getPropertyReviewRounds($submission)
    {
        $reviewRounds = $this->getReviewRounds($submission);

        $rounds = [];
        foreach ($reviewRounds as $reviewRound) {
            $rounds[] = [
                'id' => $reviewRound->getId(),
                'round' => $reviewRound->getRound(),
                'stageId' => $reviewRound->getStageId(),
                'statusId' => $reviewRound->determineStatus(),
                'status' => __($reviewRound->getStatusKey()),
            ];
        }

        return $rounds;
    }

    /**
     * Get details about a submission's stage(s)
     *
     * @param $submission Submission
     * @param $stageIds array|int|null One or more stages to retrieve.
     *  Default: null. Will return data on all app stages.
     *
     * @return array {
     *  `id` int stage id
     *  `label` string translated stage name
     *  `queries` array [{
     *    `id` int query id
     *    `assocType` int
     *    `assocId` int
     *    `stageId` int
     *    `seq` int
     *    `closed` bool
     *   }]
     *  `statusId` int stage status. note: on review stage, this refers to the
     *    status of the latest round.
     *  `status` string translated stage status name
     *  `files` array {
     *    `count` int number of files attached to stage. note: this only counts
     *      revision files.
     *   }
     */
    public function getPropertyStages($submission, $stageIds = null)
    {
        if (is_null($stageIds)) {
            $stageIds = Application::get()->getApplicationStages();
        } elseif (is_int($stageIds)) {
            $stageIds = [$stageIds];
        }

        $currentUser = \Application::get()->getRequest()->getUser();
        $context = \Application::get()->getRequest()->getContext();
        $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;

        $stages = [];
        foreach ($stageIds as $stageId) {
            $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
            $stage = [
                'id' => (int) $stageId,
                'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
                'isActiveStage' => $submission->getStageId() == $stageId,
            ];

            // Discussions in this stage
            $stage['queries'] = [];
            $request = Application::get()->getRequest();
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $queries = $queryDao->getByAssoc(
                ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                $stageId,
                $request->getUser()->getId() // Current user restriction should prevent unauthorized access
            );

            while ($query = $queries->next()) {
                $stage['queries'][] = [
                    'id' => (int) $query->getId(),
                    'assocType' => (int) $query->getAssocType(),
                    'assocId' => (int) $query->getAssocId(),
                    'stageId' => $stageId,
                    'seq' => (int) $query->getSequence(),
                    'closed' => (bool) $query->getIsClosed(),
                ];
            }

            $currentUserAssignedRoles = [];
            if ($currentUser) {
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $stageId);
                $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                while ($stageAssignment = $stageAssignmentsResult->next()) {
                    $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $contextId);
                    $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
                }
            }
            $stage['currentUserAssignedRoles'] = array_values(array_unique($currentUserAssignedRoles));

            // Stage-specific statuses
            switch ($stageId) {

                case WORKFLOW_STAGE_ID_SUBMISSION:
                    $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                    $assignedEditors = $stageAssignmentDao->editorAssignedToStage($submission->getId(), $stageId);
                    if (!$assignedEditors) {
                        $stage['statusId'] = self::STAGE_STATUS_SUBMISSION_UNASSIGNED;
                        $stage['status'] = __('submissions.queuedUnassigned');
                    }

                    // Submission stage never has revisions
                    $stage['files'] = [
                        'count' => 0,
                    ];
                    break;

                case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                    $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                    $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
                    if ($reviewRound) {
                        $stage['statusId'] = $reviewRound->determineStatus();
                        $stage['status'] = __($reviewRound->getStatusKey());

                        // Revision files in this round.
                        $stage['files'] = [
                            'count' => Services::get('submissionFile')->getCount([
                                'submissionIds' => [$submission->getId()],
                                'fileStages' => [SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION],
                                'reviewRounds' => [$reviewRound->getId()],
                            ]),
                        ];

                        // See if the  curent user can only recommend:
                        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                        $user = $request->getUser();
                        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $stageId);
                        // if the user is assigned several times in the editorial role, and
                        // one of the assignments have recommendOnly option set, consider it here
                        $stage['currentUserCanRecommendOnly'] = false;
                        foreach ($editorsStageAssignments as $editorsStageAssignment) {
                            if ($editorsStageAssignment->getUserId() == $user->getId() && $editorsStageAssignment->getRecommendOnly()) {
                                $stage['currentUserCanRecommendOnly'] = true;
                                break;
                            }
                        }
                    } else {
                        // workaround for pkp/pkp-lib#4231, pending formal data model
                        $stage['files'] = [
                            'count' => 0
                        ];
                    }
                    break;

                // Get revision files for editing and production stages.
                // Review rounds are handled separately in the review stage below.
                case WORKFLOW_STAGE_ID_EDITING:
                case WORKFLOW_STAGE_ID_PRODUCTION:
                    $stage['files'] = [
                        'count' => Services::get('submissionFile')->getCount([
                            'submissionIds' => [$submission->getId()],
                            'fileStages' => [WORKFLOW_STAGE_ID_EDITING ? SubmissionFile::SUBMISSION_FILE_COPYEDIT : SubmissionFile::SUBMISSION_FILE_PROOF],
                        ]),
                    ];
                    break;
            }

            $stages[] = $stage;
        }

        return $stages;
    }

    /**
     * Get the correct access URL for a submission's workflow based on a user's
     * role.
     *
     * The returned URL will point to the correct workflow page based on whether
     * the user should be treated as an author, reviewer or editor/assistant for
     * this submission.
     *
     * @param $submission Submission
     * @param $userId an optional user id
     *
     * @return string|false URL; false if the user does not exist or an
     *   appropriate access URL could not be determined
     */
    public function getWorkflowUrlByUserRoles($submission, $userId = null)
    {
        $request = Application::get()->getRequest();

        if (is_null($userId)) {
            $user = $request->getUser();
        } else {
            $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
            $user = $userDao->getById($userId);
        }

        if (is_null($user)) {
            return false;
        }

        $submissionContext = $request->getContext();

        if (!$submissionContext || $submissionContext->getId() != $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        $dispatcher = $request->getDispatcher();

        // Check if the user is an author of this submission
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $authorUserGroupIds = $userGroupDao->getUserGroupIdsByRoleId(Role::ROLE_ID_AUTHOR);
        $stageAssignmentsFactory = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), null, null, $user->getId());

        $authorDashboard = false;
        while ($stageAssignment = $stageAssignmentsFactory->next()) {
            if (in_array($stageAssignment->getUserGroupId(), $authorUserGroupIds)) {
                $authorDashboard = true;
            }
        }

        // Send authors, journal managers and site admins to the submission
        // wizard for incomplete submissions
        if ($submission->getSubmissionProgress() > 0 &&
            ($authorDashboard ||
                $user->hasRole([Role::ROLE_ID_MANAGER], $submissionContext->getId()) ||
                $user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE))) {
            return $dispatcher->url(
                $request,
                \PKPApplication::ROUTE_PAGE,
                $submissionContext->getPath(),
                'submission',
                'wizard',
                $submission->getSubmissionProgress(),
                ['submissionId' => $submission->getId()]
            );
        }

        // Send authors to author dashboard
        if ($authorDashboard) {
            return $dispatcher->url(
                $request,
                \PKPApplication::ROUTE_PAGE,
                $submissionContext->getPath(),
                'authorDashboard',
                'submission',
                $submission->getId()
            );
        }

        // Send reviewers to review wizard
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());
        if ($reviewAssignment) {
            return $dispatcher->url(
                $request,
                \PKPApplication::ROUTE_PAGE,
                $submissionContext->getPath(),
                'reviewer',
                'submission',
                $submission->getId()
            );
        }

        // Give any other users the editorial workflow URL. If they can't access
        // it, they'll be blocked there.
        return $dispatcher->url(
            $request,
            \PKPApplication::ROUTE_PAGE,
            $submissionContext->getPath(),
            'workflow',
            'access',
            $submission->getId()
        );
    }

    /**
     * Check if a user can delete a submission
     *
     * @param $submission Submission|int Submission object or submission ID
     *
     * @return bool
     */
    public function canCurrentUserDelete($submission)
    {
        if (!($submission instanceof Submission)) {
            $submission = $this->get((int) $submission);
            if (!$submission) {
                return false;
            }
        }

        $request = Application::get()->getRequest();
        $contextId = $submission->getContextId();

        $currentUser = $request->getUser();
        if (!$currentUser) {
            return false;
        }

        $canDelete = false;

        // Only allow admins and journal managers to delete submissions, except
        // for authors who can delete their own incomplete submissions
        if ($currentUser->hasRole([Role::ROLE_ID_MANAGER], $contextId) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::CONTEXT_SITE)) {
            $canDelete = true;
        } else {
            if ($submission->getSubmissionProgress() != 0) {
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR, WORKFLOW_STAGE_ID_SUBMISSION, $currentUser->getId());
                $assignment = $assignments->next();
                if ($assignment) {
                    $canDelete = true;
                }
            }
        }

        return $canDelete;
    }

    /**
     * Get review rounds for a submission
     *
     * @param $submission Submission
     *
     * @return \Iterator
     */
    public function getReviewRounds($submission)
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        return $reviewRoundDao->getBySubmissionId($submission->getId())->toIterator();
    }

    /**
     * Get review assignments for a submission
     *
     * @param $submission Submission
     *
     * @return array
     */
    public function getReviewAssignments($submission)
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        return $reviewAssignmentDao->getBySubmissionId($submission->getId());
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_SUBMISSION, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_SUBMISSION),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_SUBMISSION),
            $primaryLocale,
            $allowedLocales
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_SUBMISSION), $allowedLocales);

        // The contextId must match an existing context
        $validator->after(function ($validator) use ($props) {
            if (isset($props['contextid']) && !$validator->errors()->get('contextid')) {
                $submissionContext = Services::get('context')->get($props['contextid']);
                if (!$submissionContext) {
                    $validator->errors()->add('contextId', __('submission.submit.noContext'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_SUBMISSION), $allowedLocales);
        }

        HookRegistry::call('Submission::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($submission, $request)
    {
        $submission->stampLastActivity();
        $submission->stampModified();
        if (!$submission->getData('dateSubmitted') && !$submission->getData('submissionProgress')) {
            $submission->setData('dateSubmitted', Core::getCurrentDate());
        }
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        $submissionId = $submissionDao->insertObject($submission);
        $submission = $this->get($submissionId);

        HookRegistry::call('Submission::add', [&$submission, $request]);

        return $submission;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($submission, $params, $request)
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */

        $newSubmission = $submissionDao->newDataObject();
        $newSubmission->_data = array_merge($submission->_data, $params);
        $submission->stampLastActivity();
        $submission->stampModified();

        HookRegistry::call('Submission::edit', [&$newSubmission, $submission, $params, $request]);

        $submissionDao->updateObject($newSubmission);
        $newSubmission = $this->get($newSubmission->getId());

        return $newSubmission;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($submission)
    {
        HookRegistry::call('Submission::delete::before', [&$submission]);

        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        $submissionDao->deleteObject($submission);

        HookRegistry::call('Submission::delete', [&$submission]);
    }

    /**
     * Check if a user can edit a publications metadata
     *
     * @param int $submissionId
     * @param int $userId
     *
     * @return boolean
     */
    public function canEditPublication($submissionId, $userId)
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submissionId, $userId, null)->toArray();
        // Check for permission from stage assignments
        foreach ($stageAssignments as $stageAssignment) {
            if ($stageAssignment->getCanChangeMetadata()) {
                return true;
            }
        }
        // If user has no stage assigments, check if user can edit anyway ie. is manager
        $context = Application::get()->getRequest()->getContext();
        if (count($stageAssignments) == 0 && $this->_canUserAccessUnassignedSubmissions($context->getId(), $userId)) {
            return true;
        }
        // Else deny access
        return false;
    }

    /**
     * Check whether the user is by default allowed to edit publications metadata
     *
     * @param $contextId int
     * @param $userId int
     *
     * @return boolean true if the user is allowed to edit metadata by default
     */
    private static function _canUserAccessUnassignedSubmissions($contextId, $userId)
    {
        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $roles = $roleDao->getByUserId($userId, $contextId);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $allowedRoles = $userGroupDao->getNotChangeMetadataEditPermissionRoles();
        foreach ($roles as $role) {
            if (in_array($role->getRoleId(), $allowedRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update a submission's status and current publication id if necessary
     *
     * Checks the status of the submission's publications and sets
     * the appropriate status and current publication id.
     *
     * @param Submission $submission
     *
     * @return Submission
     */
    public function updateStatus($submission)
    {
        $status = $newStatus = $submission->getData('status');
        $currentPublicationId = $submission->getData('currentPublicationId');
        $publications = $submission->getData('publications');

        // There should always be at least one publication for a submission. If
        // not, an error has occurred in the code and will cause problems.
        if (empty($publications)) {
            throw new \Exception('Tried to update the status of submission ' . $submission->getId() . ' and no publications were found.');
        }

        // Get the new current publication after status changes or deletions
        // Use the latest published publication or, failing that, the latest publication
        $newCurrentPublicationId = array_reduce($publications, function ($a, $b) {
            return $b->getData('status') === PKPSubmission::STATUS_PUBLISHED && $b->getId() > $a ? $b->getId() : $a;
        }, 0);
        if (!$newCurrentPublicationId) {
            $newCurrentPublicationId = array_reduce($publications, function ($a, $b) {
                return $a > $b->getId() ? $a : $b->getId();
            }, 0);
        }

        // Declined submissions should remain declined even if their
        // publications change
        if ($status !== PKPSubmission::STATUS_DECLINED) {
            $newStatus = PKPSubmission::STATUS_QUEUED;
            foreach ($publications as $publication) {
                if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
                    $newStatus = PKPSubmission::STATUS_PUBLISHED;
                    break;
                }
                if ($publication->getData('status') === PKPSubmission::STATUS_SCHEDULED) {
                    $newStatus = PKPSubmission::STATUS_SCHEDULED;
                    continue;
                }
            }
        }

        HookRegistry::call('Submission::updateStatus', [&$status, $submission]);

        $updateParams = [];
        if ($status !== $newStatus) {
            $updateParams['status'] = $newStatus;
        }
        if ($currentPublicationId !== $newCurrentPublicationId) {
            $updateParams['currentPublicationId'] = $newCurrentPublicationId;
        }
        if (!empty($updateParams)) {
            $submission = $this->edit($submission, $updateParams, Application::get()->getRequest());
        }

        return $submission;
    }
}

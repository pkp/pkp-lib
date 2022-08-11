<?php

/**
 * @file api/v1/submissions/PKPSubmissionHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

namespace PKP\API\v1\submissions;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Collector;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\handler\APIHandler;
use PKP\mail\mailables\PublicationVersionNotify;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DecisionWritePolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class PKPSubmissionHandler extends APIHandler
{
    /** @var int The default number of items to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int Max items that can be requested */
    public const MAX_COUNT = 100;

    /** @var array Handlers that must be authorized to access a submission */
    public $requiresSubmissionAccess = [
        'get',
        'edit',
        'delete',
        'getGalleys',
        'getParticipants',
        'getPublications',
        'getPublication',
        'addPublication',
        'versionPublication',
        'editPublication',
        'publishPublication',
        'unpublishPublication',
        'deletePublication',
        'getContributors',
        'getContributor',
        'addContributor',
        'deleteContributor',
        'editContributor',
        'saveContributorsOrder',
        'addDecision',
    ];

    /** @var array Handlers that must be authorized to write to a publication */
    public $requiresPublicationWriteAccess = [
        'editPublication',
        'addContributor',
        'deleteContributor',
        'editContributor',
        'saveContributorsOrder',
    ];

    /** @var array Handlers that must be authorized to access a submission's production stage */
    public $requiresProductionStageAccess = [
        'addPublication',
        'versionPublication',
        'publishPublication',
        'unpublishPublication',
        'deletePublication',
    ];

    /** @var array Roles that can access a submission's production stage */
    public $productionStageAccessRoles = [
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'submissions';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/participants',
                    'handler' => [$this, 'getParticipants'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/participants/{stageId:\d+}',
                    'handler' => [$this, 'getParticipants'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications',
                    'handler' => [$this, 'getPublications'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}',
                    'handler' => [$this, 'getPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors',
                    'handler' => [$this, 'getContributors'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors/{contributorId:\d+}',
                    'handler' => [$this, 'getContributor'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications',
                    'handler' => [$this, 'addPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/version',
                    'handler' => [$this, 'versionPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors',
                    'handler' => [$this, 'addContributor'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/decisions',
                    'handler' => [$this, 'addDecision'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}',
                    'handler' => [$this, 'editPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/publish',
                    'handler' => [$this, 'publishPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/unpublish',
                    'handler' => [$this, 'unpublishPublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors/{contributorId:\d+}',
                    'handler' => [$this, 'editContributor'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors/saveOrder',
                    'handler' => [$this, 'saveContributorsOrder'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}',
                    'handler' => [$this, 'deletePublication'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/publications/{publicationId:\d+}/contributors/{contributorId:\d+}',
                    'handler' => [$this, 'deleteContributor'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
        ];
        parent::__construct();
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array($routeName, $this->requiresSubmissionAccess)) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        if (in_array($routeName, $this->requiresPublicationWriteAccess)) {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

        if (in_array($routeName, $this->requiresProductionStageAccess)) {
            $this->addPolicy(new StageRolePolicy($this->productionStageAccessRoles, WORKFLOW_STAGE_ID_PRODUCTION, false));
        }

        if ($routeName === 'addDecision') {
            $this->addPolicy(new DecisionWritePolicy($request, $args, (int) $request->getUserVar('decision'), $request->getUser()));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of submissions
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        $context = $request->getContext();

        $collector = $this->getSubmissionCollector($slimRequest->getQueryParams());

        Hook::call('API::submissions::params', [$collector, $slimRequest]);

        // Prevent users from viewing submissions they're not assigned to,
        // except for journal managers and admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
        if (!$canAccessUnassignedSubmission) {
            if (!is_array($collector->assignedTo)) {
                $collector->assignedTo([$currentUser->getId()]);
            } elseif ($collector->assignedTo != [$currentUser->getId()]) {
                return $response->withStatus(403)->withJsonError('api.submissions.403.requestedOthersUnpublishedSubmissions');
            }
        }

        $submissions = Repo::submission()->getMany($collector);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($context->getId())->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        return $response->withJson([
            'itemsMax' => Repo::submission()->getCount($collector->limit(null)->offset(null)),
            'items' => Repo::submission()->getSchemaMap()->summarizeMany($submissions, $userGroups, $genres),
        ], 200);
    }

    /**
     * Configure a submission Collector based on the query params
     */
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $request = Application::get()->getRequest();
        /** @var \PKP\context\Context $context */
        $context = $request->getContext();

        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($queryParams as $param => $val) {
            switch ($param) {
                case 'orderBy':
                    if (in_array($val, [
                        $collector::ORDERBY_DATE_PUBLISHED,
                        $collector::ORDERBY_DATE_SUBMITTED,
                        $collector::ORDERBY_LAST_ACTIVITY,
                        $collector::ORDERBY_LAST_MODIFIED,
                        $collector::ORDERBY_SEQUENCE,
                        $collector::ORDERBY_TITLE,
                    ])) {
                        $direction = isset($queryParams['orderDirection']) && $queryParams['orderDirection'] === $collector::ORDER_DIR_ASC
                            ? $collector::ORDER_DIR_ASC
                            : $collector::ORDER_DIR_DESC;
                        $collector->orderBy($val, $direction);
                    }
                    break;

                case 'categoryIds':
                    $collector->filterByCategoryIds(array_map('intval', $this->paramToArray($val)));
                    break;

                case 'status':
                    $collector->filterByStatus(array_map('intval', $this->paramToArray($val)));
                    break;

                case 'stageIds':
                    $collector->filterByStageIds(array_map('intval', $this->paramToArray($val)));
                    break;

                case 'assignedTo':
                    $val = array_map('intval', $this->paramToArray($val));
                    if ($val == [\PKP\submission\Collector::UNASSIGNED]) {
                        $val = array_shift($val);
                    }
                    $collector->assignedTo($val);
                    break;

                case 'daysInactive':
                    $collector->filterByDaysInactive((int) $val);
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;

                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;

                case 'count':
                    $collector->limit(min(self::MAX_COUNT, (int) $val));
                    break;

                case 'isIncomplete':
                    $collector->filterByIncomplete(true);
                    break;

                case 'isOverdue':
                    $collector->filterByOverdue(true);
                    break;
                case 'doiStatus':
                    $collector->filterByDoiStatuses(array_map('intval', $this->paramToArray($val)));
                    break;
                case 'hasDois':
                    $collector->filterByHasDois((bool) $val, $context->getEnabledDoiTypes());
                    break;
            }
        }

        return $collector;
    }

    /**
     * Get a single submission
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function get($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres), 200);
    }

    /**
     * Add a new submission
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function add($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if ($request->getContext()->getData('disableSubmissions')) {
            return $response->withStatus(403)->withJsonError('author.submit.notAccepting');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION, $slimRequest->getParsedBody());
        $params['contextId'] = $request->getContext()->getId();

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getData('supportedSubmissionLocales');

        $errors = Repo::submission()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $sectionId = $params['sectionId'];
        unset($params['sectionId']);

        $submission = Repo::submission()->newDataObject($params);
        $publication = Repo::publication()->newDataObject(['sectionId' => $sectionId]);
        $submissionId = Repo::submission()->add($submission, $publication);

        $submission = Repo::submission()->get($submissionId);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres), 200);
    }

    /**
     * Edit a submission
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function edit($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION, $slimRequest->getParsedBody());
        $params['id'] = $submission->getId();
        $params['contextId'] = $request->getContext()->getId();

        // Don't allow a submission's context to be changed
        if (isset($params['contextId']) && $params['contextId'] !== $submission->getData('contextId')) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.cantChangeContext');
        }

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getData('supportedSubmissionLocales');

        $errors = Repo::submission()->validate($submission, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::submission()->edit($submission, $params, $request);

        $submission = Repo::submission()->get($submission->getId());

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres), 200);
    }

    /**
     * Delete a submission
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function delete($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        $submissionProps = Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres);

        Repo::submission()->delete($submission);

        return $response->withJson($submissionProps, 200);
    }

    /**
     * Get the participants assigned to a submission
     *
     * This does not return reviewers.
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getParticipants($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $stageId = $args['stageId'] ?? null;

        if (!$submission || $submission->getData('contextId') !== $context->getId()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $data = [];

        $usersIterator = Repo::user()->getMany(
            Repo::user()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->assignedTo($submission->getId(), $stageId)
        );
        $map = Repo::user()->getSchemaMap();
        foreach ($usersIterator as $user) {
            $data[] = $map->summarizeReviewer($user);
        }

        return $response->withJson($data, 200);
    }


    /**
     * Get all of this submissions's publications
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getPublications($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $collector = Repo::publication()->getCollector();
        $collector->filterBySubmissionIds([$submission->getId()]);
        $publications = Repo::publication()->getMany($collector);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $currentUserReviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer(
            $submission->getId(),
            $request->getUser()->getId()
        );
        $anonymize = $currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson([
            'itemsMax' => Repo::publication()->getCount($collector->limit(null)->offset(null)),
            'items' => Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->summarizeMany($publications, $anonymize),
        ], 200);
    }

    /**
     * Get one of this submission's publications
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getPublication($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Add a new publication to this submission
     *
     * This will create a new publication from scratch. If you want to create a new
     * version of a publication, see self::versionPublication().
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function addPublication($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $slimRequest->getParsedBody());
        $params['submissionId'] = $submission->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }
        $primaryLocale = $submissionContext->getPrimaryLocale();
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        // A publication may have a different primary locale
        if (!empty($params['locale']) && in_array($params['locale'], $allowedLocales)) {
            $primaryLocale = $params['locale'];
        }

        $errors = Repo::publication()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $publication = Repo::publication()->newDataObject($params);
        $newId = Repo::publication()->add($publication);
        $publication = Repo::publication()->get($newId);

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Create a new version of a publication
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function versionPublication($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $args['publicationId']);
        $context = $request->getContext();

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        $newId = Repo::publication()->version($publication, $request);
        $publication = Repo::publication()->get($newId);

        $notificationManager = new NotificationManager();
        $usersIterator = Repo::user()->getMany(
            Repo::user()->getCollector()
                ->filterByContextIds([$submission->getContextId()])
                ->assignedTo($submission->getId())
        );

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        foreach ($usersIterator as $user) {
            $notification = $notificationManager->createNotification(
                $request,
                $user->getId(),
                PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION,
                $submission->getContextId(),
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                Notification::NOTIFICATION_LEVEL_TASK,
            );

            // Check if user is subscribed to this type of notification emails
            if (!$notification || in_array(
                PKPNotification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $user->getId(),
                    (int) $context->getId()
                )
            )
            ) {
                continue;
            }

            $mailable = new PublicationVersionNotify($context, $submission, $notification);
            $template = Repo::emailTemplate()->getByKey($context->getId(), PublicationVersionNotify::getEmailTemplateKey());
            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->recipients([$user])
                ->body($template->getLocalizedData('body'))
                ->subject($template->getLocalizedData('subject'));

            Mail::send($mailable);
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Edit one of this submission's publications
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function editPublication($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
        }

        // Prevent users from editing publications if they do not have permission. Except for admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) && !Repo::submission()->canEditPublication($submission->getId(), $currentUser->getId())) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.userCantEdit');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $slimRequest->getParsedBody());
        $params['id'] = $publication->getId();

        // Don't allow the status to be modified through the API. The `/publish` and /unpublish endpoints
        // should be used instead.
        if (array_key_exists('status', $params)) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditStatus');
        }

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }
        $primaryLocale = $publication->getData('locale');
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        $errors = Repo::publication()->validate($publication, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::publication()->edit($publication, $params);

        $publication = Repo::publication()->get($publication->getId());

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Publish one of this submission's publications
     *
     * If this is a GET request, it will run the pre-publish validation
     * checks and return errors but it will not perform the final
     * publication step.
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function publishPublication($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.alreadyPublished');
        }

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }
        $primaryLocale = $submission->getData('locale');
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        $errors = Repo::publication()->validatePublish($publication, $submission, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::publication()->publish($publication);

        $publication = Repo::publication()->get($publication->getId());

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Unpublish one of this submission's publications
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function unpublishPublication($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if (!in_array($publication->getData('status'), [PKPSubmission::STATUS_PUBLISHED, PKPSubmission::STATUS_SCHEDULED])) {
            return $response->withStatus(403)->withJsonError('api.publication.403.alreadyUnpublished');
        }

        Repo::publication()->unpublish($publication);

        $publication = Repo::publication()->get($publication->getId());

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return $response->withJson(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            200
        );
    }

    /**
     * Delete one of this submission's publications
     *
     * Published publications can not be deleted. First you must unpublish them.
     * See self::unpublishPublication().
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function deletePublication($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantDeletePublished');
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($submission->getData('contextId'))->toArray();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        $output = Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication);

        Repo::publication()->delete($publication);

        return $response->withJson($output, 200);
    }

    /**
     * Get one of a publication's contributors
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getContributor($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $publication = Repo::publication()->get((int) $args['publicationId']);
        $author = Repo::author()->get((int) $args['contributorId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if (!$author) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if ($publication->getId() !== $author->getData('publicationId')) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        return $response->withJson(
            Repo::author()->getSchemaMap()->map($author),
            200
        );
    }

    /**
     * Get all publication's contributors
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getContributors($slimRequest, $response, $args)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        $collector = Repo::author()->getCollector();
        $collector->filterByPublicationIds([$publication->getId()]);
        $authors = Repo::author()->getMany($collector);

        return $response->withJson([
            'itemsMax' => Repo::author()->getCount($collector->limit(null)->offset(null)),
            'items' => Repo::author()->getSchemaMap()->summarizeMany($authors),
        ], 200);
    }

    /**
     * Add a new contributor to publication
     *
     * This will create a new contributor from scratch.
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function addContributor($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_AUTHOR, $slimRequest->getParsedBody());
        $params['publicationId'] = $publication->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }
        $primaryLocale = $submissionContext->getPrimaryLocale();
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        // A publication may have a different primary locale
        if (!empty($params['locale']) && in_array($params['locale'], $allowedLocales)) {
            $primaryLocale = $params['locale'];
        }

        $errors = Repo::author()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $author = Repo::author()->newDataObject($params);
        $newId = Repo::author()->add($author);
        $author = Repo::author()->get($newId);

        return $response->withJson(
            Repo::author()->getSchemaMap()->map($author),
            200
        );
    }

    /**
     * Delete one of this publication's contributors
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function deleteContributor($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $args['publicationId']);
        $author = Repo::author()->get((int) $args['contributorId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        if (!$author) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($publication->getId() !== $author->getData('publicationId')) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $output = Repo::author()->getSchemaMap()->map($author);

        Repo::author()->delete($author);

        return $response->withJson($output, 200);
    }

    /**
     * Edit one of this publication's contributors
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function editContributor($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $args['publicationId']);
        $author = Repo::author()->get((int) $args['contributorId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if (!$author) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_AUTHOR, $slimRequest->getParsedBody());
        $params['id'] = $author->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = Services::get('context')->get($submission->getData('contextId'));
        }

        $primaryLocale = $publication->getData('locale');
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        if ($publication->getId() !== $author->getData('publicationId')) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        // Prevent users from editing publications if they do not have permission. Except for admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) && !Repo::submission()->canEditPublication($submission->getId(), $currentUser->getId())) {
            return $response->withStatus(403)->withJsonError('api.submissions.403.userCantEdit');
        }

        $errors = Repo::author()->validate($author, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::author()->edit($author, $params);
        $author = Repo::author()->get($author->getId());

        return $response->withJson(
            Repo::author()->getSchemaMap()->map($author),
            200
        );
    }

    /**
     * Save new order of contributors array
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function saveContributorsOrder($slimRequest, $response, $args)
    {
        $params = $slimRequest->getParsedBody();

        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $args['publicationId']);

        if (!$publication) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
        }

        if (!empty($params['sortedAuthors'])) {
            $authors = [];
            foreach ($params['sortedAuthors'] as $author) {
                $newAuthor = Repo::author()->get((int) $author['id']);

                array_push($authors, $newAuthor);
            }

            Repo::author()->setAuthorsOrder($publication->getId(), $authors);
        }

        return $response->withJson($publication->getId());
    }

    /**
     * Record an editorial decision for a submission, such as
     * a decision to accept or reject the submission, request
     * revisions, or send it to another stage.
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function addDecision($slimRequest, $response, $args)
    {
        $request = $this->getRequest(); /** @var Request $request */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE); /** @var DecisionType $decisionType */

        if ($submission->getData('status') === Submission::STATUS_PUBLISHED) {
            return $response->withStatus(403)->withJsonError('api.decisions.403.alreadyPublished');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DECISION, $slimRequest->getParsedBody());
        $params['submissionId'] = $submission->getId();
        $params['dateDecided'] = Core::getCurrentDate();
        $params['editorId'] = $request->getUser()->getId();
        $params['stageId'] = $decisionType->getStageId();

        $errors = Repo::decision()->validate($params, $decisionType, $submission, $request->getContext());

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $decision = Repo::decision()->newDataObject($params);
        $decisionId = Repo::decision()->add($decision);
        $decision = Repo::decision()->get($decisionId);

        return $response->withJson(Repo::decision()->getSchemaMap()->map($decision), 200);
    }
}

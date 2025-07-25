<?php

/**
 * @file api/v1/submissions/PKPSubmissionController.php
 *
 * Copyright (c) 2023-2025 Simon Fraser University
 * Copyright (c) 2023-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionController
 *
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

namespace PKP\API\v1\submissions;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use APP\mail\variables\ContextEmailVariable;
use APP\notification\NotificationManager;
use APP\publication\Publication;
use APP\section\Section;
use APP\submission\Collector;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use PKP\affiliation\Affiliation;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\components\forms\publication\PKPPublicationIdentifiersForm;
use PKP\components\forms\publication\PKPPublicationLicenseForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\forms\submission\ChangeSubmissionLanguageMetadataForm;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\jobs\orcid\SendAuthorMail;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\mail\mailables\PublicationVersionNotify;
use PKP\mail\mailables\SubmissionSavedForLater;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\orcid\OrcidManager;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use APP\publication\enums\VersionStage;
use PKP\publication\helpers\PublicationVersionInfoResource;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DecisionWritePolicy;
use PKP\security\authorization\internal\SubmissionCompletePolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;
use PKP\userGroup\UserGroup;

class PKPSubmissionController extends PKPBaseController
{
    use AnonymizeData;

    /** @var int The default number of items to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int Max items that can be requested */
    public const MAX_COUNT = 100;

    /** Handlers that must be authorized to access a submission */
    public array $requiresSubmissionAccess = [
        'get',
        'edit',
        'saveForLater',
        'submit',
        'delete',
        'changeLocale',
        'getGalleys',
        'getDecisions',
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
        'getPublicationReferenceForm',
        'getPublicationMetadataForm',
        'getPublicationIdentifierForm',
        'getPublicationLicenseForm',
        'getPublicationTitleAbstractForm',
        'getChangeLanguageMetadata',
        'changeVersion',
        'getNextAvailableVersion',
    ];

    /** @var array Handlers that must be authorized to write to a publication */
    public $requiresPublicationWriteAccess = [
        'editPublication',
        'addContributor',
        'deleteContributor',
        'editContributor',
        'saveContributorsOrder',
        'changeLocale',
        'changeVersion'
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
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {

            Route::get('', $this->getMany(...))
                ->name('submission.getMany');

            Route::get('{submissionId}', $this->get(...))
                ->name('submission.getSubmission')
                ->whereNumber('submissionId');

            Route::get('{submissionId}/publications', $this->getPublications(...))
                ->name('submission.publications.getMany')
                ->whereNumber('submissionId');

            Route::get('{submissionId}/publications/{publicationId}', $this->getPublication(...))
                ->name('submission.publications.get')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::get('{submissionId}/publications/{publicationId}/contributors', $this->getContributors(...))
                ->name('submission.publication.contributor.getMany')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::get('{submissionId}/publications/{publicationId}/contributors/{contributorId}', $this->getContributor(...))
                ->name('submission.publication.contributor.get')
                ->whereNumber(['submissionId', 'publicationId', 'contributorId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {
            Route::get('{submissionId}/participants', $this->getParticipants(...))
                ->name('submission.participants.getMany')
                ->whereNumber('submissionId');

            Route::get('{submissionId}/participants/{stageId}', $this->getParticipants(...))
                ->name('submission.participants.stage.getMany')
                ->whereNumber(['submissionId', 'stageId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ])->group(function () {

            Route::get('{submissionId}/decisions', $this->getDecisions(...))
                ->name('submission.decisions.getMany')
                ->whereNumber('submissionId');

            Route::post('{submissionId}/decisions', $this->addDecision(...))
                ->name('submission.decision.add')
                ->whereNumber('submissionId');

            Route::delete('{submissionId}', $this->delete(...))
                ->name('submission.delete')
                ->whereNumber('submissionId');

            Route::put('{submissionId}/publications/{publicationId}/changeLocale', $this->changeLocale(...))
                ->name('submission.publication.changeLocale')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/version', $this->changeVersion(...))
                ->name('submission.publication.version')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::get('{submissionId}/nextAvailableVersion', $this->getNextAvailableVersion(...))
                ->name('submission.getNextAvailableVersion')
                ->whereNumber(['submissionId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {

            Route::post('{submissionId}/publications', $this->addPublication(...))
                ->name('submission.publication.add')
                ->whereNumber('submissionId');

            Route::post('{submissionId}/publications/{publicationId}/version', $this->versionPublication(...))
                ->name('submission.publication.version.get')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/publish', $this->publishPublication(...))
                ->name('submission.publication.publish')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/unpublish', $this->unpublishPublication(...))
                ->name('submission.publication.unpublish')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::delete('{submissionId}/publications/{publicationId}', $this->deletePublication(...))
                ->name('submission.publication.delete')
                ->whereNumber(['submissionId', 'publicationId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {

            Route::post('{submissionId}/publications/{publicationId}/contributors', $this->addContributor(...))
                ->name('submission.contributor.add')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}', $this->editPublication(...))
                ->name('submission.publication.edit')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/contributors/{contributorId}', $this->editContributor(...))
                ->name('submission.publication.contributor.edit')
                ->whereNumber(['submissionId', 'publicationId', 'contributorId']);

            Route::put('{submissionId}/publications/{publicationId}/contributors/saveOrder', $this->saveContributorsOrder(...))
                ->name('submission.publication.contributor.order.save')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::delete('{submissionId}/publications/{publicationId}/contributors/{contributorId}', $this->deleteContributor(...))
                ->name('submission.publication.contributor.delete')
                ->whereNumber(['submissionId', 'publicationId', 'contributorId']);

            Route::prefix('{submissionId}/publications/{publicationId}/_components')->group(function () {
                Route::get('metadata', $this->getPublicationMetadataForm(...))->name('submission.publication._components.metadata');
                Route::get('reference', $this->getPublicationReferenceForm(...))->name('submission.publication._components.reference');
                Route::get('titleAbstract', $this->getPublicationTitleAbstractForm(...))->name('submission.publication._components.titleAbstract');
                Route::get('changeLanguageMetadata', $this->getChangeLanguageMetadata(...))->name('submission.publication._components.changeLanguageMetadata');
            })->whereNumber(['submissionId', 'publicationId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {
            Route::prefix('{submissionId}/publications/{publicationId}/_components')->group(function () {
                Route::get('identifier', $this->getPublicationIdentifierForm(...))->name('submission.publication._components.identifiers');
                Route::get('permissionDisclosure', $this->getPublicationLicenseForm(...))->name('submission.publication._components.permissionDisclosure');
            })->whereNumber(['submissionId', 'publicationId']);
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {

            Route::put('{submissionId}', $this->edit(...))
                ->name('submission.edit')
                ->whereNumber('submissionId');

            Route::put('{submissionId}/saveForLater', $this->saveForLater(...))
                ->name('submission.saveForLater')
                ->whereNumber('submissionId');

            Route::put('{submissionId}/submit', $this->submit(...))
                ->name('submission.submit')
                ->whereNumber('submissionId');
        });

        Route::post('', $this->add(...))
            ->name('submission.add')
            ->middleware([
                self::roleAuthorizer(Role::getAllRoles()),
            ]);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array($actionName, $this->requiresSubmissionAccess)) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        if (in_array($actionName, $this->requiresPublicationWriteAccess)) {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

        if (in_array($actionName, $this->requiresProductionStageAccess)) {
            $this->addPolicy(new StageRolePolicy($this->productionStageAccessRoles, WORKFLOW_STAGE_ID_PRODUCTION, false));
        }

        if ($actionName === 'addDecision') {
            $this->addPolicy(new SubmissionCompletePolicy($request, $args));
            $this->addPolicy(new DecisionWritePolicy($request, $args, (int) $request->getUserVar('decision'), $request->getUser()));
        }

        if (in_array(
            $actionName,
            [
                'getPublicationReferenceForm',
                'getPublicationMetadataForm',
                'getPublicationIdentifierForm',
                'getPublicationLicenseForm',
                'getPublicationTitleAbstractForm',
                'getChangeLanguageMetadata'
            ]
        )) {
            $this->addPolicy(new SubmissionCompletePolicy($request, $args));
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of submissions
     *
     * @hook API::submissions::params [$collector, $illuminateRequest]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $currentUser = $request->getUser();
        $context = $request->getContext();

        $collector = $this->getSubmissionCollector($illuminateRequest->query());

        Hook::run('API::submissions::params', [$collector, $illuminateRequest]);

        // Prevent users from viewing submissions they're not assigned to,
        // except for journal managers and admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
        if (!$canAccessUnassignedSubmission) {
            if (!is_array($collector->assignedTo)) {
                $collector->assignedTo([$currentUser->getId()]);
            } elseif ($collector->assignedTo != [$currentUser->getId()]) {
                return response()->json([
                    'error' => __('api.submissions.403.requestedOthersUnpublishedSubmissions'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $submissions = $collector->getMany();

        $anonymizeReviews = $this->anonymizeReviews($submissions);

        $userGroups = UserGroup::withContextIds($context->getId())->cursor();

        $genres = Repo::genre()->getByContextId($context->getId());

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::submission()->getSchemaMap()->summarizeMany($submissions, $userGroups, $genres->all(), $anonymizeReviews)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Configure a submission Collector based on the query params
     */
    protected function getSubmissionCollector(array $queryParams): Collector
    {
        $request = $this->getRequest();
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
                    $collector->filterByCategoryIds(array_map(intval(...), paramToArray($val)));
                    break;

                case 'status':
                    $collector->filterByStatus(array_map(intval(...), paramToArray($val)));
                    break;

                case 'stageIds':
                    $collector->filterByStageIds(array_map(intval(...), paramToArray($val)));
                    break;

                case 'assignedTo':
                    $collector->assignedTo(array_map(intval(...), paramToArray($val)));
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
                    $collector->filterByDoiStatuses(array_map(intval(...), paramToArray($val)));
                    break;
                case 'hasDois':
                    $collector->filterByHasDois((bool) $val, $context->getEnabledDoiTypes());
                    break;
                case 'isUnassigned':
                    $collector->filterByisUnassigned(true);
                    break;
            }
        }

        return $collector;
    }

    /**
     * Get a single submission
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        // Anonymize sensitive review assignment data if user is a reviewer or author assigned to the article and review isn't open
        $reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$submission->getId()])->getMany()->remember();

        $anonymizeReviews = $this->anonymizeReviews($submission, $reviewAssignments);

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));


        return response()->json(Repo::submission()->getSchemaMap()->map(
            $submission,
            $userGroups,
            $genres->all(),
            $userRoles,
            $reviewAssignments,
            null,
            null,
            !$anonymizeReviews || $anonymizeReviews->isEmpty() ? false : $anonymizeReviews
        ), Response::HTTP_OK);
    }

    /**
     * Add a new submission
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();

        if ($context->getData('disableSubmissions')) {
            return response()->json([
                'error' => __('author.submit.notAccepting'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION, $illuminateRequest->input());

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_SUBMISSION, $params);
        if (!empty($readOnlyErrors)) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $params['contextId'] = $context->getId();

        $errors = Repo::submission()->validate(null, $params, $context);

        $sectionIdPropName = Application::getSectionIdPropName();
        if (isset($params[$sectionIdPropName])) {
            $sectionId = $params[$sectionIdPropName];
            $section = Repo::section()->get($sectionId, $context->getId());

            if (!$section) {
                $errors[$sectionIdPropName] = [__('api.submission.400.sectionDoesNotExist')];
            } else {
                if ($section->getIsInactive()) {
                    $errors[$sectionIdPropName] = [__('api.submission.400.inactiveSection')];
                } else {
                    if ($section->getEditorRestricted() && !$this->isEditor()) {
                        $errors[$sectionIdPropName] = [__('submission.sectionRestrictedToEditors')];
                    }
                }
            }
        }
        $submitterUserGroups = UserGroup::withContextIds($context->getId())
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR])
            ->whereHas('userUserGroups', function ($query) use ($user) {
                $query->withUserId($user->getId());
            })
            ->get();


        $userGroupIdPropName = 'userGroupId';

        if (isset($params[$userGroupIdPropName])) {
            $submitAsUserGroup = $submitterUserGroups
                ->first(function (UserGroup $userGroup) use ($params, $userGroupIdPropName) {
                    return $userGroup->id === $params[$userGroupIdPropName];
                });
            if (!$submitAsUserGroup) {
                $errors[$userGroupIdPropName] = [__('api.submissions.400.invalidSubmitAs')];
            }
        } elseif ($submitterUserGroups->count()) {
            $submitAsUserGroup = $submitterUserGroups
                ->sort(function (UserGroup $a, UserGroup $b) {
                    return ((int)$a->roleId) === Role::ROLE_ID_AUTHOR ? -1 : 1;
                })
                ->first();
        } else {
            $submitAsUserGroup = UserGroup::withContextIds($context->getId())->withRoleIds(Role::ROLE_ID_AUTHOR)->first();
            if (!$submitAsUserGroup) {
                $errors[$userGroupIdPropName] = [__('submission.wizard.notAllowed.description')];
            } else {
                Repo::userGroup()->assignUserToGroup(
                    $user->getId(),
                    $submitAsUserGroup->id
                );
            }
        }

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $publicationProps = [];
        if (isset($params[$sectionIdPropName])) {
            $publicationProps[$sectionIdPropName] = $params[$sectionIdPropName];
            unset($params[$sectionIdPropName]);
        }

        $submission = Repo::submission()->newDataObject($params);
        $publication = Repo::publication()->newDataObject($publicationProps);
        $submissionId = Repo::submission()->add($submission, $publication, $request->getContext());

        $submission = Repo::submission()->get($submissionId);

        // Assign submitter to submission
        Repo::stageAssignment()
            ->build(
                $submission->getId(),
                $submitAsUserGroup->id,
                $request->getUser()->getId(),
                $submitAsUserGroup->recommendOnly,
                // Authors can always edit metadata before submitting
                $submission->getData('submissionProgress')
                    ? true
                    : $submitAsUserGroup->permitMetadataEdit
            );

        // Create an author record from the submitter's user account
        if ($submitAsUserGroup->roleId === Role::ROLE_ID_AUTHOR) {
            $author = Repo::author()->newAuthorFromUser($request->getUser(), $submission, $context);
            $author->setData('publicationId', $publication->getId());
            $author->setUserGroupId($submitAsUserGroup->id);
            $authorId = Repo::author()->add($author);
            Repo::publication()->edit($publication, ['primaryContactId' => $authorId]);
        }

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        if (!$userGroups instanceof LazyCollection) {
            $userGroups = $userGroups->lazy();
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        return response()->json(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres->all(), $userRoles), Response::HTTP_OK);
    }

    /**
     * Edit a submission
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION, $illuminateRequest->input());

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_SUBMISSION, $params);
        if (!empty($readOnlyErrors)) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $params['id'] = $submission->getId();
        $params['contextId'] = $request->getContext()->getId();

        $errors = Repo::submission()->validate($submission, $params, $request->getContext());

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::submission()->edit($submission, $params);

        $submission = Repo::submission()->get($submission->getId());

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        return response()->json(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres->all(), $userRoles), Response::HTTP_OK);
    }

    /**
     * Save a submission for later
     *
     * Saves the current step and sends the submitter an
     * email with a link to resume their submission.
     */
    public function saveForLater(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $params = $illuminateRequest->input();
        if (!empty($params['step'])) {
            if (!ctype_alnum(str_replace(['-', '_'], '', $params['step']))) {
                return response()->json([
                    'step' => [__('validator.alpha_dash')]
                ], Response::HTTP_BAD_REQUEST);
            }

            Repo::submission()->edit($submission, ['submissionProgress' => $params['step']]);
        }

        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), SubmissionSavedForLater::getEmailTemplateKey());
        $mailable = new SubmissionSavedForLater($context, $submission);
        $mailable
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$request->getUser()])
            // The template may not exist, see pkp/pkp-lib#9217
            ->subject($emailTemplate?->getLocalizedData('subject') ?? __('emails.submissionSavedForLater.subject'))
            ->body($emailTemplate?->getLocalizedData('body') ?? __('emails.submissionSavedForLater.body'));

        if (!$emailTemplate) {
            $templateVariables = $mailable->getData();
            $mailable->addData([
                'contextName' => $templateVariables[ContextEmailVariable::CONTEXT_NAME],
                'contextUrl' => $templateVariables[ContextEmailVariable::CONTEXT_URL],
            ]);
        }

        Mail::send($mailable);

        $submission = Repo::submission()->get($submission->getId());
        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        return response()->json(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres->all(), $userRoles), Response::HTTP_OK);
    }

    /**
     * Submit a submission
     *
     * Submits a submission by changing its `submissionProgress` property.
     *
     * Pass the `_validateOnly` property to validate the submission without submitting it.
     */
    public function submit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        /** @var Submission $submission*/
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getCurrentPublication();

        $errors = Repo::submission()->validateSubmit($submission, $context);

        /** @var int $sectionId */
        $sectionId = $publication->getData(Application::getSectionIdPropName());

        if ($sectionId) {
            $section = Repo::section()->get($sectionId, $context->getId());
        }

        if (isset($section) &&
            (
                $section->getIsInactive() ||
                ($section->getEditorRestricted() && !$this->isEditor())
            )
        ) {
            $errors[Application::getSectionIdPropName()] = __('submission.wizard.sectionClosed.message', [
                'contextName' => $context->getLocalizedData('name'),
                'section' => $section->getLocalizedTitle(),
                'email' => $context->getData('contactEmail'),
                'name' => $context->getData('contactName'),
            ]);
        }

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        if ($illuminateRequest->input('_validateOnly')) {
            return response()->json([], Response::HTTP_OK);
        }

        Repo::submission()->submit($submission, $context);

        $submission = Repo::submission()->get($submission->getId());

        if ($illuminateRequest->input('confirmCopyright')) {
            $user = $request->getUser();
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_COPYRIGHT_AGREED,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'submission.event.copyrightAgreed',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'username' => $user->getUsername(),
                'userFullName' => $user->getFullName(),
                'copyrightNotice' => $context->getData('copyrightNotice'),
            ]);

            Repo::eventLog()->add($eventLog);
        }

        $contextId = $context->getId();
        $userGroups = UserGroup::withContextIds($contextId)->cursor();


        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        $notificationManager = new NotificationManager();
        $notificationManager->updateNotification(
            Application::get()->getRequest(),
            [Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION],
            null,
            PKPApplication::ASSOC_TYPE_SUBMISSION,
            $submission->getId()
        );

        return response()->json(Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres->all(), $userRoles), Response::HTTP_OK);
    }

    /**
     * Delete a submission
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();


        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        $submissionProps = Repo::submission()->getSchemaMap()->map($submission, $userGroups, $genres->all(), $userRoles);

        Repo::submission()->delete($submission);

        return response()->json($submissionProps, Response::HTTP_OK);
    }

    /**
     * Change submission language
     */
    public function changeLocale(Request $illuminateRequest): JsonResponse
    {
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $paramsSubmission = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION, $illuminateRequest->input());
        $newLocale = $paramsSubmission['locale'] ?? null;

        // Submission language can not be changed when there are more than one publication or a publication's status is published
        if (!$newLocale || count($submission->getData('publications')) > 1 || $publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json(['error' => __('api.submission.403.cantChangeSubmissionLanguage')], Response::HTTP_FORBIDDEN);
        }

        // Convert a form field value to multilingual (if it is not) and merge rest values
        collect(app()->get('schema')->getMultilingualProps(PKPSchemaService::SCHEMA_PUBLICATION))
            ->each(
                fn (string $prop) =>
                $illuminateRequest->whenHas(
                    $prop,
                    fn ($value) =>
                    $illuminateRequest->merge([
                        $prop => array_merge(
                            $publication->getData($prop) ?? [],
                            (is_array($value) && array_key_exists($newLocale, $value)) ? $value : [$newLocale => $value]
                        )
                    ])
                )
            );

        $responsePublication = $this->editPublication($illuminateRequest);

        if ($responsePublication->status() !== 200) {
            return $responsePublication;
        }

        $this->copyMultilingualData($submission, $newLocale);

        return $this->edit($illuminateRequest);
    }

    /**
     * Change version data for publication
     */
    public function changeVersion(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getId() != $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $illuminateRequest->input());

        $submissionContext = $request->getContext();

        $errors = Repo::publication()->validate($publication, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $versionStage = $this->validateVersionStage($illuminateRequest);
        $versionIsMinor = $this->validateVersionIsMinor($illuminateRequest);

        Repo::publication()->updateVersion($publication, $versionStage, $versionIsMinor);

        return $this->edit($illuminateRequest);
    }

    /**
     * Get next potential version for submission
     */
    public function getNextAvailableVersion(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $versionStage = $this->validateVersionStage($illuminateRequest);
        $versionIsMinor = $this->validateVersionIsMinor($illuminateRequest);

        $potentialVersionInfo = Repo::submission()->getNextAvailableVersion($submission, $versionStage, $versionIsMinor);

        return response()->json(
            (new PublicationVersionInfoResource($potentialVersionInfo))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    /**
     * Get the decisions recorded on a submission
     */
    public function getDecisions(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || $submission->getData('contextId') !== $context->getId()) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $decisionTypes = array_map(intval(...), paramToArray($illuminateRequest->input('decisionTypes') ?? []));
        $editorIds = array_map(intval(...), paramToArray($illuminateRequest->input('editorIds') ?? []));
        $reviewRoundId = $illuminateRequest->input('reviewRoundId') ? [(int)$illuminateRequest->input('reviewRoundId')] : null;
        $stageId = $illuminateRequest->input('stageId') ? [(int)$illuminateRequest->input('stageId')] : null;

        $collector = Repo::decision()->getCollector();
        $decisionIterator = $collector
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByDecisionTypes(!empty($decisionTypes) ? $decisionTypes : null)
            ->filterByEditorIds(!empty($editorIds) ? $editorIds : null)
            ->filterByReviewRoundIds($reviewRoundId)
            ->filterByStageIds($stageId)
            ->orderBy($collector::ORDERBY_DATE_DECIDED, $collector::ORDER_DIR_DESC)
            ->getMany();

        $data = Repo::decision()
            ->getSchemaMap()
            ->mapMany($decisionIterator->values());

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get the participants assigned to a submission
     */
    public function getParticipants(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $args = $illuminateRequest->input();
        $stageId = $args['stageId'] ?? $illuminateRequest->route('stageId') !== null ? (int) $illuminateRequest->route('stageId') : null;

        if (!$submission || $submission->getData('contextId') !== $context->getId()) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [];

        $usersIterator = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->assignedTo($submission->getId(), $stageId)
            ->getMany();

        $map = Repo::user()->getSchemaMap();
        foreach ($usersIterator as $user) {
            $data[] = $map->summarizeReviewer($user, ['submission' => $submission, 'stageId' => $stageId]);
        }

        return response()->json($data, Response::HTTP_OK);
    }


    /**
     * Get all of this submissions's publications
     */
    public function getPublications(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $collector = Repo::publication()->getCollector()
            ->filterBySubmissionIds([$submission->getId()]);

        $publications = $collector->getMany();

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $currentUserReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByReviewerIds([$request->getUser()->getId()], true)
            ->getMany()
            ->first();

        $anonymize = $currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));


        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->summarizeMany($publications, $anonymize)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Get one of this submission's publications
     */
    public function getPublication(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->get();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Add a new publication to this submission
     */
    public function addPublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $illuminateRequest->input());
        $params['submissionId'] = $submission->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $errors = Repo::publication()->validate(null, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $publication = Repo::publication()->newDataObject($params);
        $newId = Repo::publication()->add($publication);
        $publication = Repo::publication()->get($newId);

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Create a new version of a publication
     */
    public function versionPublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));
        $context = $request->getContext();

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $versionStageStr = $illuminateRequest->input('versionStage');
        $versionStage = $versionStageStr ? VersionStage::tryFrom($versionStageStr) : null;

        $versionIsMinor = $this->validateVersionIsMinor($illuminateRequest);

        $newId = Repo::publication()->version($publication, $versionStage, $versionIsMinor);
        $publication = Repo::publication()->get($newId);

        $notificationManager = new NotificationManager();
        $usersIterator = Repo::user()->getCollector()
            ->filterByContextIds([$submission->getData('contextId')])
            ->assignedTo($submission->getId())
            ->getMany();

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        foreach ($usersIterator as $user) {
            $notification = $notificationManager->createNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION,
                $submission->getData('contextId'),
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                Notification::NOTIFICATION_LEVEL_TASK,
            );

            // Check if user is subscribed to this type of notification emails
            if (!$notification || in_array(
                Notification::NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $user->getId(),
                    (int) $context->getId()
                )
            )) {
                continue;
            }

            $mailable = new PublicationVersionNotify($context, $submission);
            $template = Repo::emailTemplate()->getByKey($context->getId(), PublicationVersionNotify::getEmailTemplateKey());
            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->recipients([$user])
                ->body($template->getLocalizedData('body'))
                ->subject($template->getLocalizedData('subject'))
                ->allowUnsubscribe($notification);

            Mail::send($mailable);
        }

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Edit one of this submission's publications
     */
    public function editPublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantEditPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Prevent users from editing publications if they do not have permission. Except for admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) && !Repo::submission()->canEditPublication($submission->getId(), $currentUser->getId())) {
            return response()->json([
                'error' => __('api.submissions.403.userCantEdit'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $illuminateRequest->input());
        $params['id'] = $publication->getId();

        // Don't allow the status to be modified through the API. The `/publish` and /unpublish endpoints
        // should be used instead.
        if (array_key_exists('status', $params)) {
            return response()->json([
                'error' => __('api.publication.403.cantEditStatus'),
            ], Response::HTTP_FORBIDDEN);
        }

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $errors = Repo::publication()->validate($publication, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::publication()->edit($publication, $params);
        $publication = Repo::publication()->get($publication->getId());

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));


        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Publish one of this submission's publications
     *
     * If this is a GET request, it will run the pre-publish validation
     * checks and return errors but it will not perform the final
     * publication step.
     */
    public function publishPublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.alreadyPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }
        $primaryLocale = $submission->getData('locale');
        $allowedLocales = $submissionContext->getData('supportedSubmissionLocales');

        $errors = Repo::publication()->validatePublish($publication, $submission, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::publication()->publish($publication);

        $publication = Repo::publication()->get($publication->getId());

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();


        $genres = Repo::genre()
                    ->getByContextId($submission->getData('contextId'));

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Unpublish one of this submission's publications
     */
    public function unpublishPublication(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (!in_array($publication->getData('status'), [PKPSubmission::STATUS_PUBLISHED, PKPSubmission::STATUS_SCHEDULED])) {
            return response()->json([
                'error' => __('api.publication.403.alreadyUnpublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        Repo::publication()->unpublish($publication);

        $publication = Repo::publication()->get($publication->getId());

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();


        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Delete one of this submission's publications
     *
     * Published publications can not be deleted. First you must unpublish them.
     * See self::unpublishPublication().
     */
    public function deletePublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantDeletePublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        $userGroups = UserGroup::withContextIds($submission->getData('contextId'))->cursor();

        $genres = Repo::genre()->getByContextId($submission->getData('contextId'));


        $output = Repo::publication()->getSchemaMap($submission, $userGroups, $genres->all())->map($publication);

        Repo::publication()->delete($publication);

        return response()->json($output, Response::HTTP_OK);
    }

    /**
     * Get one of a publication's contributors
     */
    public function getContributor(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));
        $author = Repo::author()->get((int) $illuminateRequest->route('contributorId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$author) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($publication->getId() !== $author->getData('publicationId')) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::author()->getSchemaMap()->map($author),
            Response::HTTP_OK
        );
    }

    /**
     * Get all publication's contributors
     */
    public function getContributors(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        $collector = Repo::author()->getCollector()
            ->filterByPublicationIds([$publication->getId()]);
        $authors = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::author()->getSchemaMap()->summarizeMany($authors)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add a new contributor to publication
     *
     * This will create a new contributor from scratch.
     */
    public function addContributor(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantEditPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_AUTHOR, $illuminateRequest->input());

        // Allows author ORCID request email to be triggered from frontend before author ID exists
        $shouldSendOrcidEmail = false;
        switch ($params['orcid']) {
            case null:
                unset($params['orcid']);
                break;
            case 'shouldRequestVerification':
                unset($params['orcid']);
                $shouldSendOrcidEmail = true;
                break;
        }

        $params['publicationId'] = $publication->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $errors = Repo::author()->validate(null, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $affiliationParams = $params['affiliations'];
        unset($params['affiliations']);
        $author = Repo::author()->newDataObject($params);
        $newId = Repo::author()->add($author);

        $affiliations = $newAffiliationErrors = [];
        foreach ($affiliationParams as $position => $affiliationParam) {
            $affiliationParam['authorId'] = $newId;
            $affiliationErrors = Repo::affiliation()->validate(null, $affiliationParam, $submission, $submissionContext);
            // Map errors to the specific affiliation in the UI using the position = index
            if (!empty($affiliationErrors)) {
                $newAffiliationErrors['affiliations'][$position] = $affiliationErrors;
            }
            $affiliation = Repo::affiliation()->newDataObject($affiliationParam);
            $affiliations[] = $affiliation;
        }

        if (!empty($newAffiliationErrors)) {
            return response()->json($newAffiliationErrors, Response::HTTP_BAD_REQUEST);
        }

        foreach ($affiliations as $affiliation) {
            Repo::affiliation()->add($affiliation);
        }

        $author = Repo::author()->get($newId);

        // Dispatches author ORCID email now that the new user exists
        if ($shouldSendOrcidEmail) {
            try {
                $author->setData('orcidVerificationRequested', true);
                Repo::author()->edit($author, ['orcidVerificationRequested']);
                dispatch(new SendAuthorMail($author, $submissionContext, true));
            } catch (\Exception $exception) {
                OrcidManager::logError("Could not send email to new author with authorId: {$author->getId()}. Reason: $exception");
            }
        }

        return response()->json(
            Repo::author()->getSchemaMap()->map($author),
            Response::HTTP_OK
        );
    }

    /**
     * Delete one of this publication's contributors
     */
    public function deleteContributor(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));
        $author = Repo::author()->get((int) $illuminateRequest->route('contributorId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantEditPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$author) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($publication->getId() !== $author->getData('publicationId')) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $output = Repo::author()->getSchemaMap()->map($author);

        Repo::author()->delete($author);

        return response()->json($output, Response::HTTP_OK);
    }

    /**
     * Edit one of this publication's contributors
     */
    public function editContributor(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));
        $author = Repo::author()->get((int) $illuminateRequest->route('contributorId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$author) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantEditPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_AUTHOR, $illuminateRequest->input());
        $params['id'] = $author->getId();

        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        if ($publication->getId() !== $author->getData('publicationId')) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Prevent users from editing publications if they do not have permission. Except for admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) && !Repo::submission()->canEditPublication($submission->getId(), $currentUser->getId())) {
            return response()->json([
                'error' => __('api.submissions.403.userCantEdit'),
            ], Response::HTTP_FORBIDDEN);
        }

        $errors = Repo::author()->validate($author, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $affiliations = $newAffiliationErrors = [];
        foreach ($params['affiliations'] as $position => $affiliationParam) {
            $affiliationErrors = Repo::affiliation()->validate(null, $affiliationParam, $submission, $submissionContext);
            // Map errors to the specific affiliation in the UI using the position = index
            if (!empty($affiliationErrors)) {
                $newAffiliationErrors['affiliations'][$position] = $affiliationErrors;
            }
            // Create a new affiliation object even if an existing affiliation is edited.
            // This way we will have the list of all actual affiliations from the edited form.
            // Later, in Repo::affiliation()->saveAffiliations(), we compare this list with
            // the author affiliations that exist in the DB, to see if any was removed in the edited form.
            $affiliation = Repo::affiliation()->newDataObject($affiliationParam);
            $affiliations[] = $affiliation;
        }

        if (!empty($newAffiliationErrors)) {
            return response()->json($newAffiliationErrors, Response::HTTP_BAD_REQUEST);
        }

        $author->setAffiliations($affiliations);
        // remove affiliations parameters because we have already set them properly for the author
        // so that they are not considered once again when editing the author below
        unset($params['affiliations']);

        Repo::author()->edit($author, $params);
        $author = Repo::author()->get($author->getId());

        return response()->json(
            Repo::author()->getSchemaMap()->map($author),
            Response::HTTP_OK
        );
    }

    /**
     * Save new order of contributors array
     */
    public function saveContributorsOrder(Request $illuminateRequest): JsonResponse
    {
        $params = $illuminateRequest->input();

        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $currentUser = $request->getUser();

        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Publications can not be edited when they are published
        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.publication.403.cantEditPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (!empty($params['sortedAuthors'])) {
            $authors = [];
            foreach ($params['sortedAuthors'] as $author) {
                $newAuthor = Repo::author()->get((int) $author['id']);

                array_push($authors, $newAuthor);
            }

            Repo::author()->setAuthorsOrder($publication->getId(), $authors);
        }

        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        $authorsArray = Repo::author()->getSchemaMap()->summarizeMany($authors)->toArray();
        $indexedArray = array_values($authorsArray);

        return response()->json(
            $indexedArray,
            Response::HTTP_OK
        );
    }

    /**
     * Record an editorial decision for a submission, such as
     * a decision to accept or reject the submission, request
     * revisions, or send it to another stage.
     */
    public function addDecision(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE); /** @var DecisionType $decisionType */

        if ($submission->getData('status') === Submission::STATUS_PUBLISHED) {
            return response()->json([
                'error' => __('api.decisions.403.alreadyPublished'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DECISION, $illuminateRequest->input());
        $params['submissionId'] = $submission->getId();
        $params['dateDecided'] = Core::getCurrentDate();
        $params['editorId'] = $request->getUser()->getId();
        $params['stageId'] = $decisionType->getStageId();

        $errors = Repo::decision()->validate($params, $decisionType, $submission, $request->getContext());

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $decision = Repo::decision()->newDataObject($params);
        $decisionId = Repo::decision()->add($decision);

        // In some cases, recording a decision may delete the decision. This
        // happens for example with the Cancel Review Round decision. When
        // the decision is added, the review round is deleted and all decisions
        // related to that round are deleted. In such cases, we return the
        // original Decision object rather than fetching it from the data store.
        $decision = Repo::decision()->get($decisionId) ?? $decision;

        return response()->json(Repo::decision()->getSchemaMap()->map($decision), Response::HTTP_OK);
    }

    protected function getFirstUserGroupInRole(Enumerable $userGroups, int $role): ?UserGroup
    {
        return $userGroups->first(fn (UserGroup $userGroup) => $userGroup->roleId === $role);
    }

    /**
     * Is the current user an editor
     */
    protected function isEditor(): bool
    {
        return !empty(
            array_intersect(
                Section::getEditorRestrictedRoles(),
                $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
            )
        );
    }

    /**
     * This method returns errors for any params that match
     * properties in the schema with writeDisabledInApi set to true.
     *
     * This is used for properties that can not be edited through
     * the API, but which otherwise can be edited by the entity's
     * repository.
     */
    protected function getWriteDisabledErrors(string $schemaName, array $params): array
    {
        $schema = app()->get('schema')->get($schemaName);

        $writeDisabledProps = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->writeDisabledInApi)) {
                $writeDisabledProps[] = $propName;
            }
        }

        $errors = [];

        $notAllowedProps = array_intersect(
            $writeDisabledProps,
            array_keys($params)
        );

        if (!empty($notAllowedProps)) {
            foreach ($notAllowedProps as $propName) {
                $errors[$propName] = [__('api.400.propReadOnly', ['prop' => $propName])];
            }
        }

        return $errors;
    }

    /**
     * Get Publication Metadata Form component
     */
    protected function getPublicationMetadataForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $context = $data['context']; /** @var Context $context*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/

        $submissionLocale = $submission->getData('locale');
        $locales = $this->getPublicationFormLocales($context, $submission);

        $request = $this->getRequest();
        $vocabSuggestionUrlBase = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $context->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__', 'submissionId' => $submission->getId()]);

        $metadataForm = new PKPMetadataForm($publicationApiUrl, $locales, $publication, $context, $vocabSuggestionUrlBase);

        return response()->json($this->getLocalizedForm($metadataForm, $submissionLocale, $locales), Response::HTTP_OK);
    }

    /**
     * Get Publication Reference/Citation Form component
     */
    protected function getPublicationReferenceForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $publication = $data['publication']; /** @var Publication $publication*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/

        $citationsForm = new PKPCitationsForm($publicationApiUrl, $publication);

        return response()->json($citationsForm->getConfig(), Response::HTTP_OK);
    }


    /**
     * Get Publication License Form component
     */
    protected function getPublicationLicenseForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $context = $data['context']; /** @var Context $context*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/

        $submissionLocale = $submission->getData('locale');
        $locales = $this->getPublicationFormLocales($context, $submission);
        $authorUserGroups = UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withContextIds([$submission->getData('contextId')])
            ->get();
        $publicationLicenseForm = new PKPPublicationLicenseForm($publicationApiUrl, $locales, $publication, $context, $authorUserGroups);

        return response()->json($this->getLocalizedForm($publicationLicenseForm, $submissionLocale, $locales), Response::HTTP_OK);
    }

    /**
     * Get Publication Identifier Form.
     */
    protected function getPublicationIdentifierForm(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        // Check if there are any enabled Identifiers
        $identifiersEnabled = false;
        $pubIdPlugins = PluginRegistry::getPlugins('pubIds');
        foreach ($pubIdPlugins as $pubIdPlugin) {
            if ($pubIdPlugin->isObjectTypeEnabled('Publication', $request->getContext()->getId())) {
                $identifiersEnabled = true;
                break;
            }
        }

        if (!$identifiersEnabled) {
            return response()->json([
                'error' => __('api.publications.403.noEnabledIdentifiers'),
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $context = $data['context']; /** @var Context $context*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/
        $locales = $this->getPublicationFormLocales($context, $submission);

        $identifiersForm = new PKPPublicationIdentifiersForm($publicationApiUrl, $locales, $publication, $context);

        return response()->json($identifiersForm->getConfig(), Response::HTTP_OK);
    }

    /**
     * Get Publication TitleAbstract Form component
    */
    protected function getPublicationTitleAbstractForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $submission = $data['submission']; /** @var Submission $submission */
        $locales = $this->getPublicationFormLocales($data['context'], $submission);
        $submissionLocale = $submission->getData('locale');
        $titleAbstract = new TitleAbstractForm($data['publicationApiUrl'], $locales, $data['publication']);

        return response()->json($this->getLocalizedForm($titleAbstract, $submissionLocale, $locales), Response::HTTP_OK);
    }

    /**
     * Get ChangeSubmissionLanguageMetadata Form component
     */
    protected function getChangeLanguageMetadata(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json(['error' => $data['error']], $data['status']);
        }

        $context = $data['context']; /** @var Context $context*/
        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $locales = $this->getPublicationFormLocales($context, $submission);

        $changeSubmissionLanguageApiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getData('urlPath'),
            "submissions/{$submission->getId()}/publications/{$publication->getId()}/changeLocale"
        );

        $changeSubmissionLanguageMetadataForm = new ChangeSubmissionLanguageMetadataForm($changeSubmissionLanguageApiUrl, $submission, $publication, $context);
        $submissionLocale = $submission->getData('locale');

        return response()->json($this->getLocalizedForm($changeSubmissionLanguageMetadataForm, $submissionLocale, $locales), Response::HTTP_OK);
    }

    /**
     * Utility method used to get the metadata locale information for a submission publications and context
    */
    protected function getPublicationFormLocales(Context $context, Submission $submission): array
    {
        return collect($context->getSupportedSubmissionMetadataLocaleNames() + $submission->getPublicationLanguageNames())
            ->map(fn (string $name, string $locale) => ['key' => $locale, 'label' => $name])
            ->values()
            ->toArray();
    }

    /**
     * Get URL for a given Publication
     */
    protected function getPublicationUrl(PKPRequest $request, Context $context, Submission $submission, Publication $publication): string
    {
        return $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'submissions/' . $submission->getId() . '/publications/' . $publication->getId());
    }

    /**
     * Get the form configuration data with the correct
     * locale settings based on the publication's locale
     *
     * Uses the publication locale as the primary and
     * visible locale, and puts that locale first in the
     * list of supported locales.
     *
     * Call this instead of $form->getConfig() to display
     * a form with the correct publication locales
     */
    protected function getLocalizedForm(FormComponent $form, string $submissionLocale, array $locales): array
    {
        $config = $form->getConfig();

        $config['primaryLocale'] = $submissionLocale;
        $config['visibleLocales'] = [$submissionLocale];
        $config['supportedFormLocales'] = collect($locales)
            ->sortBy([fn (array $a, array $b) => $b['key'] === $submissionLocale ? 1 : -1])
            ->values()
            ->toArray();

        return $config;
    }

    /***
     * Get the Context for a given submission.
     */
    protected function getContext(PKPRequest $request, Submission $submission): Context
    {
        $submissionContext = $request->getContext();
        if ($submission->getData('contextId') !== $submissionContext->getId()) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        return $submissionContext;
    }

    /**
     * Used by handlers for '{submissionId}/publications/{publicationId}/_components/xxx'
     * to perform common validation and retrieve submission and publication data
     *
     * Performs validations on the submission and publication data derived from the request.
     * If the validations pass, it returns the necessary data (submission, publication, context, etc).
     * If the validations fail, it returns error information.
     *
     * @return array An array with validated data or error details for client response.
     */
    protected function getSubmissionAndPublicationData(Request $illuminateRequest): array
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = Repo::publication()->get((int)$illuminateRequest->route('publicationId'));

        if (!$publication || !$submission) {
            return [
                'error' => __('api.404.resourceNotFound'),
                'status' => Response::HTTP_NOT_FOUND,
            ];
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return [
                'error' => __('api.publications.403.submissionsDidNotMatch'),
                'status' => Response::HTTP_FORBIDDEN,
            ];
        }

        $submissionContext = $request->getContext();
        if ($submission->getData('contextId') !== $submissionContext->getId()) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $publicationApiUrl = $this->getPublicationUrl($request, $submissionContext, $submission, $publication);

        return [
            'submission' => $submission,
            'publication' => $publication,
            'context' => $submissionContext,
            'publicationApiUrl' => $publicationApiUrl,
        ];
    }

    /**
     * Copy author, files, etc. multilingual fields from old to new changed language
     */
    protected function copyMultilingualData(Submission $submission, string $newLocale): void
    {
        $oldLocale = $submission->getData('locale');
        $editProps = fn (Author|SubmissionFile|Affiliation $item, array $props): array => collect($props)
            ->mapWithKeys(fn (string $prop): array => [$prop => ($data = $item->getData($prop)[$oldLocale] ?? null) ? [$newLocale => $data] : null])
            ->filter()
            ->toArray();

        // Submission files
        $fileProps = [
            'name',
        ];
        Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany()
            ->each(function (SubmissionFile $file) use ($fileProps, $editProps, $newLocale) {
                if (!($file->getData('name')[$newLocale] ?? null)) {
                    Repo::submissionFile()->edit($file, $editProps($file, $fileProps));
                }
            });

        // Contributor
        $contributorProps = [
            'givenName',
            'familyName',
            'preferredPublicName',
        ];
        $affiliationProps = [
            'name',
        ];
        Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$submission->getLatestPublication()->getId()])
            ->getMany()
            ->each(function (Author $contributor) use ($contributorProps, $affiliationProps, $editProps, $newLocale) {
                foreach ($contributor->getAffiliations() as $affiliation) {
                    if (!($affiliation->getData('name')[$newLocale] ?? null)) {
                        Repo::affiliation()->edit($affiliation, $editProps($affiliation, $affiliationProps));
                    }
                }
                if (!($contributor->getData('givenName')[$newLocale] ?? null)) {
                    Repo::author()->edit($contributor, $editProps($contributor, $contributorProps));
                }
            });
    }

    private function validateVersionStage(Request $illuminateRequest): VersionStage
    {
        $validator = Validator::make($illuminateRequest->all(), [
            'versionStage' => [
                'required',
                Rule::in(array_column(VersionStage::cases(), 'value')),
            ],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return VersionStage::from($validator->validated()['versionStage']);
    }

    private function validateVersionIsMinor(Request $illuminateRequest): ?bool
    {
        return filter_var(
            $illuminateRequest->input('versionIsMinor') ?? true,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
    }
}

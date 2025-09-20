<?php

/**
 * @file api/v1/submissions/tasks/EditorialTaskController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionController
 *
 * @brief Handle API requests for operations with tasks, discussions, and notes.
 *
 */

namespace PKP\API\v1\submissions\tasks;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\submissions\tasks\formRequests\AddNote;
use PKP\API\v1\submissions\tasks\formRequests\AddTask;
use PKP\API\v1\submissions\tasks\formRequests\EditTask;
use PKP\API\v1\submissions\tasks\resources\NoteResource;
use PKP\API\v1\submissions\tasks\resources\TaskResource;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\editorialTask\Participant;
use PKP\note\Note;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\QueryAccessPolicy;
use PKP\security\authorization\QueryWorkflowStageAccessPolicy;
use PKP\security\authorization\QueryWritePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;

class EditorialTaskController extends PKPBaseController
{
    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'submissions';
    }

    /**
     * @inheritDoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_REVIEWER,
            ]),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGroupRoutes(): void
    {
        Route::post('{submissionId}/tasks', $this->addTask(...))
            ->name('submission.task.add')
            ->whereNumber('submissionId');

        Route::put('{submissionId}/tasks/{taskId}', $this->editTask(...))
            ->name('submission.task.edit')
            ->whereNumber(['submissionId', 'taskId']);

        Route::delete('{submissionId}/tasks/{taskId}', $this->deleteTask(...))
            ->name('submission.task.delete')
            ->whereNumber(['submissionId', 'taskId']);

        Route::get('{submissionId}/tasks/{taskId}', $this->getTask(...))
            ->name('submission.task.get')
            ->whereNumber(['submissionId', 'taskId']);

        Route::get('{submissionId}/stage/{stageId}/tasks', $this->getTasks(...))
            ->name('submission.task.getMany')
            ->whereNumber(['submissionId', 'stageId']);

        Route::put('{submissionId}/tasks/{taskId}/close', $this->closeTask(...))
            ->name('submission.task.close')
            ->whereNumber(['submissionId', 'taskId']);

        Route::put('{submissionId}/tasks/{taskId}/open', $this->openTask(...))
            ->name('submission.task.open')
            ->whereNumber(['submissionId', 'taskId']);

        Route::put('{submissionId}/tasks/{taskId}/start', $this->startTask(...))
            ->name('submission.task.start')
            ->whereNumber(['submissionId', 'taskId']);

        Route::post('{submissionId}/tasks/{taskId}/notes', $this->addNote(...))
            ->name('submission.note.add')
            ->whereNumber(['submissionId', 'taskId']);

        Route::delete('{submissionId}/tasks/{taskId}/notes/{noteId}', $this->deleteNote(...))
            ->name('submission.note.delete')
            ->whereNumber(['submissionId', 'taskId', 'noteId']);
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
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        // For operations to retrieve task(s), we just ensure that the user has access to it
        if (in_array($actionName, ['getTask', 'addNote', 'deleteNote'])) {
            $stageId = $request->getUserVar('stageId');
            $this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, !empty($stageId) ? (int) $stageId : null, 'taskId'));
        }

        // To modify a task, need to check read and write access policies
        if (in_array($actionName, ['editTask', 'deleteTask', 'closeTask', 'openTask', 'startTask'])) {
            $stageId = $request->getUserVar('stageId');
            $this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, !empty($stageId) ? (int) $stageId : null, 'taskId'));
            $this->addPolicy(new QueryWritePolicy($request));
        }

        // To create a task or get a list of tasks, check if the user has access to the workflow stage; note that controller must ensure to get a list of tasks where user is a participant
        if (in_array($actionName, ['addTask', 'getTasks'])) {
            $this->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, (int) $request->getUserVar('stageId')));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Creates a task or discussion associated with the submission
     */
    public function addTask(AddTask $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();

        $editorialTask = new EditorialTask($validated);
        $editorialTask->save();
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $editorialTask->refresh();

        return response()->json(
            new TaskResource(resource: $editorialTask, data: $this->getTaskData($submission, $editorialTask)),
            Response::HTTP_OK
        );
    }

    /**
     * Get a single task or discussion associated with the submission
     */
    public function getTask(Request $illuminateRequest): JsonResponse
    {
        $editTask = EditorialTask::find($illuminateRequest->route('taskId'));
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            (new TaskResource(resource: $editTask, data: $this->getTaskData($submission, $editTask))),
            Response::HTTP_OK
        );
    }

    /**
     * Get a list of all available tasks and discussions related to the submission
     */
    public function getTasks(Request $illuminateRequest): JsonResponse
    {
        $currentUser = $this->getRequest()->getUser();
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $stageId = (int) $illuminateRequest->route('stageId');

        // Managers have access to all tasks and discussions irrespectable of the participation
        if ($currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $submission->getData('contextId'))) {
            $collector = EditorialTask::withAssocType(PKPApplication::ASSOC_TYPE_SUBMISSION)
                ->withAssocIds([$submission->getId()])
                ->withStageId($stageId);
        } else {
            // Other users have access to tasks, where they are included as participants
            $collector = EditorialTask::withAssocType(PKPApplication::ASSOC_TYPE_SUBMISSION)
                ->withAssocIds([$submission->getId()])
                ->withParticipantIds([$currentUser->getId()])
                ->withStageId($stageId);
        }

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'isOpen':
                    $collector = $collector->withOpen();
                    break;
                case 'orderBy':
                    if (in_array($val, [
                        EditorialTask::ORDERBY_DATE_CREATED,
                        EditorialTask::ORDERBY_DATE_DUE,
                        EditorialTask::ORDERBY_DATE_STARTED,
                    ])) {
                        $direction = $illuminateRequest->query('orderDirection') === EditorialTask::ORDER_DIR_DESC
                            ? EditorialTask::ORDER_DIR_DESC
                            : EditorialTask::ORDER_DIR_ASC;
                        $collector->orderByDate($val, $direction);
                    }
                    break;
            }
        }

        $tasks = $collector->get();
        $taskIds = $tasks->pluck('id')->toArray();

        // Get task participants and creators
        $participantIds = Participant::withTaskIds($taskIds)->get()->pluck('userId')->merge(
            $tasks->pluck('createdBy')
        )->filter()->unique()->toArray();

        $users = Repo::user()->getCollector()->filterByUserIds($participantIds)->getMany();

        $stageAssignments = StageAssignment::with('userGroup')
            ->withSubmissionIds([$submission->getId()])
            ->withStageIds([$stageId])
            ->get();


        $userGroups = UserGroup::with('userUserGroups')
            ->withContextIds($submission->getData('contextId'))
            ->withUserIds($participantIds)
            ->get();

        $reviewAssignments = in_array($stageId, Application::get()->getReviewStages()) ? Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByReviewerIds($participantIds)
            ->filterByStageId($stageId)
            ->getMany() :
            collect()->lazy();

        return response()->json([
            'items' => TaskResource::collection(resource: $tasks, data: [
                'submission' => $submission,
                'stageAssignments' => $stageAssignments,
                'users' => $users,
                'userGroups' => $userGroups,
                'reviewAssignments' => $reviewAssignments,
            ]),
            'itemMax' => $tasks->count(),
        ], Response::HTTP_OK);
    }

    /**
     * Edit a task or discussion associated with the submission
     */
    public function editTask(EditTask $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */
        $editTask = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_QUERY); /** @var EditorialTask $editTask */

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $illuminateRequest->validated();

        if (!$editTask->update($validated)) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(
            new TaskResource(resource: $editTask->refresh(), data: $this->getTaskData($submission, $editTask)),
            Response::HTTP_OK
        );
    }

    /**
     * Remove task or discussion associated with the submission
     */
    public function deleteTask(Request $illuminateRequest): JsonResponse
    {
        $editTask = EditorialTask::find($illuminateRequest->route('taskId'));

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $editTask->delete();

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Close a task or discussion; closed task cannot be edited anymore
     */
    public function closeTask(Request $illuminateRequest): JsonResponse
    {
        $editTask = EditorialTask::find($illuminateRequest->route('taskId'));
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($editTask->dateClosed) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }

        $editTask->fill(['dateClosed' => Carbon::now()])->save();
        $editTask->refresh();
        return response()->json(
            new TaskResource(resource: $editTask, data: $this->getTaskData($submission, $editTask)),
            Response::HTTP_OK
        );
    }

    /**
     * Re-open a closed task or discussion
     */
    public function openTask(Request $illuminateRequest): JsonResponse
    {
        $editTask = EditorialTask::find($illuminateRequest->route('taskId'));
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$editTask->dateClosed) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }

        $editTask->fill(['dateClosed' => null])->save();
        $editTask->refresh();
        return response()->json(
            new TaskResource(resource: $editTask, data: $this->getTaskData($submission, $editTask)),
            Response::HTTP_OK
        );
    }

    /**
     * Start a task or discussion; once started, it cannot be unstarted
     */
    public function startTask(Request $illuminateRequest): JsonResponse
    {
        $editTask = EditorialTask::find($illuminateRequest->route('taskId'));
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        if (!$editTask) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($editTask->dateStarted || $editTask->type === EditorialTaskType::DISCUSSION->value) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }

        $editTask->fill([
            'dateStarted' => Carbon::now(),
            'startedBy' => $this->getRequest()->getUser()->getId(),
        ])->save();
        $editTask->refresh();
        return response()->json(
            new TaskResource(resource: $editTask, data: $this->getTaskData($submission, $editTask)),
            Response::HTTP_OK
        );
    }

    /**
     * Get task related data to supply the editorial task and editorial tasl participants resource
     */
    protected function getTaskData(Submission $submission, EditorialTask $editTask): array
    {
        $stageAssignments = StageAssignment::with('userGroup')
            ->withSubmissionIds([$submission->getId()])
            ->withStageIds([$editTask->stageId])
            ->get();

        $participantIds = $editTask->participants()->get()->pluck('userId')->unique()->toArray();
        $creatorId = $editTask->createdBy;
        if (!in_array($creatorId, $participantIds)) {
            $participantIds[] = $creatorId;
        }

        $users = Repo::user()->getCollector()->filterByUserIds($participantIds)->getMany();
        $userGroups = UserGroup::with('userUserGroups')
            ->withContextIds($submission->getData('contextId'))
            ->withUserIds($participantIds)
            ->get();
        $reviewAssignments = in_array($editTask->stageId, Application::get()->getReviewStages()) ? Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByReviewerIds($participantIds)
            ->filterByStageId($editTask->stageId)
            ->getMany() :
            collect()->lazy();

        return [
            'submission' => $submission,
            'stageAssignments' => $stageAssignments,
            'users' => $users,
            'userGroups' => $userGroups,
            'reviewAssignments' => $reviewAssignments,
        ];
    }

    /**
     * Add a reply to a task or discussion, excluding the headnote
     */
    public function addNote(AddNote $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();

        $note = new Note($validated);
        $note->save();
        $note->refresh();

        return response()->json(
            new NoteResource(resource: $note, data: ['users' => collect([$this->getRequest()->getUser()])]),
            Response::HTTP_OK
        );
    }

    /**
     * Delete a note from a task or discussion, excluding the headnote
     */
    public function deleteNote(Request $illuminateRequest): JsonResponse
    {
        $note = Note::find($illuminateRequest->route('noteId'));

        if (!$note) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($note->isHeadnote) {
            return response()->json([
                'error' => __('api.403.forbidden'),
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getRequest()->getUser();
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        // Allow removing the note for its creator or a manager/admin
        if ($note->userId !== $user->getId() || !$user->hasRole([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $submission->getData('contextId'))) {
            return response()->json([
                'error' => __('api.403.forbidden'),
            ], Response::HTTP_FORBIDDEN);
        }

        $note->delete();

        return response()->json([], Response::HTTP_OK);
    }
}

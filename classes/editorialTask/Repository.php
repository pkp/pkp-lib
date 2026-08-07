<?php

/**
 * @file classes/query/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @see EditorialTask
 *
 * @brief Operations for retrieving and modifying Query objects.
 */

namespace PKP\editorialTask;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\XMLDAO;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class Repository
{
    /**
     * Retrieve a count of all open queries totalled by stage
     *
     * @param ?int[] $participantIds Only include queries with these participants
     *
     * @return array<int,int> [int $stageId => int $count]
     */
    public function countOpenPerStage(int $submissionId, ?array $participantIds = null): array
    {
        $counts = EditorialTask::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->when($participantIds !== null, function ($q) use ($participantIds) {
                $q->withUserIds($participantIds);
            })
            ->withClosed(false)
            ->selectRaw('stage_id, COUNT(stage_id) as count')
            ->groupBy('stage_id')
            ->get()
            ->mapWithKeys(fn ($row, $key) => [$row->stage_id => $row->count])
            ->toArray();

        return collect(Application::get()->getApplicationStages())
            ->mapWithKeys(fn ($stageId, $key) => [$stageId => $counts[$stageId] ?? 0])
            ->toArray();
    }

    /**
     * Sequentially renumber queries in their sequence order.
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId Assoc ID per assocType
     */
    public function resequence($assocType, $assocId): void
    {
        $result = EditorialTask::withAssoc($assocType, $assocId)
            ->orderBy('seq')
            ->get();

        $result->each(function (EditorialTask $item, int $key = 1) {
            $item->update(['seq' => $key]);
        });
    }

    /**
     * Start a query
     *
     * Inserts the query, assigns participants, and creates the head note
     *
     * @return int The new query id
     */
    public function addQuery(int $submissionId, int $stageId, string $title, string $content, User $fromUser, array $participantUserIds, int $contextId, bool $sendEmail = true): int
    {
        $maxSeq = EditorialTask::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->max('seq') ?? 0;

        $task = EditorialTask::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'stageId' => $stageId,
            'seq' => $maxSeq + 1,
            'createdBy' => $fromUser->getId(),
            'type' => EditorialTaskType::DISCUSSION->value,
            EditorialTask::ATTRIBUTE_PARTICIPANTS => array_map(fn (int $participantId) => ['userId' => $participantId], array_unique($participantUserIds)),
            'title' => $title,
        ]);

        Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $task->id,
            'contents' => $content,
            'userId' => $fromUser->getId(),
        ]);

        // Add task for assigned participants
        $notificationMgr = new NotificationManager();

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        foreach ($task->participants()->get() as $participant) {
            $notificationMgr->createNotification(
                $participant->userId,
                Notification::NOTIFICATION_TYPE_NEW_QUERY,
                $contextId,
                Application::ASSOC_TYPE_QUERY,
                $task->id,
                Notification::NOTIFICATION_LEVEL_TASK
            );

            if (!$sendEmail) {
                continue;
            }

            // Check if the user is unsubscribed
            $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $participant->userId,
                $contextId
            );
            if (in_array(Notification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                continue;
            }

            $recipient = $participant->user;
            $mailable = new Mailable();
            $mailable->to($recipient->getEmail(), $recipient->getFullName());
            $mailable->from($fromUser->getEmail(), $fromUser->getFullName());
            $mailable->subject($title);
            $mailable->body($content);

            Mail::send($mailable);
        }

        return $task->id;
    }

    /**
     * Create a query with a submission's comments for the editors
     *
     * Creates the query and assigns all participants
     *
     * @return int new query id
     */
    public function addCommentsForEditorsQuery(Submission $submission): int
    {
        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $participantUserIds = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ])
            ->withStageIds([$submission->getData('stageId')])
            ->get()
            ->pluck('user_id')
            ->all();

        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $authorAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$submission->getData('stageId')])
            ->get();

        $fromUser = $authorAssignments->isEmpty()
            ? Application::get()->getRequest()->getUser()
            : Repo::user()->get($authorAssignments->first()->userId);

        return $this->addQuery(
            $submission->getId(),
            $submission->getData('stageId'),
            __('submission.submit.coverNote'),
            $submission->getData('commentsForTheEditors'),
            $fromUser,
            $participantUserIds,
            $submission->getData('contextId')
        );
    }

    public function autoCreateFromTemplates(Submission $submission, int $stageId): void
    {
        $contextId = (int) $submission->getData('contextId');

        $templates = Template::query()
            ->withContextId($contextId)
            ->withStageId($stageId)
            ->withInclude(true)
            ->get();

        foreach ($templates as $template) {
            $templateId = (int) $template->id;

            if ($this->taskAlreadyCreatedFromTemplate($submission->getId(), $templateId)) {
                continue;
            }

            $task = $template->promote($submission, false); // no participants

            $maxSeq = (float) (EditorialTask::query()
                ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
                ->where('assoc_id', $submission->getId())
                ->max('seq') ?? 0);

            $task->seq = $maxSeq + 1;

            // createdBy left as default (NULL) for system-created tasks
            $task->save();
        }
    }

    private function taskAlreadyCreatedFromTemplate(int $submissionId, int $templateId): bool
    {
        return DB::table('edit_tasks')
            ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $submissionId)
            ->where('edit_task_template_id', $templateId)
            ->exists();
    }


    /**
     * Deletes all tasks, notes, and notifications associated with the given submission ID.
     */
    public function deleteBySubmissionId(int $submissionId): void
    {
        $editorialTasks = EditorialTask::withAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION, $submissionId)->get();
        $primaryKeyName = (new EditorialTask())->getKeyName();
        $taskIds = $editorialTasks->pluck($primaryKeyName)->all();

        if (!empty($taskIds)) {
            EditorialTask::whereIn($primaryKeyName, $taskIds)->delete();
            Note::whereIn('assoc_id', $taskIds)->where('assoc_type', PKPApplication::ASSOC_TYPE_QUERY)->delete();
            Notification::whereIn('assoc_id', $taskIds)->where('assoc_type', PKPApplication::ASSOC_TYPE_QUERY)->delete();
        }
    }

    public function removeParticipantFromSubmissionTasks(int $submissionId, int $userId, int $contextId): void
    {
        $user = Repo::user()->get($userId);

        // Non-managerial only
        if ($user && $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $contextId)) {
            return;
        }

        $taskIds = EditorialTask::query()
            ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $submissionId)
            ->pluck('edit_task_id')
            ->all();

        if (empty($taskIds)) {
            return;
        }

        Participant::query()
            ->whereIn('edit_task_id', $taskIds)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Check if the given template is accessible by the given user
     */
    public function isTemplateAccessibleToUser(Template $template, User $user): bool
    {
        if (!$template->restrictToUserGroups) {
            return true;
        }

        $userGroupIds = UserGroup::withUserIds([$user->getId()])
            ->withContextIds([$template->contextId])
            ->pluck((new UserGroup())->getPrimaryKeyName())
            ->toArray();

        $template->loadMissing('userGroups');

        return $template->userGroups()
            ->whereIn('user_group_id', $userGroupIds)
            ->exists();
    }

    /**
     * Get the main email template path and filename.
     */
    public function getDefaultTemplatesFilename()
    {
        return 'registry/taskTemplates.xml';
    }

    /**
     * Install default task templates
     *
     */
    public function installTaskTemplates(?Context $context = null, ?array $addedLocales = null): bool
    {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct($this->getDefaultTemplatesFilename(), ['template']);

        if (!isset($data['template'])) {
            return false;
        }

        // Set data from a template
        $templatesData = [];
        foreach ($data['template'] as $entry) {
            $attrs = [
                'title' => Arr::get($entry, 'attributes.title'),
                'description' => Arr::get($entry, 'attributes.description'),
            ];

            $attrs['key'] = Arr::get($entry, 'attributes.key');
            $stageId = Arr::get($entry, 'attributes.stageId');
            if (defined($stageId)) {
                $attrs['stageId'] = $stageId;
            }

            $templatesData[] = array_filter($attrs);
        }

        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var \PKP\site\SiteDAO $siteDao */
        $siteLocales = $siteDao->getSite()->getSupportedLocales();

        $contexts = [];
        if ($context) {
            $contexts[] = $context;
        } else {
            $contextDao = Application::getContextDAO();
            $contexts = $contextDao->getAll()->toArray();
        }

        foreach ($contexts as $context) {
            foreach ($templatesData as $templateData) {
                $fillables = [];
                $localizedTitle = $localizedDescription = [];
                foreach ($templateData as $key => $data) {
                    $locales = $addedLocales ?? $siteLocales;
                    foreach ($locales as $locale) {
                        $previous = Locale::getMissingKeyHandler();
                        Locale::setMissingKeyHandler(fn (string $localeKey): string => '');
                        switch ($key) {
                            case 'title':
                                $localizedTitle[$locale] = __($data, [], $locale);
                                break;
                            case 'description':
                                $localizedDescription[$locale] = __($data, [], $locale);
                                break;
                        }
                        Locale::setMissingKeyHandler($previous);
                    }

                    switch ($key) {
                        case 'stageId':
                            $fillables['stageId'] = constant($data);
                            break;
                        case 'key':
                            $fillables['key'] = $data;
                            break;
                    }
                }

                $fillables['title'] = $localizedTitle;
                $fillables['description'] = $localizedDescription;

                $fillables = array_merge($fillables, [
                    'contextId' => $context->getId(),
                    'type' => EditorialTaskType::DISCUSSION->value, // Only discussions are implemented as default templates.
                ]);

                // If exists by email key, just add new localizations, if available
                $template = null;
                if (isset($fillables['key'])) {
                    $template = Template::withkeys([$fillables['key']], $fillables['contextId'])->first();
                }

                if ($template) {
                    $fillables['title'] = array_merge($template->title, $fillables['title']);
                    $fillables['description'] = array_merge($template->description, $fillables['description']);
                    $template->fill($fillables);
                } else {
                    // This is a new template. Add data in all available locales
                    foreach ($siteLocales as $locale) {
                        $previous = Locale::getMissingKeyHandler();
                        Locale::setMissingKeyHandler(fn (string $localeKey): string => '');

                        if (!isset($fillables['title'][$locale])) {
                            $fillables['title'][$locale] = __($templateData['title'], [], $locale);
                        }
                        if (!isset($fillables['description'][$locale])) {
                            $fillables['description'][$locale] = __($templateData['description'], [], $locale);
                        }
                        Locale::setMissingKeyHandler($previous);
                    }
                    $template = new Template($fillables);
                }

                $template->save();
            }
        }

        return true;
    }

    /**
     * Uninstall localized data on email task templates
     *
     * @param array<string> $locales
     */
    public function deleteTemplateLocaleData(array $locales): void
    {
        DB::table('edit_task_template_settings')
            ->whereIn('locale', $locales)
            ->delete();
    }
}

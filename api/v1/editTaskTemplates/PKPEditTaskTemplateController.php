<?php

namespace PKP\API\v1\editTaskTemplates;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PKP\API\v1\submissions\resources\TaskResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\editorialTask\Participant;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\userGroup\UserGroup;
use PKP\editorialTask\Template;

class PKPEditTaskTemplateController extends PKPBaseController
{
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new CanAccessSettingsPolicy());
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getHandlerPath(): string
    {
        return 'editTaskTemplates';
    }

    public function getRouteGroupMiddleware(): array
    {
        return ['has.user', 'has.context'];
    }

    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ])->group(function () {
            Route::post('', $this->add(...));
        });
    }

    /**
     * POST /api/v1/editTaskTemplates
     * Body: stageId, title, include, type, dateDue?, userGroupIds[], participants[] (userId,isResponsible?)
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $currentUser = $request->getUser();

        // gather payload
        $payload = [
            'stageId' => (int) $illuminateRequest->input('stageId'),
            'title' => (string) $illuminateRequest->input('title', ''),
            'include' => (bool) $illuminateRequest->boolean('include', false),
            'type' => (int) $illuminateRequest->input('type'), // see #2
            'emailTemplateId' => $illuminateRequest->input('emailTemplateId'),
            'userGroupIds' => array_values(array_map('intval', (array) $illuminateRequest->input('userGroupIds', []))),
            'participants' => (array) $illuminateRequest->input('participants', []),
        ];

        if ($illuminateRequest->has('dateDue')) {
            $payload['dateDue'] = $illuminateRequest->input('dateDue');
        }

        // validation
        $typeValues = array_column(EditorialTaskType::cases(), 'value');
        $isTask = $payload['type'] === EditorialTaskType::TASK->value;
        $isDiscussion = $payload['type'] === EditorialTaskType::DISCUSSION->value;

        $rules = [
            'stageId' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'include' => ['boolean'],

            'type' => ['required', Rule::in($typeValues)],
            'dateDue' => $isTask
                ? ['required', 'date_format:Y-m-d', 'after:today']
                : ['prohibited'],

            'emailTemplateId' => ['nullable', 'integer', Rule::exists('email_templates', 'email_id')],

            'userGroupIds' => ['required', 'array', 'min:1'],
            'userGroupIds.*' => ['integer', 'distinct', Rule::exists('user_groups', 'user_group_id')],

            'participants' => ['required', 'array'],
            'participants.*' => ['required', 'array:userId,isResponsible'],
            'participants.*.userId' => ['required', 'integer', 'distinct', Rule::exists('users', 'user_id')],
            'participants.*.isResponsible' => $isTask ? ['required', 'boolean'] : ['prohibited'],
        ];

        $validator = Validator::make($payload, $rules);

        $validator->after(function ($v) use ($payload, $isTask, $isDiscussion) {
            $parts = $payload['participants'] ?? [];

            if ($isTask && count($parts) < 1) {
                $v->errors()->add('participants', 'At least one participant is required for a task.');
            }
            if ($isDiscussion && count($parts) < 2) {
                $v->errors()->add('participants', 'At least two participants are required for a discussion.');
            }

            if ($isTask) {
                $responsibles = 0;
                foreach ($parts as $p) {
                    if (!empty($p['isResponsible'])) {
                        $responsibles++;
                    }
                }
                if ($responsibles > 1) {
                    $v->errors()->add('participants', 'There should be the only one user responsible for the task');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // userGroupIds must belong to this context
        $validCount = DB::table('user_groups')
            ->where('context_id', $context->getId())
            ->whereIn('user_group_id', $payload['userGroupIds'])
            ->count();

        if ($validCount !== count($payload['userGroupIds'])) {
            return response()->json(
                ['userGroupIds' => ['One or more userGroupIds do not belong to this context']],
                Response::HTTP_BAD_REQUEST
            );
        }

        $templateId = DB::transaction(function () use ($context, $payload) {
            $template = Template::create([
                'stage_id' => $payload['stageId'],
                'title' => $payload['title'],
                'context_id' => $context->getId(),
                'include' => $payload['include'],
                'email_template_id' => $payload['emailTemplateId'] ?? null,
            ]);

            // attach user groups via pivot
            $template->userGroups()->sync($payload['userGroupIds']);

            // store extra template config in settings (non-localized)
            DB::table('edit_task_template_settings')->insert([
                [
                    'edit_task_template_id' => $template->getKey(),
                    'locale' => '',
                    'setting_name' => 'type',
                    'setting_value'=> (string) ((int) $payload['type']),
                ],
                [
                    'edit_task_template_id' => $template->getKey(),
                    'locale' => '',
                    'setting_name' => 'participants',
                    'setting_value'=> json_encode(array_map(
                        fn (array $p) => [
                            'userId' => (int) $p['userId'],
                            'isResponsible' => (bool) ($p['isResponsible'] ?? false)
                        ],
                        $payload['participants']
                    )),
                ],
                [
                    'edit_task_template_id' => $template->getKey(),
                    'locale' => '',
                    'setting_name' => 'dateDue',
                    'setting_value'=> $payload['dateDue'] ? (string) $payload['dateDue'] : null,
                ],
            ]);

            return $template->getKey();
        });

        // serialize with TaskResource
        $tpl = DB::table('edit_task_templates')->where('edit_task_template_id', $templateId)->first();

        $settings = DB::table('edit_task_template_settings')
            ->where('edit_task_template_id', $templateId)
            ->pluck('setting_value', 'setting_name');

        $type = (int) ($settings['type'] ?? $payload['type']);
        $dateDueStr = $settings['dateDue'] ?? ($payload['dateDue'] ?? null);
        $dateDue = !empty($dateDueStr) ? Carbon::createFromFormat('Y-m-d', $dateDueStr) : null;


        $participantsSetting = $settings['participants'] ?? '[]';
        $participantsData = json_decode($participantsSetting, true) ?: [];

        // Build a transient EditorialTask that mirrors a real task
        $editorialTask = new EditorialTask([
            'edit_task_id' => (int) $templateId,
            'type' => $type,
            'assocType' => null,
            'assocId' => null,
            'stageId' => (int) $tpl->stage_id,
            'title' => $tpl->title,
            'createdBy' => $currentUser->getId(),
            'dateDue' => $dateDue,
            'dateStarted'=> null,
            'dateClosed' => null,
        ]);

        // attach participants relation for TaskResource
        $participantModels = collect($participantsData)->map(
            fn (array $p) => new Participant([
                'userId' => (int) $p['userId'],
                'isResponsible' => (bool) ($p['isResponsible'] ?? false),
            ])
        );
        $editorialTask->setRelation('participants', $participantModels);

        // build the data bundle required by TaskResource
        $participantIds = $participantModels->pluck('userId')->unique()->values()->all();
        if (!in_array($currentUser->getId(), $participantIds, true)) {
            $participantIds[] = $currentUser->getId();
        }

        $users = Repo::user()->getCollector()
            ->filterByUserIds($participantIds)
            ->getMany();

        $userGroups = UserGroup::with('userUserGroups')
            ->withContextIds($context->getId())
            ->withUserIds($participantIds)
            ->get();
        
        return (new TaskResource(
            resource: $editorialTask,
            data: [
                'submission' => null,
                'users' => $users,
                'userGroups' => $userGroups,
                'stageAssignments' => collect(),
                'reviewAssignments' => collect(),
            ]
        ))->response()->setStatusCode(Response::HTTP_CREATED);

    }
}

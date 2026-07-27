<?php

/**
 * @file api/v1/_test/PKPSubmissionScenarioController.php
 *
 * Copyright (c) 2023-2026 Simon Fraser University
 * Copyright (c) 2023-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionScenarioController
 *
 * @ingroup api_v1__test
 *
 * @brief POST /api/v1/_test/scenarios/submission — seed a submission at a state.
 *
 * The spec describes the END STATE a test needs (submitted or draft, decisions
 * taken, review rounds with reviewers, published). The builder walks the
 * application there through the same services the UI uses — Repo::submission()
 * ->submit(), Repo::decision()->add(), EditorAction::addReviewer(),
 * ReviewerAction::confirmReview(), Repo::publication()->publish() — so the
 * database rows, hooks, event log and notifications match the UI path.
 *
 * Nothing here names a workflow stage: the initial stage and review stage come
 * from the application's own stage roster, and app concepts (a section, an
 * issue) arrive as declared overlay properties from the app subclass.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\mail\mailables\ReviewRequest;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\submission\action\EditorAction;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\ReviewerAction;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\testing\scenario\ScenarioException;
use PKP\user\User;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;

class PKPSubmissionScenarioController extends PKPTestApiController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::post('scenarios/submission', $this->addSubmission(...))->name('_test.scenarios.submission');
    }

    public function addSubmission(Request $illuminateRequest): JsonResponse
    {
        return $this->build(function () use ($illuminateRequest) {
            $spec = $this->readSpec($illuminateRequest, $this->schema('submission'));

            $context = Application::getContextDAO()->getByPath($spec['context']);

            if (!$context) {
                throw new ScenarioException("No context at urlPath '{$spec['context']}'.", 'context');
            }

            $submitter = $this->requireUser($spec['submitter'], 'submitter');

            return $this->inContext($context, fn () => $this->buildSubmission($spec, $context, $submitter));
        });
    }

    /**
     * @throws ScenarioException
     */
    protected function buildSubmission(array $spec, Context $context, User $submitter): array
    {
        $submission = $this->actingAs($submitter, fn () => $this->createSubmission($spec, $context, $submitter));

        $echo = [
            'tag' => $spec['tag'],
            'contextId' => $context->getId(),
            'submissionId' => $submission->getId(),
            'submitterId' => $submitter->getId(),
        ];

        $echo['submissionFileId'] = $this->actingAs(
            $submitter,
            fn () => $this->addSubmissionFile($submission, $context, $submitter)
        );

        if (($spec['submitted'] ?? true) !== false) {
            $this->actingAs($submitter, fn () => $this->submitSubmission($submission->getId(), $context));
        }

        $echo['decisionIds'] = $this->applyDecisions($spec, $context);
        $echo['reviewRounds'] = $this->applyReviewRounds($spec, $context);

        if ($spec['published'] ?? false) {
            $echo['publishedPublicationId'] = $this->publish($submission->getId(), $context);
        }

        $submission = Repo::submission()->get($submission->getId());
        $publication = $submission->getCurrentPublication();

        return $echo + [
            'publicationId' => $publication?->getId(),
            'stageId' => (int) $submission->getData('stageId'),
            'status' => (int) $submission->getData('status'),
            'submissionProgress' => $submission->getData('submissionProgress'),
            'dateSubmitted' => $submission->getData('dateSubmitted'),
            'title' => $publication?->getLocalizedTitle($submission->getData('locale')),
        ] + $this->submissionEcho($submission, $spec);
    }

    /**
     * Extra response fields the app wants echoed (an OJS issue id, ...).
     */
    protected function submissionEcho(Submission $submission, array $spec): array
    {
        return [];
    }

    //
    // Creation
    //

    /**
     * Create the submission the way the submission wizard's first step does.
     *
     * @throws ScenarioException
     */
    protected function createSubmission(array $spec, Context $context, User $submitter): Submission
    {
        $locale = $spec['locale'] ?? $context->getSupportedDefaultSubmissionLocale();
        $title = ($spec['title'] ?? 'Scenario submission') . ' [' . $spec['tag'] . ']';

        $submissionProps = [
            'contextId' => $context->getId(),
            'locale' => $locale,
            'stageId' => $this->initialStageId(),
            'submissionProgress' => 'start',
        ];

        $publicationProps = [
            'title' => [$locale => $title],
        ];

        if (isset($spec['abstract'])) {
            $publicationProps['abstract'] = [$locale => $spec['abstract']];
        }

        $this->applyPublicationOverlay($spec, $context, $publicationProps);

        $submitAsUserGroup = $this->resolveSubmitAsUserGroup($context, $submitter);

        $submission = Repo::submission()->newDataObject($submissionProps);
        $publication = Repo::publication()->newDataObject($publicationProps);
        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $this->journal->recordSubmission($submissionId);
        $this->submissionId = $submissionId;

        $submission = Repo::submission()->get($submissionId);

        Repo::stageAssignment()->build(
            $submissionId,
            $submitAsUserGroup->id,
            $submitter->getId(),
            $submitAsUserGroup->recommendOnly,
            // Authors can always edit metadata before submitting
            $submission->getData('submissionProgress') ? true : $submitAsUserGroup->permitMetadataEdit
        );

        if ((int) $submitAsUserGroup->roleId === Role::ROLE_ID_AUTHOR) {
            $author = Repo::author()->newAuthorFromUser($submitter, $submission, $context);
            $author->setData('publicationId', $publication->getId());
            $author->setData('contributorType', ContributorType::PERSON->getName());
            $author->setContributorRoles(
                ContributorRole::query()
                    ->withContextId($context->getId())
                    ->withIdentifier(ContributorRoleIdentifier::AUTHOR->getName())
                    ->limit(1)
                    ->get()
                    ->all()
            );
            $authorId = Repo::author()->add($author);
            Repo::publication()->edit($publication, ['primaryContactId' => $authorId]);
        }

        return Repo::submission()->get($submissionId);
    }

    /**
     * Complete the submission the way the wizard's final step does.
     *
     * Repo::submission()->submit() is most of it, but the submit ENDPOINT also
     * refreshes the approve-submission notification afterwards — a side effect
     * that lives in the controller rather than the repository. Seeding calls the
     * same notification service rather than reproducing its rows, so a seeded
     * submission carries the notifications a wizard submission carries.
     */
    protected function submitSubmission(int $submissionId, Context $context): void
    {
        Repo::submission()->submit(Repo::submission()->get($submissionId), $context);

        (new NotificationManager())->updateNotification(
            Application::get()->getRequest(),
            [Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION],
            null,
            PKPApplication::ASSOC_TYPE_SUBMISSION,
            $submissionId
        );
    }

    /**
     * Hook for the app subclass to translate its overlay keys (an OJS section)
     * into publication properties.
     */
    protected function applyPublicationOverlay(array $spec, Context $context, array &$publicationProps): void
    {
    }

    /**
     * The user group the submitter submits under, chosen the way the submission
     * API chooses it: an author group they are already in, else a manager group,
     * else enrol them in the context's author group.
     *
     * @throws ScenarioException
     */
    protected function resolveSubmitAsUserGroup(Context $context, User $submitter): UserGroup
    {
        $groups = UserGroup::withContextIds([$context->getId()])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR])
            ->whereHas('userUserGroups', fn ($query) => $query->withUserId($submitter->getId())->withActive())
            ->get()
            ->sort(fn (UserGroup $a, UserGroup $b) => ((int) $a->roleId) === Role::ROLE_ID_AUTHOR ? -1 : 1);

        if ($groups->count()) {
            return $groups->first();
        }

        $authorGroup = UserGroup::withContextIds([$context->getId()])->withRoleIds([Role::ROLE_ID_AUTHOR])->first();

        if (!$authorGroup) {
            throw new ScenarioException("Context '{$context->getPath()}' has no author user group to submit under.", 'submitter');
        }

        Repo::userGroup()->assignUserToGroup($submitter->getId(), $authorGroup->id);

        return $authorGroup;
    }

    /**
     * Every seeded submission gets a real submission file through the real file
     * service, so tests asserting on files never have to seed one themselves.
     *
     * @throws ScenarioException
     */
    protected function addSubmissionFile(Submission $submission, Context $context, User $uploader): int
    {
        $genre = $this->primaryGenre($context);
        $sourcePath = $this->materializeFixtureFile();

        try {
            $fileId = app()->get('file')->add(
                $sourcePath,
                Repo::submissionFile()->getSubmissionDir($context->getId(), $submission->getId())
                    . '/' . uniqid() . '.pdf'
            );
        } finally {
            @unlink($sourcePath);
        }

        $submissionFile = Repo::submissionFile()->newDataObject([
            'fileId' => $fileId,
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $uploader->getId(),
            'genreId' => $genre->getId(),
            'fileStage' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            'name' => [$submission->getData('locale') => 'article.pdf'],
        ]);

        return Repo::submissionFile()->add($submissionFile);
    }

    /**
     * The context's primary document genre — the one a manuscript is filed under.
     * Chosen by shape (a non-dependent, non-supplementary document genre) rather
     * than by key, so it resolves in every app.
     *
     * @throws ScenarioException
     */
    protected function primaryGenre(Context $context): Genre
    {
        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId())->toArray();

        foreach ($genres as $genre) {
            if ((int) $genre->getCategory() === Genre::GENRE_CATEGORY_DOCUMENT
                && !$genre->getDependent()
                && !$genre->getSupplementary()
            ) {
                return $genre;
            }
        }

        if (!empty($genres)) {
            return reset($genres);
        }

        throw new ScenarioException("Context '{$context->getPath()}' has no enabled genres.", 'context');
    }

    /**
     * Write a minimal, valid one-page PDF to a temporary file.
     *
     * Generated rather than shipped as a binary fixture so the PHP layer has no
     * asset to keep in sync; the JS layer's richer fixture files replace it once
     * a test needs specific file content.
     */
    protected function materializeFixtureFile(): string
    {
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length 62 >>\nstream\nBT /F1 24 Tf 72 700 Td (Scenario submission file) Tj ET\nendstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . (count($objects) + 1) . "\n" . "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        $path = tempnam(sys_get_temp_dir(), 'pkpscenario') . '.pdf';
        file_put_contents($path, $pdf);

        return $path;
    }

    //
    // Decisions
    //

    /**
     * Record each decision through the real decision service, in order.
     *
     * @throws ScenarioException
     *
     * @return array<int, int> decision ids
     *
     */
    protected function applyDecisions(array $spec, Context $context): array
    {
        $decisionIds = [];

        foreach (array_values($spec['decisions'] ?? []) as $index => $decisionSpec) {
            $decisionIds[] = $this->recordDecision(
                $decisionSpec['decision'],
                $decisionSpec['editor'] ?? null,
                $spec,
                $context,
                "decisions.{$index}"
            );
        }

        return $decisionIds;
    }

    /**
     * @throws ScenarioException
     */
    protected function recordDecision(string $decisionName, ?string $editorUsername, array $spec, Context $context, string $specKey): int
    {
        $submission = Repo::submission()->get($this->currentSubmissionId());
        $decisionType = $this->resolveDecisionType($decisionName, "{$specKey}.decision");
        $editor = $this->resolveEditor($editorUsername, $context, $submission, "{$specKey}.editor");

        return $this->actingAs($editor, function () use ($decisionType, $submission, $context, $editor, $specKey) {
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $decisionType->getStageId());

            $params = [
                'submissionId' => $submission->getId(),
                'dateDecided' => Core::getCurrentDate(),
                'editorId' => $editor->getId(),
                'decision' => $decisionType->getDecision(),
                'stageId' => $decisionType->getStageId(),
                'reviewRoundId' => $reviewRound?->getId(),
            ];

            $errors = Repo::decision()->validate($params, $decisionType, $submission, $context);

            if (!empty($errors)) {
                throw new ScenarioException(
                    "The decision '{$decisionType->getDecision()}' is not valid for this submission: "
                        . json_encode($errors),
                    $specKey
                );
            }

            return Repo::decision()->add(Repo::decision()->newDataObject($params));
        });
    }

    /**
     * Resolve a decision name against the APPLICATION's own decision-type roster.
     *
     * @throws ScenarioException
     */
    protected function resolveDecisionType(string $name, string $specKey): DecisionType
    {
        $types = Repo::decision()->getDecisionTypes();

        $match = $types->first(fn (DecisionType $type) => $this->decisionName($type) === $name);

        if (!$match) {
            throw new ScenarioException(
                "Unknown decision '{$name}'. This application offers: "
                    . $types->map(fn (DecisionType $type) => $this->decisionName($type))->sort()->implode(', ') . '.',
                $specKey
            );
        }

        return $match;
    }

    protected function decisionName(DecisionType $type): string
    {
        $parts = explode('\\', $type::class);

        return lcfirst(end($parts));
    }

    /**
     * The user recording a decision. Defaults to an editorial user in the context;
     * either way the editor is assigned to the submission first, because an editor
     * who has not been assigned cannot take a decision through the UI either.
     *
     * @throws ScenarioException
     */
    protected function resolveEditor(?string $username, Context $context, Submission $submission, string $specKey): User
    {
        $editorialRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        if ($username !== null) {
            $editor = $this->requireUser($username, $specKey);
            $group = UserGroup::withContextIds([$context->getId()])
                ->withRoleIds($editorialRoleIds)
                ->whereHas('userUserGroups', fn ($query) => $query->withUserId($editor->getId())->withActive())
                ->first();

            if (!$group) {
                throw new ScenarioException(
                    "User '{$username}' has no editorial role in context '{$context->getPath()}' and cannot take decisions there.",
                    $specKey
                );
            }
        } else {
            $group = UserGroup::withContextIds([$context->getId()])
                ->withRoleIds($editorialRoleIds)
                ->whereHas('userUserGroups', fn ($query) => $query->withActive())
                ->get()
                ->sort(fn (UserGroup $a, UserGroup $b) => ((int) $a->roleId) === Role::ROLE_ID_MANAGER ? -1 : 1)
                ->first();

            if (!$group) {
                throw new ScenarioException(
                    "Context '{$context->getPath()}' has no user in an editorial role to take decisions.",
                    $specKey
                );
            }

            $editorId = UserUserGroup::query()
                ->withUserGroupIds([$group->id])
                ->withActive()
                ->pluck('user_id')
                ->first();

            $editor = Repo::user()->get((int) $editorId);
        }

        Repo::stageAssignment()->build($submission->getId(), $group->id, $editor->getId());

        return $editor;
    }

    //
    // Review rounds
    //

    /**
     * Attach reviewers to review rounds.
     *
     * Entry i of `reviewRounds` targets round i+1 of the app's review stage. Round
     * 1 is created by the app's own promoting decision when the spec did not ask
     * for one explicitly; later rounds must be created by a decision in the spec,
     * because which decision opens a new round is an editorial choice a test
     * should state rather than have the harness guess.
     *
     * @throws ScenarioException
     */
    protected function applyReviewRounds(array $spec, Context $context): array
    {
        $roundSpecs = array_values($spec['reviewRounds'] ?? []);

        if (empty($roundSpecs)) {
            return [];
        }

        $stageId = $this->reviewStageId();

        if ($stageId === null) {
            throw new ScenarioException(
                'This application has no review stage, so reviewRounds cannot be seeded.',
                'reviewRounds'
            );
        }

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */

        if (!$reviewRoundDao->submissionHasReviewRound($this->currentSubmissionId(), $stageId)) {
            $promoting = $this->promoteToReviewDecision();

            if ($promoting === null) {
                throw new ScenarioException(
                    'The submission is not in a review stage and this application declares no promoting decision; '
                        . 'add the decision that opens review to `decisions`.',
                    'reviewRounds'
                );
            }

            $this->recordDecision($promoting, null, $spec, $context, 'reviewRounds');
        }

        $echo = [];

        foreach ($roundSpecs as $index => $roundSpec) {
            $round = $index + 1;
            $reviewRound = $reviewRoundDao->getReviewRound($this->currentSubmissionId(), $stageId, $round);

            if (!$reviewRound) {
                throw new ScenarioException(
                    "Review round {$round} does not exist. Add the decision that opens it (for example a new review round) to `decisions`.",
                    "reviewRounds.{$index}"
                );
            }

            $echo[] = [
                'id' => $reviewRound->getId(),
                'round' => $round,
                'reviewAssignments' => $this->seedReviewers($roundSpec['reviewers'] ?? [], $reviewRound, $context, "reviewRounds.{$index}.reviewers"),
                'status' => (int) $reviewRoundDao->getById($reviewRound->getId())->getStatus(),
            ];
        }

        return $echo;
    }

    /**
     * @throws ScenarioException
     */
    protected function seedReviewers(array $reviewerSpecs, ReviewRound $reviewRound, Context $context, string $specKeyPrefix): array
    {
        $submission = Repo::submission()->get($reviewRound->getSubmissionId());
        $editor = $this->resolveEditor(null, $context, $submission, $specKeyPrefix);
        $assignments = [];

        foreach (array_values($reviewerSpecs) as $index => $reviewerSpec) {
            $specKey = "{$specKeyPrefix}.{$index}";
            $reviewer = $this->requireUser($reviewerSpec['user'], "{$specKey}.user");
            $status = $reviewerSpec['status'] ?? 'invited';

            $this->actingAs($editor, function () use ($submission, $reviewer, $reviewRound, $context) {
                $round = $reviewRound;

                // EditorAction reads the notification template and personal
                // message from the request, exactly as the Add Reviewer form
                // posts them: the form's message box is pre-filled with the
                // template body, so seeding sends the same email a real
                // invitation sends.
                $templateKey = ReviewRequest::getEmailTemplateKey();
                $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);

                $this->withRequestVars([
                    'template' => $templateKey,
                    'personalMessage' => $template?->getLocalizedData('body') ?? '',
                ], fn () => (new EditorAction())->addReviewer(
                    Application::get()->getRequest(),
                    $submission,
                    $reviewer->getId(),
                    $round,
                    Core::getCurrentDate(strtotime('+4 weeks')),
                    Core::getCurrentDate(strtotime('+1 week')),
                    $context->getData('defaultReviewMode')
                ));
            });

            $assignment = Repo::reviewAssignment()->getCollector()
                ->filterByReviewRoundIds([$reviewRound->getId()])
                ->filterByReviewerIds([$reviewer->getId()])
                ->getMany()
                ->first();

            if (!$assignment) {
                throw new ScenarioException("Could not assign reviewer '{$reviewerSpec['user']}'.", "{$specKey}.user");
            }

            // The Add Reviewer form stamps the notification date after assigning.
            Repo::reviewAssignment()->edit($assignment, [
                'dateNotified' => Core::getCurrentDate(),
                'considered' => ReviewAssignment::REVIEW_ASSIGNMENT_NEW,
            ]);

            if (in_array($status, ['accepted', 'declined'])) {
                $assignment = Repo::reviewAssignment()->get($assignment->getId());
                $this->actingAs($reviewer, fn () => (new ReviewerAction())->confirmReview(
                    Application::get()->getRequest(),
                    $assignment,
                    $submission,
                    $status === 'declined'
                ));
            }

            $assignment = Repo::reviewAssignment()->get($assignment->getId());

            $assignments[] = [
                'id' => $assignment->getId(),
                'reviewerId' => $reviewer->getId(),
                'username' => $reviewerSpec['user'],
                'status' => $status,
                'dateConfirmed' => $assignment->getDateConfirmed(),
                'declined' => (bool) $assignment->getDeclined(),
            ];
        }

        return $assignments;
    }

    /**
     * The review stage this application uses for `reviewRounds`, or null when the
     * application has no review stage. Read from the app's stage roster.
     */
    protected function reviewStageId(): ?int
    {
        $stages = Application::get()->getReviewStages();

        return empty($stages) ? null : (int) end($stages);
    }

    /**
     * The decision that moves a submission into review in this application, or
     * null when it has no such decision. Declared by the app subclass.
     */
    protected function promoteToReviewDecision(): ?string
    {
        return null;
    }

    //
    // Publishing
    //

    /**
     * @throws ScenarioException
     */
    protected function publish(int $submissionId, Context $context): int
    {
        $submission = Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();
        $editor = $this->resolveEditor(null, $context, $submission, 'published');

        return $this->actingAs($editor, function () use ($publication, $submission, $context) {
            $errors = Repo::publication()->validatePublish(
                $publication,
                $submission,
                $context->getSupportedSubmissionMetadataLocales(),
                $context->getPrimaryLocale()
            );

            if (!empty($errors)) {
                throw new ScenarioException('The publication cannot be published: ' . json_encode($errors), 'published');
            }

            Repo::publication()->publish($publication);

            return $publication->getId();
        });
    }

    //
    // Bookkeeping
    //

    protected ?int $submissionId = null;

    protected function currentSubmissionId(): int
    {
        if ($this->submissionId === null) {
            throw new ScenarioException('No submission has been created yet.', null, 500);
        }

        return $this->submissionId;
    }
}

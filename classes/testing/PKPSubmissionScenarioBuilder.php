<?php

/**
 * @file classes/testing/PKPSubmissionScenarioBuilder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionScenarioBuilder
 *
 * @brief POST /api/v1/_test/scenarios/submission — walk a submission to a
 * declared end-state through the SAME services the wizard and workflow
 * screens use (PRINCIPLES D3): Repo::submission()->add()/submit() (real
 * SubmissionSubmitted event → stage-1 discussion, AssignEditors, tasks),
 * Repo::decision()->add() (real DecisionType::runAdditionalActions — a
 * sendExternalReview decision creates review round 1 itself),
 * EditorAction::addReviewer and ReviewerAction::confirmReview for reviewer
 * states. Tests never script the journey to their starting point.
 *
 * Step-2 core schema: tag*, context* (urlPath), submitter* (username), title,
 * abstract, locale, submitted (explicit false = wizard-resumable draft),
 * decisions[] (real decision names, app-resolved), reviewRounds[] with
 * reviewers[] {username, status: invited|accepted|declined}, published.
 * Overlays: OJS section/issue; OMP series/seriesPosition + per-round stage
 * internal|external; OPS section (reviewRounds REJECTED — no review stage).
 * Richer keys return per feature, each with a parity entry.
 *
 * Feature passthroughs so far (each with a parity-ledger entry):
 * - author {orcid*, orcidIsVerified?} — ORCID iD fixture state on the
 *   submitter's contributor record (U4): a connected iD is only reachable
 *   through ORCID's own OAuth sign-in, which can never complete in the test
 *   env (outbound HTTP fails fast at the dead-port `[proxy]` in
 *   config.test.inc.php, and the sandbox ORCID credentials are dummies), so
 *   the unauthenticated/verified field states are seeded directly
 *   (the same author_settings rows the OAuth landing stores, minus tokens).
 *
 * The workflow start stage comes from each app's submission schema default —
 * never hard-coded here (a hard-coded initial stage once made every seeded
 * OPS submission invisible; PRINCIPLES D5).
 */

namespace PKP\testing;

use APP\core\Application;
use APP\facades\Repo;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
use PKP\context\Context;
use PKP\controllers\grid\users\reviewer\form\traits\HasReviewDueDate;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\action\EditorAction;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\ReviewerAction;
use PKP\submission\reviewRound\ReviewRound;
use PKP\testing\Spec;
use PKP\testing\SpecException;
use PKP\user\User;
use PKP\userGroup\UserGroup;

abstract class PKPSubmissionScenarioBuilder
{
    use HasReviewDueDate;

    public const REVIEWER_STATUSES = ['invited', 'accepted', 'declined'];

    /**
     * Read the app's section/series overlay keys off the root spec and return
     * the publication props they seed (e.g. ['sectionId' => …] OJS/OPS,
     * ['seriesId' => …, 'seriesPosition' => …] OMP). Parse-phase: no writes.
     */
    abstract protected function parsePublicationOverlay(Context $context, Spec $root): array;

    /** The review stage a round spec seeds into (OMP reads its per-round overlay). */
    protected function reviewStageIdForRound(Spec $roundSpec): int
    {
        return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
    }

    /** OPS overrides to reject review seeding outright. */
    protected function assertReviewRoundsSupported(Spec $root): void
    {
    }

    /** App hook before publish (OJS: issue assignment). Overlay parse happens here too. */
    protected function beforePublish(Context $context, \PKP\submission\PKPSubmission $submission, \PKP\publication\PKPPublication $publication, array $overlayPlan): void
    {
    }

    /** Parse app publish-overlay keys (OJS: issue). */
    protected function parsePublishOverlay(Context $context, Spec $root): array
    {
        return [];
    }

    public function build(array $data): array
    {
        $root = new Spec($data);

        // ---- Parse phase: read everything; unknown keys 400 before writes.
        $tag = (string) $root->require('tag');
        $contextPath = (string) $root->require('context');
        $context = Application::getContextDAO()->getByPath($contextPath);
        if (!$context) {
            throw new SpecException('context', "Unknown context urlPath \"{$contextPath}\"");
        }
        $locale = (string) $root->get('locale', $context->getPrimaryLocale());

        $submitterUsername = (string) $root->require('submitter');
        $submitter = Repo::user()->getByUsername($submitterUsername, true);
        if (!$submitter) {
            throw new SpecException('submitter', "Unknown submitter username \"{$submitterUsername}\"");
        }

        $title = $root->localized('title', $locale, "Submission {$tag}");
        // Default the abstract: the wizard requires one on abstract-requiring
        // sections, so a real completed submission always has it (parity
        // spot-check defect 3).
        $abstract = $root->localized('abstract', $locale, "Seeded abstract for {$tag}.");
        $submitted = (bool) $root->get('submitted', true);
        $published = (bool) $root->get('published', false);
        $publicationProps = $this->parsePublicationOverlay($context, $root);

        $decisionNames = (array) $root->get('decisions', []);
        $decisionTypes = [];
        foreach ($decisionNames as $i => $name) {
            $decisionTypes[] = [$name, $this->resolveDecisionType((string) $name, "decisions.{$i}")];
        }

        if ($root->has('reviewRounds')) {
            $this->assertReviewRoundsSupported($root);
        }
        $roundPlans = [];
        foreach ($root->childList('reviewRounds') as $roundSpec) {
            $reviewers = [];
            foreach ($roundSpec->childList('reviewers') as $reviewerSpec) {
                $username = (string) $reviewerSpec->require('username');
                $status = (string) $reviewerSpec->get('status', 'invited');
                if (!in_array($status, self::REVIEWER_STATUSES)) {
                    throw new SpecException("{$reviewerSpec->path}.status", 'Reviewer status must be one of: ' . implode(', ', self::REVIEWER_STATUSES));
                }
                $reviewer = Repo::user()->getByUsername($username, true);
                if (!$reviewer) {
                    throw new SpecException("{$reviewerSpec->path}.username", "Unknown reviewer username \"{$username}\"");
                }
                $reviewers[] = ['user' => $reviewer, 'status' => $status];
            }
            $roundPlans[] = [
                'stageId' => $this->reviewStageIdForRound($roundSpec),
                'reviewers' => $reviewers,
            ];
        }

        $authorPlan = null;
        if (($authorSpec = $root->child('author')) !== null) {
            $authorPlan = [
                'orcid' => (string) $authorSpec->require('orcid'),
                'orcidIsVerified' => (bool) $authorSpec->get('orcidIsVerified', false),
            ];
        }

        $publishOverlayPlan = $this->parsePublishOverlay($context, $root);
        $root->assertConsumed();

        if ($published && !$submitted) {
            throw new SpecException('published', 'published: true requires a submitted submission');
        }

        // ---- Execute phase.
        $request = Application::get()->getRequest();

        // The seeding request is site-wide, but the services and listeners the
        // build invokes (notification managers, mailables) read
        // $request->getContext(). Force the router's context to the
        // submission's context for the duration of the build.
        $restoreRouterContext = $this->forceRequestContext($context);
        try {
            return $this->execute($root, $context, $locale, $tag, $submitter, $title, $abstract, $submitted, $published, $publicationProps, $decisionTypes, $roundPlans, $publishOverlayPlan, $authorPlan);
        } finally {
            $restoreRouterContext();
        }
    }

    /**
     * Point the current router at a context so $request->getContext() answers
     * during the build; returns a restorer. Test-harness-only reflection — the
     * production code path never runs this.
     */
    private function forceRequestContext(Context $context): callable
    {
        $router = Application::get()->getRequest()->getRouter();
        $property = new \ReflectionProperty(\PKP\core\PKPRouter::class, '_context');
        $previous = $property->isInitialized($router) ? $property->getValue($router) : null;
        $property->setValue($router, $context);
        return function () use ($router, $property, $previous): void {
            $property->setValue($router, $previous);
        };
    }

    private function execute(
        Spec $root,
        Context $context,
        string $locale,
        string $tag,
        User $submitter,
        array $title,
        ?array $abstract,
        bool $submitted,
        bool $published,
        array $publicationProps,
        array $decisionTypes,
        array $roundPlans,
        array $publishOverlayPlan,
        ?array $authorPlan = null
    ): array {
        $request = Application::get()->getRequest();

        // Create + (maybe) submit as the submitter — wizard parity.
        $previousActingUser = Registry::get('user');
        Registry::set('user', $submitter);
        try {
            $submitAsUserGroup = $this->resolveSubmitAsUserGroup($context, $submitter);

            $submissionParams = ['contextId' => $context->getId(), 'locale' => $locale];
            $submission = Repo::submission()->newDataObject($submissionParams);
            $publication = Repo::publication()->newDataObject($publicationProps);
            $submissionId = Repo::submission()->add($submission, $publication, $context);
            $submission = Repo::submission()->get($submissionId);
            $publication = Repo::publication()->get($submission->getData('currentPublicationId'));

            Repo::stageAssignment()->build(
                $submission->getId(),
                $submitAsUserGroup->id,
                $submitter->getId(),
                $submitAsUserGroup->recommendOnly,
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

                if ($authorPlan !== null) {
                    // Same author_settings rows the OAuth verify landing
                    // stores (orcid, orcidIsVerified), written through the
                    // real author repository; token fields deliberately
                    // absent (parity ledger 2026-08-07).
                    Repo::author()->edit(Repo::author()->get($authorId), [
                        'orcid' => $authorPlan['orcid'],
                        'orcidIsVerified' => $authorPlan['orcidIsVerified'],
                    ]);
                    $authorPlan = null;
                }
            }

            if ($authorPlan !== null) {
                throw new SpecException('author', 'author requires the submitter to submit as an Author (no contributor record was created)');
            }

            $publicationEdits = ['title' => $title];
            if ($abstract !== null) {
                $publicationEdits['abstract'] = $abstract;
            }
            // Re-fetch: editing through the pre-primaryContact object would
            // write its stale data back and erase primaryContactId (parity
            // spot-check defect 1).
            $publication = Repo::publication()->get($publication->getId());
            Repo::publication()->edit($publication, $publicationEdits);

            if ($submitted) {
                $submission = Repo::submission()->get($submissionId);
                Repo::submission()->submit($submission, $context);

                // The real submit endpoint's tail: create the unpublished-state
                // notifications (APPROVE_SUBMISSION and its app delegates) the
                // workflow's approval UI runs on (parity spot-check defect 2).
                $notificationManagerClass = '\APP\notification\NotificationManager';
                $notificationManager = new $notificationManagerClass();
                $notificationManager->updateNotification(
                    $request,
                    [\PKP\notification\Notification::NOTIFICATION_TYPE_APPROVE_SUBMISSION],
                    null,
                    \PKP\core\PKPApplication::ASSOC_TYPE_SUBMISSION,
                    $submissionId
                );
            }
        } finally {
            Registry::set('user', $previousActingUser);
        }

        // Decisions + review rounds, acting as the editor (admin).
        $editor = $this->actingEditor();
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
        $roundIndex = 0;
        $seededAssignments = [];

        $previousActingUser = Registry::get('user');
        Registry::set('user', $editor);
        try {
            foreach ($decisionTypes as [$name, $decisionType]) {
                $submission = Repo::submission()->get($submissionId);
                $decision = Repo::decision()->newDataObject();
                $decision->setData('decision', $decisionType->getDecision());
                $decision->setData('submissionId', $submissionId);
                $decision->setData('editorId', $editor->getId());
                $decision->setData('stageId', $decisionType->getStageId());
                if (in_array($decisionType->getStageId(), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
                    $lastRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId, $decisionType->getStageId());
                    if ($lastRound) {
                        $decision->setData('reviewRoundId', $lastRound->getId());
                    }
                }
                Repo::decision()->add($decision);

                // A decision that promotes into a review stage creates that
                // round — seed the next declared round spec into it.
                $newStageId = $decisionType->getNewStageId(Repo::submission()->get($submissionId), null);
                if (in_array($newStageId, [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW]) && $roundIndex < count($roundPlans)) {
                    $round = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId, $newStageId);
                    if ($round) {
                        $seededAssignments = array_merge(
                            $seededAssignments,
                            $this->seedRoundReviewers($context, $submissionId, $round, $roundPlans[$roundIndex]['reviewers'])
                        );
                        $roundIndex++;
                    }
                }
            }

            // Remaining declared rounds (no decision created them): build the
            // round with the same shape the real decision path produces —
            // mirrored from DecisionType::createReviewRound (DecisionType.php
            // ~511-541): PENDING_REVIEWERS status, the latest UNPUBLISHED
            // publication id (getLatestUnPublishedPublicationId, ~556-562),
            // and the REVIEW_ROUND_STATUS notification row the workflow's
            // round header runs on.
            while ($roundIndex < count($roundPlans)) {
                $plan = $roundPlans[$roundIndex];
                $submission = Repo::submission()->get($submissionId);
                $lastRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId, $plan['stageId']);
                $roundNumber = $lastRound ? $lastRound->getRound() + 1 : 1;
                $publicationId = $submission->getData('publications')
                    ->filter(fn ($publication) => $publication->getData('status') !== \PKP\publication\PKPPublication::STATUS_PUBLISHED)
                    ->reduce(fn ($a, $b) => $a && $a->getId() > $b->getId() ? $a : $b)
                    ?->getId();
                $round = $reviewRoundDao->build(
                    $submissionId,
                    $publicationId,
                    $plan['stageId'],
                    $roundNumber,
                    ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS
                );
                $notificationCount = Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ROUND, $round->getId())
                    ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS)
                    ->withContextId($submission->getData('contextId'))
                    ->count();
                if ($notificationCount == 0) {
                    $notificationMgr = new \APP\notification\NotificationManager();
                    $notificationMgr->createNotification(
                        null,
                        Notification::NOTIFICATION_TYPE_REVIEW_ROUND_STATUS,
                        $submission->getData('contextId'),
                        Application::ASSOC_TYPE_REVIEW_ROUND,
                        $round->getId()
                    );
                }
                $seededAssignments = array_merge(
                    $seededAssignments,
                    $this->seedRoundReviewers($context, $submissionId, $round, $plan['reviewers'])
                );
                $roundIndex++;
            }

            if ($published) {
                $submission = Repo::submission()->get($submissionId);
                $publication = Repo::publication()->get($submission->getData('currentPublicationId'));
                $this->beforePublish($context, $submission, $publication, $publishOverlayPlan);
                $publication = Repo::publication()->get($publication->getId());

                // The real publish endpoint validates before publishing
                // (PKPSubmissionController::publishPublication ~1413-1424):
                // a seed the UI would refuse must fail loudly, not publish
                // an impossible state.
                $primaryLocale = $submission->getData('locale');
                $allowedLocales = $context->getData('supportedSubmissionLocales');
                $errors = Repo::publication()->validatePublish($publication, $submission, (array) $allowedLocales, $primaryLocale);
                if (!empty($errors)) {
                    throw new SpecException('published', 'The publish endpoint would refuse this publication: ' . json_encode($errors));
                }

                // Mirrored from PKPSubmissionController::publishPublication
                // (~1426-1445): publish without status stamping, sweep the
                // author stage assignments to canChangeMetadata = 0
                // (controller-only code — exists nowhere else), then
                // re-derive submission status/current publication off a
                // fresh fetch.
                Repo::publication()->publish($publication, false);

                $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                    ->get();
                foreach ($stageAssignments as $stageAssignment) {
                    $userGroup = $stageAssignment->userGroup;
                    if ($userGroup && $userGroup->roleId === Role::ROLE_ID_AUTHOR) {
                        $stageAssignment->canChangeMetadata = 0;
                        $stageAssignment->save();
                    }
                }

                $submission = Repo::submission()->get($submission->getId());
                Repo::submission()->updateStatus($submission);
                Repo::submission()->updateCurrentPublication($submission);
            }
        } finally {
            Registry::set('user', $previousActingUser);
        }

        // ---- Response.
        $submission = Repo::submission()->get($submissionId);
        $rounds = [];
        $roundsIterator = $reviewRoundDao->getBySubmissionId($submissionId);
        while ($round = $roundsIterator->next()) {
            $rounds[] = ['id' => $round->getId(), 'round' => $round->getRound(), 'stageId' => $round->getStageId()];
        }

        return [
            'tag' => $tag,
            'submissionId' => $submissionId,
            'publicationId' => $submission->getData('currentPublicationId'),
            'stageId' => $submission->getData('stageId'),
            'status' => $submission->getData('status'),
            'submissionProgress' => $submission->getData('submissionProgress'),
            'reviewRounds' => $rounds,
            'reviewAssignments' => $seededAssignments,
        ];
    }

    /**
     * The wizard's submit-as resolution (PKPSubmissionController::add parity):
     * the user's author/manager group, authors preferred; fall back to
     * enrolling in the context's default author group.
     */
    protected function resolveSubmitAsUserGroup(Context $context, User $submitter): UserGroup
    {
        $submitterUserGroups = UserGroup::withContextIds($context->getId())
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR])
            ->whereHas('userUserGroups', function ($query) use ($submitter) {
                $query->withUserId($submitter->getId())->withActive();
            })
            ->get();

        if ($submitterUserGroups->count()) {
            return $submitterUserGroups
                ->sort(fn (UserGroup $a, UserGroup $b) => ((int) $a->roleId) === Role::ROLE_ID_AUTHOR ? -1 : 1)
                ->first();
        }

        $submitAsUserGroup = UserGroup::withContextIds($context->getId())
            ->withRoleIds(Role::ROLE_ID_AUTHOR)
            ->first();
        if (!$submitAsUserGroup) {
            throw new SpecException('submitter', 'This context has no author user group to submit as');
        }
        Repo::userGroup()->assignUserToGroup($submitter->getId(), $submitAsUserGroup->id);
        return $submitAsUserGroup;
    }

    /**
     * Assign + confirm/decline reviewers on a round through the real editor
     * and reviewer actions.
     *
     * @param array{user: User, status: string}[] $reviewers
     */
    protected function seedRoundReviewers(Context $context, int $submissionId, \PKP\submission\reviewRound\ReviewRound $round, array $reviewers): array
    {
        $request = Application::get()->getRequest();
        $editorAction = new EditorAction();
        $reviewerAction = new ReviewerAction();
        $seeded = [];

        // addReviewer() reads the Add Reviewer form's skipEmail user var; the
        // form's "do not send email" path is the parity-correct one for
        // seeding (scenario-side mail is dropped regardless — Mail::fake).
        // Deviation from the mail-sending path is recorded in the parity
        // ledger (no REVIEW_REQUEST email log row on seeded assignments).
        app('request')->merge(['skipEmail' => 1]);

        // Due dates at Add Reviewer form parity: the app's own
        // HasReviewDueDate trait (review due = today + numWeeksPerReview,
        // response due = today + numWeeksPerResponse, each from its OWN
        // interval with the trait's 4/3-week fallbacks), formatted as the
        // datepicker's Y-m-d altField submits them (parity fix 2026-08-02 —
        // the builder used to seed review due = today + response + review).
        [$reviewDueTimestamp, $responseDueTimestamp] = $this->getDueDates($context);
        $reviewDueDate = date('Y-m-d', $reviewDueTimestamp);
        $responseDueDate = date('Y-m-d', $responseDueTimestamp);

        // The Add Reviewer form always submits a review method: the context's
        // defaultReviewMode with a double-anonymous fallback
        // (ReviewerForm::initData ~206-210; schema default 2). Passing null
        // here would let the review_assignments column default (anonymous)
        // win — a state no UI path produces (parity fix 2026-08-23).
        $reviewMethod = (int) $context->getData('defaultReviewMode');
        if (!$reviewMethod) {
            $reviewMethod = ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
        }

        // The section/series default review form, exactly as the Add Reviewer
        // form resolves it (ReviewerForm::initData ~212-221) and validates it
        // on save (ReviewerForm::execute ~366-369).
        $submission = Repo::submission()->get($submissionId);
        $sectionId = $submission->getCurrentPublication()->getData(Application::getSectionIdPropName());
        $section = $sectionId ? Repo::section()->get($sectionId, $context->getId()) : null;
        $reviewFormId = $section ? (int) $section->getReviewFormId() : 0;
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var \PKP\reviewForm\ReviewFormDAO $reviewFormDao */
        $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

        foreach ($reviewers as $plan) {
            $reviewer = $plan['user'];
            $submission = Repo::submission()->get($submissionId);
            $editorAction->addReviewer(
                $request,
                $submission,
                $reviewer->getId(),
                $round,
                $reviewDueDate,
                $responseDueDate,
                $reviewMethod,
                // pass explicitly: the seeding request is site-wide, so the
                // service must not fall back to $request->getContext()
                (bool) $context->getData('defaultReviewPublicVisibility')
            );
            $assignment = Repo::reviewAssignment()->getCollector()
                ->filterByReviewRoundIds([$round->getId()])
                ->filterByReviewerIds([$reviewer->getId()])
                ->getMany()
                ->first();
            if (!$assignment) {
                throw new SpecException('reviewRounds', "Review assignment for \"{$reviewer->getUsername()}\" was not created");
            }
            // The Add Reviewer form's post-add stamp, mirrored from
            // ReviewerForm::execute ~371-375: notification date, the
            // section's default review form, and considered = NEW.
            Repo::reviewAssignment()->edit($assignment, [
                'dateNotified' => \PKP\core\Core::getCurrentDate(),
                'reviewFormId' => $reviewForm ? $reviewFormId : null,
                'considered' => ReviewAssignment::REVIEW_ASSIGNMENT_NEW,
            ]);

            if ($plan['status'] !== 'invited') {
                $assignment = Repo::reviewAssignment()->get($assignment->getId());
                $previousActingUser = Registry::get('user');
                Registry::set('user', $reviewer);
                try {
                    $reviewerAction->confirmReview(
                        $request,
                        $assignment,
                        $submission,
                        $plan['status'] === 'declined'
                    );
                } finally {
                    Registry::set('user', $previousActingUser);
                }
            }

            $assignment = Repo::reviewAssignment()->get($assignment->getId());
            $seeded[] = [
                'id' => $assignment->getId(),
                'reviewerId' => $reviewer->getId(),
                'username' => $reviewer->getUsername(),
                'status' => $plan['status'],
                'reviewRoundId' => $round->getId(),
            ];
        }
        return $seeded;
    }

    /** Resolve a decision name against the app's own decision types. */
    protected function resolveDecisionType(string $name, string $specKey): \PKP\decision\DecisionType
    {
        $types = Repo::decision()->getDecisionTypes();
        foreach ($types as $type) {
            if (lcfirst(class_basename($type)) === $name) {
                return $type;
            }
        }
        $available = $types
            ->map(fn ($type) => lcfirst(class_basename($type)))
            ->sort()
            ->values()
            ->join(', ');
        throw new SpecException($specKey, "Unknown decision \"{$name}\". This app's decisions: {$available}");
    }

    /** The editor decisions are attributed to: the installer's admin. */
    protected function actingEditor(): User
    {
        $admin = Repo::user()->getByUsername('admin', true);
        if (!$admin) {
            throw new SpecException('context', 'No admin user found — is the test install bootstrapped?');
        }
        return $admin;
    }
}

<?php

/**
 * @file classes/testing/scenario/Processor/SubmissionBuilderProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionBuilderProcessor
 *
 * @brief Creates the submission + its first (bare) publication, creates
 *        the submitter's Author row, and assigns the submitter to
 *        submission stage as an author.
 *
 * Leaves the publication deliberately bare — PublicationsProcessor fills
 * in metadata / attributes / publish on the second pass so the concerns
 * stay separate.
 *
 * Mirrors the happy-path shape of PKPSubmissionController::add, minus
 * auth/validation/user-group-disambiguation (not relevant in a
 * test-gated endpoint).
 */

namespace PKP\testing\scenario\Processor;

use APP\core\Application;
use APP\facades\Repo;
use PKP\author\contributorRole\ContributorType;
use PKP\core\Core;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;
use PKP\userGroup\UserGroup;

class SubmissionBuilderProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        // Always runs — a submission scenario isn't a submission without a submission.
        return true;
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $context = $ctx->contextByPath($spec['journal']);
        $submitter = $ctx->userByUsername($spec['submitter']);
        $locale = $spec['locale'] ?? 'en';
        $sectionId = $this->resolveSectionId($context->getId(), $spec['section'], $locale);

        // Create submission + bare publication atomically via Repo.
        $submission = Repo::submission()->newDataObject([
            'contextId' => $context->getId(),
            'locale' => $locale,
            'status' => \PKP\submission\PKPSubmission::STATUS_QUEUED,
            'stageId' => WORKFLOW_STAGE_ID_SUBMISSION,
            'submissionProgress' => '',
            'dateSubmitted' => Core::getCurrentDate(),
        ]);
        $publication = Repo::publication()->newDataObject([
            'sectionId' => $sectionId,
            // PublicationVersionInfo requires non-null major/minor when
            // versionStage is later set. Seed the natural defaults a new
            // submission would get via the UI.
            'versionMajor' => 1,
            'versionMinor' => 0,
        ]);
        $submissionId = Repo::submission()->add($submission, $publication, $context);

        // Re-fetch so later processors see the wired-up currentPublicationId.
        $submission = Repo::submission()->get($submissionId);
        $publicationId = (int)$submission->getData('currentPublicationId');

        // Assign the submitter to stage 1 as author.
        $authorUserGroup = UserGroupLookup::userGroupForRole($context->getId(), 'author');
        Repo::stageAssignment()->build(
            $submissionId,
            (int)$authorUserGroup->id,
            $submitter->getId()
        );

        // Create the Author row from the submitter's user record and mark
        // them as primary contact. Mirrors PKPSubmissionController::add
        // (lines 737-751) without the user-group-role branching.
        $author = Repo::author()->newAuthorFromUser($submitter, $submission, $context);
        $author->setData('publicationId', $publicationId);
        $author->setData('contributorType', ContributorType::PERSON->getName());
        $authorId = Repo::author()->add($author);

        $freshPublication = Repo::publication()->get($publicationId);
        Repo::publication()->edit($freshPublication, ['primaryContactId' => $authorId]);

        $ctx->recordSubmission($submissionId, $publicationId, $context->getId());

        return [];
    }

    /**
     * Resolve a section abbrev (e.g. 'ART') to its numeric section ID in the
     * given context. Falls back to the first section if the abbrev is empty.
     */
    private function resolveSectionId(int $contextId, string $abbrev, string $locale): int
    {
        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$contextId])
            ->getMany();

        foreach ($sections as $section) {
            $sectionAbbrev = $section->getLocalizedData('abbrev', $locale);
            if ($sectionAbbrev === $abbrev) {
                return (int)$section->getId();
            }
        }

        // Second pass: try any locale's abbrev, in case the spec locale
        // differs from where the section label was stored.
        foreach ($sections as $section) {
            $allAbbrevs = $section->getData('abbrev') ?? [];
            if (in_array($abbrev, $allAbbrevs, true)) {
                return (int)$section->getId();
            }
        }

        throw new \RuntimeException(
            "Section with abbrev '{$abbrev}' not found in context {$contextId}. "
            . "Bootstrap seeds sections; if this test needs a new one, add it to the baseline spec."
        );
    }
}

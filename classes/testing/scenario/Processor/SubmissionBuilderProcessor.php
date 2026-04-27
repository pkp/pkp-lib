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
 *        the submitter's Author row, assigns the submitter to submission
 *        stage as an author, and attaches a default Article Text file —
 *        mirroring the field-shape produced by SubmissionFilesUploadForm
 *        on a real wizard upload.
 *
 * Optional spec fields handled here:
 *   - commentsForEditor: copied to the submission's commentsForTheEditors
 *     setting (the field key the wizard's "For the Editors" step writes).
 *   - submitted: when true (default-true if the spec has decisions or
 *     reviewRounds), Repo::submission()->submit() is invoked to mirror
 *     the wizard's final Submit click — this fires SubmissionSubmitted
 *     and converts a present commentsForTheEditors value into a Stage 1
 *     "Comments for the Editor" discussion via
 *     Repo::editorialTask()->addCommentsForEditorsQuery().
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
use PKP\submissionFile\SubmissionFile;
use PKP\testing\scenario\GenreLookup;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;

class SubmissionBuilderProcessor implements ScenarioProcessor
{
    /** Path of the bundled default Article Text PDF, relative to the OJS root. */
    private const DEFAULT_ARTICLE_FIXTURE = 'lib/pkp/playwright/fixtures/files/default-article.pdf';

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

        // Optional `author` passthrough — fields the spec wants to seed on
        // the just-created Author row that the public REST endpoints
        // refuse to set (notably `orcid` / `orcidIsVerified`, which the
        // Author validator hard-blocks via api.orcid.403.cannotUpdateAuthorOrcid
        // — see Repository::validate). Tests that need a verified ORCID
        // iD on the seeded contributor (e.g. the article-page reader
        // assertion in row #55) call Repo::author()->edit() directly here
        // to bypass that validator. Stay narrow: only fields explicitly
        // listed below are recognised.
        if (!empty($spec['author']) && is_array($spec['author'])) {
            $authorEditParams = [];
            foreach (['orcid', 'orcidIsVerified'] as $k) {
                if (array_key_exists($k, $spec['author'])) {
                    $authorEditParams[$k] = $spec['author'][$k];
                }
            }
            if (!empty($authorEditParams)) {
                $authorRow = Repo::author()->get($authorId);
                Repo::author()->edit($authorRow, $authorEditParams);
            }
        }

        $freshPublication = Repo::publication()->get($publicationId);
        Repo::publication()->edit($freshPublication, ['primaryContactId' => $authorId]);

        // Attach the default Article Text file. Mirrors
        // SubmissionFilesUploadForm::execute() field-for-field — copy the
        // bundled fixture into the canonical files-dir layout via the
        // 'file' service, then build the SubmissionFile data object with
        // the same fields the form sets and call
        // Repo::submissionFile()->add($submissionFile).
        $this->attachDefaultArticleFile($submission, $context, $submitter);

        // commentsForEditor (spec key) → commentsForTheEditors (submission
        // setting key). The setting is what Repo::submission()->submit()
        // reads to decide whether to create the Stage 1 cover-note query.
        if (!empty($spec['commentsForEditor'])) {
            Repo::submission()->edit($submission, [
                'commentsForTheEditors' => $spec['commentsForEditor'],
            ]);
            // Refresh in-memory copy so the submit() call below sees the
            // setting on the submission it operates on.
            $submission = Repo::submission()->get($submissionId);
        }

        // submit() converts a wizard-in-progress submission into a
        // submitted one: clears submissionProgress, fires
        // SubmissionSubmitted, and creates the cover-note discussion when
        // commentsForTheEditors is set. Default-true ONLY when the
        // scenario implies post-wizard editorial action (decisions or
        // reviewRounds present) so existing draft-style fixtures don't
        // shift shape. Tests that need a draft with decisions can opt
        // out via `submitted: false`.
        $shouldSubmit = $spec['submitted']
            ?? (!empty($spec['decisions']) || !empty($spec['reviewRounds']));

        if ($shouldSubmit) {
            Repo::submission()->submit($submission, $context);
        }

        $ctx->recordSubmission($submissionId, $publicationId, $context->getId());

        return [];
    }

    /**
     * Copy the bundled default-article.pdf into the submission's files-dir
     * tree and create the matching SubmissionFile + files row. Mirrors
     * SubmissionFilesUploadForm::execute() field-for-field for the
     * "new submission file" branch (no revisedFileId, no review-round
     * association).
     */
    private function attachDefaultArticleFile(
        \APP\submission\Submission $submission,
        \PKP\context\Context $context,
        \PKP\user\User $submitter
    ): void {
        $fixturePath = $this->resolveFixturePath();
        $genre = GenreLookup::genreForKey($context->getId(), 'ARTICLE');

        // Files-dir layout: {context-dir}/{contextId}/{submission-dir}/{submissionId}.
        // Same call SubmissionFilesUploadForm uses; the leading slash on
        // each dir is stripped by getSubmissionDir().
        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $context->getId(),
            $submission->getId()
        );
        $extension = 'pdf';
        $relativePath = $submissionDir . '/' . uniqid() . '.' . $extension;

        // app('file')->add streams the source file into Flysystem at
        // $relativePath, returns the new files.file_id.
        $fileId = app()->get('file')->add($fixturePath, $relativePath);

        // Build the SubmissionFile data object with the same field set
        // SubmissionFilesUploadForm::execute() populates on a fresh upload
        // (lines 215-228). assocType / assocId stay null for stage-1 files.
        $submissionFile = Repo::submissionFile()->dao->newDataObject();
        $submissionFile->setData('fileId', $fileId);
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_SUBMISSION);
        $submissionFile->setData(
            'name',
            'default-article.pdf',
            $submission->getData('locale')
        );
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('uploaderUserId', $submitter->getId());
        $submissionFile->setData('assocType', null);
        $submissionFile->setData('assocId', null);
        $submissionFile->setData('genreId', (int)$genre->getId());

        Repo::submissionFile()->add($submissionFile);
    }

    /**
     * Locate the bundled default-article.pdf fixture. The OJS root is two
     * directories up from this file (lib/pkp/classes/testing/scenario/Processor)
     * — actually four. Resolve via INDEX_FILE_LOCATION when available
     * (set by index.php), else fall back to walking up from __DIR__.
     */
    private function resolveFixturePath(): string
    {
        $base = defined('INDEX_FILE_LOCATION')
            ? dirname(INDEX_FILE_LOCATION)
            : dirname(__DIR__, 6);

        $path = $base . '/' . self::DEFAULT_ARTICLE_FIXTURE;
        if (!is_readable($path)) {
            throw new \RuntimeException(
                "Default article fixture not readable at {$path}. "
                . "Was the scenario endpoint run from outside an OJS install?"
            );
        }
        return $path;
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

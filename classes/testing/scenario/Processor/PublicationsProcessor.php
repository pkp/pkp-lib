<?php

/**
 * @file classes/testing/scenario/Processor/PublicationsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationsProcessor
 *
 * @brief Fills in metadata + attributes + optional publish on each
 *        publication in the spec's `publications` array.
 *
 * Index 0 targets the bare publication already created by
 * SubmissionBuilderProcessor. Index > 0 creates a chained new version
 * via Repo::publication()->version(), which copies authors/citations
 * and derives versionMajor/versionMinor from the versionIsMinor flag
 * — exactly what the UI's publish form does when a user creates a
 * new version.
 */

namespace PKP\testing\scenario\Processor;

use APP\facades\Repo;
use APP\publication\enums\VersionStage;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;

class PublicationsProcessor implements ScenarioProcessor
{
    /** Content metadata fields the spec accepts under publications[].metadata. */
    private const METADATA_FIELDS = [
        'title', 'subtitle', 'prefix', 'abstract', 'plainLanguageSummary',
        'keywords', 'subjects', 'disciplines', 'supportingAgencies',
        'coverage', 'type', 'source', 'rights', 'fundingStatement',
        'dataAvailability', 'copyrightHolder', 'copyrightYear',
        'licenseUrl', 'pages', 'urlPath',
    ];

    /** Publication-level attribute fields the spec accepts directly on a publications[] entry. */
    private const ATTRIBUTE_FIELDS = ['jatsPublicVisibility'];

    public function appliesTo(array $spec): bool
    {
        return !empty($spec['publications']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $tag = $spec['tag'];
        $publications = $spec['publications'];
        $previousPublicationId = $ctx->firstPublicationId();

        foreach ($publications as $i => $pubSpec) {
            if ($i === 0) {
                $publicationId = $previousPublicationId;
            } else {
                $previous = Repo::publication()->get($previousPublicationId);
                $versionStage = isset($pubSpec['versionStage'])
                    ? VersionStage::from($pubSpec['versionStage'])
                    : null;
                $publicationId = Repo::publication()->version(
                    $previous,
                    $versionStage,
                    (bool)($pubSpec['versionIsMinor'] ?? true)
                );
                $previousPublicationId = $publicationId;
            }

            $this->applyMetadataAndAttributes($publicationId, $pubSpec, $tag);

            if (!empty($pubSpec['published'])) {
                $this->publish($publicationId, $pubSpec, $ctx);
            }

            $publication = Repo::publication()->get($publicationId);
            $ctx->recordPublication([
                'id' => (int)$publication->getId(),
                'versionStage' => $publication->getData('versionStage'),
                'versionMajor' => $publication->getData('versionMajor'),
                'versionMinor' => $publication->getData('versionMinor'),
                'status' => $publication->getData('status'),
                'issueId' => $publication->getData('issueId'),
                'datePublished' => $publication->getData('datePublished'),
            ]);
        }

        return [];
    }

    /**
     * Merge metadata (with the tag appended to every title locale) plus
     * UI-settable attributes onto the target publication via one edit() call.
     */
    private function applyMetadataAndAttributes(int $publicationId, array $pubSpec, string $tag): void
    {
        $metadata = $pubSpec['metadata'] ?? [];
        $editParams = [];

        foreach (self::METADATA_FIELDS as $field) {
            if (array_key_exists($field, $metadata)) {
                $editParams[$field] = $metadata[$field];
            }
        }

        // Append [tag] to every locale of the title for parallel isolation.
        if (isset($editParams['title']) && is_array($editParams['title'])) {
            foreach ($editParams['title'] as $locale => $value) {
                $editParams['title'][$locale] = trim((string)$value) . " [{$tag}]";
            }
        }

        // Set versionStage here on the index-0 publication too (where
        // version() wasn't called to do it). For index > 0 it's redundant
        // but harmless — Repo::publication()->version already set it.
        if (isset($pubSpec['versionStage'])) {
            $editParams['versionStage'] = $pubSpec['versionStage'];
        }

        foreach (self::ATTRIBUTE_FIELDS as $field) {
            if (array_key_exists($field, $pubSpec)) {
                $editParams[$field] = $pubSpec[$field];
            }
        }

        if (empty($editParams)) {
            return;
        }

        $publication = Repo::publication()->get($publicationId);
        Repo::publication()->edit($publication, $editParams);
    }

    /**
     * Resolve optional issue → issueId, then Repo::publication()->publish.
     */
    private function publish(int $publicationId, array $pubSpec, ScenarioContext $ctx): void
    {
        if (isset($pubSpec['issue'])) {
            $issueId = $this->resolveIssueId($pubSpec['issue'], $ctx->submissionContextId());
            $publication = Repo::publication()->get($publicationId);
            Repo::publication()->edit($publication, ['issueId' => $issueId]);
        }

        $publication = Repo::publication()->get($publicationId);
        // Pass `false` to skip the auto submission-status update —
        // matches PKPSubmissionController::publishPublication
        // (lib/pkp/api/v1/submissions/PKPSubmissionController.php:1442).
        Repo::publication()->publish($publication, false);

        // After publish, production iterates stage_assignments and clears
        // canChangeMetadata on every AUTHOR role assignment — authors
        // lose metadata-edit after publish. Mirror that here so
        // scenario-seeded published submissions reflect the same
        // permission state.
        $submissionId = Repo::publication()->get($publicationId)->getData('submissionId');
        $authorAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->get();
        foreach ($authorAssignments as $stageAssignment) {
            $stageAssignment->canChangeMetadata = 0;
            $stageAssignment->save();
        }
    }

    /**
     * Accepts three forms:
     *   - { volume, number, year } — key lookup into Phase 1 bootstrap issues
     *   - 'latest'  — most recently published issue in this journal
     *   - 'current' — the journal's current (unpublished) issue
     */
    private function resolveIssueId(array|string $issueSpec, int $contextId): int
    {
        if (is_string($issueSpec)) {
            return match ($issueSpec) {
                'latest' => $this->resolveLatestPublishedIssue($contextId),
                'current' => $this->resolveCurrentIssue($contextId),
                default => throw new \InvalidArgumentException("Unknown issue shorthand '{$issueSpec}'; use 'latest' or 'current'"),
            };
        }

        $collector = Repo::issue()->getCollector()->filterByContextIds([$contextId]);
        if (isset($issueSpec['volume'])) {
            $collector = $collector->filterByVolumes([(int)$issueSpec['volume']]);
        }
        if (isset($issueSpec['number'])) {
            $collector = $collector->filterByNumbers([(string)$issueSpec['number']]);
        }
        if (isset($issueSpec['year'])) {
            $collector = $collector->filterByYears([(int)$issueSpec['year']]);
        }
        $matches = $collector->getMany();
        if ($matches->isEmpty()) {
            throw new \RuntimeException(
                "No issue found in context {$contextId} matching " . json_encode($issueSpec)
                . ". Seed the needed issue in the bootstrap spec."
            );
        }
        if ($matches->count() > 1) {
            throw new \RuntimeException(
                "Issue spec is ambiguous — " . $matches->count() . " matches. Add more identifying fields."
            );
        }
        return (int)$matches->first()->getId();
    }

    private function resolveLatestPublishedIssue(int $contextId): int
    {
        $issues = Repo::issue()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublished(true)
            ->orderBy(Repo::issue()->getCollector()::ORDERBY_PUBLISHED, Repo::issue()->getCollector()::ORDER_DIR_DESC)
            ->getMany();
        if ($issues->isEmpty()) {
            throw new \RuntimeException(
                "publications[].issue = 'latest' but context {$contextId} has no published issues. "
                . "Seed one in the bootstrap spec."
            );
        }
        return (int)$issues->first()->getId();
    }

    private function resolveCurrentIssue(int $contextId): int
    {
        $current = Repo::issue()->getCurrent($contextId);
        if (!$current) {
            throw new \RuntimeException(
                "publications[].issue = 'current' but context {$contextId} has no current issue."
            );
        }
        return (int)$current->getId();
    }
}

<?php

/**
 * @file classes/submissionFile/VariantGroup.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VariantGroup
 *
 * @brief Eloquent model for variant groups that link related media file variants.
 */

namespace PKP\submissionFile;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;

class VariantGroup extends Model
{
    use HasCamelCasing;

    protected $table = 'variant_groups';
    protected $primaryKey = 'variant_group_id';
    public $timestamps = false;
    protected $guarded = [];

    /**
     * Maximum number of files allowed in a single variant group.
     */
    public const int MAX_GROUP_SIZE = 2;

    /**
     * Link two media submission files into a variant group and propagate metadata from primary to secondary.
     *
     * @throws \Exception on validation failure (group conflict, capacity)
     */
    public static function link(SubmissionFile $primaryFile, SubmissionFile $secondaryFile, int $submissionId): void
    {
        $primaryGroupId = $primaryFile->getData('variantGroupId');
        $secondaryGroupId = $secondaryFile->getData('variantGroupId');

        // Already in the same group
        if ($primaryGroupId && $secondaryGroupId && $primaryGroupId === $secondaryGroupId) {
            return;
        }

        // Both in different groups
        if ($primaryGroupId && $secondaryGroupId && $primaryGroupId !== $secondaryGroupId) {
            throw new \Exception(__('api.submissionFiles.400.filesInDifferentGroups'));
        }

        // Check capacity on existing groups
        foreach (array_filter([$primaryGroupId, $secondaryGroupId]) as $groupId) {
            $count = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_MEDIA])
                ->filterByVariantGroupIds([$groupId])
                ->getMany()
                ->count();

            if ($count >= static::MAX_GROUP_SIZE) {
                throw new \Exception(__('api.submissionFiles.400.variantGroupAtCapacity'));
            }
        }

        // Create or reuse group
        if (!$primaryGroupId && !$secondaryGroupId) {
            $variantGroup = static::create([]);
            $variantGroupId = $variantGroup->getKey();
            Repo::submissionFile()->edit($primaryFile, ['variantGroupId' => $variantGroupId]);
            Repo::submissionFile()->edit($secondaryFile, ['variantGroupId' => $variantGroupId]);
        } elseif ($primaryGroupId) {
            Repo::submissionFile()->edit($secondaryFile, ['variantGroupId' => $primaryGroupId]);
        } else {
            Repo::submissionFile()->edit($primaryFile, ['variantGroupId' => $secondaryGroupId]);
        }

        // Apply common fields from primary to secondary
        $primaryFile = Repo::submissionFile()->get($primaryFile->getId());
        $allData = $primaryFile->getAllData();
        $commonMediaFileFields = Repo::submissionFile()->getCommonMediaFileFields();

        $sharedData = array_intersect_key($allData, array_flip($commonMediaFileFields));

        if (!empty($sharedData)) {
            $secondaryFile = Repo::submissionFile()->get($secondaryFile->getId());
            Repo::submissionFile()->edit($secondaryFile, $sharedData);
        }
    }

    /**
     * Unlink a file from its variant group and completely remove group.
     *
     * @return int[] submissionFileIds of affected files
     */
    public static function unlink(SubmissionFile $submissionFile, int $submissionId): array
    {
        $variantGroupId = $submissionFile->getData('variantGroupId');
        if (!$variantGroupId) {
            return [$submissionFile->getId()];
        }

        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_MEDIA])
            ->filterByVariantGroupIds([$variantGroupId])
            ->getMany();

        $affectedSubmissionFileIds = [];
        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->edit($submissionFile, ['variantGroupId' => null]);
            $affectedSubmissionFileIds[] = $submissionFile->getId();
        }

        static::where('variant_group_id', $variantGroupId)->delete();

        return $affectedSubmissionFileIds;
    }

    /**
     * Clean up a variant group after a related submission file has been deleted.
     *
     * If only one file remains, it is ungrouped.
     * If the group is empty or has a single submission file associated with it, the group record is removed.
     */
    public static function cleanupAfterDelete(int $variantGroupId, int $submissionId): void
    {
        $remainingSiblings = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_MEDIA])
            ->filterByVariantGroupIds([$variantGroupId])
            ->getMany();

        if ($remainingSiblings->count() === 1) {
            Repo::submissionFile()->edit($remainingSiblings->first(), ['variantGroupId' => null]);
        }

        if ($remainingSiblings->count() <= 1) {
            static::where('variant_group_id', $variantGroupId)->delete();
        }
    }

    /**
     * Apply shared metadata edits to sibling files in the same variant group.
     */
    public static function applyMetadataToSiblings(SubmissionFile $file, array $params, int $submissionId): void
    {
        $variantGroupId = $file->getData('variantGroupId');
        if (!$variantGroupId) {
            return;
        }

        $commonMediaFileFields = Repo::submissionFile()->getCommonMediaFileFields();
        $siblingParams = array_intersect_key($params, array_flip($commonMediaFileFields));

        if (empty($siblingParams)) {
            return;
        }

        $siblings = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_MEDIA])
            ->filterByVariantGroupIds([$variantGroupId])
            ->getMany();

        foreach ($siblings as $sibling) {
            if ($sibling->getId() === $file->getId()) {
                continue;
            }
            Repo::submissionFile()->edit($sibling, $siblingParams);
        }
    }
}

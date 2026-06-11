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
use Illuminate\Support\Facades\DB;

class VariantGroup extends Model
{
    use HasCamelCasing;

    protected $table = 'variant_groups';
    protected $primaryKey = 'variant_group_id';
    public $timestamps = false;
    protected $guarded = [];

    /**
     * Link two media submission files into a variant group and propagate metadata from primary to secondary.
     *
     * If either file already belongs to a different variant group, it is unlinked from it first,
     * leaving its former sibling ungrouped.
     *
     * @return int[] submissionFileIds of affected files
     */
    public static function link(SubmissionFile $primaryFile, SubmissionFile $secondaryFile, int $submissionId): array
    {
        $primaryGroupId = $primaryFile->getData('variantGroupId');
        $secondaryGroupId = $secondaryFile->getData('variantGroupId');

        // Already in the same group
        if ($primaryGroupId && $secondaryGroupId && $primaryGroupId === $secondaryGroupId) {
            return [$primaryFile->getId(), $secondaryFile->getId()];
        }

        return DB::transaction(function () use ($primaryFile, $secondaryFile, $submissionId, $primaryGroupId, $secondaryGroupId) {
            $affectedFileIds = [];

            // Dissolve existing groups; former siblings become ungrouped
            if ($primaryGroupId) {
                $affectedFileIds = array_merge($affectedFileIds, static::unlink($primaryFile, $submissionId));
            }
            if ($secondaryGroupId) {
                $affectedFileIds = array_merge($affectedFileIds, static::unlink($secondaryFile, $submissionId));
            }

            $primaryFile = Repo::submissionFile()->get($primaryFile->getId());
            $secondaryFile = Repo::submissionFile()->get($secondaryFile->getId());

            $variantGroup = static::create([]);
            $variantGroupId = $variantGroup->getKey();
            Repo::submissionFile()->edit($primaryFile, ['variantGroupId' => $variantGroupId]);
            Repo::submissionFile()->edit($secondaryFile, ['variantGroupId' => $variantGroupId]);

            // Apply common fields from primary to secondary
            $primaryFile = Repo::submissionFile()->get($primaryFile->getId());
            $allData = $primaryFile->getAllData();
            $commonMediaFileFields = Repo::submissionFile()->getCommonMediaFileFields();

            $sharedData = array_intersect_key($allData, array_flip($commonMediaFileFields));

            if (!empty($sharedData)) {
                $secondaryFile = Repo::submissionFile()->get($secondaryFile->getId());
                Repo::submissionFile()->edit($secondaryFile, $sharedData);
            }

            $affectedFileIds[] = $primaryFile->getId();
            $affectedFileIds[] = $secondaryFile->getId();

            return array_values(array_unique($affectedFileIds));
        });
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

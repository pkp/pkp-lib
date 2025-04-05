<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I10404_UpdateCategoryImageNameFields.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10404_UpdateCategoryImageNameFields
 *
 * @brief Migration to update Category image data properties for compatibility with FieldUploadImage component
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\file\PublicFileManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use PKP\file\ContextFileManager;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10404_UpdateCategoryImageNameFields extends Migration
{
    public const CHUNK_SIZE = 1000;

    public function up(): void
    {
        $contextIds = app()->get('context')->getIds();
        $fileNamesToMove = [];

        foreach ($contextIds as $contextId) {
            DB::table('categories')
                ->where('context_id', '=', $contextId)
                ->orderBy('category_id')
                ->chunk(self::CHUNK_SIZE, function (Collection $categories) use ($contextId, &$fileNamesToMove) {
                    $imageRecordsToUpdate = [];

                    foreach ($categories as $category) {
                        $image = $category->image;
                        if ($image) {
                            $imageDecoded = json_decode($image, true);
                            $fileNamesToMove[] = $imageDecoded['name'];
                            $fileNamesToMove[] = $imageDecoded['thumbnailName'];
                            // Swap 'name' and 'uploadName' fields
                            [$imageDecoded['name'], $imageDecoded['uploadName']] = [$imageDecoded['uploadName'], $imageDecoded['name']];
                            $imageRecordsToUpdate[$category->category_id] = json_encode($imageDecoded);
                        }
                    }

                    $this->updateCategoriesImageNameFields($contextId, collect($imageRecordsToUpdate));
                });

            // Move images
            $this->moveContextCategoryImagesToPublicFolder($contextId, $fileNamesToMove);
        }
    }

    /**
     *
     * @param Enumerable $updates List of Category image data to update. Keyed by category ID.
     */
    private function updateCategoriesImageNameFields(int $contextId, Enumerable $updates): void
    {
        $updates->chunk(self::CHUNK_SIZE)->each(function ($chunk) use ($contextId) {
            $caseStatement = 'UPDATE categories SET image = CASE category_id ';

            foreach ($chunk as $categoryId => $json) {
                $caseStatement .= "WHEN {$categoryId} THEN ? ";
            }

            $caseStatement .= 'END WHERE category_id IN (' . implode(',', $chunk->keys()->all()) . ') AND context_id = ?';
            DB::update($caseStatement, array_merge($chunk->values()->all(), [$contextId]));
        });
    }


    /**
     * Moves the Category images from file system to public dir of given context.
     */
    private function moveContextCategoryImagesToPublicFolder(int $contextId, $fileNames): void
    {
        $contextFileManager = new ContextFileManager($contextId);
        $basePath = $contextFileManager->getBasePath() . 'categories/';
        $publicFileManager = new PublicFileManager();

        foreach ($fileNames as $fileName) {
            $success = $publicFileManager->copyContextFile($contextId, $basePath . $fileName, $fileName);
            if ($success) {
                $contextFileManager->deleteByPath($basePath . $fileName);
            }
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

<?php

namespace PKP\migration\upgrade\v3_6_0;

use APP\file\PublicFileManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\file\ContextFileManager;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10449_UpdateCategoryImageNameFields extends Migration
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
                            [$imageDecoded['name'], $imageDecoded['uploadName']] = [$imageDecoded['uploadName'], $imageDecoded['name']];
                            $imageRecordsToUpdate[$category->category_id] = json_encode($imageDecoded);
                            $fileNamesToMove[] = $imageDecoded['thumbnailName'];
                        }
                    }

                    $this->updateCategoriesImageNameFields($contextId, $imageRecordsToUpdate);
                });


            // Move images
            $this->moveContextCategoryImages($contextId, $fileNamesToMove);
        }


    }

    /**
     *
     * @param array $updates List Category image data to update. Keyed by category ID.
     */
    private function updateCategoriesImageNameFields(int $contextId, array $updates): void
    {
        if (!empty($updates)) {
            $caseStatement = 'UPDATE categories SET image = CASE category_id ';

            foreach ($updates as $categoryId => $json) {
                $caseStatement .= "WHEN {$categoryId} THEN ? ";
            }

            $caseStatement .= 'END WHERE category_id IN (' . implode(',', array_keys($updates)) . ') AND context_id = ?';
            DB::update($caseStatement, array_merge(array_values($updates), [$contextId]));
        }
    }


    /**
     * Moves the Category images from file system to public dir of given context.
     *
     * This method accepts a list f file names and copy these files. We individually copy files instead of the complete directory to avoid the minor possibility that other files may
     * have been manually placed in the categories folder, and we do not want to copy those if they do exist.
     */
    private function moveContextCategoryImages(int $contextId, $fileNames): void
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

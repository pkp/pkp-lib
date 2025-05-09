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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use PKP\config\Config;
use PKP\core\Core;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I10404_UpdateCategoryImageNameFields extends Migration
{
    private const FILE_MODE_MASK = 0666; // FileManager::FILE_MODE_MASK
    private const DIRECTORY_MODE_MASK = 0777; // FileManager::DIRECTORY_MODE_MASK
    public const CHUNK_SIZE = 1000;
    private Filesystem $fileSystem;

    public function __construct()
    {

        $umask = Config::getVar('files', 'umask', 0022);
        $adapter = new LocalFilesystemAdapter(
            '/',
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => self::FILE_MODE_MASK & ~$umask,
                    'private' => self::FILE_MODE_MASK & ~$umask,
                ],
                'dir' => [
                    'public' => self::DIRECTORY_MODE_MASK & ~$umask,
                    'private' => self::DIRECTORY_MODE_MASK & ~$umask,
                ]
            ]),
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS
        );

        $this->fileSystem = new Filesystem($adapter);
    }

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('seq');
        });

        DB::table('categories')
            ->whereNotNull('image')
            ->orderBy('category_id')
            ->chunk(self::CHUNK_SIZE, function (Collection $categories) {
                $ids = $categories->pluck('category_id')->all();
                DB::table('categories')
                    ->whereIn('category_id', $ids)
                    ->update([
                        'image' => DB::raw(
                            'REPLACE(
                                REPLACE(REPLACE(image, \'"name":\', \'"temporaryNameField":\'), \'"uploadName":\', \'"name":\'),
                                \'"temporaryNameField":\', \'"uploadName":\'
                            )'
                        ),
                    ]);
            });

        $this->moveContextCategoryImagesToPublicFolder();
    }

    /**
     * Moves the Category images from file system to public for each context.
     */
    private function moveContextCategoryImagesToPublicFolder(): void
    {
        $contextIds = DB::table($this->getContextTable())
            ->pluck($this->getContextIdColumn());

        foreach ($contextIds as $contextId) {
            // Get the category images in the context specific folder
            $categoryImages = $this->fileSystem->listContents($this->getContextCategoryFolderPath($contextId));
            $publicFilesPath = $this->getPublicFilesPath($contextId);

            foreach ($categoryImages as $categoryImage) {
                $this->fileSystem->move(
                    $categoryImage['path'],
                    $publicFilesPath . basename($categoryImage['path'])
                );
            }
        }
    }


    /**
     * Get the path to a context's public files' directory.
     */
    public function getPublicFilesPath(int $contextId): string
    {
        return Core::getBaseDir() . '/' . Config::getVar('files', 'public_files_dir') . '/' . $this->getContextFolderName() . '/' . $contextId . '/';
    }

    /**
     * Get the path to a context's category folder.
     */
    public function getContextCategoryFolderPath(int $contextId): string
    {
        return Config::getVar('files', 'files_dir') . '/' . $this->getContextFolderName() . '/' . $contextId . '/categories/';
    }

    /**
     *Get the name of the context folder.
     */
    abstract public function getContextFolderName(): string;

    /**
     * Get the name of the context table.
     */
    abstract protected function getContextTable(): string;

    /**
     * Get the name of the context ID column.
     */
    abstract protected function getContextIdColumn(): string;

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}

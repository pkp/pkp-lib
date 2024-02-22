<?php
/**
 * @file classes/announcement/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage announcements.
 */

namespace PKP\announcement;

use APP\core\Application;
use APP\core\Request;
use APP\file\PublicFileManager;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\core\PKPString;
use PKP\file\FileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\user\User;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService<Announcement> $schemaService */
    protected $schemaService;


    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Announcement
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?Announcement
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id): bool
    {
        return $this->dao->exists($id);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an announcement
     *
     * Perform validation checks on data used to add or edit an announcement.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Announcement $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            [
                'dateExpire.date_format' => __('stats.dateRange.invalidDate'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Announcement::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Announcement $announcement): int
    {
        $announcement->setData('datePosted', Core::getCurrentDate());
        $id = $this->dao->insert($announcement);
        $announcement = $this->get($id);

        if ($announcement->getImage()) {
            $this->handleImageUpload($announcement);
        }

        Hook::call('Announcement::add', [$announcement]);

        return $id;
    }

    /**
     * Update an object in the database
     *
     * Deletes the old image if it has been removed, or a new image has
     * been uploaded.
     */
    public function edit(Announcement $announcement, array $params)
    {
        $newAnnouncement = clone $announcement;
        $newAnnouncement->setAllData(array_merge($newAnnouncement->_data, $params));

        Hook::call('Announcement::edit', [$newAnnouncement, $announcement, $params]);

        $this->dao->update($newAnnouncement);

        $image = $newAnnouncement->getImage();
        $hasNewImage = $image && $image['temporaryFileId'];

        if ((!$image || $hasNewImage) && $announcement->getImage()) {
            $this->deleteImage($announcement);
        }

        if ($hasNewImage) {
            $this->handleImageUpload($newAnnouncement);
        }
    }

    /** @copydoc DAO::delete() */
    public function delete(Announcement $announcement)
    {
        Hook::call('Announcement::delete::before', [$announcement]);

        if ($announcement->getImage()) {
            $this->deleteImage($announcement);
        }

        $this->dao->delete($announcement);

        Hook::call('Announcement::delete', [$announcement]);
    }

    /**
     * Delete a collection of announcements
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $announcement) {
            $this->delete($announcement);
        }
    }

    /**
     * The subdirectory where announcement images are stored
     */
    public function getImageSubdirectory(): string
    {
        return 'announcements';
    }

    /**
     * Get the base URL for announcement file uploads
     */
    public function getFileUploadBaseUrl(?Context $context = null): string
    {
        return join('/', [
            Application::get()->getRequest()->getPublicFilesUrl($context),
            $this->getImageSubdirectory(),
        ]);
    }

    /**
     * Handle image uploads
     *
     * @throws StoreTemporaryFileException Unable to store temporary file upload
     */
    protected function handleImageUpload(Announcement $announcement): void
    {
        $image = $announcement->getImage();
        if ($image && $image['temporaryFileId']) {
            $user = Application::get()->getRequest()->getUser();
            $image = $announcement->getImage();
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFile = $temporaryFileManager->getFile((int) $image['temporaryFileId'], $user?->getId());
            $filePath = $this->getImageSubdirectory() . '/' . $this->getImageFilename($announcement, $temporaryFile);
            if (!$this->isValidImage($temporaryFile, $filePath, $user, $announcement)) {
                throw new StoreTemporaryFileException($temporaryFile, $filePath, $user, $announcement);
            }
            if ($this->storeTemporaryFile($temporaryFile, $filePath, $user->getId(), $announcement)) {
                $announcement->setImage(
                    $this->getImageData($announcement, $temporaryFile)
                );
                $this->dao->update($announcement);
            } else {
                $this->delete($announcement);
                throw new StoreTemporaryFileException($temporaryFile, $filePath, $user, $announcement);
            }
        }
    }

    /**
     * Store a temporary file upload in the public files directory
     *
     * @param string $newPath The new filename with the path relative to the public files directoruy
     * @return bool Whether or not the operation was successful
     */
    protected function storeTemporaryFile(TemporaryFile $temporaryFile, string $newPath, int $userId, Announcement $announcement): bool
    {
        $publicFileManager = new PublicFileManager();
        $temporaryFileManager = new TemporaryFileManager();

        if ($announcement->getAssocId()) {
            $result = $publicFileManager->copyContextFile(
                $announcement->getAssocId(),
                $temporaryFile->getFilePath(),
                $newPath
            );
        } else {
            $result = $publicFileManager->copySiteFile(
                $temporaryFile->getFilePath(),
                $newPath
            );
        }

        if (!$result) {
            return false;
        }

        $temporaryFileManager->deleteById($temporaryFile->getId(), $userId);

        return $result;
    }

    /**
     * Get the data array for a temporary file that has just been stored
     *
     * @return array Data about the image, like the upload name, alt text, and date uploaded
     */
    protected function getImageData(Announcement $announcement, TemporaryFile $temporaryFile): array
    {
        $image = $announcement->getImage();

        return [
            'name' => $temporaryFile->getOriginalFileName(),
            'uploadName' => $this->getImageFilename($announcement, $temporaryFile),
            'dateUploaded' => Core::getCurrentDate(),
            'altText' => !empty($image['altText']) ? $image['altText'] : '',
        ];
    }

    /**
     * Get the filename of the image upload
     */
    protected function getImageFilename(Announcement $announcement, TemporaryFile $temporaryFile): string
    {
        $fileManager = new FileManager();

        return $announcement->getId()
            . $fileManager->getImageExtension($temporaryFile->getFileType());
    }

    /**
     * Delete the image related to announcement
     */
    protected function deleteImage(Announcement $announcement): void
    {
        $image = $announcement->getImage();
        if ($image && $image['uploadName']) {
            $publicFileManager = new PublicFileManager();
            $filesPath = $announcement->getAssocId()
                ? $publicFileManager->getContextFilesPath($announcement->getAssocId())
                : $publicFileManager->getSiteFilesPath();

            $publicFileManager->deleteByPath(
                join('/', [
                    $filesPath,
                    $this->getImageSubdirectory(),
                    $image['uploadName'],
                ])
            );
        }
    }

    /**
     * Check that temporary file is an image
     */
    protected function isValidImage(TemporaryFile $temporaryFile): bool
    {
        if (getimagesize($temporaryFile->getFilePath()) === false) {
            return false;
        }
        $extension = pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION);
        $fileManager = new FileManager();
        $extensionFromMimeType = $fileManager->getImageExtension(
            PKPString::mime_content_type($temporaryFile->getFilePath())
        );
        if ($extensionFromMimeType !== '.' . $extension) {
            return false;
        }

        return true;
    }
}

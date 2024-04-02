<?php
/**
 * @file classes/highlight/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage highlights.
 */

namespace PKP\highlight;

use APP\core\Application;
use APP\core\Request;
use APP\file\PublicFileManager;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\exceptions\StoreTemporaryFileException;
use PKP\file\FileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService<Highlight> $schemaService */
    protected $schemaService;


    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Highlight
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId): ?Highlight
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * highlights to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a higlhight
     *
     * Perform validation checks on data used to add or edit a highlight.
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Highlight::validate [&$errors, $object, $props, $context]
     */
    public function validate(?Highlight $object, array $props, ?Context $context): array
    {
        $site = Application::get()->getRequest()->getSite();
        $allowedLocales = $context ? $context->getSupportedFormLocales() : $site->getSupportedLocales();
        $primaryLocale = $context ? $context->getPrimaryLocale() : $site->getPrimaryLocale();

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


        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['image'],
            [],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::run('Highlight::validate', [&$errors, $object, $props, $context]);

        return $errors;
    }

    /**
     * Add a highlight
     *
     * @hook Highlight::add [$highlight]
     */
    public function add(Highlight $highlight): int
    {
        if (!$highlight->getSequence()) {
            $highlight->setSequence(
                $this->getNextSequence($highlight->getContextId())
            );
        }

        $id = $this->dao->insert($highlight);

        $highlight = $this->get($id, $highlight->getContextId());

        if ($highlight->getImage()) {
            $this->handleImageUpload($highlight);
        }

        Hook::run('Highlight::add', [$highlight]);

        return $id;
    }

    /**
     * Edit a highlight
     *
     * @hook Highlight::edit [$newHighlight, $highlight, $params]
     */
    public function edit(Highlight $highlight, array $params): void
    {
        $newHighlight = clone $highlight;
        $newHighlight->setAllData(array_merge($newHighlight->_data, $params));

        Hook::run('Highlight::edit', [$newHighlight, $highlight, $params]);

        $this->dao->update($newHighlight);

        $image = $newHighlight->getImage();
        $hasNewImage = $image['temporaryFileId'] ?? null;

        if ((!$image || $hasNewImage) && $highlight->getImage()) {
            $this->deleteImage($highlight);
        }

        if ($hasNewImage) {
            $this->handleImageUpload($newHighlight);
        }
    }

    /** @copydoc DAO::delete() */
    public function delete(Highlight $highlight): void
    {
        Hook::run('Highlight::delete::before', [$highlight]);

        if ($highlight->getImage()) {
            $this->deleteImage($highlight);
        }

        $this->dao->delete($highlight);

        Hook::run('Highlight::delete', [$highlight]);
    }

    /**
     * Delete a collection of highlights
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $highlight) {
            $this->delete($highlight);
        }
    }

    /**
     * Get the next sequence for a highlight in the context
     *
     * This gets the correct sequence value to put a highlight last
     */
    public function getNextSequence(?int $contextId = null): int
    {
        $lastSequence = $this->dao->getLastSequence($contextId);
        return is_null($lastSequence)
            ? 1
            : $lastSequence + 1;
    }

    /**
     * The subdirectory where highlight images are stored
     */
    public function getImageSubdirectory(): string
    {
        return 'highlights';
    }

    /**
     * Get the base URL for highlight file uploads
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
     * @throws StoreTemporaryFileException
     */
    protected function handleImageUpload(Highlight $highlight): void
    {
        $image = $highlight->getImage();
        if ($image['temporaryFileId'] ?? null) {
            $user = Application::get()->getRequest()->getUser();
            $image = $highlight->getImage();
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFile = $temporaryFileManager->getFile((int) $image['temporaryFileId'], $user?->getId());
            $filepath = $this->getImageSubdirectory() . '/' . $this->getImageFilename($highlight, $temporaryFile);
            if ($this->storeTemporaryFile($temporaryFile, $filepath, $user?->getId(), $highlight)) {
                $highlight->setImage(
                    $this->getImageData($highlight, $temporaryFile)
                );
                $this->dao->update($highlight);
            } else {
                $this->delete($highlight);
                throw new StoreTemporaryFileException($temporaryFile, $filepath, $user, $highlight);
            }
        }
    }

    /**
     * Store a temporary file upload in the public files directory
     *
     * @return bool Whether or not the operation was successful
     */
    protected function storeTemporaryFile(TemporaryFile $temporaryFile, string $filepath, ?int $userId, Highlight $highlight): bool
    {
        $publicFileManager = new PublicFileManager();
        $temporaryFileManager = new TemporaryFileManager();

        if ($highlight->getContextId()) {
            $result = $publicFileManager->copyContextFile(
                $highlight->getContextId(),
                $temporaryFile->getFilePath(),
                $filepath
            );
        } else {
            $result = $publicFileManager->copySiteFile(
                $temporaryFile->getFilePath(),
                $filepath
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
    protected function getImageData(Highlight $highlight, TemporaryFile $temporaryFile): array
    {
        $image = $highlight->getImage();

        return [
            'name' => $temporaryFile->getOriginalFileName(),
            'uploadName' => $this->getImageFilename($highlight, $temporaryFile),
            'dateUploaded' => Core::getCurrentDate(),
            'altText' => !empty($image['altText']) ? $image['altText'] : '',
        ];
    }

    /**
     * Get the filename of the image upload
     */
    protected function getImageFilename(Highlight $highlight, TemporaryFile $temporaryFile): string
    {
        $fileManager = new FileManager();

        return $highlight->getId()
            . $fileManager->getImageExtension($temporaryFile->getFileType());
    }

    /**
     * Delete the image related to highlight
     */
    protected function deleteImage(Highlight $highlight): void
    {
        $image = $highlight->getImage();
        if ($image['uploadName'] ?? null) {
            $publicFileManager = new PublicFileManager();
            $filesPath = $highlight->getContextId()
                ? $publicFileManager->getContextFilesPath($highlight->getContextId())
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
}

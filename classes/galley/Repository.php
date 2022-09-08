<?php
/**
 * @file classes/galley/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class galley
 *
 * @brief A repository to find and manage galleys.
 */

namespace PKP\galley;

use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\App;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    protected PKPSchemaService $schemaService;


    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Galley
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, int $publicationId = null): ?Galley
    {
        return $this->dao->get($id, $publicationId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $publicationId = null): bool
    {
        return $this->dao->exists($id, $publicationId);
    }

    /**
     * Get a publication galley by a url path
     */
    public function getByUrlPath(string $urlPath, Publication $publication): ?Galley
    {
        return $this->dao->getByUrlPath($urlPath, $publication);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * galleys to their schema
     */
    public function getSchemaMap(Submission $submission, Publication $publication): maps\Schema
    {
        return app('maps')->withExtensions(
            $this->schemaMap,
            [
                'submission' => $submission,
                'publication' => $publication
            ]
        );
    }

    /**
     * Validate properties for a galley
     *
     * Perform validation checks on data used to add or edit an galley.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Galley $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            [
                'locale.regex' => __('validator.localeKey'),
                'urlPath.regex' => __('validator.alpha_dash_period'),
            ]
        );

        // Check required fields if we're adding a context
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

        // The publicationId must match an existing publication that is not yet published
        $validator->after(function ($validator) use ($props) {
            if (isset($props['publicationId']) && !$validator->errors()->get('publicationId')) {
                $publication = Repo::publication()->get($props['publicationId']);
                if (!$publication) {
                    $validator->errors()->add('publicationId', __('galley.publicationNotFound'));
                } elseif (in_array($publication->getData('status'), [Submission::STATUS_PUBLISHED, Submission::STATUS_SCHEDULED])) {
                    $validator->errors()->add('publicationId', __('galley.editPublishedDisabled'));
                }
            }
        });


        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get($this->dao->schema), $allowedLocales);
        }

        Hook::call('Galley::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Galley $galley): int
    {
        $id = $this->dao->insert($galley);
        Hook::call('Galley::add', [$galley]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Galley $galley, array $params): void
    {
        $newGalley = clone $galley;
        $newGalley->setAllData(array_merge($newGalley->_data, $params));

        Hook::call('Galley::edit', [$newGalley, $galley, $params]);

        $this->dao->update($newGalley);
    }

    /** @copydoc DAO::delete() */
    public function delete(Galley $galley): void
    {
        Hook::call('Galley::delete::before', [$galley]);
        $this->dao->delete($galley);

        // Delete related submission files
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(ASSOC_TYPE_GALLEY)
            ->filterByFileIds([$galley->getId()])
            ->getMany();

        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->delete($submissionFile);
        }

        Hook::call('Galley::delete', [$galley]);
    }
}

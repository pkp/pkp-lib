<?php
/**
 * @file classes/author/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class author
 *
 * @brief A repository to find and manage authors.
 */

namespace PKP\author;

use APP\author\Author;
use APP\author\DAO;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PKP\plugins\HookRegistry;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request */
    protected $request;

    /** @var PKPSchemaService */
    protected $schemaService;

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Author
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?Author
    {
        return $this->dao->get($id);
    }

    /** @copydoc DAO::getCount() */
    public function getCount(Collector $query): int
    {
        return $this->dao->getCount($query);
    }

    /** @copydoc DAO::getIds() */
    public function getIds(Collector $query): Collection
    {
        return $this->dao->getIds($query);
    }

    /** @copydoc DAO::getMany() */
    public function getMany(Collector $query): LazyCollection
    {
        return $this->dao->getMany($query);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * authors to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for an author
     *
     * Perform validation checks on data used to add or edit an author.
     *
     * @param Author|null $author The author being edited. Pass `null` if creating a new author
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported submission locales
     * @param string $primaryLocale The submission's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate($author, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_AUTHOR, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $author,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_AUTHOR),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_AUTHOR),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_AUTHOR), $allowedLocales);

        // The publicationId must match an existing publication that is not yet published
        $validator->after(function ($validator) use ($props) {
            if (isset($props['publicationId']) && !$validator->errors()->get('publicationId')) {
                $publication = Repo::publication()->get($props['publicationId']);
                if (!$publication) {
                    $validator->errors()->add('publicationId', __('author.publicationNotFound'));
                } elseif ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
                    $validator->errors()->add('publicationId', __('author.editPublishedDisabled'));
                }
            }
        });

        $errors = [];
        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }

        HookRegistry::call('Author::validate', [$errors, $author, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add(Author $author): int
    {
        $existingSeq = $author->getData('seq');

        if (!isset($existingSeq)) {
            $nextSeq = $this->dao->getNextSeq($author->getData('publicationId'));
            $author->setData('seq', $nextSeq);
        }

        $authorId = $this->dao->insert($author);
        $author = Repo::author()->get($authorId);

        HookRegistry::call('Author::add', [$author]);

        return $author->getId();
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit(Author $author, array $params)
    {
        $newAuthor = Repo::author()->newDataObject(array_merge($author->_data, $params));

        HookRegistry::call('Author::edit', [$newAuthor, $author, $params]);

        $this->dao->update($newAuthor);

        Repo::author()->get($newAuthor->getId());
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete(Author $author)
    {
        HookRegistry::call('Author::delete::before', [$author]);
        $this->dao->delete($author);

        $this->dao->resetContributorsOrder($author->getData('publicationId'));

        HookRegistry::call('Author::delete', [$author]);
    }

    /**
     * Update author names when publication locale changes.
     *
     * @param int $publicationId
     * @param string $oldLocale
     * @param string $newLocale
     */
    public function changePublicationLocale($publicationId, $oldLocale, $newLocale)
    {
        $authors = $this->getMany(
            $this
                ->getCollector()
                ->filterByPublicationIds([$publicationId])
        );

        foreach ($authors as $author) {
            if (empty($author->getGivenName($newLocale))) {
                if (empty($author->getFamilyName($newLocale)) && empty($author->getPreferredPublicName($newLocale))) {
                    // if no name exists for the new locale
                    // copy all names with the old locale to the new locale
                    $author->setGivenName($author->getGivenName($oldLocale), $newLocale);
                    $author->setFamilyName($author->getFamilyName($oldLocale), $newLocale);
                    $author->setPreferredPublicName($author->getPreferredPublicName($oldLocale), $newLocale);
                } else {
                    // if the given name does not exist, but one of the other names do exist
                    // copy only the given name with the old locale to the new locale, because the given name is required
                    $author->setGivenName($author->getGivenName($oldLocale), $newLocale);
                }

                $this->dao->update($author);
            }
        }
    }

    /**
    * Returns the authors of a given submission by using the submission's current publication
    */
    public function getSubmissionAuthors(Submission $submission, bool $onlyIncludeInBrowse = false): LazyCollection
    {
        $publication = $submission->getCurrentPublication();

        return Repo::author()->getMany(
            Repo::author()
                ->getCollector()
                ->filterByIncludeInBrowse($onlyIncludeInBrowse)
                ->filterByPublicationIds([$publication->getId()])
                ->orderBy(Repo::author()->getCollector()::ORDERBY_ID)
        );
    }

    /**
    * Reorders the authors of a publication according to the given order of the authors in the provided author array
    */
    public function setAuthorsOrder(int $publicationId, array $authors)
    {
        $seq = 0;
        foreach ($authors as $author) {
            $author->setData('seq', $seq);

            $this->dao->update($author);

            $seq++;
        }
    }
}

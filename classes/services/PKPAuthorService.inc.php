<?php

/**
 * @file classes/services/PKPAuthorService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorService
 * @ingroup services
 *
 * @brief Helper class that encapsulates author business logic
 */

namespace PKP\services;

use APP\core\Services;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\plugins\HookRegistry;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\queryBuilders\PKPAuthorQueryBuilder;
use PKP\submission\PKPSubmission;

use PKP\validation\ValidatorFactory;

class PKPAuthorService implements EntityReadInterface, EntityWriteInterface, EntityPropertyInterface
{
    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($authorId)
    {
        $authorDao = DAORegistry::getDAO('AuthorDAO'); /** @var AuthorDAO $authorDao */
        return $authorDao->getById($authorId);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getCount()
     */
    public function getCount($args = [])
    {
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getIds()
     */
    public function getIds($args = [])
    {
        return $this->getQueryBuilder($args)->getIds();
    }

    /**
     * Get a collection of Author objects limited, filtered
     * and sorted by $args
     *
     * @param array $args {
     * 		@option int|array contextIds
     * 		@option string familyName
     * 		@option string givenName
     * 		@option int|array publicationIds
     * }
     *
     * @return Iterator
     */
    public function getMany($args = [])
    {
        $authorQO = $this->getQueryBuilder($args)->getQuery();
        $authorDao = DAORegistry::getDAO('AuthorDAO'); /** @var AuthorDAO $authorDao */
        $result = $authorDao->retrieveRange($authorQO->toSql(), $authorQO->getBindings());
        $queryResults = new DAOResultFactory($result, $authorDao, '_fromRow');

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     */
    public function getMax($args = [])
    {
        // Count/offset is not supported so getMax is always
        // the same as getCount
        return $this->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     *
     * @return PKPAuthorQueryBuilder
     */
    public function getQueryBuilder($args = [])
    {
        $defaultArgs = [
            'contextIds' => [],
            'familyName' => '',
            'givenName' => '',
            'publicationIds' => null,
        ];

        $args = array_merge($defaultArgs, $args);

        $authorQB = new PKPAuthorQueryBuilder();
        $authorQB->filterByName($args['givenName'], $args['familyName']);
        if (!empty($args['publicationIds'])) {
            $authorQB->filterByPublicationIds($args['publicationIds']);
        }
        if (!empty($args['contextIds'])) {
            $authorQB->filterByContextIds($args['contextIds']);
        }
        if (!empty($args['country'])) {
            $authorQB->filterByCountry($args['country']);
        }
        if (!empty($args['affiliation'])) {
            $authorQB->filterByAffiliation($args['affiliation']);
        }

        HookRegistry::call('Author::getMany::queryBuilder', [&$authorQB, $args]);

        return $authorQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($author, $props, $args = null)
    {
        $request = $args['request'];

        $values = [];

        foreach ($props as $prop) {
            switch ($prop) {
                default:
                    $values[$prop] = $author->getData($prop);
                    break;
            }
        }

        $locales = $request->getContext()->getSupportedFormLocales();
        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_AUTHOR, $values, $locales);

        HookRegistry::call('Author::getProperties::values', [&$values, $author, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($author, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_AUTHOR);

        return $this->getProperties($author, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($author, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_AUTHOR);

        return $this->getProperties($author, $props, $args);
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_AUTHOR, $allowedLocales)
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
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
                $publication = Services::get('publication')->get($props['publicationId']);
                if (!$publication) {
                    $validator->errors()->add('publicationId', __('author.publicationNotFound'));
                } elseif ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
                    $validator->errors()->add('publicationId', __('author.editPublishedDisabled'));
                }
            }
        });

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_AUTHOR), $allowedLocales);
        }

        HookRegistry::call('Author::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($author, $request)
    {
        $authorDao = DAORegistry::getDAO('AuthorDAO'); /** @var AuthorDAO $authorDao */
        $authorId = $authorDao->insertObject($author);
        $author = $this->get($authorId);

        HookRegistry::call('Author::add', [&$author, $request]);

        return $author;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($author, $params, $request)
    {
        $authorDao = DAORegistry::getDAO('AuthorDAO'); /** @var AuthorDAO $authorDao */

        $newAuthor = $authorDao->newDataObject();
        $newAuthor->_data = array_merge($author->_data, $params);

        HookRegistry::call('Author::edit', [&$newAuthor, $author, $params, $request]);

        $authorDao->updateObject($newAuthor);
        $newAuthor = $this->get($newAuthor->getId());

        return $newAuthor;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($author)
    {
        HookRegistry::call('Author::delete::before', [&$author]);
        $authorDao = DAORegistry::getDAO('AuthorDAO'); /** @var AuthorDAO $authorDao */
        $authorDao->deleteObject($author);
        HookRegistry::call('Author::delete', [&$author]);
    }
}

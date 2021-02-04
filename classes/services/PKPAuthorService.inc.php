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

namespace PKP\Services;

use \DBResultRange;
use \DAOResultFactory;
use \DAORegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use \PKP\Services\QueryBuilders\PKPAuthorQueryBuilder;

class PKPAuthorService implements EntityReadInterface, EntityWriteInterface, EntityPropertyInterface {

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($authorId) {
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		return $authorDao->getById($authorId);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getCount()
	 */
	public function getCount($args = []) {
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getIds()
	 */
	public function getIds($args = []) {
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
	 * @return Iterator
	 */
	public function getMany($args = array()) {
		$authorQO = $this->getQueryBuilder($args)->getQuery();
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$result = $authorDao->retrieveRange($authorQO->toSql(), $authorQO->getBindings());
		$queryResults = new DAOResultFactory($result, $authorDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = []) {
		// Count/offset is not supported so getMax is always
		// the same as getCount
		return $this->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getQueryBuilder()
	 * @return PKPAuthorQueryBuilder
	 */
	public function getQueryBuilder($args = []) {

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

		\HookRegistry::call('Author::getMany::queryBuilder', array(&$authorQB, $args));

		return $authorQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($author, $props, $args = null) {
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
		$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_AUTHOR, $values, $locales);

		\HookRegistry::call('Author::getProperties::values', array(&$values, $author, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($author, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_AUTHOR);

		return $this->getProperties($author, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($author, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_AUTHOR);

		return $this->getProperties($author, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_AUTHOR, $allowedLocales)
		);

		// Check required fields
		\ValidatorFactory::required(
			$validator,
			$action,
			$schemaService->getRequiredProps(SCHEMA_AUTHOR),
			$schemaService->getMultilingualProps(SCHEMA_AUTHOR),
			$allowedLocales,
			$primaryLocale
		);

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_AUTHOR), $allowedLocales);

		// The publicationId must match an existing publication that is not yet published
		$validator->after(function($validator) use ($props) {
			if (isset($props['publicationId']) && !$validator->errors()->get('publicationId')) {
				$publication = Services::get('publication')->get($props['publicationId']);
				if (!$publication) {
					$validator->errors()->add('publicationId', __('author.publicationNotFound'));
				} else if ($publication->getData('status') === STATUS_PUBLISHED) {
					$validator->errors()->add('publicationId', __('author.editPublishedDisabled'));
				}
			}
		});

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_AUTHOR), $allowedLocales);
		}

		\HookRegistry::call('Author::validate', array(&$errors, $action, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($author, $request) {
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$authorId = $authorDao->insertObject($author);
		$author = $this->get($authorId);

		\HookRegistry::call('Author::add', array(&$author, $request));

		return $author;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($author, $params, $request) {
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */

		$newAuthor = $authorDao->newDataObject();
		$newAuthor->_data = array_merge($author->_data, $params);

		\HookRegistry::call('Author::edit', array(&$newAuthor, $author, $params, $request));

		$authorDao->updateObject($newAuthor);
		$newAuthor = $this->get($newAuthor->getId());

		return $newAuthor;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($author) {
		\HookRegistry::call('Author::delete::before', [&$author]);
		$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
		$authorDao->deleteObject($author);
		\HookRegistry::call('Author::delete', [&$author]);
	}
}

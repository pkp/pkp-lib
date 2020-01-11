<?php

/**
 * @file classes/services/PKPAuthorService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
use \PKP\Services\traits\EntityReadTrait;

class PKPAuthorService implements EntityReadInterface, EntityWriteInterface, EntityPropertyInterface {
	use EntityReadTrait;

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($authorId) {
		return DAORegistry::getDAO('AuthorDAO')->getById($authorId);
	}

	/**
	 * Get authors
	 *
	 * @param array $args {
	 * 		@option int|array contextIds
	 * 		@option string familyName
	 * 		@option string givenName
	 * 		@option int|array publicationIds
	 * 		@option int count
	 * 		@option int offset
	 * }
	 * @return Iterator
	 */
	public function getMany($args = array()) {
		$authorQB = $this->_getQueryBuilder($args);
		$authorQO = $authorQB->get();
		$range = $this->getRangeByArgs($args);
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$result = $authorDao->retrieveRange($authorQO->toSql(), $authorQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $authorDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = array()) {
		$authorQB = $this->_getQueryBuilder($args);
		$countQO = $authorQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$countResult = $authorDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $authorDao, '_fromRow');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the query object for getting authors
	 *
	 * @see self::getMany()
	 * @return object Query object
	 */
	private function _getQueryBuilder($args = []) {

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

		\HookRegistry::call('Author::getMany::queryBuilder', array($authorQB, $args));

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

		$locales = $request->getContext()->getSupportedLocales();
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

		// Check required fields if we're adding the object
		if ($action === VALIDATE_ACTION_ADD) {
			\ValidatorFactory::required(
				$validator,
				$schemaService->getRequiredProps(SCHEMA_AUTHOR),
				$schemaService->getMultilingualProps(SCHEMA_AUTHOR),
				$primaryLocale
			);
		}

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

		// Don't allow an empty value for the primary locale of the givenName field
		\ValidatorFactory::requirePrimaryLocale(
			$validator,
			['givenName'],
			$props,
			$allowedLocales,
			$primaryLocale
		);

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
		$authorId = DAORegistry::getDAO('AuthorDAO')->insertObject($author);
		$author = $this->get($authorId);

		\HookRegistry::call('Author::add', array($author, $request));

		return $author;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($author, $params, $request) {
		$authorDao = DAORegistry::getDAO('AuthorDAO');

		$newAuthor = $authorDao->newDataObject();
		$newAuthor->_data = array_merge($author->_data, $params);

		\HookRegistry::call('Author::edit', array($newAuthor, $author, $params, $request));

		$authorDao->updateObject($newAuthor);
		$newAuthor = $this->get($newAuthor->getId());

		return $newAuthor;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($author) {
		\HookRegistry::call('Author::delete::before', [$author]);
		DAORegistry::getDAO('AuthorDAO')->deleteObject($author);
		\HookRegistry::call('Author::delete', [$author]);
	}
}

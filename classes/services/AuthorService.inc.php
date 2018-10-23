<?php

/**
 * @file classes/services/PKPAuthorService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorService
 * @ingroup services
 *
 * @brief Helper class that encapsulates author business logic
 */

namespace PKP\Services;

use \ServicesContainer;
use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

class AuthorService extends PKPBaseEntityPropertyService {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct($this);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($author, $props, $args = null) {
		$values = array();
		foreach ($props as $prop) {
			switch ($prop) {
				case 'id':
					$values[$prop] = (int) $author->getId();
					break;
				case 'seq':
				case 'sequence':
					$values[$prop] = (int) $author->getSequence();
					break;
				case 'givenName':
					$values[$prop] = $author->getGivenName(null);
					break;
				case 'familyName':
					$values[$prop] = $author->getFamilyName(null);
					break;
				case 'fullName':
					$values[$prop] = $author->getFullName();
					break;
				case 'country':
					$values[$prop] = $author->getCountry();
					break;
				case 'email':
					$values[$prop] = $author->getEmail();
					break;
				case 'url':
					$values[$prop] = $author->getUrl();
					break;
				case 'userGroupId':
					$values[$prop] = $author->getUserGroupId();
					break;
				case 'includeInBrowse':
					$values[$prop] = (bool) $author->getIncludeInBrowse();
					break;
				case 'primaryContact':
					$values[$prop] = (bool) $author->getPrimaryContact();
					break;
				case 'biography':
					$values[$prop] = $author->getBiography(null);
					break;
				case 'affiliation':
					$values[$prop] = $author->getAffiliation(null);
					break;
				case 'orcid':
					$values[$prop] = $author->getOrcid(null);
					break;
			}

			$locales = $args['request']->getContext()->getSupportedLocales();
			$values = ServicesContainer::instance()->get('schema')->addMissingMultilingualValues(SCHEMA_AUTHOR, $values, $locales);

			\HookRegistry::call('Author::getProperties::values', array(&$values, $author, $props, $args));

			ksort($values);
		}

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($author, $args = null) {
		$props = array (
			'id','seq','fullName','orcid',
		);

		\HookRegistry::call('Author::getProperties::summaryProperties', array(&$props, $author, $args));

		return $this->getProperties($author, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($author, $args = null) {
		$props = array (
			'id','seq','givenName','familyName','fullName','country','email','url','userGroupId',
			'includeInBrowse','primaryContact','affiliation','biography','orcid',
		);

		\HookRegistry::call('Author::getProperties::fullProperties', array(&$props, $author, $args));

		return $this->getProperties($author, $props, $args);
	}
}

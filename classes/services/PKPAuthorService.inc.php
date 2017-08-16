<?php

/**
 * @file classes/services/PKPAuthorService.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorService
 * @ingroup services
 *
 * @brief Helper class that encapsulates author business logic
 */

namespace PKP\Services;

use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

class PKPAuthorService extends PKPBaseEntityPropertyService {

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
				case 'name':
					$values[$prop] = $author->getFullName();
					break;
				case 'firstName':
					$values[$prop] = $author->getFirstName();
					break;
				case 'middleName':
					$values[$prop] = $author->getMiddleName();
					break;
				case 'lastName':
					$values[$prop] = $author->getLastName();
					break;
				case 'suffix':
					$values[$prop] = $author->getSuffix();
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
				case 'isBrowseable':
					$values[$prop] = (bool) $author->getIncludeInBrowse();
					break;
				case 'isPrimaryContact':
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
				case '_href':
					$values[$prop] = null;
					$slimRequest = $args['slimRequest'];
					if ($slimRequest) {
						$route = $slimRequest->getAttribute('route');
						$arguments = $route->getArguments();
						$href = "/{$arguments['contextPath']}/api/{$arguments['version']}/users/".$author->getId();
						$values[$prop] = $href;
					}
					break;
				default:
					$this->getUnknownProperty($author, $prop, $values);
			}
		}
		
		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($author, $args = null) {
		$props = array (
			'id','_href','seq','name','firstName','middleName','lastName','suffix','orcid'
		);
		$props = $this->getSummaryPropertyList($author, $props);
		return $this->getProperties($author, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($author, $args = null) {
		$props = array (
			'id','seq','name','firstName','middleName','lastName','suffix','country','email','url','userGroupId',
			'isBrowseable','isPrimaryContact','affiliation','biography','orcid'
		);
		$props = $this->getFullPropertyList($author, $props);
		return $this->getProperties($author, $props, $args);
	}
}
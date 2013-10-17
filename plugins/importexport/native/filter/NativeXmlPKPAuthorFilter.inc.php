<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPKPAuthorFilter.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPKPAuthorFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of authors
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlPKPAuthorFilter extends NativeImportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function NativeXmlPKPAuthorFilter($filterGroup) {
		$this->setDisplayName('Native XML author import');
		parent::NativeImportFilter($filterGroup);
	}

	//
	// Implement template methods from NativeImportFilter
	//
	/**
	 * Return the plural element name
	 * @return string
	 */
	function getPluralElementName() {
		return 'authors';
	}

	/**
	 * Get the singular element name
	 * @return string
	 */
	function getSingularElementName() {
		return 'author';
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'lib.pkp.plugins.importexport.native.filter.NativeXmlPKPAuthorFilter';
	}


	/**
	 * Handle a submission element
	 * @param $node DOMElement
	 * @return array Array of PKPAuthor objects
	 */
	function handleElement($node) {
		$deployment = $this->getDeployment();
		$submission = $deployment->getSubmission();
		assert(is_a($submission, 'Submission'));

		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$author = $authorDao->newDataObject();
		$author->setSubmissionId($submission->getId());
		if ($node->getAttribute('primary_contact')) $author->setPrimaryContact();

		for ($n = $node->firstChild; $n !== null; $n=$n->nextSibling) if (is_a($n, 'DOMElement')) switch($n->tagName) {
			case 'firstname': $author->setFirstName($n->textContent); break;
			case 'middlename': $author->setMiddleName($n->textContent); break;
			case 'lastname': $author->setLastName($n->textContent); break;
			case 'affiliation': $author->setAffiliation($n->textContent, $n->getAttribute('locale')); break;
			case 'country': $author->setCountry($n->textContent); break;
			case 'email': $author->setEmail($n->textContent); break;
			case 'url': $author->setUrl($n->textContent); break;
			case 'biography': $author->setBiography($n->textContent, $n->getAttribute('locale')); break;
		}

		$authorDao->insertObject($author);
		return $author;
	}
}

?>

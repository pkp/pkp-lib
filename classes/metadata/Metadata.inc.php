<?php

/**
 * @file classes/metadata/Metadata.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Metadata
 * @ingroup metadata
 * @see MetadataElement
 * @see Citation
 *
 * @brief Class representing meta-data. This class and its children represent
 *        delivery (in our case NLM-citation) and discovery (in our case dcterms)
 *        meta-data.
 *        
 *        This class can represent meta-data for journals, journal issues, articles,
 *        conferences, conference proceedings (conference papers), monographs (books),
 *        monograph components (book chapters) or citations.
 *        
 *        Meta-data can be retrieved from all application objects that implement
 *        the MetadataProvider interface.
 *        
 *        The meta-data content can be converted from or to specific citation or
 *        record format implementations (e.g. COinS, OpenURL KEV, DC, etc.) though
 *        not always without information losses if these schemas are not completely
 *        compatible with the our internal meta-data format. 
 *        
 *        NB: Setting the genre defines the allowed meta-data elements. Re-setting
 *        the genre can lead to data-loss if the new genre consists of different
 *        meta-data elements.
 *        
 *        NB: Internal element naming is not 100% consistent with NLM-citation's and
 *        dcterms' nomenclature. It represents a compromise between PKP application's
 *        nomenclature requirements and the original element names. We have also only
 *        implemented such meta-data elements from NLM-citation and dcterms that are
 *        really used. We'll add further meta-data elements as required.
 *        
 *        TODO: Let all meta-data providers implement a common MetadataProvider
 *        interface once we drop PHP4 compatibility.
 *        
 *        TODO: Let PKPAuthor inherit from a "Person" class that we can use generically
 *        for authors and editors.
 *        
 *        TODO: Let Editor return an array of Persons rather than a string.
 *        
 *        TODO: Develop an object representation for NLM's "collab", "anonymous" and "etal".
 */

// $Id$


// The genre is "unknown" as long as it has not been
// decided to which genre the meta-data belong (e.g. before
// parsing or lookup).
define('METADATA_GENRE_UNKNOWN', 0x01);
define('METADATA_GENRE_BOOK', 0x02);
define('METADATA_GENRE_BOOKCHAPTER', 0x03);
define('METADATA_GENRE_JOURNAL', 0x04);
define('METADATA_GENRE_JOURNALARTICLE', 0x05);
define('METADATA_GENRE_CONFERENCE', 0x06);
define('METADATA_GENRE_CONFERENCEPROCEEDING', 0x07);
define('METADATA_GENRE_DISSERTATION', 0x08);

import('metadata.MetadataElement');

class Metadata extends DataObject {
	/** @var array authors */
	var $_authors = array();
	
	/** @var array comments */
	var $_comments = array();
	
	/** @var array supported meta-data elements */
	var $_elements = array();

	/**
	 * Return supported meta-data genres
	 * NB: PHP4 work-around for a public static class member
	 * @return array supported meta-data genres 
	 */
	function getSupportedGenres() {
		static $_supportedGenres = array(
			METADATA_GENRE_BOOK,
			METADATA_GENRE_BOOKCHAPTER,
			METADATA_GENRE_JOURNAL,
			METADATA_GENRE_JOURNALARTICLE,
			METADATA_GENRE_CONFERENCE,
			METADATA_GENRE_CONFERENCEPROCEEDING,
			METADATA_GENRE_DISSERTATION,
			METADATA_GENRE_UNKNOWN
		);
		return $_supportedGenres;
	}
	
	/**
	 * Constructor.
	 * @param $genre integer one of the supported meta-data genres
	 */
	function Metadata($genre = METADATA_GENRE_UNKNOWN) {
		parent::DataObject();
		$this->setGenre($genre);
	}

	//
	// Get/set methods
	//

	/**
	 * get the genre
	 * @return integer one of the supported meta-data genres
	 */
	function getGenre() {
		return $this->_getElement('genre');
	}
	
	/**
	 * Set the genre of the meta-data (e.g. journal, book)
	 * NB: Resetting the genre results in deleting the elements that have been
	 * present in the previous genre but are not in the new genre.
	 * @param $genre integer one of the supported meta-data genres
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setGenre($genre) {
		// Make sure that the genre is one of the supported genre types
		assert(in_array($genre, Metadata::getSupportedGenres()));
		
		// Get the previously set elements for comparison with
		// the new elements.
		$previousElements = $this->_elements;
		
		// Reset the element list
		$this->_elements = array();

		// Add meta-data elements common to all meta-data genres
		$this->_addElement(new MetadataElement('genre', METADATA_ELEMENT_TYPE_INTEGER));
		$this->_addElement(new MetadataElement('articleTitle', METADATA_ELEMENT_TYPE_STRING, true));
		$this->_addElement(new MetadataElement('author', METADATA_ELEMENT_TYPE_OBJECT, false, METADATA_ELEMENT_CARDINALITY_MANY, 'PKPAuthor'));
		$this->_addElement(new MetadataElement('firstPage'), METADATA_ELEMENT_TYPE_INTEGER);
		$this->_addElement(new MetadataElement('lastPage'), METADATA_ELEMENT_TYPE_INTEGER);
		$this->_addElement(new MetadataElement('issuedDate', METADATA_ELEMENT_TYPE_DATE));
		$this->_addElement(new MetadataElement('accessDate'), METADATA_ELEMENT_TYPE_DATE);
		$this->_addElement(new MetadataElement('doi'));
		$this->_addElement(new MetadataElement('url'));
		$this->_addElement(new MetadataElement('isbn'));
		$this->_addElement(new MetadataElement('issn'));
		$this->_addElement(new MetadataElement('comment', METADATA_ELEMENT_TYPE_STRING, false, METADATA_ELEMENT_CARDINALITY_MANY));
		
		// Add book/conference-specific meta-data
		if ($genre == METADATA_GENRE_BOOK ||
				$genre == METADATA_GENRE_BOOKCHAPTER ||
				$genre == METADATA_GENRE_CONFERENCE ||
				$genre == METADATA_GENRE_CONFERENCEPROCEEDING ||
				$genre == METADATA_GENRE_DISSERTATION ||
				$genre == METADATA_GENRE_UNKNOWN) {
			$this->_addElement(new MetadataElement('bookTitle', METADATA_ELEMENT_TYPE_STRING, true));
			$this->_addElement(new MetadataElement('editor'));
			$this->_addElement(new MetadataElement('publisher'));
			$this->_addElement(new MetadataElement('place'));
			$this->_addElement(new MetadataElement('edition'));
		}
			
		// Add journal/conference-specific meta-data
		if ($genre == METADATA_GENRE_JOURNAL ||
				$genre == METADATA_GENRE_JOURNALARTICLE ||
				$genre == METADATA_GENRE_CONFERENCE ||
				$genre == METADATA_GENRE_CONFERENCEPROCEEDING ||
				$genre == METADATA_GENRE_UNKNOWN) {
			$this->_addElement(new MetadataElement('journalTitle', METADATA_ELEMENT_TYPE_STRING, true));
			$this->_addElement(new MetadataElement('issue'));
			$this->_addElement(new MetadataElement('volume'));
			$this->_addElement(new MetadataElement('artNum'));
			$this->_addElement(new MetadataElement('pmId'));
		}
		
		// Remove content of elements that are no longer in the genre.
		$newElements =& $this->_elements;
		$removedElements = array_diff_key($previousElements, $newElements);
		foreach($removedElements as $removedElementName => $removedElement) {
			$this->_purgeElementValue($removedElementName);
		}
		
		// Set the genre as a meta-data element also
		return $this->_validateAndSetElement('genre', $genre);
	}
	
	/**
	 * Get the element names valid for the current citation genre
	 * @return array an array of string values representing the valid element names
	 */
	function getValidElementNames() {
		return array_keys($this->_elements);
	}
	
	/**
	 * Get the non-empty elements as an array
	 * @param $locale string an optional locale to retrieve localized elements
	 * @param $elementType integer must be one of the supported meta-data element types, or null (=all types), default: null
	 * @return array an array of with the element names as keys and
	 *  the element content as values.
	 */
	function getNonEmptyElementsAsArray($elementType = null, $locale = null) {
		assert(is_null($elementType) || is_integer($elementType));
		
		$elementArray = array();
		foreach($this->_elements as $elementName => $element) {
			// Check whether the element is of the required type.
			if (isset($elementType) && $element->getType() != $elementType) continue;
			 
			$elementValue = $this->_getElement($elementName, ($element->getTranslated() ? $locale : null));
			if (!empty($elementValue)) {
				$elementKey = $elementName;
				if ($element->getCardinality() == METADATA_ELEMENT_CARDINALITY_MANY) {
					$elementKey .= 's';
				}
				$elementArray[$elementKey] = $elementValue;
			}
		}
		
		return $elementArray;
	}

	/**
	 * Set elements from an array. The array's keys
	 * must correspond to element names. 
	 * @param $metadataArray array
	 * @param $locale
	 * @return bool true if elements were correctly validated and set, otherwise false
	 */
	function setElementsFromArray($metadataArray, $locale = null) {
		// Always set the genre first so that later
		// entries can be validated against the genre.
		if (isset($metadataArray['genre'])) {
			// Remove the genre from the array
			$genre = $metadataArray['genre'];
			unset($metadataArray['genre']);
			
			// Set the genre
			if (!$this->setGenre($genre)) return false;
		}

		// Get the valid elements for this genre
		$validElementNames = $this->getValidElementNames();

		// Run through the meta-data array and set the
		// corresponding elements
		foreach ($metadataArray as $elementKey => $elementValue) {
			if (empty($elementValue)) continue;
			
			// Test whether we set an array or a scalar element
			if (!in_array($elementKey, $validElementNames) && substr($elementKey, -1, 1) == 's') {
				// This seems to be an array. The element name
				// should be the element key less the letter 's'.
				$elementName = substr($elementKey, 0, -1);
			} else {
				$elementName = $elementKey;
			}
			
			// Does the element exist in the meta-data schema?
			if (!in_array($elementName, $validElementNames)) return false;
			
			// Set the element value
			if (!empty($elementValue)) {
				$setter = 'set'.ucfirst($elementKey);
				
				$element = $this->_elements[$elementName];
				if ($element->getTranslated()) {
					if (!$this->$setter($elementValue, $locale)) return false;
				} else {
					if (!$this->$setter($elementValue)) return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * Add an author.
	 * @param $author PKPAuthor
	 */
	function addAuthor($author) {
		// We need to validate author as an array as it's cardinality
		// is "many" and we don't want to re-validate all authors.
		if (!$this->_validateElement('author', array($author))) return false;
		
		$this->_authors[] = $author;
		return true;
	}

	/**
	 * Remove an author.
	 * @param $authorId ID of the author to remove
	 * @return Author the removed author or false if none was removed.
	 */
	function &removeAuthor() {
		// Remove the last author in the list
		if (!empty($this->_authors)) {
			$author =& array_pop($this->_authors);
			return $author;
		}
	
		return false;
	}

	/**
	 * Get all authors in the meta-data.
	 * @return array PKPAuthors
	 */
	function &getAuthors() {
		return $this->_authors;
	}
	
	/**
	 * Get authors as a string representation
	 * @return string authors in the format "Bohr, Niels; van der Waals, J. D.; Planck, Max"
	 */
	function getAuthorsString() {
		$authorsString = '';
		foreach ($this->_authors as $author) {
			$author = new PKPAuthor();
			$authorsString .= $author->getLastName().', '.$author->getFirstName().' '.$author->getMiddleName().';';
		}
		// Remove the final semicolon
		return substr($authorsString, 0, -1);
	}

	/**
	 * Get the first author from the meta-data
	 * @return PKPAuthor
	 */
	function &getFirstAuthor() {
		$firstAuthor = null;
		
		if (!empty($this->_authors)) {
			reset($this->_authors);
			$firstAuthor = current($this->_authors);
		}
		
		return $firstAuthor;
	}

	/**
	 * Set authors in the meta-data.
	 * @param $authors array PKPAuthors
	 * @return bool true if authors were correctly validated and set, otherwise false
	 */
	function setAuthors($authors) {
		if (!$this->_validateElement('author', $authors)) return false;
		$this->_authors = $authors;
		return true;
	}
	
	/**
	 * get the articleTitle
	 * @param $locale string retrieve the articleTitle in this locale
	 * @return string
	 */
	function getArticleTitle($locale = null) {
		return $this->_getElement('articleTitle', $locale);
	}
	
	/**
	 * set the articleTitle
	 * @param $articleTitle string
	 * @param $locale string set the articleTitle for this locale
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setArticleTitle($articleTitle, $locale = null) {
		return $this->_validateAndSetElement('articleTitle', $articleTitle, $locale);
	}
	
	/**
	 * get the firstPage
	 * @return integer
	 */
	function getFirstPage() {
		return $this->_getElement('firstPage');
	}
	
	/**
	 * set the firstPage
	 * @param $firstPage integer
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setFirstPage($firstPage) {
		return $this->_validateAndSetElement('firstPage', $firstPage);
	}
	
	/**
	 * get the lastPage
	 * @return integer
	 */
	function getLastPage() {
		return $this->_getElement('lastPage');
	}
	
	/**
	 * set the lastPage
	 * @param $lastPage integer
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setLastPage($lastPage) {
		return $this->_validateAndSetElement('lastPage', $lastPage);
	}
	
	/**
	 * get the issuedDate
	 * @return string
	 */
	function getIssuedDate() {
		return $this->_getElement('issuedDate');
	}
	
	/**
	 * set the issuedDate
	 * @param $issuedDate string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setIssuedDate($issuedDate) {
		return $this->_validateAndSetElement('issuedDate', $issuedDate);
	}
	
	/**
	 * get the accessDate
	 * @return string
	 */
	function getAccessDate() {
		return $this->_getElement('accessDate');
	}
	
	/**
	 * set the accessDate
	 * @param $accessDate string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setAccessDate($accessDate) {
		return $this->_validateAndSetElement('accessDate', $accessDate);
	}
	
	/**
	 * get the doi
	 * @return string
	 */
	function getDOI() {
		return $this->_getElement('doi');
	}
	
	/**
	 * set the doi
	 * @param $doi string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setDOI($doi) {
		return $this->_validateAndSetElement('doi', $doi);
	}
	
	/**
	 * get the pmId
	 * @return string
	 */
	function getPmId() {
		return $this->_getElement('pmId');
	}
	
	/**
	 * set the pmId
	 * @param $pmId string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setPmId($pmId) {
		return $this->_validateAndSetElement('pmId', $pmId);
	}
	
	/**
	 * get the url
	 * @return string
	 */
	function getUrl() {
		return $this->_getElement('url');
	}
	
	/**
	 * set the url
	 * @param $url string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setUrl($url) {
		return $this->_validateAndSetElement('url', $url);
	}
	
	/**
	 * get the isbn
	 * @return string
	 */
	function getIsbn() {
		return $this->_getElement('isbn');
	}
	
	/**
	 * set the isbn
	 * @param $isbn string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setIsbn($isbn) {
		return $this->_validateAndSetElement('isbn', $isbn);
	}
	
	/**
	 * get the issn
	 * @return string
	 */
	function getIssn() {
		return $this->_getElement('issn');
	}
	
	/**
	 * set the issn
	 * @param $issn string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setIssn($issn) {
		return $this->_validateAndSetElement('issn', $issn);
	}

	/**
	 * add comment
	 * @param $comment Comment
	 */
	function addComment($comment) {
		// We need to validate comments as an array as the cardinality
		// of this field is "many".
		if (!$this->_validateElement('comment', array($comment))) return false;
		
		$this->_comments[] = $comment;
		return true;
	}
	
	/**
	 * remove the last comment and return its content
	 * @return string the removed comment if one was found, otherwise false
	 */
	function removeComment() {
		// Remove the last comment in the list
		if (!empty($this->_comments)) {
			$comment = array_pop($this->_comments);
			return $comment;
		}
	
		return false;
	}
	
	/**
	 * get all comments
	 * @return array comments
	 */
	function &getComments() {
		return $this->_comments;
	}
	
	/**
	 * set comments
	 * @param $comments array comments
	 * @return bool true if comments were correctly validated and set, otherwise false
	 */
	function setComments($comments) {
		if (!$this->_validateElement('comment', $comments)) return false;
		
		$this->_comments = $comments;
		return true;
	}
	
	/**
	 * get the bookTitle
	 * @param $locale string retrieve the bookTitle in this locale
	 * @return string
	 */
	function getBookTitle($locale = null) {
		return $this->_getElement('bookTitle', $locale);
	}
	
	/**
	 * set the bookTitle
	 * @param $bookTitle string
	 * @param $locale string set the bookTitle for this locale
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setBookTitle($bookTitle, $locale = null) {
		return $this->_validateAndSetElement('bookTitle', $bookTitle, $locale);
	}
	
	/**
	 * get the editor
	 * @return string
	 */
	function getEditor() {
		return $this->_getElement('editor');
	}
	
	/**
	 * set the editor
	 * @param $editor string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setEditor($editor) {
		return $this->_validateAndSetElement('editor', $editor);
	}
	
	/**
	 * get the publisher
	 * @return string
	 */
	function getPublisher() {
		return $this->_getElement('publisher');
	}
	
	/**
	 * set the publisher
	 * @param $publisher string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setPublisher($publisher) {
		return $this->_validateAndSetElement('publisher', $publisher);
	}
	
	/**
	 * get the place
	 * @return string
	 */
	function getPlace() {
		return $this->_getElement('place');
	}
	
	/**
	 * set the place
	 * @param $place string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setPlace($place) {
		return $this->_validateAndSetElement('place', $place);
	}
	
	/**
	 * get the edition
	 * @return string
	 */
	function getEdition() {
		return $this->_getElement('edition');
	}
	
	/**
	 * set the edition
	 * @param $edition string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setEdition($edition) {
		return $this->_validateAndSetElement('edition', $edition);
	}
	
	/**
	 * get the journalTitle
	 * @param $locale string retrieve the journalTitle in this locale
	 * @return string
	 */
	function getJournalTitle($locale = null) {
		return $this->_getElement('journalTitle', $locale);
	}
	
	/**
	 * set the journalTitle
	 * @param $journalTitle string
	 * @param $locale string set the journalTitle for this locale
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setJournalTitle($journalTitle, $locale = null) {
		return $this->_validateAndSetElement('journalTitle', $journalTitle, $locale);
	}
	
	/**
	 * get the issue
	 * @return string
	 */
	function getIssue() {
		return $this->_getElement('issue');
	}
	
	/**
	 * set the issue
	 * @param $issue string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setIssue($issue) {
		return $this->_validateAndSetElement('issue', $issue);
	}
	
	/**
	 * get the volume
	 * @return string
	 */
	function getVolume() {
		return $this->_getElement('volume');
	}
	
	/**
	 * set the volume
	 * @param $volume string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setVolume($volume) {
		return $this->_validateAndSetElement('volume', $volume);
	}
	
	/**
	 * get the artNum
	 * @return string
	 */
	function getArtNum() {
		return $this->_getElement('artNum');
	}
	
	/**
	 * set the artNum
	 * @param $artNum string
	 * @return bool true if element was correctly validated and set, otherwise false
	 */
	function setArtNum($artNum) {
		return $this->_validateAndSetElement('artNum', $artNum);
	}
	
	//
	// Private methods
	//
	/**
	 * Add an element to this meta-data representation
	 * @param $element MetadataElement
	 * @return boolean true if added, false if element already existed
	 */
	function _addElement(&$element) {
		assert(!is_null($element->getName()));
		
		// check whether this element has been added before
		$name = $element->getName();
		if(isset($this->_elements[$name])) {
			return false;
		}
		$this->_elements[$name] =& $element;
		return true;
	}
	
	/**
	 * Validate the input for a given element against
	 * its element specification and set the element internally
	 * if validation succeeds.
	 * @param $elementName string
	 * @param $input mixed the input to be validated
	 * @param $locale string
	 * @return boolean true if validation succeeded and the element
	 *  was set internally, otherwise false
	 */
	function _validateAndSetElement($elementName, $input, $locale = null) {
		// validate the input
		if (!$this->_validateElement($elementName, $input)) return false;
		
		// if the validation passes then set the element value internally
		// make sure that meta-data elements reside in their own namespace
		// to avoid clashes with other data of this class.
		$this->setData('element::'.$elementName, $input, $locale);
		return true;
	}
	
	/**
	 * Validate the input for a given element against
	 * its element specification.
	 * @param $elementName string
	 * @param $input mixed the input to be validated
	 * @return boolean true if validation succeeded, otherwise false
	 */
	function _validateElement($elementName, $input) {
		// do some internal validation first
		assert(isset($this->_elements[$elementName]));
		$element =& $this->_elements[$elementName];
		assert($elementName == $element->getName());
		
		// let the MetadataElement validate the input
		if (!$element->validate($input)) return false;
		
		return true;
	}
	
	/**
	 * Return an element from the element property space
	 * or from the element array if cardinality is "many".
	 * @param $elementName string
	 * @param $locale string
	 * @return mixed the element
	 */
	function _getElement($elementName, $locale = null) {
		assert(isset($this->_elements[$elementName]));
		
		// Arrays are not saved in the data object
		$element =& $this->_elements[$elementName];
		if ($element->getCardinality() == METADATA_ELEMENT_CARDINALITY_MANY) {
			$arrayName = '_'.$elementName.'s';
			return $this->$arrayName;
		}
		
		return $this->getData('element::'.$elementName, $locale);
	}
	
	/**
	 * Purge the value of element from the element property space
	 * or purge the element array if cardinality is "many".
	 * 
	 * This method can be called for elements that are not part
	 * of the currently set genre and may therefore be missing
	 * from the internal elements list.
	 * 
	 * @param $elementName string
	 * @return boolean true on success
	 */
	function _purgeElementValue($elementName) {
		// Check whether the element is an array.
		$arrayName = '_'.$elementName.'s';
		if (isset($this->$arrayName)) {
			// Arrays are not saved in the data object
			assert(in_array($elementName, array('author', 'comment')));
			$this->$arrayName = array();
		} else {
			$this->setData('element::'.$elementName, null);
		}
		
		return true;
	}
}
?>
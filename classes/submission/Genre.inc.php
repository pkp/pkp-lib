<?php

/**
 * @file classes/submission/Genre.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Genre
 * @ingroup submission
 * @see GenreDAO
 *
 * @brief Basic class describing a genre.
 */

define('GENRE_CATEGORY_DOCUMENT', 1);
define('GENRE_CATEGORY_ARTWORK', 2);
define('GENRE_CATEGORY_SUPPLEMENTARY', 3);

class Genre extends DataObject {

	/**
	 * Get ID of context.
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set ID of context.
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get sequence of genre.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of genre.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		$this->setData('sequence', $sequence);
	}

	/**
	 * Get key of genre.
	 * @return string
	 */
	function getKey() {
		return $this->getData('key');
	}

	/**
	 * Set key of genre.
	 * @param $key string
	 */
	function setKey($key) {
		$this->setData('key', $key);
	}

	/**
	 * Get enabled status of genre.
	 * @return boolean
	 */
	function getEnabled() {
		return $this->getData('enabled');
	}

	/**
	 * Set enabled status of genre.
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		$this->setData('enabled', $enabled);
	}

	/**
	 * Set the name of the genre
	 * @param $name string
	 * @param $locale string
	 */
	function setName($name, $locale) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * Get the name of the genre
	 * @param $locale string
	 * @return string
	 */
	function getName($locale) {
		return $this->getData('name', $locale);
	}

	/**
	 * Get the localized name of the genre
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLocalizedData('name');
	}

	/**
	 * Get context file category (e.g. artwork or document)
	 * @return int GENRE_CATEGORY_...
	 */
	function getCategory() {
		return $this->getData('category');
	}

	/**
	 * Set context file category (e.g. artwork or document)
	 * @param $category int GENRE_CATEGORY_...
	 */
	function setCategory($category) {
		$this->setData('category', $category);
	}

	/**
	 * Get dependent flag
	 * @return bool
	 */
	function getDependent() {
		return $this->getData('dependent');
	}

	/**
	 * Set dependent flag
	 * @param $dependent bool
	 */
	function setDependent($dependent) {
		$this->setData('dependent', $dependent);
	}

	/**
	 * Get supplementary flag
	 * @return bool
	 */
	function getSupplementary() {
		return $this->getData('supplementary');
	}

	/**
	 * Set supplementary flag
	 * @param $supplementary bool
	 */
	function setSupplementary($supplementary) {
		$this->setData('supplementary', $supplementary);
	}

	/**
	 * Is this a default genre.
	 * @return bool
	 */
	function isDefault() {
		$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
		$defaultKeys = $genreDao->getDefaultKeys();
		return in_array($this->getKey(), $defaultKeys);
	}
}



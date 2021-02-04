<?php

/**
 * @file classes/submission/SubmissionFile.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFile
 * @ingroup submission
 *
 * @brief Submission file class.
 */

// Define the file stage identifiers.
define('SUBMISSION_FILE_SUBMISSION', 2);
define('SUBMISSION_FILE_NOTE', 3);
define('SUBMISSION_FILE_REVIEW_FILE', 4);
define('SUBMISSION_FILE_REVIEW_ATTACHMENT', 5);
//	SUBMISSION_FILE_REVIEW_REVISION defined below (FIXME: re-order before release)
define('SUBMISSION_FILE_FINAL', 6);
define('SUBMISSION_FILE_COPYEDIT', 9);
define('SUBMISSION_FILE_PROOF', 10);
define('SUBMISSION_FILE_PRODUCTION_READY', 11);
define('SUBMISSION_FILE_ATTACHMENT', 13);
define('SUBMISSION_FILE_REVIEW_REVISION', 15);
define('SUBMISSION_FILE_DEPENDENT', 17);
define('SUBMISSION_FILE_QUERY', 18);
define('SUBMISSION_FILE_INTERNAL_REVIEW_FILE', 19);
define('SUBMISSION_FILE_INTERNAL_REVIEW_REVISION', 20);

class SubmissionFile extends DataObject {

	/**
	 * @copydoc DataObject::getDAO()
	 */
	function getDAO() {
		return DAORegistry::getDAO('SubmissionFileDAO');
	}

	/**
	 * Get a piece of data for this object, localized to the current
	 * locale if possible.
	 * @param $key string
	 * @param $preferredLocale string
	 * @return mixed
	 */
	function &getLocalizedData($key, $preferredLocale = null) {
		if (is_null($preferredLocale)) $preferredLocale = AppLocale::getLocale();
		$localePrecedence = [$preferredLocale, $this->getData('locale')];
		foreach ($localePrecedence as $locale) {
			if (empty($locale)) continue;
			$value =& $this->getData($key, $locale);
			if (!empty($value)) return $value;
			unset($value);
		}

		// Fallback: Get the first available piece of data.
		$data =& $this->getData($key, null);
		foreach ((array) $data as $dataValue) {
			if (!empty($dataValue)) return $dataValue;
		}

		// No data available; return null.
		unset($data);
		$data = null;
		return $data;
	}

	//
	// Getters and Setters
	//

	/**
	 * Get the locale of the submission.
	 * This is not properly a property of the submission file
	 * (e.g. it won't be persisted to the DB with the update function)
	 * It helps solve submission locale requirement for file's multilingual metadata
	 * @deprecated 3.3.0.0
	 * @return string
	 */
	function getSubmissionLocale() {
		return $this->getData('locale');
	}

	/**
	 * Set the locale of the submission.
	 * This is not properly a property of the submission file
	 * (e.g. it won't be persisted to the DB with the update function)
	 * It helps solve submission locale requirement for file's multilingual metadata
	 * @deprecated 3.3.0.0
	 * @param $submissionLocale string
	 */
	function setSubmissionLocale($submissionLocale) {
		$this->setData('locale', $submissionLocale);
	}

	/**
	 * Get stored public ID of the file.
	 * @param @literal $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
	 * @return int
	 */
	function getStoredPubId($pubIdType) {
		return $this->getData('pub-id::'.$pubIdType);
	}

	/**
	 * Set the stored public ID of the file.
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 */
	function setStoredPubId($pubIdType, $pubId) {
		$this->setData('pub-id::'.$pubIdType, $pubId);
	}

	/**
	 * Get price of submission file.
	 * A null return indicates "not available"; 0 is free.
	 * @return numeric|null
	 */
	function getDirectSalesPrice() {
		return $this->getData('directSalesPrice');
	}

	/**
	 * Set direct sales price.
	 * A null return indicates "not available"; 0 is free.
	 * @param $directSalesPrice numeric|null
	 */
	function setDirectSalesPrice($directSalesPrice) {
		$this->setData('directSalesPrice', $directSalesPrice);
	}

	/**
	 * Get sales type of submission file.
	 * @return string
	 */
	function getSalesType() {
		return $this->getData('salesType');
	}

	/**
	 * Set sales type.
	 * @param $salesType string
	 */
	function setSalesType($salesType) {
		$this->setData('salesType', $salesType);
	}

	/**
	 * Set the genre id of this file (i.e. referring to Manuscript, Index, etc)
	 * Foreign key into genres table
	 * @deprecated 3.3.0.0
	 * @param $genreId int
	 */
	function setGenreId($genreId) {
		$this->setData('genreId', $genreId);
	}

	/**
	 * Get the genre id of this file (i.e. referring to Manuscript, Index, etc)
	 * Foreign key into genres table
	 * @deprecated 3.3.0.0
	 * @return int
	 */
	function getGenreId() {
		return $this->getData('genreId');
	}

	/**
	 * Return the "best" file ID -- If a public ID is set,
	 * use it; otherwise use the internal ID and revision.
	 * @return string
	 */
	function getBestId() {
		$publicFileId = $this->getStoredPubId('publisher-id');
		if (!empty($publicFileId)) return $publicFileId;
		return $this->getId();
	}

	/**
	 * Get file stage of the file.
	 * @deprecated 3.3.0.0
	 * @return int SUBMISSION_FILE_...
	 */
	function getFileStage() {
		return $this->getData('fileStage');
	}

	/**
	 * Set file stage of the file.
	 * @deprecated 3.3.0.0
	 * @param $fileStage int SUBMISSION_FILE_...
	 */
	function setFileStage($fileStage) {
		$this->setData('fileStage', $fileStage);
	}

	/**
	 * Get modified date of file.
	 * @deprecated 3.3.0.0
	 * @return date
	 */

	function getDateModified() {
		return $this->getData('updatedAt');
	}

	/**
	 * Set modified date of file.
	 * @deprecated 3.3.0.0
	 * @param $updatedAt date
	 */

	function setDateModified($updatedAt) {
		return $this->setData('updatedAt', $updatedAt);
	}

	/**
	 * Get viewable.
	 * @deprecated 3.3.0.0
	 * @return boolean
	 */
	function getViewable() {
		return $this->getData('viewable');
	}


	/**
	 * Set viewable.
	 * @deprecated 3.3.0.0
	 * @param $viewable boolean
	 */
	function setViewable($viewable) {
		return $this->setData('viewable', $viewable);
	}

	/**
	 * Set the uploader's user id.
	 * @deprecated 3.3.0.0
	 * @param $uploaderUserId integer
	 */
	function setUploaderUserId($uploaderUserId) {
		$this->setData('uploaderUserId', $uploaderUserId);
	}

	/**
	 * Get the uploader's user id.
	 * @deprecated 3.3.0.0
	 * @return integer
	 */
	function getUploaderUserId() {
		return $this->getData('uploaderUserId');
	}

	/**
	 * Get type that is associated with this file.
	 * @deprecated 3.3.0.0
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set type that is associated with this file.
	 * @deprecated 3.3.0.0
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		$this->setData('assocType', $assocType);
	}

	/**
	 * Get the submission chapter id.
	 * @deprecated 3.3.0.0
	 * @return int
	 */
	function getChapterId() {
		return $this->getData('chapterId');
	}

	/**
	 * Set the submission chapter id.
	 * @deprecated 3.3.0.0
	 * @param $chapterId int
	 */
	function setChapterId($chapterId) {
		$this->setData('chapterId', $chapterId);
	}
}

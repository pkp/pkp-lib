<?php

/**
 * @defgroup submission
 */

/**
 * @file classes/submission/Submission.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Submission
 * @ingroup submission
 *
 * @brief Submission class.
 */


class Submission extends DataObject {
	/** @var array Authors of this submission */
	var $authors;

	/** @var array IDs of Authors removed from this submission */
	var $removedAuthors;

	/**
	 * Constructor.
	 */
	function Submission() {
		parent::DataObject();
		$this->authors = array();
		$this->removedAuthors = array();
	}

	/**
	 * Returns the association type of this submission
	 * @return integer one of the ASSOC_TYPE_* constants
	 */
	function getAssocType() {
		// Must be implemented by sub-classes.
		assert(false);
	}

	/**
	 * Get a piece of data for this object, localized to the current
	 * locale if possible.
	 * @param $key string
	 * @return mixed
	 */
	function &getLocalizedData($key) {
		$localePrecedence = array(Locale::getLocale(), $this->getLocale());
		foreach ($localePrecedence as $locale) {
			$value =& $this->getData($key, $locale);
			if (!empty($value)) return $value;
			unset($value);
		}

		// Fallback: Get the first available piece of data.
		$data =& $this->getData($key, null);
		if (!empty($data)) return $data[array_shift(array_keys($data))];

		// No data available; return null.
		unset($data);
		$data = null;
		return $data;
	}

	//
	// Get/set methods
	//

	/**
	 * Add an author.
	 * @param $author Author
	 */
	function addAuthor($author) {
		if ($author->getSequence() == null) {
			$author->setSequence(count($this->authors) + 1);
		}
		array_push($this->authors, $author);
	}

	/**
	 * Remove an author.
	 * @param $authorId ID of the author to remove
	 * @return boolean author was removed
	 */
	function removeAuthor($authorId) {
		$found = false;

		if ($authorId != 0) {
			// FIXME maintain a hash of ID to author for quicker get/remove
			$authors = array();
			for ($i=0, $count=count($this->authors); $i < $count; $i++) {
				if ($this->authors[$i]->getId() == $authorId) {
					array_push($this->removedAuthors, $authorId);
					$found = true;
				} else {
					array_push($authors, $this->authors[$i]);
				}
			}
			$this->authors = $authors;
		}
		return $found;
	}

	/**
	 * Return string of author names, separated by the specified token
	 * @param $lastOnly boolean return list of lastnames only (default false)
	 * @param $separator string separator for names (default comma+space)
	 * @return string
	 */
	function getAuthorString($lastOnly = false, $separator = ', ') {
		$str = '';
		foreach ($this->authors as $a) {
			if (!empty($str)) {
				$str .= $separator;
			}
			$str .= $lastOnly ? $a->getLastName() : $a->getFullName();
		}
		return $str;
	}

	/**
	 * Return a list of author email addresses.
	 * @return array
	 */
	function getAuthorEmails() {
		import('lib.pkp.classes.mail.Mail');
		$returner = array();
		foreach ($this->authors as $a) {
			$returner[] = Mail::encodeDisplayName($a->getFullName()) . ' <' . $a->getEmail() . '>';
		}
		return $returner;
	}

	/**
	 * Return first author
	 * @param $lastOnly boolean return lastname only (default false)
	 * @return string
	 */
	function getFirstAuthor($lastOnly = false) {
		$author = $this->authors[0];
		if (!$author) return null;
		return $lastOnly ? $author->getLastName() : $author->getFullName();
	}


	//
	// Get/set methods
	//

	/**
	 * Get all authors of this submission.
	 * @return array Authors
	 */
	function &getAuthors() {
		return $this->authors;
	}

	/**
	 * Get a specific author of this submission.
	 * @param $authorId int
	 * @return array Authors
	 */
	function &getAuthor($authorId) {
		$author = null;

		if ($authorId != 0) {
			for ($i=0, $count=count($this->authors); $i < $count && $author == null; $i++) {
				if ($this->authors[$i]->getId() == $authorId) {
					$author =& $this->authors[$i];
				}
			}
		}
		return $author;
	}

	/**
	 * Get the IDs of all authors removed from this submission.
	 * @return array int
	 */
	function &getRemovedAuthors() {
		return $this->removedAuthors;
	}

	/**
	 * Set authors of this submission.
	 * @param $authors array Authors
	 */
	function setAuthors($authors) {
		return $this->authors = $authors;
	}

	/**
	 * Get user ID of the submitter.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set user ID of the submitter.
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * Return the user of the submitter.
	 * @return User
	 */
	function getUser() {
		$userDao =& DAORegistry::getDAO('UserDAO');
		return $userDao->getUser($this->getUserId(), true);
	}

	/**
	 * Get the locale of the submission.
	 * @return string
	 */
	function getLocale() {
		return $this->getData('locale');
	}

	/**
	 * Set the locale of the submission.
	 * @param $locale string
	 */
	function setLocale($locale) {
		return $this->setData('locale', $locale);
	}

	/**
	 * Get "localized" submission title (if applicable).
	 * @return string
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get title.
	 * @param $locale
	 * @return string
	 */
	function getTitle($locale) {
		return $this->getData('title', $locale);
	}

	/**
	 * Set title.
	 * @param $title string
	 * @param $locale
	 */
	function setTitle($title, $locale) {
		$this->setCleanTitle($title, $locale);
		return $this->setData('title', $title, $locale);
	}

	/**
	 * Set 'clean' title (with punctuation removed).
	 * @param $cleanTitle string
	 * @param $locale
	 */
	function setCleanTitle($cleanTitle, $locale) {
		$punctuation = array ("\"", "\'", ",", ".", "!", "?", "-", "$", "(", ")");
		$cleanTitle = str_replace($punctuation, "", $cleanTitle);
		return $this->setData('cleanTitle', $cleanTitle, $locale);
	}

	/**
	 * Get "localized" submission abstract (if applicable).
	 * @return string
	 */
	function getLocalizedAbstract() {
		return $this->getLocalizedData('abstract');
	}

	/**
	 * Get abstract.
	 * @param $locale
	 * @return string
	 */
	function getAbstract($locale) {
		return $this->getData('abstract', $locale);
	}

	/**
	 * Set abstract.
	 * @param $abstract string
	 * @param $locale
	 */
	function setAbstract($abstract, $locale) {
		return $this->setData('abstract', $abstract, $locale);
	}

	/**
	 * Return the localized discipline
	 * @return string
	 */
	function getLocalizedDiscipline() {
		return $this->getLocalizedData('discipline');
	}

	/**
	 * Get discipline
	 * @param $locale
	 * @return string
	 */
	function getDiscipline($locale) {
		return $this->getData('discipline', $locale);
	}

	/**
	 * Set discipline
	 * @param $discipline string
	 * @param $locale
	 */
	function setDiscipline($discipline, $locale) {
		return $this->setData('discipline', $discipline, $locale);
	}

	/**
	 * Return the localized subject classification
	 * @return string
	 */
	function getLocalizedSubjectClass() {
		return $this->getLocalizedData('subjectClass');
	}

	/**
	 * Get subject classification.
	 * @param $locale
	 * @return string
	 */
	function getSubjectClass($locale) {
		return $this->getData('subjectClass', $locale);
	}

	/**
	 * Set subject classification.
	 * @param $subjectClass string
	 * @param $locale
	 */
	function setSubjectClass($subjectClass, $locale) {
		return $this->setData('subjectClass', $subjectClass, $locale);
	}

	/**
	 * Return the localized subject
	 * @return string
	 */
	function getLocalizedSubject() {
		return $this->getLocalizedData('subject');
	}

	/**
	 * Get subject.
	 * @param $locale
	 * @return string
	 */
	function getSubject($locale) {
		return $this->getData('subject', $locale);
	}

	/**
	 * Set subject.
	 * @param $subject string
	 * @param $locale
	 */
	function setSubject($subject, $locale) {
		return $this->setData('subject', $subject, $locale);
	}

	/**
	 * Return the localized geographical coverage
	 * @return string
	 */
	function getLocalizedCoverageGeo() {
		return $this->getLocalizedData('coverageGeo');
	}

	/**
	 * Get geographical coverage.
	 * @param $locale
	 * @return string
	 */
	function getCoverageGeo($locale) {
		return $this->getData('coverageGeo', $locale);
	}

	/**
	 * Set geographical coverage.
	 * @param $coverageGeo string
	 * @param $locale
	 */
	function setCoverageGeo($coverageGeo, $locale) {
		return $this->setData('coverageGeo', $coverageGeo, $locale);
	}

	/**
	 * Return the localized chronological coverage
	 * @return string
	 */
	function getLocalizedCoverageChron() {
		return $this->getLocalizedData('coverageChron');
	}

	/**
	 * Get chronological coverage.
	 * @param $locale
	 * @return string
	 */
	function getCoverageChron($locale) {
		return $this->getData('coverageChron', $locale);
	}

	/**
	 * Set chronological coverage.
	 * @param $coverageChron string
	 * @param $locale
	 */
	function setCoverageChron($coverageChron, $locale) {
		return $this->setData('coverageChron', $coverageChron, $locale);
	}

	/**
	 * Return the localized sample coverage
	 * @return string
	 */
	function getLocalizedCoverageSample() {
		return $this->getLocalizedData('coverageSample');
	}

	/**
	 * Get research sample coverage.
	 * @param $locale
	 * @return string
	 */
	function getCoverageSample($locale) {
		return $this->getData('coverageSample', $locale);
	}

	/**
	 * Set geographical coverage.
	 * @param $coverageSample string
	 * @param $locale
	 */
	function setCoverageSample($coverageSample, $locale) {
		return $this->setData('coverageSample', $coverageSample, $locale);
	}

	/**
	 * Return the localized type (method/approach)
	 * @return string
	 */
	function getLocalizedType() {
		return $this->getLocalizedData('type');
	}

	/**
	 * Get type (method/approach).
	 * @param $locale
	 * @return string
	 */
	function getType($locale) {
		return $this->getData('type', $locale);
	}

	/**
	 * Set type (method/approach).
	 * @param $type string
	 * @param $locale
	 */
	function setType($type, $locale) {
		return $this->setData('type', $type, $locale);
	}

	/**
	 * Get language.
	 * @return string
	 */
	function getLanguage() {
		return $this->getData('language');
	}

	/**
	 * Set language.
	 * @param $language string
	 */
	function setLanguage($language) {
		return $this->setData('language', $language);
	}

	/**
	 * Return the localized sponsor
	 * @return string
	 */
	function getLocalizedSponsor() {
		return $this->getLocalizedData('sponsor');
	}

	/**
	 * Get sponsor.
	 * @param $locale
	 * @return string
	 */
	function getSponsor($locale) {
		return $this->getData('sponsor', $locale);
	}

	/**
	 * Set sponsor.
	 * @param $sponsor string
	 * @param $locale
	 */
	function setSponsor($sponsor, $locale) {
		return $this->setData('sponsor', $sponsor, $locale);
	}

	/**
	 * Get citations.
	 * @return string
	 */
	function getCitations() {
		return $this->getData('citations');
	}

	/**
	 * Set citations.
	 * @param $citations string
	 */
	function setCitations($citations) {
		return $this->setData('citations', $citations);
	}

	/**
	 * Get the localized cover filename
	 * @return string
	 */
	function getLocalizedFileName() {
		return $this->getLocalizedData('fileName');
	}

	/**
	 * get file name
	 * @param $locale string
	 * @return string
	 */
	function getFileName($locale) {
		return $this->getData('fileName', $locale);
	}

	/**
	 * set file name
	 * @param $fileName string
	 * @param $locale string
	 */
	function setFileName($fileName, $locale) {
		return $this->setData('fileName', $fileName, $locale);
	}

	/**
	 * Get the localized submission cover width
	 * @return string
	 */
	function getLocalizedWidth() {
		return $this->getLocalizedData('width');
	}

	/**
	 * get width of cover page image
	 * @param $locale string
	 * @return string
	 */
	function getWidth($locale) {
		return $this->getData('width', $locale);
	}

	/**
	 * set width of cover page image
	 * @param $locale string
	 * @param $width int
	 */
	function setWidth($width, $locale) {
		return $this->setData('width', $width, $locale);
	}

	/**
	 * Get the localized submission cover height
	 * @return string
	 */
	function getLocalizedHeight() {
		return $this->getLocalizedData('height');
	}

	/**
	 * get height of cover page image
	 * @param $locale string
	 * @return string
	 */
	function getHeight($locale) {
		return $this->getData('height', $locale);
	}

	/**
	 * set height of cover page image
	 * @param $locale string
	 * @param $height int
	 */
	function setHeight($height, $locale) {
		return $this->setData('height', $height, $locale);
	}

	/**
	 * Get the localized cover filename on the uploader's computer
	 * @return string
	 */
	function getLocalizedOriginalFileName() {
		return $this->getLocalizedData('originalFileName');
	}

	/**
	 * get original file name
	 * @param $locale string
	 * @return string
	 */
	function getOriginalFileName($locale) {
		return $this->getData('originalFileName', $locale);
	}

	/**
	 * set original file name
	 * @param $originalFileName string
	 * @param $locale string
	 */
	function setOriginalFileName($originalFileName, $locale) {
		return $this->setData('originalFileName', $originalFileName, $locale);
	}

	/**
	 * Get the localized cover alternate text
	 * @return string
	 */
	function getLocalizedCoverPageAltText() {
		return $this->getLocalizedData('coverPageAltText');
	}

	/**
	 * get cover page alternate text
	 * @param $locale string
	 * @return string
	 */
	function getCoverPageAltText($locale) {
		return $this->getData('coverPageAltText', $locale);
	}

	/**
	 * set cover page alternate text
	 * @param $coverPageAltText string
	 * @param $locale string
	 */
	function setCoverPageAltText($coverPageAltText, $locale) {
		return $this->setData('coverPageAltText', $coverPageAltText, $locale);
	}

	/**
	 * Get the flag indicating whether or not to show
	 * a cover page.
	 * @return string
	 */
	function getLocalizedShowCoverPage() {
		return $this->getLocalizedData('showCoverPage');
	}

	/**
	 * get show cover page
	 * @param $locale string
	 * @return int
	 */
	function getShowCoverPage($locale) {
		return $this->getData('showCoverPage', $locale);
	}

	/**
	 * set show cover page
	 * @param $showCoverPage int
	 * @param $locale string
	 */
	function setShowCoverPage($showCoverPage, $locale) {
		return $this->setData('showCoverPage', $showCoverPage, $locale);
	}

	/**
	 * get hide cover page thumbnail in Toc
	 * @param $locale string
	 * @return int
	 */
	function getHideCoverPageToc($locale) {
		return $this->getData('hideCoverPageToc', $locale);
	}

	/**
	 * set hide cover page thumbnail in Toc
	 * @param $hideCoverPageToc int
	 * @param $locale string
	 */
	function setHideCoverPageToc($hideCoverPageToc, $locale) {
		return $this->setData('hideCoverPageToc', $hideCoverPageToc, $locale);
	}

	/**
	 * get hide cover page in abstract view
	 * @param $locale string
	 * @return int
	 */
	function getHideCoverPageAbstract($locale) {
		return $this->getData('hideCoverPageAbstract', $locale);
	}

	/**
	 * set hide cover page in abstract view
	 * @param $hideCoverPageAbstract int
	 * @param $locale string
	 */
	function setHideCoverPageAbstract($hideCoverPageAbstract, $locale) {
		return $this->setData('hideCoverPageAbstract', $hideCoverPageAbstract, $locale);
	}

	/**
	 * Get localized hide cover page in abstract view
	 */
	function getLocalizedHideCoverPageAbstract() {
		return $this->getLocalizedData('hideCoverPageAbstract');
	}

	/**
	 * Get submission date.
	 * @return date
	 */
	function getDateSubmitted() {
		return $this->getData('dateSubmitted');
	}

	/**
	 * Set submission date.
	 * @param $dateSubmitted date
	 */
	function setDateSubmitted($dateSubmitted) {
		return $this->setData('dateSubmitted', $dateSubmitted);
	}

	/**
	 * Get the date of the last status modification.
	 * @return date
	 */
	function getDateStatusModified() {
		return $this->getData('dateStatusModified');
	}

	/**
	 * Set the date of the last status modification.
	 * @param $dateModified date
	 */
	function setDateStatusModified($dateModified) {
		return $this->setData('dateStatusModified', $dateModified);
	}

	/**
	 * Get the date of the last modification.
	 * @return date
	 */
	function getLastModified() {
		return $this->getData('lastModified');
	}

	/**
	 * Set the date of the last modification.
	 * @param $dateModified date
	 */
	function setLastModified($dateModified) {
		return $this->setData('lastModified', $dateModified);
	}

	/**
	 * Stamp the date of the last modification to the current time.
	 */
	function stampModified() {
		return $this->setLastModified(Core::getCurrentDate());
	}

	/**
	 * Stamp the date of the last status modification to the current time.
	 */
	function stampStatusModified() {
		return $this->setDateStatusModified(Core::getCurrentDate());
	}

	/**
	 * Get submission status.
	 * @return int
	 */
	function getStatus() {
		return $this->getData('status');
	}

	/**
	 * Set submission status.
	 * @param $status int
	 */
	function setStatus($status) {
		return $this->setData('status', $status);
	}

	/**
	 * Get a map for status constant to locale key.
	 * @return array
	 */
	function &getStatusMap() {
		static $statusMap;
		if (!isset($statusMap)) {
			$statusMap = array(
				STATUS_ARCHIVED => 'submissions.archived',
				STATUS_QUEUED => 'submissions.queued',
				STATUS_PUBLISHED => 'submissions.published',
				STATUS_DECLINED => 'submissions.declined',
				STATUS_QUEUED_UNASSIGNED => 'submissions.queuedUnassigned',
				STATUS_QUEUED_REVIEW => 'submissions.queuedReview',
				STATUS_QUEUED_EDITING => 'submissions.queuedEditing',
				STATUS_INCOMPLETE => 'submissions.incomplete'
			);
		}
		return $statusMap;
	}

	/**
	 * Get a locale key for the paper's current status.
	 * @return string
	 */
	function getStatusKey() {
		$statusMap =& $this->getStatusMap();
		return $statusMap[$this->getStatus()];
	}

	/**
	 * Get submission progress (most recently completed submission step).
	 * @return int
	 */
	function getSubmissionProgress() {
		return $this->getData('submissionProgress');
	}

	/**
	 * Set submission progress.
	 * @param $submissionProgress int
	 */
	function setSubmissionProgress($submissionProgress) {
		return $this->setData('submissionProgress', $submissionProgress);
	}

	/**
	 * Get submission file id.
	 * @return int
	 */
	function getSubmissionFileId() {
		return $this->getData('submissionFileId');
	}

	/**
	 * Set submission file id.
	 * @param $submissionFileId int
	 */
	function setSubmissionFileId($submissionFileId) {
		return $this->setData('submissionFileId', $submissionFileId);
	}

	/**
	 * Get revised file id.
	 * @return int
	 */
	function getRevisedFileId() {
		return $this->getData('revisedFileId');
	}

	/**
	 * Set revised file id.
	 * @param $revisedFileId int
	 */
	function setRevisedFileId($revisedFileId) {
		return $this->setData('revisedFileId', $revisedFileId);
	}

	/**
	 * Get review file id.
	 * @return int
	 */
	function getReviewFileId() {
		return $this->getData('reviewFileId');
	}

	/**
	 * Set review file id.
	 * @param $reviewFileId int
	 */
	function setReviewFileId($reviewFileId) {
		return $this->setData('reviewFileId', $reviewFileId);
	}

	/**
	 * get pages
	 * @return string
	 */
	function getPages() {
		return $this->getData('pages');
	}

	/**
	 * set pages
	 * @param $pages string
	 */
	function setPages($pages) {
		return $this->setData('pages',$pages);
	}

	/**
	 * Return submission RT comments status.
	 * @return int
	 */
	function getCommentsStatus() {
		return $this->getData('commentsStatus');
	}

	/**
	 * Set submission RT comments status.
	 * @param $commentsStatus boolean
	 */
	function setCommentsStatus($commentsStatus) {
		return $this->setData('commentsStatus', $commentsStatus);
	}
}

?>

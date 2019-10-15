<?php

/**
 * @defgroup submission Submission
 * The abstract concept of a submission is implemented here, and extended
 * in each application with the specifics of that content model, i.e.
 * Articles in OJS, Papers in OCS, and Monographs in OMP.
 */

/**
 * @file classes/submission/PKPSubmission.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmission
 * @ingroup submission
 * @see PKPSubmissionDAO
 *
 * @brief The Submission class implements the abstract data model of a
 * scholarly submission.
 */

// Submission status constants
define('STATUS_QUEUED', 1);
define('STATUS_PUBLISHED', 3);
define('STATUS_DECLINED', 4);
define('STATUS_SCHEDULED', 5);

// License settings (internal use only)
define ('PERMISSIONS_FIELD_LICENSE_URL', 1);
define ('PERMISSIONS_FIELD_COPYRIGHT_HOLDER', 2);
define ('PERMISSIONS_FIELD_COPYRIGHT_YEAR', 3);

abstract class PKPSubmission extends DataObject {
	/**
	 * Constructor.
	 */
	function __construct() {
		// Switch on meta-data adapter support.
		$this->setHasLoadableAdapters(true);

		parent::__construct();
	}

	/**
	 * Return the "best" article ID -- If a public article ID is set,
	 * use it; otherwise use the internal article Id.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getBestId() {
		$publicArticleId = $this->getStoredPubId('publisher-id');
		if (!empty($publicArticleId)) return $publicArticleId;
		return $this->getId();
	}

	/**
	 * Get the current publication
	 *
	 * Uses the `currentPublicationId` to get the current
	 * Publication object from the submission's list of
	 * publications.
	 *
	 * @return Publication|null
	 */
	public function getCurrentPublication() {
		$publicationId = $this->getData('currentPublicationId');
		$publications = $this->getData('publications');
		if (!$publicationId || empty($publications)) {
			return null;
		}
		foreach ($publications as $publication) {
			if ($publication->getId() === $publicationId) {
				return $publication;
			}
		}
	}

	/**
	 * Get the latest publication
	 *
	 * Returns the most recently created publication by ID
	 *
	 * @return Publication|null
	 */
	public function getLatestPublication() {
		$publications = $this->getData('publications');
		if (empty($publications)) {
			return null;
		}
		return array_reduce($publications, function($a, $b) {
			return $a && $a->getId() > $b->getId() ? $a : $b;
		});
	}

	/**
	 * Get the published publications
	 *
	 * Returns publications with the STATUS_PUBLISHED status
	 *
	 * @return array
	 */
	public function getPublishedPublications() {
		$publications = $this->getData('publications');
		if (empty($publications)) {
			return [];
		}
		return array_filter($publications, function($publication) {
			return $publication->getData('status') === STATUS_PUBLISHED;
		});
	}

	/**
	 * Stamp the date of the last modification to the current time.
	 */
	public function stampModified() {
		return $this->setData('lastModified', Core::getCurrentDate());
	}

	/**
	 * Stamp the date of the last status modification to the current time.
	 */
	public function stampStatusModified() {
		return $this->setData('dateStatusModified', Core::getCurrentDate());
	}

	/**
	 * Get a map for status constant to locale key.
	 * @return array
	 */
	function &getStatusMap() {
		static $statusMap;
		if (!isset($statusMap)) {
			$statusMap = array(
				STATUS_QUEUED => 'submissions.queued',
				STATUS_PUBLISHED => 'submission.status.published',
				STATUS_DECLINED => 'submission.status.declined',
				STATUS_SCHEDULED => 'submission.status.scheduled',
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
		return $statusMap[$this->getData('status')];
	}

	/**
	 * @copydoc DataObject::getDAO()
	 */
	function getDAO() {
		return Application::get()->getSubmissionDAO();
	}

	//
	// Abstract methods.
	//
	/**
	 * Get section id.
	 * @return int
	 */
	abstract function getSectionId();

	/**
	 * Get the value of a license field from the containing context.
	 * @param $locale string Locale code
	 * @param $field PERMISSIONS_FIELD_...
	 * @return string|null
	 */
	abstract function _getContextLicenseFieldValue($locale, $field);

	//
	// Deprecated methods
	//

	/**
	 * Get the localized copyright holder for the current publication
	 * @param $preferredLocale string Preferred locale code
	 * @return string Localized copyright holder.
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedCopyrightHolder($preferredLocale = null) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('copyrightHolder', $preferredLocale);
	}

	/**
	 * Get the context ID for the current publication
	 * @return int
	 * @deprecated 3.2.0.0
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set the context ID for the current publication
	 * @param $contextId int
	 * @deprecated 3.2.0.0
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get a piece of data for this object, localized to the current
	 * locale of the current publication if possible.
	 * @param $key string
	 * @param $preferredLocale string
	 * @param $returnLocale string Optional reference to string receiving return value's locale
	 * @return mixed
	 * @deprecated 3.2.0.0
	 */
	function &getLocalizedData($key, $preferredLocale = null) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			return $publication->getLocalizedData($key, $preferredLocale);
		}
		return null;
	}

	/**
	 * Get stored public ID of the submission.
	 * @param @literal $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
	 * @return int
	 * @deprecated 3.2.0.0
	 */
	function getStoredPubId($pubIdType) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('pub-id::'.$pubIdType);
	}

	/**
	 * Set the stored public ID of the submission.
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @deprecated 3.2.0.0
	 */
	function setStoredPubId($pubIdType, $pubId) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$this->setData('pub-id::'.$pubIdType, $pubId);
		}
	}

	/**
	 * Get stored copyright holder for the submission.
	 * @param $locale string locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getCopyrightHolder($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('copyrightHolder', $locale);
	}

	/**
	 * Set the stored copyright holder for the submission.
	 * @param $copyrightHolder string Copyright holder
	 * @param $locale string locale
	 * @deprecated 3.2.0.0
	 */
	function setCopyrightHolder($copyrightHolder, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('copyrightHolder', $copyrightHolder, $locale);
		}
	}

	/**
	 * Get stored copyright year for the submission.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getCopyrightYear() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('copyrightYear');
	}

	/**
	 * Set the stored copyright year for the submission.
	 * @param $copyrightYear string Copyright holder
	 * @deprecated 3.2.0.0
	 */
	function setCopyrightYear($copyrightYear) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('copyrightYear', $copyrightYear);
		}
	}

	/**
	 * Get stored license URL for the submission content.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLicenseURL() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('licenseURL');
	}

	/**
	 * Set the stored license URL for the submission content.
	 * @param $license string License of submission content
	 * @deprecated 3.2.0.0
	 */
	function setLicenseURL($licenseURL) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('licenseURL', $licenseURL);
		}
	}

	/**
	 * Set option selection indicating if author should be hidden in issue ToC.
	 * @param $hideAuthor int AUTHOR_TOC_...
	 * @deprecated 3.2.0.0
	 */
	function setHideAuthor($hideAuthor) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('hideAuthor', $hideAuthor);
		}
	}

	/**
	 * Return string of author names, separated by the specified token
	 * @param $preferred boolean If the preferred public name should be used, if exist
	 * @param $familyOnly boolean return list of family names only (default false)
	 * @return string
	 * @deprecated 3.2.0.0
	 */

	function getAuthorString($preferred = true, $familyOnly = false) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}

		$userGroupIds = array_map(function($author) {
			return $author->getData('userGroupId');
		}, $this->getAuthors());
		$userGroups = array_map(function($userGroupId) {
			return DAORegistry::getDAO('UserGroupDAO')->getbyId($userGroupId);
		}, array_unique($userGroupIds));

		return $publication->getAuthorString($userGroups);
	}

	/**
	 * Return short author names string.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getShortAuthorString() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getShortAuthorString();
	}

	/**
	 * Return a list of author email addresses of the current publication.
	 * @return array
	 * @deprecated 3.2.0.0
	 */
	function getAuthorEmails() {
		$authors = $this->getAuthors();

		import('lib.pkp.classes.mail.Mail');
		$returner = array();
		foreach($authors as $author) {
			$returner[] = Mail::encodeDisplayName($author->getFullName()) . ' <' . $author->getEmail() . '>';
		}
		return $returner;
	}

	/**
	 * Get all authors of the current publication
	 * @param $onlyIncludeInBrowse boolean whether to limit to include_in_browse authors.
	 * @return array Authors
	 * @deprecated 3.2.0.0
	 */
	function getAuthors($onlyIncludeInBrowse = false) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return [];
		}
		$authors = $publication->getData('authors');
		if (empty(!$authors)) {
			return [];
		}
		if ($onlyIncludeInBrowse) {
			return array_filter($authors, function($author) { return $author->getData('includeInBrowse'); });
		}
		return $authors;
	}

	/**
	 * Get the primary author of the current publication
	 * @return Author|null
	 * @deprecated 3.2.0.0
	 */
	function getPrimaryAuthor() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return null;
		}
		return $publication->getPrimaryAuthor();
	}

	/**
	 * Get the locale of the submission.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocale() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('locale');
	}

	/**
	 * Set the locale of the submission.
	 * @param $locale string
	 * @deprecated 3.2.0.0
	 */
	function setLocale($locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('locale', $locale);
		}
	}

	/**
	 * Get "localized" submission title (if applicable).
	 * @param $preferredLocale string
	 * @param $includePrefix bool
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedTitle($preferredLocale = null, $includePrefix = true) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedTitle($preferredLocale);
	}

	/**
	 * Get title.
	 * @param $locale
	 * @param $includePrefix bool
	 * @return string|array
	 * @deprecated 3.2.0.0
	 */
	function getTitle($locale, $includePrefix = true) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		if ($includePrefix) {
			if (is_null($locale)) {
				return $publication->getTitles();
			}
			return $publication->getLocalizedTitle($locale);
		}
		return $publication->getData('title');
	}

	/**
	 * Set title.
	 * @param $title string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setTitle($title, $locale) {
		$this->setData('title', $title, $locale);
	}

	/**
	 * Get the localized version of the subtitle
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedSubtitle() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('subtitle');
	}

	/**
	 * Get the subtitle for a given locale
	 * @param $locale string
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getSubtitle($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('subtitle', $locale);
	}

	/**
	 * Set the subtitle for a locale
	 * @param $subtitle string
	 * @param $locale string
	 * @deprecated 3.2.0.0
	 */
	function setSubtitle($subtitle, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$this->setData('subtitle', $subtitle, $locale);
		}
	}

	/**
	 * Get the submission full title (with prefix, title
	 * and subtitle).
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedFullTitle() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedFullTitle();
	}

	/**
	 * Get the submission full title (with prefix, title
	 * and subtitle).
	 * @param $locale string Locale to fetch data in.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getFullTitle($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		if ($locale) {
			return $publication->getLocalizedFullTitle($locale);
		}
		return $publication->getFullTitles();
	}

	/**
	 * Get "localized" submission prefix (if applicable).
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedPrefix() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('prefix');
	}

	/**
	 * Get prefix.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getPrefix($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('prefix', $locale);
	}

	/**
	 * Set prefix.
	 * @param $prefix string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setPrefix($prefix, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('prefix', $prefix, $locale);
		}
	}

	/**
	 * Get "localized" submission abstract (if applicable).
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedAbstract() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('abstract');
	}

	/**
	 * Get abstract.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getAbstract($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('abstract', $locale);
	}

	/**
	 * Set abstract.
	 * @param $abstract string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setAbstract($abstract, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('abstract', $abstract, $locale);
		}
	}

	/**
	 * Return the localized discipline
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedDiscipline() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('discipline');
	}

	/**
	 * Get discipline
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getDiscipline($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('discipline', $locale);
	}

	/**
	 * Set discipline
	 * @param $discipline string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setDiscipline($discipline, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('discipline', $discipline, $locale);
		}
	}

	/**
	 * Return the localized subject
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedSubject() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('subject');
	}

	/**
	 * Get subject.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getSubject($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('subjects', $locale);
	}

	/**
	 * Set subject.
	 * @param $subject string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setSubject($subject, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('subjects', $subject, $locale);
		}
	}

	/**
	 * Return the localized coverage
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedCoverage() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('coverage');
	}

	/**
	 * Get coverage.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getCoverage($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('coverage', $locale);
	}

	/**
	 * Set coverage.
	 * @param $coverage string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setCoverage($coverage, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('coverage', $coverage, $locale);
		}
	}

	/**
	 * Return the localized type (method/approach)
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedType() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('type');
	}

	/**
	 * Get type (method/approach).
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getType($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('type', $locale);
	}

	/**
	 * Set type (method/approach).
	 * @param $type string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setType($type, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('type', $type, $locale);
		}
	}

	/**
	 * Get rights.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getRights($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('rights', $locale);
	}

	/**
	 * Set rights.
	 * @param $rights string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setRights($rights, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('rights', $rights, $locale);
		}
	}

	/**
	 * Get source.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getSource($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('source', $locale);
	}

	/**
	 * Set source.
	 * @param $source string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setSource($source, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('source', $source, $locale);
		}
	}

	/**
	 * Get language.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLanguage() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('languages');
	}

	/**
	 * Set language.
	 * @param $language string
	 * @deprecated 3.2.0.0
	 */
	function setLanguage($language) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('languages', $language);
		}
	}

	/**
	 * Return the localized sponsor
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getLocalizedSponsor() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getLocalizedData('sponsor');
	}

	/**
	 * Get sponsor.
	 * @param $locale
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getSponsor($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('sponsor', $locale);
	}

	/**
	 * Set sponsor.
	 * @param $sponsor string
	 * @param $locale
	 * @deprecated 3.2.0.0
	 */
	function setSponsor($sponsor, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('sponsor', $sponsor, $locale);
		}
	}

	/**
	 * Get the copyright notice for a given locale
	 * @param $locale string
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getCopyrightNotice($locale) {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return $locale ? '' : [];
		}
		return $publication->getData('copyrightNotice', $locale);
	}

	/**
	 * Set the copyright notice for a locale
	 * @param $copyrightNotice string
	 * @param $locale string
	 * @deprecated 3.2.0.0
	 */
	function setCopyrightNotice($copyrightNotice, $locale) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('copyrightNotice', $copyrightNotice, $locale);
		}
	}

	/**
	 * Get citations.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getCitations() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('citationsRaw');
	}

	/**
	 * Set citations.
	 * @param $citations string
	 * @deprecated 3.2.0.0
	 */
	function setCitations($citations) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('citationsRaw', $citations);
		}
	}

	/**
	 * Get submission date.
	 * @return date
	 * @deprecated 3.2.0.0
	 */
	function getDateSubmitted() {
		return $this->getData('dateSubmitted');
	}

	/**
	 * Set submission date.
	 * @param $dateSubmitted date
	 * @deprecated 3.2.0.0
	 */
	function setDateSubmitted($dateSubmitted) {
		$this->setData('dateSubmitted', $dateSubmitted);
	}

	/**
	 * Get the date of the last status modification.
	 * @return date
	 * @deprecated 3.2.0.0
	 */
	function getDateStatusModified() {
		return $this->getData('dateStatusModified');
	}

	/**
	 * Set the date of the last status modification.
	 * @param $dateModified date
	 * @deprecated 3.2.0.0
	 */
	function setDateStatusModified($dateModified) {
		$this->setData('dateStatusModified', $dateModified);
	}

	/**
	 * Get the date of the last modification.
	 * @return date
	 * @deprecated 3.2.0.0
	 */
	function getLastModified() {
		return $this->getData('lastModified');
	}

	/**
	 * Set the date of the last modification.
	 * @param $dateModified date
	 * @deprecated 3.2.0.0
	 */
	function setLastModified($dateModified) {
		$this->setData('lastModified', $dateModified);
	}

	/**
	 * Get submission status.
	 * @return int
	 * @deprecated 3.2.0.0
	 */
	function getStatus() {
		return $this->getData('status');
	}

	/**
	 * Set submission status.
	 * @param $status int
	 * @deprecated 3.2.0.0
	 */
	function setStatus($status) {
		$this->setData('status', $status);
	}

	/**
	 * Get submission progress (most recently completed submission step).
	 * @return int
	 * @deprecated 3.2.0.0
	 */
	function getSubmissionProgress() {
		return $this->getData('submissionProgress');
	}

	/**
	 * Set submission progress.
	 * @param $submissionProgress int
	 * @deprecated 3.2.0.0
	 */
	function setSubmissionProgress($submissionProgress) {
		$this->setData('submissionProgress', $submissionProgress);
	}

	/**
	 * get pages
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getPages() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('pages');
	}

	/**
	 * Get starting page of a submission.  Note the return type of string - this is not to be used for page counting.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getStartingPage() {
		$publication = $this->getCurrentPublication();
		return $publication ? $publication->getStartingPage() : '';
	}

	/**
	 * Get ending page of a submission.  Note the return type of string - this is not to be used for page counting.
	 * @return string
	 * @deprecated 3.2.0.0
	 */
	function getEndingPage() {
		$publication = $this->getCurrentPublication();
		return $publication ? $publication->getEndingPage() : '';
	}

	/**
	 * get pages as a nested array of page ranges
	 * for example, pages of "pp. ii-ix, 9,15-18,a2,b2-b6" will return array( array(0 => 'ii', 1, => 'ix'), array(0 => '9'), array(0 => '15', 1 => '18'), array(0 => 'a2'), array(0 => 'b2', 1 => 'b6') )
	 * @return array
	 * @deprecated 3.2.0.0
	 */
	function getPageArray() {
		$publication = $this->getCurrentPublication();
		return $publication ? $publication->getPageArray() : '';
	}

	/**
	 * set pages
	 * @param $pages string
	 * @deprecated 3.2.0.0
	 */
	function setPages($pages) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('pages', $pages);
		}
	}

	/**
	 * Get the submission's current publication stage ID
	 * @return int
	 * @deprecated 3.2.0.0
	 */
	function getStageId() {
		return $this->getData('stageId');
	}

	/**
	 * Set the submission's current publication stage ID
	 * @param $stageId int
	 * @deprecated 3.2.0.0
	 */
	function setStageId($stageId) {
		$this->setData('stageId', $stageId);
	}

	/**
	 * Get date published.
	 * @return date
	 * @deprecated 3.2.0.0
	 */
	function getDatePublished() {
		$publication = $this->getCurrentPublication();
		if (!$publication) {
			return '';
		}
		return $publication->getData('datePublished');
	}

	/**
	 * Set date published.
	 * @param $datePublished date
	 * @deprecated 3.2.0.0
	 */
	function setDatePublished($datePublished) {
		$publication = $this->getCurrentPublication();
		if ($publication) {
			$publication->setData('datePublished', $datePublished);
		}
	}

	/**
	 * Determines whether or not the license for copyright on this submission is
	 * a Creative Commons license or not.
	 * @return boolean
	 * @deprecated 3.2.0.0
	 */
	function isCCLicense() {
		$publication = $this->getCurrentPublication();
		return $publication && $publication->isCCLicense();
	}
}

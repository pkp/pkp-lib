<?php

/**
 * @defgroup submission Submission
 * The abstract concept of a submission is implemented here, and extended
 * in each application with the specifics of that content model, i.e.
 * Articles in OJS, Papers in OCS, and Monographs in OMP.
 */

/**
 * @file classes/submission/PKPSubmission.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmission
 * @ingroup submission
 *
 * @see DAO
 *
 * @brief The Submission class implements the abstract data model of a
 * scholarly submission.
 */

namespace PKP\submission;

use APP\author\Author;
use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\statistics\StatisticsHelper;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\facades\Locale;

abstract class PKPSubmission extends \PKP\core\DataObject
{
    // Submission status constants
    public const STATUS_QUEUED = 1;
    public const STATUS_PUBLISHED = 3;
    public const STATUS_DECLINED = 4;
    public const STATUS_SCHEDULED = 5;
    public const STATUS_CANCELED = 6;

    // License settings (internal use only)
    public const PERMISSIONS_FIELD_LICENSE_URL = 1;
    public const PERMISSIONS_FIELD_COPYRIGHT_HOLDER = 2;
    public const PERMISSIONS_FIELD_COPYRIGHT_YEAR = 3;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Switch on meta-data adapter support.
        $this->setHasLoadableAdapters(true);

        parent::__construct();
    }

    /**
     * Return the "best" article ID -- If a urlPath is set,
     * use it; otherwise use the internal article Id.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getBestId()
    {
        $currentPublication = $this->getCurrentPublication();
        if (!$currentPublication) {
            return $this->getId();
        }
        return $currentPublication->getData('urlPath') ?? $this->getId();
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
    public function getCurrentPublication()
    {
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
     * Get the first publication
     *
     * Returns the first created publication
     *
     * @return Publication|null
     */
    public function getOriginalPublication()
    {
        $publishedPublications = $this->getPublishedPublications();
        if (empty($publishedPublications)) {
            return null;
        }
        return array_reduce($publishedPublications, function ($a, $b) {
            return $a && $a->getId() < $b->getId() ? $a : $b;
        });
    }

    /**
     * Get the latest publication
     *
     * Returns the most recently created publication by ID
     *
     * @return Publication|null
     */
    public function getLatestPublication()
    {
        $publications = $this->getData('publications'); /** @var \Illuminate\Support\Enumerable $publications */
        if (empty($publications)) {
            return null;
        }

        return $publications->reduce(function ($a, $b) {
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
    public function getPublishedPublications()
    {
        $publications = $this->getData('publications'); /** @var LazyCollection $publications */
        if ($publications->isEmpty()) {
            return [];
        }

        return $publications->filter(function ($publication) {
            return $publication->getData('status') === self::STATUS_PUBLISHED;
        })->toArray();
    }

    /**
     * Stamp the date of the last modification to the current time.
     */
    public function stampModified()
    {
        return $this->setData('lastModified', Core::getCurrentDate());
    }

    /**
     * Stamp the date of the last recorded activity to the current time
     */
    public function stampLastActivity()
    {
        return $this->setData('dateLastActivity', Core::getCurrentDate());
    }

    /**
     * Stamp the date of the last status modification to the current time.
     *
     * @deprecated 3.2.0.0
     */
    public function stampStatusModified()
    {
        return $this->stampLastActivity();
    }

    /**
     * Get a map for status constant to locale key.
     *
     * @return array
     */
    public function &getStatusMap()
    {
        static $statusMap = [
            self::STATUS_QUEUED => 'submissions.queued',
            self::STATUS_PUBLISHED => 'submission.status.published',
            self::STATUS_DECLINED => 'submission.status.declined',
            self::STATUS_SCHEDULED => 'submission.status.scheduled',
            self::STATUS_CANCELED => 'submission.status.canceled',
        ];
        return $statusMap;
    }

    /**
     * Get a locale key for the paper's current status.
     *
     * @return string
     */
    public function getStatusKey()
    {
        $statusMap = & $this->getStatusMap();
        return $statusMap[$this->getData('status')];
    }

    /**
     * @copydoc \PKP\core\DataObject::getDAO()
     */
    public function getDAO(): \APP\submission\DAO
    {
        return Repo::submission()->dao;
    }

    //
    // Abstract methods.
    //
    /**
     * Get section id.
     *
     * @return int
     */
    abstract public function getSectionId();

    /**
     * Get the value of a license field from the containing context.
     *
     * @param string $locale Locale code
     * @param int $field PERMISSIONS_FIELD_...
     *
     * @return string|null
     */
    abstract public function _getContextLicenseFieldValue($locale, $field);

    //
    // Deprecated methods
    //

    /**
     * Get the localized copyright holder for the current publication
     *
     * @param string $preferredLocale Preferred locale code
     *
     * @return string Localized copyright holder.
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedCopyrightHolder($preferredLocale = null)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('copyrightHolder', $preferredLocale);
    }

    /**
     * Get the context ID for the current publication
     *
     * @return int
     *
     * @deprecated 3.2.0.0
     */
    public function getContextId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set the context ID for the current publication
     *
     * @param int $contextId
     *
     * @deprecated 3.2.0.0
     */
    public function setContextId($contextId)
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get localized data for this object.
     *
     * It selects the locale in the following order:
     * - $preferredLocale
     * - the user's current locale
     * - the submission's primary locale
     * - the first locale we find data for
     *
     * @param string $key
     * @param string $preferredLocale
     */
    public function getLocalizedData($key, $preferredLocale = null)
    {
        // 1. Preferred locale
        if ($preferredLocale && $this->getData($key, $preferredLocale)) {
            return $this->getData($key, $preferredLocale);
        }
        // 2. User's current locale
        if (!empty($this->getData($key, Locale::getLocale()))) {
            return $this->getData($key, Locale::getLocale());
        }
        // 3. Submission's primary locale
        if (!empty($this->getData($key, $this->getData('locale')))) {
            return $this->getData($key, $this->getData('locale'));
        }
        // 4. The first locale we can find data for
        $data = $this->getData($key, null);
        foreach ((array) $data as $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get stored public ID of the submission.
     *
     * @param string $pubIdType @literal One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
     *
     * @return int
     *
     * @deprecated 3.2.0.0
     */
    public function getStoredPubId($pubIdType)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getStoredPubId($pubIdType);
    }

    /**
     * Set the stored public ID of the submission.
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     *
     * @deprecated 3.2.0.0
     */
    public function setStoredPubId($pubIdType, $pubId)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            if ($pubIdType == 'doi') {
                $publication->setStoredPubId($pubIdType, $pubId);
            } else {
                $this->setData('pub-id::' . $pubIdType, $pubId);
            }
        }
    }

    /**
     * Get stored copyright holder for the submission.
     *
     * @param string $locale locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCopyrightHolder($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('copyrightHolder', $locale);
    }

    /**
     * Set the stored copyright holder for the submission.
     *
     * @param string $copyrightHolder Copyright holder
     * @param string $locale locale
     *
     * @deprecated 3.2.0.0
     */
    public function setCopyrightHolder($copyrightHolder, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('copyrightHolder', $copyrightHolder, $locale);
        }
    }

    /**
     * Get stored copyright year for the submission.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCopyrightYear()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('copyrightYear');
    }

    /**
     * Set the stored copyright year for the submission.
     *
     * @param string $copyrightYear Copyright holder
     *
     * @deprecated 3.2.0.0
     */
    public function setCopyrightYear($copyrightYear)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('copyrightYear', $copyrightYear);
        }
    }

    /**
     * Get stored license URL for the submission content.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLicenseURL()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('licenseUrl');
    }

    /**
     * Set the stored license URL for the submission content.
     *
     * @deprecated 3.2.0.0
     */
    public function setLicenseURL($licenseURL)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('licenseUrl', $licenseURL);
        }
    }

    /**
     * Set option selection indicating if author should be hidden in issue ToC.
     *
     * @param int $hideAuthor AUTHOR_TOC_...
     *
     * @deprecated 3.2.0.0
     */
    public function setHideAuthor($hideAuthor)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('hideAuthor', $hideAuthor);
        }
    }

    /**
     * Get the locale of the submission.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocale()
    {
        return $this->getData('locale');
    }

    /**
     * Set the locale of the submission.
     *
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setLocale($locale)
    {
        $this->setData('locale', $locale);
    }

    /**
     * Get "localized" submission title (if applicable).
     *
     * @param string $preferredLocale
     * @param bool $includePrefix
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedTitle($preferredLocale = null, $includePrefix = true)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedTitle($preferredLocale);
    }

    /**
     * Get title.
     *
     * @param string $locale
     * @param bool $includePrefix
     *
     * @return string|array
     *
     * @deprecated 3.2.0.0
     */
    public function getTitle($locale, $includePrefix = true)
    {
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
     *
     * @param string $title
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setTitle($title, $locale)
    {
        $this->setData('title', $title, $locale);
    }

    /**
     * Get the localized version of the subtitle
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedSubtitle()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('subtitle');
    }

    /**
     * Get the subtitle for a given locale
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getSubtitle($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('subtitle', $locale);
    }

    /**
     * Set the subtitle for a locale
     *
     * @param string $subtitle
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setSubtitle($subtitle, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $this->setData('subtitle', $subtitle, $locale);
        }
    }

    /**
     * Get the submission full title (with prefix, title
     * and subtitle).
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedFullTitle()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedFullTitle();
    }

    /**
     * Get the submission full title (with prefix, title
     * and subtitle).
     *
     * @param string $locale Locale to fetch data in.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getFullTitle($locale)
    {
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
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedPrefix()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('prefix');
    }

    /**
     * Get prefix.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getPrefix($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('prefix', $locale);
    }

    /**
     * Set prefix.
     *
     * @param string $prefix
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setPrefix($prefix, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('prefix', $prefix, $locale);
        }
    }

    /**
     * Get "localized" submission abstract (if applicable).
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedAbstract()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('abstract');
    }

    /**
     * Get abstract.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getAbstract($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('abstract', $locale);
    }

    /**
     * Set abstract.
     *
     * @param string $abstract
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setAbstract($abstract, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('abstract', $abstract, $locale);
        }
    }

    /**
     * Return the localized discipline
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedDiscipline()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('discipline');
    }

    /**
     * Get discipline
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getDiscipline($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('discipline', $locale);
    }

    /**
     * Set discipline
     *
     * @param string $discipline
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setDiscipline($discipline, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('discipline', $discipline, $locale);
        }
    }

    /**
     * Return the localized subject
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedSubject()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('subject');
    }

    /**
     * Get subject.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getSubject($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('subjects', $locale);
    }

    /**
     * Set subject.
     *
     * @param string $subject
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setSubject($subject, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('subjects', $subject, $locale);
        }
    }

    /**
     * Return the localized coverage
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedCoverage()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('coverage');
    }

    /**
     * Get coverage.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCoverage($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('coverage', $locale);
    }

    /**
     * Set coverage.
     *
     * @param string $coverage
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setCoverage($coverage, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('coverage', $coverage, $locale);
        }
    }

    /**
     * Return the localized type (method/approach)
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedType()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('type');
    }

    /**
     * Get type (method/approach).
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getType($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('type', $locale);
    }

    /**
     * Set type (method/approach).
     *
     * @param string $type
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setType($type, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('type', $type, $locale);
        }
    }

    /**
     * Get rights.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getRights($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('rights', $locale);
    }

    /**
     * Set rights.
     *
     * @param string $rights
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setRights($rights, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('rights', $rights, $locale);
        }
    }

    /**
     * Get source.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getSource($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('source', $locale);
    }

    /**
     * Set source.
     *
     * @param string $source
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setSource($source, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('source', $source, $locale);
        }
    }

    /**
     * Get language.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLanguage()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('languages');
    }

    /**
     * Set language.
     *
     * @param string $language
     *
     * @deprecated 3.2.0.0
     */
    public function setLanguage($language)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('languages', $language);
        }
    }

    /**
     * Return the localized sponsor
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getLocalizedSponsor()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getLocalizedData('sponsor');
    }

    /**
     * Get sponsor.
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getSponsor($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('sponsor', $locale);
    }

    /**
     * Set sponsor.
     *
     * @param string $sponsor
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setSponsor($sponsor, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('sponsor', $sponsor, $locale);
        }
    }

    /**
     * Get the copyright notice for a given locale
     *
     * @param string $locale
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCopyrightNotice($locale)
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return $locale ? '' : [];
        }
        return $publication->getData('copyrightNotice', $locale);
    }

    /**
     * Set the copyright notice for a locale
     *
     * @param string $copyrightNotice
     * @param string $locale
     *
     * @deprecated 3.2.0.0
     */
    public function setCopyrightNotice($copyrightNotice, $locale)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('copyrightNotice', $copyrightNotice, $locale);
        }
    }

    /**
     * Get citations.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getCitations()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('citationsRaw');
    }

    /**
     * Set citations.
     *
     * @param string $citations
     *
     * @deprecated 3.2.0.0
     */
    public function setCitations($citations)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('citationsRaw', $citations);
        }
    }

    /**
     * Get submission date.
     *
     * @return date
     *
     * @deprecated 3.2.0.0
     */
    public function getDateSubmitted()
    {
        return $this->getData('dateSubmitted');
    }

    /**
     * Set submission date.
     *
     * @param date $dateSubmitted
     *
     * @deprecated 3.2.0.0
     */
    public function setDateSubmitted($dateSubmitted)
    {
        $this->setData('dateSubmitted', $dateSubmitted);
    }

    /**
     * Get the date of the last status modification.
     *
     * @return date
     *
     * @deprecated 3.2.0.0
     */
    public function getDateStatusModified()
    {
        return $this->getData('dateLastActivity');
    }

    /**
     * Set the date of the last status modification.
     *
     * @param date $dateModified
     *
     * @deprecated 3.2.0.0
     */
    public function setDateStatusModified($dateModified)
    {
        $this->setData('dateLastActivity', $dateModified);
    }

    /**
     * Get the date of the last modification.
     *
     * @return date
     *
     * @deprecated 3.2.0.0
     */
    public function getLastModified()
    {
        return $this->getData('lastModified');
    }

    /**
     * Set the date of the last modification.
     *
     * @param date $dateModified
     *
     * @deprecated 3.2.0.0
     */
    public function setLastModified($dateModified)
    {
        $this->setData('lastModified', $dateModified);
    }

    /**
     * Get submission status.
     *
     * @return int
     *
     * @deprecated 3.2.0.0
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * Set submission status.
     *
     * @param int $status
     *
     * @deprecated 3.2.0.0
     */
    public function setStatus($status)
    {
        $this->setData('status', $status);
    }

    /**
     * Get submission progress (most recently completed submission step).
     *
     * @return int
     *
     * @deprecated 3.2.0.0
     */
    public function getSubmissionProgress()
    {
        return $this->getData('submissionProgress');
    }

    /**
     * Set submission progress.
     *
     * @param int $submissionProgress
     *
     * @deprecated 3.2.0.0
     */
    public function setSubmissionProgress($submissionProgress)
    {
        $this->setData('submissionProgress', $submissionProgress);
    }

    /**
     * get pages
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getPages()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('pages');
    }

    /**
     * Get starting page of a submission.  Note the return type of string - this is not to be used for page counting.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getStartingPage()
    {
        $publication = $this->getCurrentPublication();
        return $publication ? $publication->getStartingPage() : '';
    }

    /**
     * Get ending page of a submission.  Note the return type of string - this is not to be used for page counting.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getEndingPage()
    {
        $publication = $this->getCurrentPublication();
        return $publication ? $publication->getEndingPage() : '';
    }

    /**
     * get pages as a nested array of page ranges
     * for example, pages of "pp. ii-ix, 9,15-18,a2,b2-b6" will return array( array(0 => 'ii', 1, => 'ix'), array(0 => '9'), array(0 => '15', 1 => '18'), array(0 => 'a2'), array(0 => 'b2', 1 => 'b6') )
     *
     * @return array
     *
     * @deprecated 3.2.0.0
     */
    public function getPageArray()
    {
        $publication = $this->getCurrentPublication();
        return $publication ? $publication->getPageArray() : '';
    }

    /**
     * set pages
     *
     * @param string $pages
     *
     * @deprecated 3.2.0.0
     */
    public function setPages($pages)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('pages', $pages);
        }
    }

    /**
     * Get the submission's current publication stage ID
     *
     * @return int
     *
     * @deprecated 3.2.0.0
     */
    public function getStageId()
    {
        return $this->getData('stageId');
    }

    /**
     * Set the submission's current publication stage ID
     *
     * @param int $stageId
     *
     * @deprecated 3.2.0.0
     */
    public function setStageId($stageId)
    {
        $this->setData('stageId', $stageId);
    }

    /**
     * Get date published.
     *
     * @return date
     *
     * @deprecated 3.2.0.0
     */
    public function getDatePublished()
    {
        $publication = $this->getCurrentPublication();
        if (!$publication) {
            return '';
        }
        return $publication->getData('datePublished');
    }

    /**
     * Set date published.
     *
     * @param date $datePublished
     *
     * @deprecated 3.2.0.0
     */
    public function setDatePublished($datePublished)
    {
        $publication = $this->getCurrentPublication();
        if ($publication) {
            $publication->setData('datePublished', $datePublished);
        }
    }

    /**
     * Determines whether or not the license for copyright on this submission is
     * a Creative Commons license or not.
     *
     * @return bool
     *
     * @deprecated 3.2.0.0
     */
    public function isCCLicense()
    {
        $publication = $this->getCurrentPublication();
        return $publication && $publication->isCCLicense();
    }

    /**
     * Get views of the submission.
     *
     * @deprecated 3.4
     *
     * @return int
     */
    public function getViews()
    {
        $filters = [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),
            'contextIds' => [$this->getData('contextId')],
            'submissionIds' => [$this->getId()],
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION],
        ];
        $metrics = Services::get('publicationStats')
            ->getQueryBuilder($filters)
            ->getSum([])
            ->value('metric');
        return $metrics ? $metrics : 0;
    }

    /**
     * Display the object in Import/Export results
     *
     * @return string A string that Identifies the object
     */
    public function getUIDisplayString()
    {
        return __('plugins.importexport.submission.cli.display', ['submissionId' => $this->getId(), 'submissionTitle' => $this->getLocalizedTitle()]);
    }
}

// Expose global constants unless operating in strict mode.
if (!PKP_STRICT_MODE) {
    foreach ([
        'STATUS_QUEUED', 'STATUS_PUBLISHED', 'STATUS_DECLINED', 'STATUS_SCHEDULED', 'STATUS_CANCELED',
        'PERMISSIONS_FIELD_LICENSE_URL', 'PERMISSIONS_FIELD_COPYRIGHT_HOLDER', 'PERMISSIONS_FIELD_COPYRIGHT_YEAR'
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('\PKP\submission\PKPSubmission::' . $constantName));
        }
    }
}

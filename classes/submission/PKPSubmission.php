<?php

/**
 * @defgroup submission Submission
 * The abstract concept of a submission is implemented here, and extended
 * in each application with the specifics of that content model, i.e.
 * Articles in OJS, Preprints in OPS, and Monographs in OMP.
 */

/**
 * @file classes/submission/PKPSubmission.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmission
 *
 * @ingroup submission
 *
 * @see DAO
 *
 * @brief The Submission class implements the abstract data model of a
 * scholarly submission.
 */

namespace PKP\submission;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\DAO;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\facades\Locale;
use PKP\i18n\LocaleMetadata;

/**
 * @extends \PKP\core\DataObject<DAO>
 */
abstract class PKPSubmission extends \PKP\core\DataObject
{
    // Submission status constants
    public const STATUS_QUEUED = 1;
    public const STATUS_PUBLISHED = 3;
    public const STATUS_DECLINED = 4;
    public const STATUS_SCHEDULED = 5;

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
     */
    public function getBestId()
    {
        return strlen($urlPath = (string) $this->getCurrentPublication()?->getData('urlPath')) ? $urlPath : $this->getId();
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
        $statusMap = &$this->getStatusMap();
        return $statusMap[$this->getData('status')];
    }

    /**
     * @copydoc \PKP\core\DataObject::getDAO()
     */
    public function getDAO(): DAO
    {
        return Repo::submission()->dao;
    }

    /**
     * Get metadata language names from publications
     */
    public function getPublicationLanguageNames(): array
    {
        return (($l = $this->getData('locale')) ? [$l => Locale::getSubmissionLocaleDisplayNames([$l])[$l]] : [])
            + collect($this->getData('publications'))
                ->flatMap(fn (Publication $p): array => $p->getLanguageNames())
                ->toArray();
    }

    /**
     * Get metadata languages from publications
     */
    public function getPublicationLanguages(?array ...$additionalLanguages): array
    {
        return collect([$this->getData('locale')])
            ->concat($this->getData('publications')->map(fn (Publication $p): array => $p->getLanguages()))
            ->concat($additionalLanguages)
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->toArray();
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
     * Get the default/fall back locale the values should exist for
     */
    public function getDefaultLocale(): ?string
    {
        return $this->getData('locale');
    }

    /**
     * Get stored public ID of the submission.
     *
     * @param string $pubIdType @literal One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
     *
     * @return string
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
     * Display the object in Import/Export results
     *
     * @return string A string that Identifies the object
     */
    public function getUIDisplayString()
    {
        return __('plugins.importexport.submission.cli.display', ['submissionId' => $this->getId(), 'submissionTitle' => $this->getCurrentPublication()->getLocalizedTitle()]);
    }
}

// Expose global constants unless operating in strict mode.
if (!PKP_STRICT_MODE) {
    foreach ([
        'STATUS_QUEUED', 'STATUS_PUBLISHED', 'STATUS_DECLINED', 'STATUS_SCHEDULED',
        'PERMISSIONS_FIELD_LICENSE_URL', 'PERMISSIONS_FIELD_COPYRIGHT_HOLDER', 'PERMISSIONS_FIELD_COPYRIGHT_YEAR'
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('\PKP\submission\PKPSubmission::' . $constantName));
        }
    }
}

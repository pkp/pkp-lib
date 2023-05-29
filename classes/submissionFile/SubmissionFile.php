<?php

/**
 * @file classes/submissionFile/SubmissionFile.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFile
 *
 * @ingroup submission
 *
 * @brief Submission file class.
 */

namespace PKP\submissionFile;

use APP\facades\Repo;
use PKP\facades\Locale;

/**
 * @extends \PKP\core\DataObject<DAO>
 */
class SubmissionFile extends \PKP\core\DataObject
{
    // Define the file stage identifiers.
    public const SUBMISSION_FILE_SUBMISSION = 2;
    public const SUBMISSION_FILE_NOTE = 3;
    public const SUBMISSION_FILE_REVIEW_FILE = 4;
    public const SUBMISSION_FILE_REVIEW_ATTACHMENT = 5;
    public const SUBMISSION_FILE_FINAL = 6;
    public const SUBMISSION_FILE_COPYEDIT = 9;
    public const SUBMISSION_FILE_PROOF = 10;
    public const SUBMISSION_FILE_PRODUCTION_READY = 11;
    public const SUBMISSION_FILE_ATTACHMENT = 13;
    public const SUBMISSION_FILE_REVIEW_REVISION = 15;
    public const SUBMISSION_FILE_DEPENDENT = 17;
    public const SUBMISSION_FILE_QUERY = 18;
    public const SUBMISSION_FILE_INTERNAL_REVIEW_FILE = 19;
    public const SUBMISSION_FILE_INTERNAL_REVIEW_REVISION = 20;

    public const INTERNAL_REVIEW_STAGES = [
        SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
        SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
    ];

    public const EXTERNAL_REVIEW_STAGES = [
        SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
        SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
    ];

    /**
     * Get a piece of data for this object, localized to the current
     * locale if possible.
     *
     * @param string $key
     * @param string $preferredLocale
     */
    public function &getLocalizedData($key, $preferredLocale = null)
    {
        if (is_null($preferredLocale)) {
            $preferredLocale = Locale::getLocale();
        }
        $localePrecedence = [$preferredLocale, $this->getData('locale')];
        foreach ($localePrecedence as $locale) {
            if (empty($locale)) {
                continue;
            }
            $value = & $this->getData($key, $locale);
            if (!empty($value)) {
                return $value;
            }
            unset($value);
        }

        // Fallback: Get the first available piece of data.
        $data = & $this->getData($key, null);
        foreach ((array) $data as $dataValue) {
            if (!empty($dataValue)) {
                return $dataValue;
            }
        }

        // No data available; return null.
        unset($data);
        $data = null;
        return $data;
    }

    /**
     * Get the locale of the submission.
     * This is not properly a property of the submission file
     * (e.g. it won't be persisted to the DB with the update function)
     * It helps solve submission locale requirement for file's multilingual metadata
     *
     * @deprecated 3.3.0.0
     *
     * @return string
     */
    public function getSubmissionLocale()
    {
        return $this->getData('locale');
    }

    /**
     * Set the locale of the submission.
     * This is not properly a property of the submission file
     * (e.g. it won't be persisted to the DB with the update function)
     * It helps solve submission locale requirement for file's multilingual metadata
     *
     * @deprecated 3.3.0.0
     *
     * @param string $submissionLocale
     */
    public function setSubmissionLocale($submissionLocale)
    {
        $this->setData('locale', $submissionLocale);
    }

    /**
     * Get stored public ID of the file.
     *
     * @param string $pubIdType @literal One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>). @endliteral
     *
     * @return string
     */
    public function getStoredPubId($pubIdType)
    {
        if ($pubIdType === 'doi') {
            return $this->getDoi();
        } else {
            return $this->getData('pub-id::' . $pubIdType);
        }
    }

    /**
     * Set the stored public ID of the file.
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     */
    public function setStoredPubId($pubIdType, $pubId)
    {
        if ($pubIdType == 'doi') {
            if ($doiObject = $this->getData('doiObject')) {
                Repo::doi()->edit($doiObject, ['doi' => $pubId]);
            } else {
                $newDoiObject = Repo::doi()->newDataObject(
                    [
                        'doi' => $pubId,
                        'contextId' => Repo::submission()->get($this->getData('submissionId'))->getData('contextId')
                    ]
                );
                $doiId = Repo::doi()->add($newDoiObject);
                $this->setData('doiId', $doiId);
            }
        } else {
            $this->setData('pub-id::' . $pubIdType, $pubId);
        }
    }

    /**
     * Get price of submission file.
     * A null return indicates "not available"; 0 is free.
     *
     * @return float|null
     */
    public function getDirectSalesPrice()
    {
        return $this->getData('directSalesPrice');
    }

    /**
     * Set direct sales price.
     * A null return indicates "not available"; 0 is free.
     *
     * @param float|null $directSalesPrice
     */
    public function setDirectSalesPrice($directSalesPrice)
    {
        $this->setData('directSalesPrice', $directSalesPrice);
    }

    /**
     * Get sales type of submission file.
     *
     * @return string
     */
    public function getSalesType()
    {
        return $this->getData('salesType');
    }

    /**
     * Set sales type.
     *
     * @param string $salesType
     */
    public function setSalesType($salesType)
    {
        $this->setData('salesType', $salesType);
    }

    /**
     * Set the genre id of this file (i.e. referring to Manuscript, Index, etc)
     * Foreign key into genres table
     *
     * @deprecated 3.3.0.0
     *
     * @param int $genreId
     */
    public function setGenreId($genreId)
    {
        $this->setData('genreId', $genreId);
    }

    /**
     * Get the genre id of this file (i.e. referring to Manuscript, Index, etc)
     * Foreign key into genres table
     *
     * @deprecated 3.3.0.0
     *
     * @return int
     */
    public function getGenreId()
    {
        return $this->getData('genreId');
    }

    /**
     * Return the "best" file ID -- If a public ID is set,
     * use it; otherwise use the internal ID and revision.
     *
     * @return string
     */
    public function getBestId()
    {
        return strlen($publisherId = (string) $this->getStoredPubId('publisher-id')) ? $publisherId : $this->getId();
    }

    /**
     * Get file stage of the file.
     *
     * @deprecated 3.3.0.0
     *
     * @return int SubmissionFile::SUBMISSION_FILE_...
     */
    public function getFileStage()
    {
        return $this->getData('fileStage');
    }

    /**
     * Set file stage of the file.
     *
     * @deprecated 3.3.0.0
     *
     * @param int $fileStage SubmissionFile::SUBMISSION_FILE_...
     */
    public function setFileStage($fileStage)
    {
        $this->setData('fileStage', $fileStage);
    }

    /**
     * Get modified date of file.
     *
     * @deprecated 3.3.0.0
     *
     * @return string
     */

    public function getDateModified()
    {
        return $this->getData('updatedAt');
    }

    /**
     * Set modified date of file.
     *
     * @deprecated 3.3.0.0
     *
     * @param string $updatedAt
     */

    public function setDateModified($updatedAt)
    {
        return $this->setData('updatedAt', $updatedAt);
    }

    /**
     * Get viewable.
     *
     * @deprecated 3.3.0.0
     *
     * @return bool
     */
    public function getViewable()
    {
        return $this->getData('viewable');
    }


    /**
     * Set viewable.
     *
     * @deprecated 3.3.0.0
     *
     * @param bool $viewable
     */
    public function setViewable($viewable)
    {
        return $this->setData('viewable', $viewable);
    }

    /**
     * Set the uploader's user id.
     *
     * @deprecated 3.3.0.0
     *
     * @param int $uploaderUserId
     */
    public function setUploaderUserId($uploaderUserId)
    {
        $this->setData('uploaderUserId', $uploaderUserId);
    }

    /**
     * Get the uploader's user id.
     *
     * @deprecated 3.3.0.0
     *
     * @return int
     */
    public function getUploaderUserId()
    {
        return $this->getData('uploaderUserId');
    }

    /**
     * Get type that is associated with this file.
     *
     * @deprecated 3.3.0.0
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set type that is associated with this file.
     *
     * @deprecated 3.3.0.0
     *
     * @param int $assocType
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get the submission chapter id.
     *
     * @deprecated 3.3.0.0
     *
     * @return int
     */
    public function getChapterId()
    {
        return $this->getData('chapterId');
    }

    /**
     * Set the submission chapter id.
     *
     * @deprecated 3.3.0.0
     *
     * @param int $chapterId
     */
    public function setChapterId($chapterId)
    {
        $this->setData('chapterId', $chapterId);
    }

    /**
     * Helper method to fetch current DOI
     *
     */
    public function getDoi(): ?string
    {
        $doiObject = $this->getData('doiObject');

        if (empty($doiObject)) {
            return null;
        } else {
            return $doiObject->getData('doi');
        }
    }

    /**
     * @copydoc \PKP\core\DataObject::getDAO()
     */
    public function getDAO(): DAO
    {
        return Repo::submissionFile()->dao;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submissionFile\SubmissionFile', '\SubmissionFile');
    foreach ([
        'SUBMISSION_FILE_SUBMISSION',
        'SUBMISSION_FILE_NOTE',
        'SUBMISSION_FILE_REVIEW_FILE',
        'SUBMISSION_FILE_REVIEW_ATTACHMENT',
        'SUBMISSION_FILE_FINAL',
        'SUBMISSION_FILE_COPYEDIT',
        'SUBMISSION_FILE_PROOF',
        'SUBMISSION_FILE_PRODUCTION_READY',
        'SUBMISSION_FILE_ATTACHMENT',
        'SUBMISSION_FILE_REVIEW_REVISION',
        'SUBMISSION_FILE_DEPENDENT',
        'SUBMISSION_FILE_QUERY',
        'SUBMISSION_FILE_INTERNAL_REVIEW_FILE',
        'SUBMISSION_FILE_INTERNAL_REVIEW_REVISION',
    ] as $constantName) {
        define($constantName, constant('\SubmissionFile::' . $constantName));
    }
}

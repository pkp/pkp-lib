<?php

/**
 * @file classes/submission/Representation.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Representation
 * @ingroup submission
 *
 * @brief A submission's representation (Publication Format, Galley, ...)
 */

namespace PKP\submission;

use APP\core\Application;

use APP\facades\Repo;

class Representation extends \PKP\core\DataObject
{
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
     * Get sequence of format in format listings for the submission.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set sequence of format in format listings for the submission.
     *
     * @param float $seq
     */
    public function setSequence($seq)
    {
        $this->setData('seq', $seq);
    }

    /**
     * Get "localized" format name (if applicable).
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get the format name (if applicable).
     *
     * @param string $locale
     *
     * @return string
     */
    public function getName($locale)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set name.
     *
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale = null)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Determines if a representation is approved or not.
     *
     * @return bool
     */
    public function getIsApproved()
    {
        return (bool) $this->getData('isApproved');
    }

    /**
     * Sets whether a representation is approved or not.
     *
     * @param bool $isApproved
     */
    public function setIsApproved($isApproved)
    {
        return $this->setData('isApproved', $isApproved);
    }

    /**
     * Returns current DOI
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
     * Get stored public ID of the submission.
     *
     * This helper function is required by PKPPubIdPlugins.
     * NB: To maintain backwards compatability, getDoi() is called from here
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     *
     * @return int
     */
    public function getStoredPubId($pubIdType)
    {
        if ($pubIdType == 'doi') {
            return $this->getDoi();
        } else {
            return $this->getData('pub-id::' . $pubIdType);
        }
    }

    /**
     * Set the stored public ID of the submission.
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     */
    public function setStoredPubId($pubIdType, $pubId)
    {
        $this->setData('pub-id::' . $pubIdType, $pubId);
    }

    /**
     * Get the remote URL at which this representation is retrievable.
     *
     * @return string
     *
     * @deprecated 3.2.0.0
     */
    public function getRemoteURL()
    {
        return $this->getData('urlRemote');
    }

    /**
     * Set the remote URL for retrieving this representation.
     *
     * @param string $remoteURL
     *
     * @deprecated 3.2.0.0
     */
    public function setRemoteURL($remoteURL)
    {
        return $this->setData('urlRemote', $remoteURL);
    }

    /**
     * Get the context id from the submission assigned to this representation.
     *
     * @return int
     */
    public function getContextId()
    {
        $publication = Repo::publication()->get($this->getData('publicationId'));
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        return $submission->getContextId();
    }

    /**
     * @copydoc \PKP\core\DataObject::getDAO()
     */
    public function getDAO()
    {
        return Application::getRepresentationDAO();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\Representation', '\Representation');
}

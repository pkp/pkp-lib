<?php

/**
 * @file plugins/importexport/native/filter/PreprintNativeXmlFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Preprint to a Native XML document.
 */

namespace APP\plugins\importexport\native\filter;

class PreprintNativeXmlFilter extends \PKP\plugins\importexport\native\filter\SubmissionNativeXmlFilter
{
    //
    // Implement template methods from PersistableFilter
    //
    /**
     * @copydoc PersistableFilter::getClassName()
     */
    public function getClassName()
    {
        return 'plugins.importexport.native.filter.PreprintNativeXmlFilter';
    }

    //
    // Submission conversion functions
    //
    /**
     * Create and return a submission node.
     *
     * @param DOMDocument $doc
     * @param Submission $submission
     *
     * @return DOMElement
     */
    public function createSubmissionNode($doc, $submission)
    {
        $deployment = $this->getDeployment();
        $submissionNode = parent::createSubmissionNode($doc, $submission);

        return $submissionNode;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\importexport\native\filter\PreprintNativeXmlFilter', '\PreprintNativeXmlFilter');
}

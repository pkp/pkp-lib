<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPreprintGalleyFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPreprintGalleyFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Native XML document to a set of publication formats.
 */

namespace APP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\facades\Repo;
use DOMElement;
use PKP\galley\Galley;

class NativeXmlPreprintGalleyFilter extends \PKP\plugins\importexport\native\filter\NativeXmlRepresentationFilter
{
    //
    // Implement template methods from NativeImportFilter
    //
    /**
     * Return the plural element name
     *
     * @return string
     */
    public function getPluralElementName()
    {
        return 'preprint_galleys'; // defined if needed in the future.
    }

    /**
     * Get the singular element name
     *
     * @return string
     */
    public function getSingularElementName()
    {
        return 'preprint_galley';
    }

    /**
     * Handle a submission element
     *
     * @param DOMElement $node
     *
     * @return Galley Galley object
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $submission = $deployment->getSubmission();
        assert(is_a($submission, 'Submission'));

        $submissionFileRefNodes = $node->getElementsByTagName('submission_file_ref');
        assert($submissionFileRefNodes->length <= 1);
        $addSubmissionFile = false;
        if ($submissionFileRefNodes->length == 1) {
            $fileNode = $submissionFileRefNodes->item(0);
            $newSubmissionFileId = $deployment->getSubmissionFileDBId($fileNode->getAttribute('id'));
            if ($newSubmissionFileId) {
                $addSubmissionFile = true;
            }
        }
        /** @var Galley $representation */
        $representation = parent::handleElement($node);

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'name':
                        // Labels are not localized in Galleys, but we use the <name locale="....">...</name> structure.
                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $submission->getLocale();
                        }
                        $representation->setLabel($n->textContent);
                        $representation->setLocale($locale);
                        break;
                }
            }
        }

        if ($addSubmissionFile) {
            $representation->setData('submissionFileId', $newSubmissionFileId);
        }
        Repo::galley()->dao->insert($representation);

        if ($addSubmissionFile) {
            // Update the submission file.

            $submissionFile = Repo::submissionFile()->get($newSubmissionFileId);
            Repo::submissionFile()->edit(
                $submissionFile,
                [
                    'assocType' => Application::ASSOC_TYPE_REPRESENTATION,
                    'assocId' => $representation->getId(),
                ]
            );
        }

        // representation proof files
        return $representation;
    }
}

<?php

/**
 * @file classes/mail/PreprintMailTemplate.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintMailTemplate
 * @ingroup mail
 *
 * @brief Subclass of SubmissionMailTemplate for sending emails related to preprints.
 *
 * This allows for preprint-specific functionality like logging, etc.
 */

namespace APP\mail;

use PKP\db\DAORegistry;
use PKP\mail\SubmissionMailTemplate;

class PreprintMailTemplate extends SubmissionMailTemplate
{
    /**
     * @copydoc SubmissionMailTemplate::assignParams()
     */
    public function assignParams($paramArray = [])
    {
        $publication = $this->submission->getCurrentPublication();
        if ($sectionId = $publication->getData('sectionId')) {
            $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
            $section = $sectionDao->getById($sectionId);
            if ($section) {
                $paramArray['sectionName'] = strip_tags($section->getLocalizedTitle());
            }
        }
        parent::assignParams($paramArray);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\mail\PreprintMailTemplate', '\PreprintMailTemplate');
}

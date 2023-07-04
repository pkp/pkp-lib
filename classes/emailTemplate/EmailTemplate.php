<?php

/**
 * @file classes/emailTemplate/EmailTemplate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplate
 *
 * @ingroup mail
 *
 * @see EmailTemplateDAO
 *
 * @brief Describes basic email template properties.
 */

namespace PKP\emailTemplate;

class EmailTemplate extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //

    /**
     * Get ID of journal/conference/...
     *
     * @deprecated 3.2
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of journal/conference/...
     *
     * @deprecated 3.2
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('contextId', $assocId);
    }

    /**
     * Get ID of email template.
     *
     * @deprecated 3.2
     *
     * @return int
     */
    public function getEmailId()
    {
        return $this->getData('id');
    }

    /**
     * Set ID of email template.
     *
     * @deprecated 3.2
     *
     * @param int $emailId
     */
    public function setEmailId($emailId)
    {
        $this->setData('id', $emailId);
    }

    /**
     * Get the enabled setting of email template.
     *
     * @deprecated 3.2
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set the enabled setting of email template.
     *
     * @deprecated 3.2
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Get subject of email template.
     *
     * @deprecated 3.2
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->getData('subject');
    }

    /**
     * Set subject of email.
     *
     * @deprecated 3.2
     *
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->setData('subject', $subject);
    }

    /**
     * Get body of email template.
     *
     * @deprecated 3.2
     *
     * @return string
     */
    public function getBody()
    {
        return $this->getData('body');
    }

    /**
     * Set body of email template.
     *
     * @deprecated 3.2
     *
     * @param string $body
     */
    public function setBody($body)
    {
        $this->setData('body', $body);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\emailTemplate\EmailTemplate', '\EmailTemplate');
}

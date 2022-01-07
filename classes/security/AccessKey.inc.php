<?php

/**
 * @defgroup security Security
 * Concerns related to security, such as access keys, user groups, and roles.
 */

/**
 * @file classes/security/AccessKey.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AccessKey
 * @ingroup security
 *
 * @see AccessKeyDAO
 *
 * @brief AccessKey class.
 */

namespace PKP\security;

class AccessKey extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //
    /**
     * Get context.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->getData('context');
    }

    /**
     * Set context.
     *
     * @param string $context
     */
    public function setContext($context)
    {
        $this->setData('context', $context);
    }

    /**
     * Get key hash.
     *
     * @return string
     */
    public function getKeyHash()
    {
        return $this->getData('keyHash');
    }

    /**
     * Set key hash.
     *
     * @param string $keyHash
     */
    public function setKeyHash($keyHash)
    {
        $this->setData('keyHash', $keyHash);
    }

    /**
     * Get user ID.
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * Set user ID.
     *
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }

    /**
     * Get associated ID.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set associated ID.
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get expiry date.
     *
     * @return string
     */
    public function getExpiryDate()
    {
        return $this->getData('expiryDate');
    }

    /**
     * Set expiry date.
     *
     * @param string $expiryDate
     */
    public function setExpiryDate($expiryDate)
    {
        $this->setData('expiryDate', $expiryDate);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\AccessKey', '\AccessKey');
}

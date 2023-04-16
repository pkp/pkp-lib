<?php

/**
 * @file classes/security/AccessKeyDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AccessKeyDAO
 *
 * @ingroup security
 *
 * @see AccessKey
 *
 * @brief Operations for retrieving and modifying AccessKey objects.
 */

namespace PKP\security;

use PKP\core\Core;
use PKP\plugins\Hook;

class AccessKeyDAO extends \PKP\db\DAO
{
    /**
     * Retrieve an accessKey by ID.
     *
     * @param int $accessKeyId
     *
     * @return AccessKey
     */
    public function getAccessKey($accessKeyId)
    {
        $result = $this->retrieve(
            sprintf(
                'SELECT * FROM access_keys WHERE access_key_id = ? AND expiry_date > %s',
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [(int) $accessKeyId]
        );
        $row = $result->current();
        return $row ? $this->_returnAccessKeyFromRow((array) $row) : null;
    }

    /**
     * Retrieve a accessKey object user ID.
     *
     * @param string $context
     * @param int $userId
     *
     * @return AccessKey
     */
    public function getAccessKeyByUserId($context, $userId)
    {
        $result = $this->retrieve(
            sprintf(
                'SELECT * FROM access_keys WHERE context = ? AND user_id = ? AND expiry_date > %s',
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            [$context, $userId]
        );
        $row = $result->current();
        return $row ? $this->_returnAccessKeyFromRow((array) $row) : null;
    }

    /**
     * Retrieve a accessKey object by key.
     *
     * @param string $context
     * @param int $userId
     * @param string $keyHash
     * @param int $assocId
     *
     * @return AccessKey
     */
    public function getAccessKeyByKeyHash($context, $userId, $keyHash, $assocId = null)
    {
        $paramArray = [$context, $keyHash, (int) $userId];
        if (isset($assocId)) {
            $paramArray[] = (int) $assocId;
        }
        $result = $this->retrieve(
            sprintf(
                'SELECT * FROM access_keys WHERE context = ? AND key_hash = ? AND user_id = ? AND expiry_date > %s' . (isset($assocId) ? ' AND assoc_id = ?' : ''),
                $this->datetimeToDB(Core::getCurrentDate())
            ),
            $paramArray
        );
        $row = $result->current();
        return $row ? $this->_returnAccessKeyFromRow((array) $row) : null;
    }

    /**
     * Instantiate and return a new data object.
     *
     * @return AccessKey
     */
    public function newDataObject()
    {
        return new AccessKey();
    }

    /**
     * Internal function to return an AccessKey object from a row.
     *
     * @param array $row
     *
     * @return AccessKey
     */
    public function _returnAccessKeyFromRow($row)
    {
        $accessKey = $this->newDataObject();
        $accessKey->setId($row['access_key_id']);
        $accessKey->setKeyHash($row['key_hash']);
        $accessKey->setExpiryDate($this->datetimeFromDB($row['expiry_date']));
        $accessKey->setContext($row['context']);
        $accessKey->setAssocId($row['assoc_id']);
        $accessKey->setUserId($row['user_id']);

        Hook::call('AccessKeyDAO::_returnAccessKeyFromRow', [&$accessKey, &$row]);

        return $accessKey;
    }

    /**
     * Insert a new accessKey.
     *
     * @param AccessKey $accessKey
     */
    public function insertObject($accessKey)
    {
        $this->update(
            sprintf(
                'INSERT INTO access_keys
				(key_hash, expiry_date, context, assoc_id, user_id)
				VALUES
				(?, %s, ?, ?, ?)',
                $this->datetimeToDB($accessKey->getExpiryDate())
            ),
            [
                $accessKey->getKeyHash(),
                $accessKey->getContext(),
                $accessKey->getAssocId() == '' ? null : (int) $accessKey->getAssocId(),
                (int) $accessKey->getUserId()
            ]
        );

        $accessKey->setId($this->getInsertId());
        return $accessKey->getId();
    }

    /**
     * Update an existing accessKey.
     *
     * @param AccessKey $accessKey
     */
    public function updateObject($accessKey)
    {
        return $this->update(
            sprintf(
                'UPDATE access_keys
				SET
					key_hash = ?,
					expiry_date = %s,
					context = ?,
					assoc_id = ?,
					user_id = ?
				WHERE access_key_id = ?',
                $this->datetimeToDB($accessKey->getExpiryDate())
            ),
            [
                $accessKey->getKeyHash(),
                $accessKey->getContext(),
                $accessKey->getAssocId() == '' ? null : (int) $accessKey->getAssocId(),
                (int) $accessKey->getUserId(),
                (int) $accessKey->getId()
            ]
        );
    }

    /**
     * Delete an accessKey.
     *
     * @param AccessKey $accessKey
     */
    public function deleteObject($accessKey)
    {
        return $this->deleteAccessKeyById($accessKey->getId());
    }

    /**
     * Delete an accessKey by ID.
     *
     * @param int $accessKeyId
     */
    public function deleteAccessKeyById($accessKeyId)
    {
        return $this->update('DELETE FROM access_keys WHERE access_key_id = ?', [(int) $accessKeyId]);
    }

    /**
     * Transfer access keys to another user ID.
     *
     * @param int $oldUserId
     * @param int $newUserId
     */
    public function transferAccessKeys($oldUserId, $newUserId)
    {
        return $this->update(
            'UPDATE access_keys SET user_id = ? WHERE user_id = ?',
            [(int) $newUserId, (int) $oldUserId]
        );
    }

    /**
     * Delete expired access keys.
     */
    public function deleteExpiredKeys()
    {
        return $this->update(
            sprintf(
                'DELETE FROM access_keys WHERE expiry_date <= %s',
                $this->datetimeToDB(Core::getCurrentDate())
            )
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\AccessKeyDAO', '\AccessKeyDAO');
}

<?php

/**
 * @file classes/context/ContextDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextDAO
 *
 * @ingroup core
 *
 * @see DAO
 *
 * @brief Operations for retrieving and modifying context objects.
 */

namespace PKP\context;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\db\DAOResultFactory;
use PKP\db\SchemaDAO;
use PKP\security\Role;

/**
 * @template T of Context
 *
 * @extends SchemaDAO<T>
 */
abstract class ContextDAO extends SchemaDAO
{
    /**
     * Retrieve the IDs and names of all contexts in an associative array.
     *
     * @return array<int,string>
     */
    public function getNames(bool $enabledOnly = false): array
    {
        $contexts = [];
        $iterator = $this->getAll($enabledOnly);
        while ($context = $iterator->next()) {
            $contexts[$context->getId()] = $context->getLocalizedName();
        }
        return $contexts;
    }

    /**
     * Get a list of localized settings.
     */
    public function getLocaleFieldNames(): array
    {
        return ['name', 'description'];
    }

    /**
     * Check if a context exists
     */
    public function exists(int $id): bool
    {
        return DB::table($this->tableName)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Check if a context exists with a specified path.
     */
    public function existsByPath(string $path): bool
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM ' . $this->tableName . ' WHERE path = ?',
            [(string) $path]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Retrieve a context by path.
     *
     * @param string $path
     *
     * @return ?T
     */
    public function getByPath($path)
    {
        $result = $this->retrieve(
            'SELECT * FROM ' . $this->tableName . ' WHERE path = ?',
            [(string) $path]
        );
        $row = (array) $result->current();
        return $row ? $this->_fromRow($row) : null;
    }

    /**
     * Retrieve all contexts.
     *
     * @param bool $enabledOnly true iff only enabled contexts should be included
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<T> containing matching Contexts
     */
    public function getAll($enabledOnly = false, $rangeInfo = null)
    {
        $result = $this->retrieveRange(
            'SELECT * FROM ' . $this->tableName .
            ($enabledOnly ? ' WHERE enabled = 1' : '') .
            ' ORDER BY seq',
            [],
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Retrieve available contexts.
     * If user-based contexts, retrieve all contexts assigned by user group
     *   or all contexts for site admin
     * If not user-based, retrieve all enabled contexts.
     *
     * @param ?int $userId Optional user ID to find available contexts for
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @return DAOResultFactory<T> containing matching Contexts
     */
    public function getAvailable($userId = null, $rangeInfo = null)
    {
        $params = [];
        if ($userId) {
            $currentDateTime = Core::getCurrentDate();
            $params = array_merge(
                $params,
                [(int) $userId, $currentDateTime, $currentDateTime, (int) $userId, (int) Role::ROLE_ID_SITE_ADMIN, $currentDateTime, $currentDateTime]
            );
        }

        $result = $this->retrieveRange(
            'SELECT c.* FROM ' . $this->tableName . ' c
            WHERE ' .
                ($userId ?
                    'c.' . $this->primaryKeyColumn . ' IN (
                        SELECT DISTINCT ug.context_id
                        FROM user_groups ug
                        JOIN user_user_groups uug ON (ug.user_group_id = uug.user_group_id)
                        WHERE
                            uug.user_id = ?
                            AND (uug.date_start IS NULL OR uug.date_start <= ?)
                            AND (uug.date_end IS NULL OR uug.date_end > ?)
                    )
                    OR ? IN (
                        SELECT user_id
                        FROM user_groups ug
                        JOIN user_user_groups uug ON (ug.user_group_id = uug.user_group_id)
                        WHERE
                            ug.role_id = ?
                            AND (uug.date_start IS NULL OR uug.date_start <= ?)
                            AND (uug.date_end IS NULL OR uug.date_end > ?)
                    )'
                : 'c.enabled = 1') .
            ' ORDER BY seq',
            $params,
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Get journals by setting.
     *
     * @param string $settingName
     *
     * @return DAOResultFactory<T>
     */
    public function getBySetting($settingName, $settingValue, ?int $contextId = Application::SITE_CONTEXT_ID_ALL)
    {
        $params = [$settingName, $settingValue];
        if ($contextId !== Application::SITE_CONTEXT_ID_ALL) {
            $params[] = $contextId;
        }

        $result = $this->retrieve(
            'SELECT * FROM ' . $this->tableName . ' AS c
			LEFT JOIN ' . $this->settingsTableName . ' AS cs
			ON c.' . $this->primaryKeyColumn . ' = cs.' . $this->primaryKeyColumn .
            ' WHERE cs.setting_name = ? AND cs.setting_value = ?' .
            ($contextId !== Application::SITE_CONTEXT_ID_ALL ? ' AND c.' . $this->primaryKeyColumn . ' = ?' : ''),
            $params
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /**
     * Sequentially renumber each context according to their sequence order.
     */
    public function resequence()
    {
        $result = $this->retrieve('SELECT ' . $this->primaryKeyColumn . ' AS context_id FROM ' . $this->tableName . ' ORDER BY seq');

        foreach ($result as $key => $value) {
            $this->update('UPDATE ' . $this->tableName . ' SET seq = ? WHERE ' . $this->primaryKeyColumn . ' = ?', [$key + 1, $value->context_id]);
        }
    }
}

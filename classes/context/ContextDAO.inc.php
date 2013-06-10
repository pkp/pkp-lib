<?php
/**
 * @file classes/core/ContextDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextDAO
 * @ingroup core
 * @see DAO
 *
 * @brief Operations for retrieving and modifying context objects.
 */

class ContextDAO extends DAO {
	/**
	 * Constructor
	 */
	function ContextDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a context by context ID.
	 * @param $contextId int
	 * @return Context
	 */
	function getById($contextId) {
		$result = $this->retrieve(
			'SELECT * FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
			(int) $contextId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve the IDs and names of all contexts in an associative array.
	 * @param $enabledOnly true iff only enabled contexts are to be included
	 * @return array
	 */
	function getNames($enabledOnly = false) {
		$contexts = array();
		$iterator = $this->getAll($enabledOnly);
		while ($context = $iterator->next()) {
			$contexts[$context->getId()] = $context->getLocalizedName();
		}
		return $contexts;
	}

	/**
	 * Get a list of localized settings.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('name', 'description');
	}

	/**
	 * Internal function to return a Context object from a row.
	 * @param $row array
	 * @return Context
	 */
	function _fromRow($row) {
		$context = $this->newDataObject();
		$context->setId($row[$this->_getPrimaryKeyColumn()]);
		$context->setPath($row['path']);
		$context->setSequence($row['seq']);
		$this->getDataObjectSettings($this->_getSettingsTableName(), $this->_getPrimaryKeyColumn(), $row[$this->_getPrimaryKeyColumn()], $context);
		return $context;
	}

	/**
	 * Check if a context exists with a specified path.
	 * @param $path string the path for the context
	 * @return boolean
	 */
	function existsByPath($path) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM ' . $this->_getTableName() . ' WHERE path = ?',
			(string) $path
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a context by path.
	 * @param $path string
	 * @return Context
	 */
	function getByPath($path) {
		$result = $this->retrieve(
			'SELECT * FROM ' . $this->_getTableName() . ' WHERE path = ?',
			(string) $path
		);
		if ($result->RecordCount() == 0) return null;

		$returner = $this->_fromRow($result->GetRowAssoc(false));
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all contexts.
	 * @param $enabledOnly true iff only enabled contexts should be included
	 * @param $rangeInfo Object optional
	 * @return DAOResultFactory containing matching Contexts
	 */
	function getAll($enabledOnly = false, $rangeInfo = null) {
		$result = $this->retrieveRange(
			'SELECT * FROM ' . $this->_getTableName() .
			($enabledOnly?' WHERE enabled = 1':'') .
			' ORDER BY seq',
			false,
			$rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Get journals by setting.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $contextId int
	 * @return DAOResultFactory
	 */
	function getBySetting($settingName, $settingValue, $contextId = null) {
		$params = array($settingName, $settingValue);
		if ($contextId) $params[] = $contextId;

		$result = $this->retrieve(
			'SELECT * FROM ' . $this->_getTableName() . 'AS c
			LEFT JOIN ' . $this->_getSettingsTableName() . 'AS cs
			ON c.' . $this->_getPrimaryKeyColumn() . ' = cs.' . $this->_getPrimaryKeyColumn() .
			'WHERE cs.setting_name = ? AND cs.setting_value = ?' .
			($contextId?' AND c.' . $this->_getPrimaryKeyColumn() . ' = ?':''),
			$params
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}


	/**
	 * Get the ID of the last inserted context.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId($this->_getTableName(), $this->_getPrimaryKeyColumn());
	}

	/**
	 * Delete a context by object
	 * @param $context Context
	 */
	function deleteObject($context) {
		$this->deleteById($context->getId());
	}

	/**
	 * Delete a context by ID.
	 * @param $contextId int
	 */
	function deleteById($contextId) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->deleteAssignmentsByContextId($contextId);
		$userGroupDao->deleteByContextId($contextId);

		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genreDao->deleteByContextId($contextId);

		$this->update('DELETE FROM context_defaults WHERE context_id = ?', (int) $contextId);

		$this->update(
			'DELETE FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
			(int) $contextId
		);
	}

	/**
	 * Sequentially renumber each context according to their sequence order.
	 */
	function resequence() {
		$result = $this->retrieve(
			'SELECT ' . $this->_getPrimaryKeyColumn() . ' FROM ' . $this->_getTableName() . ' ORDER BY seq'
		);

		for ($i=1; !$result->EOF; $i+=2) {
			list($contextId) = $result->fields;
			$this->update(
				'UPDATE ' . $this->_getTableName() . ' SET seq = ? WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
				array(
					$i,
					$contextId
				)
			);

			$result->MoveNext();
		}
		$result->Close();
	}

	//
	// Protected methods
	//
	/**
	 * Get the table name for this context.
	 * @return string
	 */
	protected function _getTableName() {
		assert(false); // Must be overridden by subclasses.
	}

	/**
	 * Get the table name for this context's settings table.
	 * @return string
	 */
	protected function _getSettingsTableName() {
		assert(false); // Must be overridden by subclasses.
	}

	/**
	 * Get the name of the primary key column for this context.
	 * @return string
	 */
	protected function _getPrimaryKeyColumn() {
		assert(false); // Must be overridden by subclasses
	}
}

?>

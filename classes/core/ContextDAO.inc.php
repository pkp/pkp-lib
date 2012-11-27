<?php
/**
 * @file classes/core/ContextDAO.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PressDAO
 * @ingroup press
 * @see Press
 *
 * @brief Operations for retrieving and modifying Press objects.
 */

class ContextDAO extends DAO {
	/**
	 * Constructor
	 */
	function ContextDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a press by press ID.
	 * @param $contextId int
	 * @return Context
	 */
	function getById($contextId) {
		$result =& $this->retrieve(
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
	 * @return array
	 */
	function getNames() {
		$contexts = array();
		$iterator =& $this->getAll();
		while ($context = $iterator->next()) {
			$contexts[$context->getId()] = $context->getLocalizedName();
		}
		return $contexts;
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
		$context->setEnabled($row['enabled']);
		$context->setPrimaryLocale($row['primary_locale']);
		return $context;
	}

	/**
	 * Check if a context exists with a specified path.
	 * @param $path string the path for the context
	 * @return boolean
	 */
	function existsByPath($path) {
		$result =& $this->retrieve(
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
		$result =& $this->retrieve(
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
	 * @return DAOResultFactory containing matching presses
	 */
	function getAll($rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM ' . $this->_getTableName() . ' ORDER BY seq',
			false,
			$rangeInfo
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
	 * Delete a context by ID.
	 * @param $contextId int
	 */
	function deleteById($contextId) {
		$this->update(
			'DELETE FROM ' . $this->_getTableName() . ' WHERE ' . $this->_getPrimaryKeyColumn() . ' = ?',
			(int) $contextId
		);
	}

	/**
	 * Sequentially renumber each press according to their sequence order.
	 */
	function resequence() {
		$result =& $this->retrieve(
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
		unset($result);
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
	 * Get the name of the primary key column for this context.
	 * @return string
	 */
	protected function _getPrimaryKeyColumn() {
		assert(false); // Must be overridden by subclasses
	}
}

?>

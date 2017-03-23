<?php
/**
 * @defgroup db DB
 * Implements basic database concerns such as connection abstraction.
 */

/**
 * @file classes/db/DBQueryBuilder.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DBQueryBuilder
 * @ingroup db
 *
 * @brief A simple class to build and compile database query strings and params
 *
 * Example usage:
 * $query = new DBQueryBuilder();
 * $query->from('submissions s');
 * $query->where('s.status = ?', (int) $statusId);
 * $queryString = $query->getQuery();
 * $params = $query->getParams();
 */

class DBQueryBuilder {
	/**
	 * SELECT statements
	 *
	 * @param array
	 */
	private $_select = array();

	/**
	 * SELECT params
	 *
	 * @param array
	 */
	private $_selectParams = array();

	/**
	 * FROM statements
	 *
	 * @param array
	 */
	private $_from = array();

	/**
	 * FROM params
	 *
	 * @param array
	 */
	private $_fromParams = array();

	/**
	 * WHERE statements
	 *
	 * @param array
	 */
	private $_where = array();

	/**
	 * WHERE params
	 *
	 * @param array
	 */
	private $_whereParams = array();

	/**
	 * ORDER BY statements
	 *
	 * @param array
	 */
	private $_orderBy = array();

	/**
	 * ORDER BY params
	 *
	 * @param array
	 */
	private $_orderByParams = array();

	/**
	 * GROUP BY statements
	 *
	 * @param array
	 */
	private $_groupBy = array();

	/**
	 * GROUP BY params
	 *
	 * @param array
	 */
	private $_groupByParams = array();

	/**
	 * Add a statement with optional params
	 *
	 * @param string $type Type of statement to add
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function add($type, $statement, $params = null) {

		$type = '_' . $type;
		$typeParams = $type . 'Params';
		if (!isset($this->{$type}) || !isset($this->{$typeParams})) {
			error_log('Query builder was asked to add a statement type it does not recognize: ' . $type);
			return;
		}

		if (!is_array($statement)) {
			$statement = array($statement);
		}
		$this->{$type} = array_merge($this->{$type}, $statement);

		if (!is_null($params)) {
			if (!is_array($params)) {
				$params = array($params);
			}
			$this->{$typeParams} = array_merge($this->{$typeParams}, $params);
		}
	}

	/**
	 * Add a SELECT statement
	 *
	 * A wrapper for the add method.
	 *
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function select($statement, $params = null) {
		$this->add('select', $statement, $params);
	}

	/**
	 * Add a FROM statement
	 *
	 * A wrapper for the add method.
	 *
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function from($statement, $params = null) {
		$this->add('from', $statement, $params);
	}

	/**
	 * Add a WHERE statement
	 *
	 * A wrapper for the add method.
	 *
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function where($statement, $params = null) {
		$this->add('where', $statement, $params);
	}

	/**
	 * Add an GROUP BY statement
	 *
	 * A wrapper for the add method.
	 *
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function groupBy($statement, $params = null) {
		$this->add('groupBy', $statement, $params);
	}

	/**
	 * Add an ORDER BY statement
	 *
	 * A wrapper for the add method.
	 *
	 * @param string|array $statement One or more statements to add
	 * @param string|int|array $params Optional One or more params to add
	 */
	public function orderBy($statement, $params = null) {
		$this->add('orderBy', $statement, $params);
	}

	/**
	 * Compile the query statement
	 *
	 * @return string
	 */
	public function getQuery() {
		return 'SELECT ' . join(',', $this->_select)
			. ' FROM ' . join(' ', $this->_from)
			. ' WHERE ' . join(' AND ', $this->_where)
			. ' GROUP BY ' . join(',', $this->_groupBy)
			. ' ORDER BY ' . join(',', $this->_orderBy);
	}

	/**
	 * Compile the query params
	 *
	 * @return array
	 */
	public function getParams() {
		return array_merge(
			$this->_selectParams,
			$this->_fromParams,
			$this->_whereParams,
			$this->_groupByParams,
			$this->_orderByParams
		);
	}
}

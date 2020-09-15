<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/Mapping.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Mapping
 * @ingroup lib_pkp_classes_user
 *
 * @brief Keeps the mapping of a column header and its data provider, a callable.
 */

namespace PKP\User\Report;

class Mapping {
	/** @var string The column header */
	private $_caption;

	/** @var callable The column data provider */
	private $_value;

	/**
	 * Constructor
	 * @param string $caption The column header
	 * @param callable $value The column data provider. The function will be called for each report row and should return an string,
	 * the first argument is an \User instance, the second is an Illuminate row object.
	 */
	public function __construct(string $caption, callable $value)
	{
		$this->_caption = $caption;
		$this->_value = $value;
	}

	/**
	 * Retrieves the column header
	 * @return string
	 */
	public function getCaption(): string
	{
		return $this->_caption;
	}

	/**
	 * Retrieves the column data provider
	 * @return callable
	 */
	public function getValue(): callable
	{
		return $this->_value;
	}

	/**
	 * Calls the data provider and returns its data
	 * @param \User $user The User instance
	 * @param object $userRecord The data row produced by the query builder
	 * @return ?string
	 */
	public function __invoke(\User $user, object $userRecord): ?string
	{
		return ($this->_value)($user, $userRecord);
	}
}

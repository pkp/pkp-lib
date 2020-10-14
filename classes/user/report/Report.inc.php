<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/Report.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 * @ingroup lib_pkp_classes_user
 *
 * @brief Responsible to retrieve and provide users data to the Mapping objects.
 */

namespace PKP\User\Report;

import('lib.pkp.classes.user.UserDAO');

class Report implements \IteratorAggregate {
	/** @var Mapping[] An array of Mapping objects responsible to feed the report's header and body */
	private $_mappings = [];

	/** @var SerializerInterface The report serializer */
	private $_serializer;

	/** @var iterable The report data source */
	private $_dataSource;

	/**
	 * Constructor
	 * @param iterable $dataSource The data source, should yield /User objects
	 * @param SerializerInterface $serializer The serializer
	 * @param bool $addDefaultMappings Whether default mappings should be automatically added (defaults to true)
	 */
	public function __construct(iterable $dataSource, SerializerInterface $serializer, ?bool $addDefaultMappings = true)
	{
		$this->_dataSource = $dataSource;
		$this->_serializer = $serializer;
		if ($addDefaultMappings) {
			new Mappings\Standard($this);
			new Mappings\UserGroups($this);
			new Mappings\Notifications($this);
		}
	}

	/**
	 * Retrieves the data mappings
	 * @return Mapping[] A list of Mapping objects
	 */
	public function getMappings(): array
	{
		return $this->_mappings;
	}

	/**
	 * Replaces the data mappings
	 * @param Mapping[] $mappings A list of Mapping objects
	 * @return $this
	 */
	public function setMappings(array $mappings): self
	{
		$this->_mappings = $mappings;
		return $this;
	}

	/**
	 * Appends data mappings
	 * @param Mapping ...$mappings A list of Mapping objects
	 * @return $this
	 */
	public function addMappings(Mapping ...$mappings): self
	{
		array_push($this->_mappings, ...$mappings);
		return $this;
	}

	/**
	 * Serializes the report to the given output
	 * @param resource $output A ready to write stream
	 */
	public function serialize($output): void
	{
		$this->_serializer->serialize($this, $output);
	}

	/**
	 * Implements the IteratorAggregate interface
	 * @return Traversable A data row generator
	 */
	public function getIterator(): \Traversable
	{
		foreach ($this->_dataSource as $user) {
			yield $this->_getDataRow($user);
		};
	}

	/**
	 * Retrieves the report headings
	 * @return string[]
	 */
	public function getHeadings(): array
	{
		return array_map(
			function (Mapping $mapping): ?string
			{
				return $mapping->getCaption();
			},
			$this->_mappings
		);
	}

	/**
	 * Retrieves a report data row
	 * @param \User $user An user instance
	 * @return string[]
	 */	
	private function _getDataRow(\User $user): array
	{
		return array_map(
			function (Mapping $mapping) use ($user): ?string
			{
				return $mapping($user);
			},
			$this->_mappings
		);
	}
}

<?php 

/**
 * @file classes/repositories/IssueRepositoryInterface.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @interface IssueRepositoryInterface
 * @ingroup repositories
 *
 * @brief Issue repository interface
 */

namespace App\Repositories;

use \Issue;

interface IssueRepositoryInterface {
	/**
	 * Validate an issue data object
	 * 
	 * @param array $issueData
	 * @throws App\Repositories\Exceptions\ValidationException
	 * 
	 * @return boolean
	 */
	public function validate($issueData);

	/**
	 * Create an Issue data object
	 * 
	 * @param Journal $journal
	 * @param array $issueData
	 * @throws App\Repositories\Exceptions\ValidationException
	 * 
	 * @return \Issue $issue
	 */
	public function create($journal, $issueData);
	
	/**
	 * Update an existing issue
	 * @param Issue $issue
	 * @param array $issueData
	 * 
	 * @return \Issue $issue
	 */
	public function update(Issue $issue, $issueData);
	
	/**
	 * Delete an issue
	 * @param Issue $issue
	 * 
	 * @return boolean
	 */
// 	public function delete(Issue $issue);
	
// 	public function addGalley();
// 	public function publish();
}
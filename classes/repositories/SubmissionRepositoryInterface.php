<?php 

/**
 * @file classes/repositories/SubmissionRepositoryInterface.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @interface SubmissionRepositoryInterface
 * @ingroup repositories
 *
 * @brief Submission repository interface
 */

namespace App\Repositories;

use \Journal;
use \User;
use \Submission;

interface SubmissionRepositoryInterface {
	
	public function create(Journal $journal, User $user, $submissionData);
	
	public function update(Submission $submission, $user, $submissionData);
	
// 	public function updateMetadata();
// 	public function delete();
// 	public function validate();
}
<?php 

/**
 * @file classes/repositories/IssueRepository.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @interface IssueRepository
 * @ingroup repositories
 *
 * @brief Issue repository implementation
 */

namespace App\Repositories;

use \Issue;

class IssueRepository implements IssueRepositoryInterface {
	
	/**
	 * Constructor
	 */
	public function __construct() {}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \App\Repositories\IssueRepositoryInterface::validate()
	 */
	public function validate($issueData) {
		
		if (!$issueData['showVolume'] && !$issueData['showYear'] && !$issueData['showNumber'] && !$issueData['showTitle']) {
			throw new Exceptions\ValidationException(__('editor.issues.issueIdentificationRequired'));
		}
		
		if ($issueData['showVolume'] && !$issueData['volume']) {
			throw new Exceptions\ValidationException(__('editor.issues.volumeRequired'), 'editor.issues.volumeRequired');
		}
		
		if ($issueData['showYear'] && !$issueData['year']) {
			throw new Exceptions\ValidationException(__('editor.issues.yearRequired'), 'editor.issues.yearRequired');
		}
		
		if ($issueData['showNumber'] && empty($issueData['number'])) {
			throw new Exceptions\ValidationException(__('editor.issues.numberRequired'), 'editor.issues.numberRequired');
		}
		
		if ($issueData['showTitle'] && empty($issueData['title'])) {
			throw new Exceptions\ValidationException(__('editor.issues.titleRequired'), 'editor.issues.titleRequired');
		}
		
		return true;
	}

	protected function getDefaultIssueData(\Issue $issue = null) {
		return array(
			'showVolume'         => !is_null($issue) ? $issue->getShowVolume() : false,
			'volume'             => !is_null($issue) ? $issue->getVolume() : 0,
			'showYear'           => !is_null($issue) ? $issue->getShowYear() : false,
			'year'               => !is_null($issue) ? $issue->getYear() : 0,
			'showNumber'         => !is_null($issue) ? $issue->getShowNumber() : false,
			'number'             => !is_null($issue) ? $issue->getNumber() : '',
			'showTitle'          => !is_null($issue) ? $issue->getShowTitle() : false,
			'title'              => !is_null($issue) ? $issue->getTitle(null) : '',
		);
	}
	
	protected function storeIssueData(&$issue, $issueData) {
		$issue->setTitle($issueData['title']);
		$issue->setVolume($issueData['volume']);
		$issue->setNumber($issueData['number']);
		$issue->setYear($issueData['year']);
		$issue->setDescription($issueData['description']);
		$issue->setShowVolume($issueData['showVolume']);
		$issue->setShowNumber($issueData['showNumber']);
		$issue->setShowYear($issueData['showYear']);
		$issue->setShowTitle($issueData['showTitle']);
		$issue->setAccessStatus(isset($issueData['accessStatus']) ? $issueData['accessStatus'] : ISSUE_ACCESS_OPEN);
		
		if (isset($issueData['enableOpenAccessDate'])) {
			$issue->setOpenAccessDate($issueData['enableOpenAccessDate']);
		}
		else {
			$issue->setOpenAccessDate(null);
		}
		
		$issue->setPublished(0);
		$issue->setCurrent(0);
	}
	
	public function create($journal, $issueData) {
		
		$issueDataDefault = $this->getDefaultIssueData();
		$issueDataDefault['accessStatus'] = \ServicesContainer::instance()->get('issue')->determineAccessStatus($journal);
		
		$issueData = array_merge($issueDataDefault, $issueData);
		$this->validate($issueData);
		
		$issueDao = \DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->newDataObject();
		
		$issue->setJournalId($journal->getId());
		$this->storeIssueData($issue, $issueData);
		
		
		$issueDao->insertObject($issue);
		
		// TODO handler cover file
		
		return $issue;
	}
	
	public function update(Issue $issue, $issueData) {
		$issueDataDefault = $this->getDefaultIssueData($issue);
		$issueData = array_merge($issueDataDefault, $issueData);
		
		$this->validate($issueData);
		$this->storeIssueData($issue, $issueData);
		
		$issueDao = \DAORegistry::getDAO('IssueDAO');
		$issueDao->updateObject($issue);
		
		// TODO handler cover file
		
		return $issue;
	}
	
	public function delete(Issue $issue) {
	}
}
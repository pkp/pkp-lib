<?php 

/**
 * @file classes/repositories/SubmissionRepository.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @interface SubmissionRepository
 * @ingroup repositories
 *
 * @brief Issue repository implementation
 */

namespace App\Repositories;

use \Journal;
use \User;
use \Application;
use \DAORegistry;
use \Submission;
use \Core;
use \PKPString;

class SubmissionRepository implements SubmissionRepositoryInterface {

	/**
	 * Constructor
	 */
	public function __construct() {
	}
	
	/**
	 * Add or update comments to editor
	 * @param $submissionId int
	 * @param $commentsToEditor string
	 * @param $userId int
	 * @param $query Query optional
	 */
	protected function setCommentsToEditor($submissionId, $commentsToEditor, $userId, $query = null) {
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$noteDao = DAORegistry::getDAO('NoteDAO');

		if (!isset($query)){
			if ($commentsToEditor) {
				$query = $queryDao->newDataObject();
				$query->setAssocType(ASSOC_TYPE_SUBMISSION);
				$query->setAssocId($submissionId);
				$query->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
				$query->setSequence(REALLY_BIG_NUMBER);
				$queryDao->insertObject($query);
				$queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submissionId);
				$queryDao->insertParticipant($query->getId(), $userId);
				$queryId = $query->getId();

				$note = $noteDao->newDataObject();
				$note->setUserId($userId);
				$note->setAssocType(ASSOC_TYPE_QUERY);
				$note->setTitle(__('submission.submit.coverNote'));
				$note->setContents($commentsToEditor);
				$note->setDateCreated(Core::getCurrentDate());
				$note->setDateModified(Core::getCurrentDate());
				$note->setAssocId($queryId);
				$noteDao->insertObject($note);
			}
		} else{
			$queryId = $query->getId();
			$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $queryId);
			if (!$notes->wasEmpty()) {
				$note = $notes->next();
				if ($commentsToEditor) {
					$note->setContents($commentsToEditor);
					$note->setDateModified(Core::getCurrentDate());
					$noteDao->updateObject($note);
				} else {
					$noteDao->deleteObject($note);
					$queryDao->deleteObject($query);
				}
			}
		}
	}
	
	/**
	 * Create a submission
	 * 
	 * @param Journal $journal
	 * @param User $user
	 * @param array $submissionData
	 * 		$submissionData['sectionId'] int
	 * 		$submissionData['locale'] string
	 * 		$submissionData['authorUserGroupId'] int
	 * 		$submissionData['commentsToEditor'] string
	 * 
	 * @return Submission
	 */
	public function create(Journal $journal, User $user, $submissionData) {
		$step = 1;
		$submissionDao = Application::getSubmissionDAO();
		
		$submission = $submissionDao->newDataObject();
		$submission->setContextId($journal->getId());
		$submission->setSectionId($submissionData['sectionId']);
		$submission->setLanguage(PKPString::substr($submission->getLocale(), 0, 2));
		$submission->setLocale($submissionData['locale']);
		$submission->stampStatusModified();
		$submission->setSubmissionProgress($step + 1);
		$submission->setStageId(WORKFLOW_STAGE_ID_SUBMISSION);
		$submission->setCopyrightNotice($journal->getLocalizedSetting('copyrightNotice'), $submissionData['locale']);
		
		// Insert the submission
		$submissionId = $submissionDao->insertObject($submission);
		
		// Set user to initial author
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$author = $authorDao->newDataObject();
		$author->setFirstName($user->getFirstName());
		$author->setMiddleName($user->getMiddleName());
		$author->setLastName($user->getLastName());
		$author->setAffiliation($user->getAffiliation(null), null);
		$author->setCountry($user->getCountry());
		$author->setEmail($user->getEmail());
		$author->setUrl($user->getUrl());
		$author->setBiography($user->getBiography(null), null);
		$author->setPrimaryContact(1);
		$author->setIncludeInBrowse(1);
		
		// user group to display the submitter as
		$author->setUserGroupId($submissionData['authorUserGroupId']);
		$author->setSubmissionId($submissionId);
		$authorDao->insertObject($author);
		
		// Assign the user author to the stage
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignmentDao->build($submissionId, $submissionData['authorUserGroupId'], $user->getId());
		
		// Add comments to editor
		if ($submissionData['commentsToEditor']) {
			$this->setCommentsToEditor($submissionId, $submissionData['commentsToEditor'], $user->getId());
		}
		
		return $submission;
	}
	
	/**
	 * Update submission
	 * @param Submission $submission
	 * @param array $submissionData
	 * 		$submissionData['step'] int
	 * 		$submissionData['language'] string
	 * 		$submissionData['locale'] string
	 * 		$submissionData['commentsToEditor'] string
	 */
	public function update(Submission $submission, $user, $submissionData)  {
		$submissionId = $submission->getId();
		$submission->setLanguage(PKPString::substr($submission->getLocale(), 0, 2));
		$submission->setLocale($submissionData['locale']);
		
		// TODO verify step logic
		if ($submissionData['step'] && ($submission->getSubmissionProgress() <= $submissionData['step'])) {
			$submission->stampStatusModified();
			$submission->setSubmissionProgress($submissionData['step'] + 1);
		}
		
		$query = null;
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$queries = $queryDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);
		if ($queries) $query = $queries->next();
		
		$this->setCommentsToEditor($submissionId, $submissionData['commentsToEditor'], $user->getId(), $query);
		
		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->updateObject($submission);
	}
	
	/**
	 * Update submission metadata
	 * @param Submission $submission
	 * @param array $metadata
	 */
// 	public function updateMetadata(Submission $submission, $metadata) {
		
// 	}
}
<?php
/**
 * @file classes/submission/RepresentationDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationDAO
 * @ingroup submission
 * @see Representation
 *
 * @brief Abstract DAO for fetching/working with DB storage of Representation objects
 */

import('lib.pkp.classes.submission.ISubmissionVersionedDAO');
import('lib.pkp.classes.submission.SubmissionVersionedDAO');

abstract class RepresentationDAO extends SubmissionVersionedDAO implements ISubmissionVersionedDAO {

	/**
	 * Retrieves a representation by ID.
	 * @param $representationId int Representation ID.
	 * @param $submissionId int Optional submission ID.
	 * @param $contextId int Optional context ID.
	 * @return DAOResultFactory
	 */
	abstract function getById($representationId, $submissionId = null, $contextId = null);

	///**
	// * Retrieves an iterator of representations for a submission
	// * @param $submissionId int
	// * @param $contextId int
	// * @return DAOResultFactory
	// */
	//abstract function getBySubmissionId($submissionId, $contextId = null, $submissionVersion = null);

	#region ISubmissionVersionedDAO Members

	/**
	 *
	 * @param  $submissionId
	 */
	function newVersion($submissionId) {
		$submissionDao = Application::getSubmissionDAO();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /** @var $submissionFileDao SubmissionFileDAO */

		list($oldVersion, $newVersion) = $this->provideSubmissionVersionsForNewVersion($submissionId);

		$representationResults = $this->getBySubmissionId($submissionId, null, $oldVersion);
		$representations = $representationResults->toArray();

		/** @var $representation Representation */
		foreach($representations as $representation) {
			$oldRepresentationId = $representation->getId();

			$representation->setIsCurrentSubmissionVersion(false);
			$this->updateObject($representation);

			$representation->setIsApproved(false);

			$representation->setIsCurrentSubmissionVersion(true);
			$representation->setSubmissionVersion($newVersion);
			$representation->setPrevVerAssocId($oldRepresentationId);

			$this->insertObject($representation);

			//$oldRepresentation = $this->getById($oldRepresentationId);

			// copy file and link copy to $publicationFormat version
			$representationFiles = $submissionFileDao->getLatestRevisionsByAssocId(
				ASSOC_TYPE_REPRESENTATION,
				$representation->getPrevVerAssocId(),
				$representation->getSubmissionId(),
				null,
				null,
				$representation->getSubmissionVersion()
			);

			foreach ($representationFiles as $representationFile) {
				/** @var $representationFile SubmissionFile */
				$representationFile->setAssocId($representation->getId());
				$representationFile->setSalesType(null);
				$representationFile->setDirectSalesPrice(null);
				$representationFile->setViewable(false);

				$submissionFileDao->updateObject($representationFile);
			}
		}
	}

	function getMasterTableName() {
		return;
	}
	#endregion
}



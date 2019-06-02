<?php

/**
 * SubmissionRelatedDataObject short summary.
 *
 * SubmissionRelatedDataObject description.
 *
 * @version 1.0
 * @author defstat
 */
class SubmissionVersionedDataObject extends DataObject {

	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}

	function getSubmissionId() {
		return $this->getData('submissionId');
	}
	/**
	 * Set submission version.
	 * @param $submissionVersion int
	 */
	function setSubmissionVersion($submissionVersion) {
		$this->setData('submissionVersion', $submissionVersion);
	}

	/**
	 * Get submission version.
	 * @return int
	 */
	function getSubmissionVersion() {
		if (!$this->getData('submissionVersion')) {
			$this->setSubmissionVersion($this->getEntityCurrentSubmissionVersion());
		}

		return $this->getData('submissionVersion');
	}

	/**
	 * Set submission version.
	 * @param $submissionVersion int
	 */
	function setPrevVerAssocId($prevVerAssocId) {
		$this->setData('prevVerAssocId', $prevVerAssocId);
	}

	/**
	 * Get submission version.
	 * @return int
	 */
	function getPrevVerAssocId() {
		return $this->getData('prevVerAssocId');
	}

	function setIsCurrentSubmissionVersion($isCurrentSubmissionVersion) {
		return $this->setData('isCurrentSubmissionVersion', $isCurrentSubmissionVersion);
	}

	function getIsCurrentSubmissionVersion() {
		if (is_null($this->getData('isCurrentSubmissionVersion'))) {
			$this->setIsCurrentSubmissionVersion(true);
		}

		return $this->getData('isCurrentSubmissionVersion');
	}

	/**
	 * Any entity that implements this interface should have a $submissionId attribute.
	 * This method should be able to retrieve the $currentSubmissionVersion from that $submissionId
	 * @param $entity DataObject
	 * @return int The current submission version
	 */
	function getEntityCurrentSubmissionVersion() {
		if (method_exists($this, 'getSubmissionId')) {
			$submissionDao = Application::getSubmissionDAO(); /** @var $submissionDao SubmissionDAO */
			$submission = $submissionDao->getById($this->getSubmissionId()); /** @var $submission Submission */

			$currentSubmissionVersion = $submission->getCurrentSubmissionVersion();
			if ($currentSubmissionVersion) {
				return $currentSubmissionVersion;
			}

			return 1;
		}

		throw new Exception("SubmissionVersionedDataObject should be implemented on entities that contain a getSubmissionId function");
	}
}

<?php
/**
 * @file tests/PKPSubmissionsTest.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsTest
 * @ingroup tests
 * @see SubmissionHandler
 *
 * @brief Parent class for the /submissions endpoint
 */

import('lib.pkp.tests.PKPApiTestCase');

class PKPSubmissionsTest extends PKPApiTestCase {
	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	public function setUp() {
		parent::setUp();
		$this->submissionDao = Application::getSubmissionDao();
	}

	/**
	 * Search for submissions by title and status
	 * @param $title string Title
	 * @param $status int Status
	 * @return array
	 */
	protected function _findSubmissionByTitle($title, $status = STATUS_PUBLISHED) {
		$submissions = array();
		$result = $this->submissionDao->getByStatus(
			$status, null, null, null, $title, null, null, null, null, ""
		);
		while ($submission = $result->next()) {
			$submissions[] = $submission;
		}
		return $submissions;
	}

	/**
	 * @covers /submissions
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionsWithoutToken() {
		$response = $this->_sendRequest('GET', '/submissions', array(), false);
	}

	/**
	 * @covers /submissions
	 */
	public function testGetSubmissions() {
		$response = $this->_sendRequest('GET', '/submissions');
		$this->assertEquals(200, $response->getStatusCode());
		
		$data = $this->_getResponseData($response);
		$this->assertArrayHasKey('itemsMax', $data);
		$this->assertArrayHasKey('items', $data);
	}

	/**
	 * @covers /submissions/{submissionId}
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionByIdWithValidIdWithoutToken() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}", array(), false);
	}

	/**
	 * @covers /submissions/{submissionId}
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionByIdWithInvalidId() {
		$response = $this->_sendRequest('GET', "/submissions/{$this->_invalidId}");
		$this->assertSame(404, $response->getStatusCode());
	}

	/**
	 * @covers /submissions/{submissionId}
	 */
	public function testGetSubmissionByIdWithValidId() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}");
		$this->assertSame(200, $response->getStatusCode());

		$data = $this->_getResponseData($response);
		$this->assertArrayHasKey('id', $data);
		$this->assertArrayHasKey('title', $data);
		$this->assertArrayHasKey('abstract', $data);
		$this->assertArrayHasKey('authors', $data);
		$this->assertArrayHasKey('section', $data);
	}

	/**
	 * @covers /submissions/{submissionId}/participants
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionParticipantsWithoutToken() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}/participants", array(), false);
		$this->assertSame(404, $response->getStatusCode());
	}

	/**
	 * @covers /submissions/{submissionId}/participants
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionParticipantsWithInvalidId() {
		$response = $this->_sendRequest('GET', "/submissions/{$this->_invalidId}/participants");
		$this->assertSame(404, $response->getStatusCode());
	}

	/**
	 * @covers /submissions/{submissionId}/participants
	 */
	public function testGetSubmissionParticipants() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}/participants");
		$this->assertSame(200, $response->getStatusCode());

		$data = $this->_getResponseData($response);
		$this->assertTrue(is_array($data));
		$this->assertNotEmpty($data);

		$participant = (array) array_shift($data);
		$this->assertArrayHasKey('id', $participant);
		$this->assertArrayHasKey('_href', $participant);
		$this->assertArrayHasKey('userName', $participant);
		$this->assertArrayHasKey('email', $participant);
		$this->assertArrayHasKey('groups', $participant);
	}

	/**
	 * @covers /submissions/{submissionId}/participants/{stageId}
	 * @expectedException GuzzleHttp\Exception\ClientException
	 */
	public function testGetSubmissionParticipantsAssignedToStageWithoutToken() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}/participants/" . WORKFLOW_STAGE_ID_SUBMISSION, array(), false);
		$this->assertSame(404, $response->getStatusCode());
	}

	/**
	 * @covers /submissions/{submissionId}/participants/{stageId}
	 */
	public function testGetSubmissionParticipantsAssignedToStage() {
		$submission = $this->_getFirstEntity('/submissions');
		$response = $this->_sendRequest('GET', "/submissions/{$submission->id}/participants/" . WORKFLOW_STAGE_ID_SUBMISSION);
		$this->assertSame(200, $response->getStatusCode());

		$data = $this->_getResponseData($response);
		$this->assertTrue(is_array($data));
		$this->assertNotEmpty($data);

		$participant = (array) array_shift($data);
		$this->assertArrayHasKey('id', $participant);
		$this->assertArrayHasKey('_href', $participant);
		$this->assertArrayHasKey('userName', $participant);
		$this->assertArrayHasKey('email', $participant);
		$this->assertArrayHasKey('groups', $participant);
	}
}


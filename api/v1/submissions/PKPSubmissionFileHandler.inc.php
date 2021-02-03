<?php

/**
 * @file api/v1/submissions/PKPSubmissionFileHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_ constants

class PKPSubmissionFileHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'submissions/{submissionId}/files';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'getMany'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionFileId}',
					'handler' => [$this, 'get'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
			],
			'POST' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'add'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
			],
			'PUT' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionFileId}',
					'handler' => [$this, 'edit'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
			],
			'DELETE' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionFileId}',
					'handler' => [$this, 'delete'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
			],
		];
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		$route = $this->getSlimRequest()->getAttribute('route');
		import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy'); // SUBMISSION_FILE_ACCESS_

		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

		if ($route->getName() === 'add') {
			$params = $this->getSlimRequest()->getParsedBody();
			$fileStage = isset($params['fileStage']) ? (int) $params['fileStage'] : 0;
			import('lib.pkp.classes.security.authorization.internal.SubmissionFileStageAccessPolicy');
			$this->addPolicy(new SubmissionFileStageAccessPolicy($fileStage, SUBMISSION_FILE_ACCESS_MODIFY, 'api.submissionFiles.403.unauthorizedFileStageIdWrite'));

		} elseif ($route->getName() === 'getMany') {
			// Anyone passing SubmissionAccessPolicy is allowed to access getMany,
			// but the endpoint will return different files depending on the user's
			// stage assignments.

		} else {
			$accessMode = $this->getSlimRequest()->getMethod() === 'GET'
				? SUBMISSION_FILE_ACCESS_READ
				: SUBMISSION_FILE_ACCESS_MODIFY;
			import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, $accessMode, (int) $route->getArgument('submissionFileId')));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get a collection of submission files
	 *
	 * @param \Slim\Http\Request $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$params = [];

		foreach ($slimRequest->getQueryParams() as $param => $val) {
			switch ($param) {
				case 'fileStages':
				case 'reviewRoundIds':
					if (is_string($val)) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$params[$param] = array_map('intval', $val);
					break;
			}
		}

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$stageAssignments = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		$allowedFileStages = [];

		// Managers can access files for submissions they are not assigned to
		if (empty($stageAssignments)) {
			if (!in_array(ROLE_ID_MANAGER, $userRoles)) {
				return $response->withStatus(403)->withJsonError('api.403.unauthorized');
			}
			// @see PKPSubmissionFileService::getAssignedFileStages() for excluded file stages
			$allowedFileStages = [
				SUBMISSION_FILE_SUBMISSION,
				SUBMISSION_FILE_REVIEW_FILE,
				SUBMISSION_FILE_FINAL,
				SUBMISSION_FILE_COPYEDIT,
				SUBMISSION_FILE_PROOF,
				SUBMISSION_FILE_PRODUCTION_READY,
				SUBMISSION_FILE_ATTACHMENT,
				SUBMISSION_FILE_REVIEW_REVISION,
				SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
				SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
			];

		// Set the allowed file stages based on stage assignment
		// @see PKPSubmissionFileService::getAssignedFileStages() for excluded file stages
		} else {
			$allowedFileStages = Services::get('submissionFile')->getAssignedFileStages($stageAssignments, SUBMISSION_FILE_ACCESS_READ);
		}

		if (empty($params['fileStages'])) {
			$params['fileStages'] = $allowedFileStages;
		} else {
			foreach ($params['fileStages'] as $fileStage) {
				if (!in_array($fileStage, $allowedFileStages)) {
					return $response->withStatus(403)->withJsonError('api.submissionFiles.403.unauthorizedFileStageId');
				}
			}
		}

		// Check if requested reviewRounds are valid
		if (!empty($params['reviewRoundIds'])) {

			// Get the valid review round ids for allowed file stage ids
			$allowedReviewRoundIds = [];
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
			if (!empty(array_intersect([SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION], $params['fileStages']))) {
				$result = $reviewRoundDao->getBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_INTERNAL_REVIEW);
				while ($reviewRound = $result->next()) {
					$allowedReviewRoundIds[] = $reviewRound->getId();
				}
			}
			if (!empty(array_intersect([SUBMISSION_FILE_REVIEW_FILE, SUBMISSION_FILE_REVIEW_REVISION], $params['fileStages']))) {
				$result = $reviewRoundDao->getBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
				while ($reviewRound = $result->next()) {
					$allowedReviewRoundIds[] = $reviewRound->getId();
				}
			}

			foreach ($params['reviewRoundIds'] as $reviewRoundId) {
				if (!in_array($reviewRoundId, $allowedReviewRoundIds)) {
					return $response->withStatus(403)->withJsonError('api.submissionFiles.403.unauthorizedReviewRound');
				}
			}
		}

		\HookRegistry::call('API::submissions::files::params', [&$params, $slimRequest]);

		$params['submissionIds'] = [$this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION)->getId()];

		$items = [];
		$filesIterator = Services::get('submissionFile')->getMany($params);
		$propertyArgs = [
			'request' => $request,
			'slimRequest' => $slimRequest,
			'submission' => $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION),
		];
		foreach ($filesIterator as $file) {
			$items[] = Services::get('submissionFile')->getSummaryProperties($file, $propertyArgs);
		}

		$data = [
			'itemsMax' => Services::get('submissionFile')->getCount($params),
			'items' => $items,
		];

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single submission file
	 *
	 * @param \Slim\Http\Request $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);

		$data = Services::get('submissionFile')->getFullProperties($submissionFile, [
			'request' => $this->getRequest(),
			'slimRequest' 	=> $slimRequest,
			'submission' => $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Add a new submission file
	 *
	 * @param \Slim\Http\Request $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return Response
	 */
	public function add($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		if (empty($_FILES)) {
			return $response->withStatus(400)->withJsonError('api.files.400.noUpload');
		}

		if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			return $this->getUploadErrorResponse($response, $_FILES['file']['error']);
		}

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$extension = $fileManager->parseFileExtension($_FILES['file']['name']);

		$submissionDir = Services::get('submissionFile')->getSubmissionDir($request->getContext()->getId(), $submission->getId());
		$fileId = Services::get('file')->add(
			$_FILES['file']['tmp_name'],
			$submissionDir . '/' . uniqid() . '.' . $extension
		);

		$params = $this->convertStringsToSchema(SCHEMA_SUBMISSION_FILE, $slimRequest->getParsedBody());
		$params['fileId'] = $fileId;
		$params['submissionId'] = $submission->getId();
		$params['uploaderUserId'] = (int) $request->getUser()->getId();

		$primaryLocale = $request->getContext()->getPrimaryLocale();
		$allowedLocales = $request->getContext()->getData('supportedSubmissionLocales');

		// Set the name if not passed with the request
		if (empty($params['name'])) {
			$params['name'][$primaryLocale] = $_FILES['file']['name'];
		}

		// If no genre has been set and there is only one genre possible, set it automatically
		if (empty($params['genreId'])) {
			$genres = DAORegistry::getDAO('GenreDAO')->getEnabledByContextId($request->getContext()->getId());
			list($firstGenre, $secondGenre) = [$genres->next(), $genres->next()];
			if ($firstGenre && !$secondGenre) {
				$params['genreId'] = $firstGenre->getId();
			}
		}

		$errors = Services::get('submissionFile')->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		// Review attachments and discussion files can not be uploaded through this API endpoint
		$notAllowedFileStages = [
			SUBMISSION_FILE_NOTE,
			SUBMISSION_FILE_REVIEW_ATTACHMENT,
			SUBMISSION_FILE_QUERY,
		];
		if (in_array($params['fileStage'], $notAllowedFileStages)) {
			return $response->withStatus(400)->withJsonError('api.submissionFiles.403.unauthorizedFileStageIdWrite');
		}

		// A valid review round is required when uploading to a review file stage
		$reviewFileStages = [
			SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
			SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
			SUBMISSION_FILE_REVIEW_FILE,
			SUBMISSION_FILE_REVIEW_REVISION,
		];
		if (in_array($params['fileStage'], $reviewFileStages)) {
			if (empty($params['assocType']) || $params['assocType'] !== ASSOC_TYPE_REVIEW_ROUND || empty($params['assocId'])) {
				return $response->withStatus(400)->withJsonError('api.submissionFiles.400.missingReviewRoundAssocType');
			}
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$reviewRound = $reviewRoundDao->getById($params['assocId']);
			$stageId = in_array($params['fileStage'], [SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SUBMISSION_FILE_INTERNAL_REVIEW_REVISION])
				? WORKFLOW_STAGE_ID_INTERNAL_REVIEW
				: WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
			if (!$reviewRound
					|| $reviewRound->getData('submissionId') != $params['submissionId']
					|| $reviewRound->getData('stageId') != $stageId) {
				return $response->withStatus(400)->withJsonError('api.submissionFiles.400.reviewRoundSubmissionNotMatch');
			}
		}

		$submissionFile = DAORegistry::getDao('SubmissionFileDAO')->newDataObject();
		$submissionFile->setAllData($params);

		$submissionFile = Services::get('submissionFile')->add($submissionFile, $request);

		$data = Services::get('submissionFile')->getFullProperties($submissionFile, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'submission' => $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Edit a submission file
	 *
	 * @param \Slim\Http\Request $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return Response
	 */
	public function edit($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);

		$params = $this->convertStringsToSchema(SCHEMA_SUBMISSION_FILE, $slimRequest->getParsedBody());

		// Don't allow these properties to be modified
		unset($params['submissionId'], $params['fileId'], $params['uploaderUserId']);

		if (empty($params) && empty($_FILES['file'])) {
			return $response->withStatus(400)->withJsonError('api.submissionsFiles.400.noParams');
		}

		$primaryLocale = $request->getContext()->getPrimaryLocale();
		$allowedLocales = $request->getContext()->getData('supportedSubmissionLocales');

		$errors = Services::get('submissionFile')->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		// Upload a new file
		if (!empty($_FILES['file'])) {

			if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
				return $this->getUploadErrorResponse($response, $_FILES['file']['error']);
			}

			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			$extension = $fileManager->parseFileExtension($_FILES['file']['name']);
			$submissionDir = Services::get('submissionFile')->getSubmissionDir($request->getContext()->getId(), $submission->getId());
			$fileId = Services::get('file')->add(
				$_FILES['file']['tmp_name'],
				$submissionDir . '/' . uniqid() . '.' . $extension
			);

			$params['fileId'] = $fileId;
			$params['uploaderUserId'] = $request->getUser()->getId();
			if (empty($params['name'])) {
				$params['name'][$primaryLocale] = $_FILES['file']['name'];
			}
		}

		$submissionFile = Services::get('submissionFile')->edit($submissionFile, $params, $request);

		$data = Services::get('submissionFile')->getFullProperties($submissionFile, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'submission' => $submission,
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Delete a submission file
	 *
	 * @param \Slim\Http\Request $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return Response
	 */
	public function delete($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);

		$data = Services::get('submissionFile')->getFullProperties($submissionFile, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'submission' => $submission,
		]);

		Services::get('submissionFile')->delete($submissionFile);

		return $response->withJson($data, 200);
	}

	/**
	 * Helper method to get the appropriate response when an error
	 * has occurred during a file upload
	 *
	 * @param APIResponse $response
	 * @param int $error One of the UPLOAD_ERR_ constants
	 * @return APIResponse
	 */
	private function getUploadErrorResponse($response, $error) {
		switch ($error) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return $response->withStatus(400)->withJsonError('api.files.400.fileSize', ['maxSize' => Application::getReadableMaxFileSize()]);
			case UPLOAD_ERR_PARTIAL:
				return $response->withStatus(400)->withJsonError('api.files.400.uploadFailed');
			case UPLOAD_ERR_NO_FILE:
				return $response->withStatus(400)->withJsonError('api.files.400.noUpload');
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
				return $response->withStatus(400)->withJsonError('api.files.400.config');
		}
		return $response->withStatus(400)->withJsonError('api.files.400.uploadFailed');
	}
}

<?php
/**
 * @file api/v1/contexts/PKPTemporaryFilesHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryFilesHandler
 * @ingroup api_v1_users
 *
 * @brief Handle API requests to upload a file and receive a temporary file ID.
 */
import('lib.pkp.classes.handler.APIHandler');

class PKPTemporaryFilesHandler extends APIHandler {
	/**
	 * @copydoc APIHandler::__construct()
	 */
	public function __construct() {
		$this->_handlerPath = 'temporaryFiles';
		$roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR, ROLE_ID_ASSISTANT);
		$this->_endpoints = array(
			'OPTIONS' => array(
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'getOptions'),
					'roles' => $roles,
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'uploadFile'),
					'roles' => $roles,
				),
			),
		);

		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * A helper method which adds the necessary response headers to allow
	 * file uploads
	 *
	 * @param $response Response object
	 * @return Response
	 */
	private function getResponse($response) {
		return $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, X-PINGOTHER, X-File-Name, Cache-Control');
	}

	/**
	 * Upload a requested file
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 * @return Response
	 */
	public function uploadFile($slimRequest, $response, $args) {
		$request = $this->getRequest();

		if (empty($_FILES)) {
			return $response->withStatus(400)->withJsonError('api.temporaryFiles.400.noUpload');
		}

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$fileName = $temporaryFileManager->getFirstUploadedPostName();
		$uploadedFile = $temporaryFileManager->handleUpload($fileName, $request->getUser()->getId());

		if ($uploadedFile === false) {
			if ($temporaryFileManager->uploadError($fileName)) {
				switch ($temporaryFileManager->getUploadErrorCode($fileName)) {
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
					return $response->withStatus(400)->withJsonError('api.temporaryFiles.400.fileSize', ['maxSize' => Application::getReadableMaxFileSize()]);
					case UPLOAD_ERR_PARTIAL:
					return $response->withStatus(400)->withJsonError('api.temporaryFiles.409.uploadFailed');
					case UPLOAD_ERR_NO_FILE:
					return $response->withStatus(400)->withJsonError('api.temporaryFiles.400.noUpload');
					case UPLOAD_ERR_NO_TMP_DIR:
					case UPLOAD_ERR_CANT_WRITE:
					case UPLOAD_ERR_EXTENSION:
					return $response->withStatus(400)->withJsonError('api.temporaryFiles.400.config');
				}
			}
			return $response->withStatus(400)->withJsonError('api.temporaryFiles.409.uploadFailed');
		}

		return $this->getResponse($response->withJson(['id' => $uploadedFile->getId()]));
	}

	/**
	 * Respond affirmatively to a HTTP OPTIONS request with headers which allow
	 * file uploads
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 * @return Response
	 */
	public function getOptions($slimRequest, $response, $args) {
		return $this->getResponse($response);
	}
}

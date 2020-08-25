<?php
/**
 * @file api/v1/contexts/PKPUploadPublicFileHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUploadPublicFileHandler
 * @ingroup api_v1_uploadPublicFile
 *
 * @brief Handle API requests to upload a file to a user's public directory.
 */
import('lib.pkp.classes.handler.APIHandler');

class PKPUploadPublicFileHandler extends APIHandler {
	/**
	 * @copydoc APIHandler::__construct()
	 */
	public function __construct() {
		$this->_handlerPath = '_uploadPublicFile';
		$roles = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR, ROLE_ID_ASSISTANT, ROLE_ID_READER];
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

		if (empty($_FILES) || empty($_FILES['file'])) {
			return $response->withStatus(400)->withJsonError('api.temporaryFiles.400.noUpload');
		}

		$siteDir = Core::getBaseDir() . '/' . Config::getVar('files', 'public_files_dir') . '/site';

		if (!file_exists($siteDir) || !is_writeable($siteDir)) {
			return $response->withStatus(500)->withJsonError('api.publicFiles.500.badFilesDir');
		}
		$userDir = $siteDir . '/images/' . $request->getUser()->getUsername();
		$isUserAllowed = true;
		$allowedDirSize = Config::getVar('files', 'public_user_dir_size', 5000) * 1024;
		$allowedFileTypes = ['gif', 'jpg', 'png'];

		HookRegistry::call('API::uploadPublicFile::permissions', [
			&$userDir,
			&$isUserAllowed,
			&$allowedDirSize,
			&$allowedFileTypes,
			$request,
			$this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES),
		]);

		// Allow plugins to control who can upload files
		if (!$isUserAllowed) {
			return $response->withStatus(403)->withJsonError('api.publicFiles.403.unauthorized');
		}

		// Don't allow user to exceed the alotted space in their public directory
		$currentSize = 0;
		if ($allowedDirSize > 0 && file_exists($userDir)) {
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($userDir, FilesystemIterator::SKIP_DOTS)) as $object) {
				$currentSize += $object->getSize();
			}
		}
		if (($currentSize + $_FILES['file']['size']) > $allowedDirSize) {
			return $response->withStatus(413)->withJsonError('api.publicFiles.413.noDirSpace', [
				'fileUploadSize' => ceil($_FILES['file']['size']/1024),
				'dirSizeLeft' => ceil(($allowedDirSize - $currentSize)/1024),
			]);
		}

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$filename = $fileManager->getUploadedFileName('file');
		$filename = trim(
			preg_replace(
				"/[^a-z0-9\.\-]+/",
				"",
				str_replace(
					[' ', '_', ':'],
					'-',
					strtolower($filename)
				)
			)
		);
		$extension = pathinfo(strtolower(trim($filename)), PATHINFO_EXTENSION);

		// Only allow permitted file types
		if (!in_array($extension, $allowedFileTypes)) {
			return $response->withStatus(400)->withJsonError('api.publicFiles.400.extensionNotSupported', [
				'fileTypes' => join(__('common.commaListSeparator'), $allowedFileTypes)
			]);
		}

		// Perform additional checks on images
		if (in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'jpe'])) {
			if (getimagesize($_FILES['file']['tmp_name']) === false) {
				return $response->withStatus(400)->withJsonError('api.publicFiles.400.invalidImage');
			}
			$extensionFromMimeType = $fileManager->getImageExtension(PKPString::mime_content_type($_FILES['file']['tmp_name']));
			if ($extensionFromMimeType !== '.' . $extension) {
				return $response->withStatus(400)->withJsonError('api.publicFiles.400.mimeTypeNotMatched');
			}
		}

		// Save the file
		$destinationPath = $siteDir . '/images/' . $request->getUser()->getUsername() . '/' . $filename;
		$success = $fileManager->uploadFile('file', $destinationPath);

		if ($success === false) {
			if ($fileManager->uploadError($filename)) {
				switch ($fileManager->getUploadErrorCode($filename)) {
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

		return $this->getResponse($response->withJson([
			'url' => $request->getBaseUrl() . '/' .
					Config::getVar('files', 'public_files_dir') . '/site/images/' .
					$request->getUser()->getUsername() . '/' .
					$filename,
		]));
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

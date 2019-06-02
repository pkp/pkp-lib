<?php
/**
 * @file classes/services/PKPSiteService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for the overall site
 */

namespace PKP\Services;

use \Application;
use \DAORegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;

class PKPSiteService implements EntityPropertyInterface {

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($site, $props, $args = null) {
		$request = $args['request'];
		$router = $request->getRouter();
		$dispatcher = $request->getDispatcher();

		$values = array();
		foreach ($props as $prop) {
			$values[$prop] = $site->getData($prop);
		}

		$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_SITE, $values, $site->getSupportedLocales());

		\HookRegistry::call('Site::getProperties', array(&$values, $site, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($site, $args = null) {
		return $this->getFullProperties($site, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($site, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_SITE);

		return $this->getProperties($site, $props, $args);
	}

	/**
	 * Validate the properties of a site
	 *
	 * Passes the properties through the SchemaService to validate them, and
	 * performs any additional checks needed to validate a site.
	 *
	 * This does NOT authenticate the current user to perform the action.
	 *
	 * @param $props array The data to validate
	 * @param $allowedLocales array Which locales are allowed for this context
	 * @param $primaryLocale string
	 * @return array List of error messages. The array keys are property names
	 */
	public function validate($props, $allowedLocales, $primaryLocale) {
		\AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_ADMIN,
			LOCALE_COMPONENT_APP_ADMIN,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_SITE, $allowedLocales),
			[
				'primaryLocale.regex' => __('validator.localeKey'),
				'supportedLocales.regex' => __('validator.localeKey'),
			]
		);

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales(
			$validator,
			$schemaService->getMultilingualProps(SCHEMA_SITE),
			$allowedLocales
		);

		// Don't allow an empty value for the primary locale for some fields
		\ValidatorFactory::requirePrimaryLocale(
			$validator,
			['title', 'contactName', 'contactEmail'],
			$props,
			$allowedLocales,
			$primaryLocale
		);

		// If a new file has been uploaded, check that the temporary file exists and
		// the current user owns it
		$user = Application::get()->getRequest()->getUser();
		\ValidatorFactory::temporaryFilesExist(
			$validator,
			['pageHeaderTitleImage', 'styleSheet'],
			['pageHeaderTitleImage'],
			$props,
			$allowedLocales,
			$user ? $user->getId() : null
		);

		// If sidebar blocks are passed, ensure the block plugin exists and is
		// enabled
		$validator->after(function($validator) use ($props) {
			if (!empty($props['sidebar']) && !$validator->errors()->get('sidebar')) {
				$plugins = \PluginRegistry::loadCategory('blocks', true);
				foreach ($props['sidebar'] as $pluginName) {
					if (empty($plugins[$pluginName])) {
						$validator->errors()->add('sidebar', __('manager.setup.layout.sidebar.invalidBlock', ['name' => $pluginName]));
					}
				}
			}
		});

		// Ensure the theme plugin is installed and enabled
		$validator->after(function($validator) use ($props) {
			if (!empty($props['themePluginPath']) && !$validator->errors()->get('themePluginPath')) {
				$plugins = \PluginRegistry::loadCategory('themes', true);
				$found = false;
				foreach ($plugins as $plugin) {
					if ($props['themePluginPath'] === $plugin->getDirName()) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$validator->errors()->add('themePluginPath', __('manager.setup.theme.notFound'));
				}
			}
		});

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_SITE), $allowedLocales);
		}

		\HookRegistry::call('Site::validate', array(&$errors, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * Edit the site
	 *
	 * This does not check if the user is authorized to edit the site, or validate or sanitize
	 * the new content.
	 *
	 * @param $site Context The context to edit
	 * @param $params Array Key/value array of new data
	 * @param $request Request
	 * @return Site
	 */
	public function edit($site, $params, $request) {
		$siteDao = DAORegistry::getDAO('SiteDAO');

		// Move uploaded files into place and update the params
		$userId = $request->getUser() ? $request->getUser()->getId() : null;
		$supportedLocales = $site->getSupportedLocales();
		if (array_key_exists('pageHeaderTitleImage', $params)) {
			foreach ($supportedLocales as $localeKey) {
				if (!array_key_exists($localeKey, $params['pageHeaderTitleImage'])) {
					continue;
				}
				$params['pageHeaderTitleImage'][$localeKey] = $this->_saveFileParam($site, $params['pageHeaderTitleImage'][$localeKey], 'pageHeaderTitleImage', $userId, $localeKey, true);
			}
		}
		if (array_key_exists('styleSheet', $params)) {
			$params['styleSheet'] = $this->_saveFileParam($site, $params['styleSheet'], 'styleSheet', $userId);
		}

		$newSite = $siteDao->newDataObject();
		$newSite->_data = array_merge($site->_data, $params);

		\HookRegistry::call('Site::edit', array($newSite, $site, $params, $request));

		$siteDao->updateObject($newSite);
		$newSite = $siteDao->getSite();

		return $newSite;
	}

	/**
	 * Move a temporary file to the site's public directory
	 *
	 * @param $context Context
	 * @param $temporaryFile TemporaryFile
	 * @param $fileNameBase string Unique identifier to use for the filename. The
	 *  Extension and locale will be appended.
	 * @param $userId int ID of the user who uploaded the temporary file
	 * @param $localeKey string Example: en_US. Leave empty for a file that is
	 *  not localized.
	 * @return string|boolean The new filename or false on failure
	 */
	public function moveTemporaryFile($context, $temporaryFile, $fileNameBase, $userId, $localeKey = '') {
		import('classes.file.PublicFileManager');
		$publicFileManager = new \PublicFileManager();
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new \TemporaryFileManager();

		$fileName = $fileNameBase;
		if ($localeKey) {
			$fileName .= '_' . $localeKey;
		}

		$extension = $publicFileManager->getDocumentExtension($temporaryFile->getFileType());
		if (!$extension) {
			$extension = $publicFileManager->getImageExtension($temporaryFile->getFileType());
		}
		$fileName .= $extension;

		if (!$publicFileManager->copyFile($temporaryFile->getFilePath(), $publicFileManager->getSiteFilesPath() . '/' . $fileName)) {
			return false;
		}

		$temporaryFileManager->deleteById($temporaryFile->getId(), $userId);

		return $fileName;
	}

	/**
	 * Handle a site setting for an uploaded file
	 *
	 * - Moves the temporary file to the public directory
	 * - Resets the param value to what is expected to be stored in the db
	 *
	 * This method is protected because all operations which edit the site should
	 * go through the editSite method in order to ensure that the appropriate hooks are fired.
	 *
	 * @param $site Site The site being edited
	 * @param $value mixed The param value to be saved. Contains the temporary
	 *  file ID if a new file has been uploaded.
	 * @param $settingName string The name of the setting to save, typically used
	 *  in the filename.
	 * @param $userId integer ID of the user who owns the temporary file
	 * @param $localeKey string Optional. Used in the filename for multilingual
	 *  properties.
	 * @param $isImage boolean Optional. For image files which return alt text,
	 *  width, height, etc in the param value.
	 * @return string|array|null New param value or null on failure
	 */
	protected function _saveFileParam($site, $value, $settingName, $userId, $localeKey = '', $isImage = false) {
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new \TemporaryFileManager();

		// If the value is null, clean up any existing file in the system
		if (is_null($value)) {
			$setting = $site->getData($settingName, $localeKey);
			if ($setting) {
				$fileName = $isImage ? $setting['uploadName'] : $setting;
				import('classes.file.PublicFileManager');
				$publicFileManager = new \PublicFileManager();
				$publicFileManager->removeSiteFile($fileName);
			}
			return null;
		}

		// Get uploaded file to move
		if ($isImage) {
			if (empty($value['temporaryFileId'])) {
				return $value; // nothing to upload
			}
			$temporaryFileId = (int) $value['temporaryFileId'];
		} else {
			if (!ctype_digit($value)) {
				return $value; // nothing to upload
			}
			$temporaryFileId = (int) $value;
		}

		$temporaryFile = $temporaryFileManager->getFile($temporaryFileId, $userId);
		$fileName = $this->moveTemporaryFile($site, $temporaryFile, $settingName, $userId, $localeKey);

		if ($fileName) {
			// Get the details for image uploads
			if ($isImage) {
				import('classes.file.PublicFileManager');
				$publicFileManager = new \PublicFileManager();

				list($width, $height) = getimagesize($publicFileManager->getSiteFilesPath() . '/' . $fileName);
				$altText = !empty($value['altText']) ? $value['altText'] : '';

				return [
					'originalFilename' => $temporaryFile->getOriginalFileName(),
					'uploadName' => $fileName,
					'width' => $width,
					'height' => $height,
					'dateUploaded' => \Core::getCurrentDate(),
					'altText' => $altText,
				];
			} else {
				return $fileName;
			}
		}

		return false;
	}
}

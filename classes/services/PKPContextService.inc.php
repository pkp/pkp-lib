<?php
/**
 * @file classes/services/PKPContextService.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for contexts (journals
 *  and presses)
 */

namespace PKP\Services;

use \DBResultRange;
use \Application;
use \DAOResultFactory;
use \DAORegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use \APP\Services\QueryBuilders\ContextQueryBuilder;

import('lib.pkp.classes.db.DBResultRange');

abstract class PKPContextService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {

	/**
	 * @var array List of file directories to create on installation. Use %d to
	 *  use the context ID in a file path.
	 */
	var $installFileDirs;

	/**
	 * @var array The file directory where context files are stored. Expects
	 *  `journals` or `presses`.
	 */
	var $contextsFileDirName;

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($contextId) {
		return Application::getContextDAO()->getById($contextId);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getCount()
	 */
	public function getCount($args = []) {
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getIds()
	 */
	public function getIds($args = []) {
		return $this->getQueryBuilder($args)->getIds();
	}

	/**
	 * Get a collection of Context objects limited, filtered
	 * and sorted by $args
	 *
	 * @param array $args {
	 * 		@option bool isEnabled
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * }
	 * @return Iterator
	 */
	public function getMany($args = array()) {
		$range = null;
		if (isset($args['count'])) {
			import('lib.pkp.classes.db.DBResultRange');
			$range = new \DBResultRange($args['count'], null, isset($args['offset']) ? $args['offset'] : 0);
		}
		// Pagination is handled by the DAO, so don't pass count and offset
		// arguments to the QueryBuilder.
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		$contextListQO = $this->getQueryBuilder($args)->getQuery();
		$contextDao = Application::getContextDAO();
		$result = $contextDao->retrieveRange($contextListQO->toSql(), $contextListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $contextDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = array()) {
		// Don't accept args to limit the results
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getQueryBuilder()
	 * @return ContextQueryBuilder
	 */
	public function getQueryBuilder($args = array()) {

		$defaultArgs = array(
			'isEnabled' => null,
			'searchPhrase' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$contextListQB = new ContextQueryBuilder();
		$contextListQB
			->filterByIsEnabled($args['isEnabled'])
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('Context::getMany::queryBuilder', array($contextListQB, $args));

		return $contextListQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($context, $props, $args = null) {
		$slimRequest = $args['slimRequest'];
		$request = $args['request'];
		$dispatcher = $request->getDispatcher();

		$values = array();

		foreach ($props as $prop) {
			switch ($prop) {
				case 'url':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						$context->getPath()
					);
					break;
				case '_href':
					$values[$prop] = null;
					if (!empty($slimRequest)) {
						$route = $slimRequest->getAttribute('route');
						$values[$prop] = $dispatcher->url(
							$args['request'],
							ROUTE_API,
							$context->getData('urlPath'),
							'contexts/' . $context->getId()
						);
					}
					break;
				default:
					$values[$prop] = $context->getData($prop);
					break;
			}
		}

		$supportedLocales = empty($args['supportedLocales']) ? $context->getSupportedFormLocales() : $args['supportedLocales'];
		$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_CONTEXT, $values, $supportedLocales);

		\HookRegistry::call('Context::getProperties', array(&$values, $context, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($context, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_CONTEXT);

		return $this->getProperties($context, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($context, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_CONTEXT);

		return $this->getProperties($context, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
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
			$schemaService->getValidationRules(SCHEMA_CONTEXT, $allowedLocales),
			[
				'urlPath.regex' => __('admin.contexts.form.pathAlphaNumeric'),
				'primaryLocale.regex' => __('validator.localeKey'),
				'supportedFormLocales.regex' => __('validator.localeKey'),
				'supportedLocales.regex' => __('validator.localeKey'),
				'supportedSubmissionLocales.*.regex' => __('validator.localeKey'),
			]
		);

		// Check required fields
		\ValidatorFactory::required(
			$validator,
			$action,
			$schemaService->getRequiredProps(SCHEMA_CONTEXT),
			$schemaService->getMultilingualProps(SCHEMA_CONTEXT),
			$allowedLocales,
			$primaryLocale
		);

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_CONTEXT), $allowedLocales);

		// Ensure that a urlPath, if provided, does not already exist
		$validator->after(function($validator) use ($action, $props) {
			if (isset($props['urlPath']) && !$validator->errors()->get('urlPath')) {
				$contextDao = Application::getContextDAO();
				$contextWithPath = $contextDao->getByPath($props['urlPath']);
				if ($contextWithPath) {
					if (!($action === VALIDATE_ACTION_EDIT
							&& isset($props['id'])
							&& (int) $contextWithPath->getId() === $props['id'])) {
						$validator->errors()->add('urlPath', __('admin.contexts.form.pathExists'));
					}
				}
			}
		});

		// If a new file has been uploaded, check that the temporary file exists and
		// the current user owns it
		$user = Application::get()->getRequest()->getUser();
		\ValidatorFactory::temporaryFilesExist(
			$validator,
			['favicon', 'homepageImage', 'pageHeaderLogoImage', 'styleSheet'],
			['favicon', 'homepageImage', 'pageHeaderLogoImage'],
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
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_CONTEXT), $allowedLocales);
		}

		\HookRegistry::call('Context::validate', array(&$errors, $action, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($context, $request) {
		$site = $request->getSite();
		$currentUser = $request->getUser();
		$contextDao = Application::getContextDAO();
		$contextSettingsDao = Application::getContextSettingsDAO();

		if (!$context->getData('primaryLocale')) {
			$context->setData('primaryLocale', $site->getPrimaryLocale());
		}
		if (!$context->getData('supportedLocales')) {
			$context->setData('supportedLocales', $site->getSupportedLocales());
		}

		// Specify values needed to render default locale strings
		$localeParams = array(
			'indexUrl' => $request->getIndexUrl(),
			'primaryLocale' => $context->getData('primaryLocale'),
			'contextName' => $context->getData('name', $context->getPrimaryLocale()),
			'contextPath' => $context->getData('urlPath'),
			'contextUrl' => $request->getDispatcher()->url(
				$request,
				ROUTE_PAGE,
				$context->getPath()
			),
		);

		// Allow plugins to extend the $localeParams for new property defaults
		\HookRegistry::call('Context::defaults::localeParams', array(&$localeParams, $context, $request));

		$context = Services::get('schema')->setDefaults(
			SCHEMA_CONTEXT,
			$context,
			$context->getData('supportedLocales'),
			$context->getData('primaryLocale'),
			$localeParams
		);

		if (!$context->getData('supportedFormLocales')) {
			$context->setData('supportedFormLocales', [$context->getData('primaryLocale')]);
		}
		if (!$context->getData('supportedSubmissionLocales')) {
			$context->setData('supportedSubmissionLocales', [$context->getData('primaryLocale')]);
		}

		$contextDao->insertObject($context);
		$contextDao->resequence();

		$context = $this->get($context->getId());

		// Move uploaded files into place and update the settings
		$supportedLocales = $context->getSupportedFormLocales();
		$fileUploadProps = ['favicon', 'homepageImage', 'pageHeaderLogoImage'];
		$params = [];
		foreach ($fileUploadProps as $fileUploadProp) {
			$value = $context->getData($fileUploadProp);
			if (empty($value)) {
				continue;
			}
			foreach ($supportedLocales as $localeKey) {
				if (!array_key_exists($localeKey, $value)) {
					continue;
				}
				$value[$localeKey] = $this->_saveFileParam($context, $value[$localeKey], $fileUploadProp, $currentUser->getId(), $localeKey, true);
			}
			$params[$fileUploadProp] = $value;
		}
		if (!empty($params['styleSheet'])) {
			$params['styleSheet'] = $this->_saveFileParam($context, $params['styleSheet'], 'styleSheet', $userId);
		}
		$context = $this->edit($context, $params, $request);

		$genreDao = \DAORegistry::getDAO('GenreDAO');
		$genreDao->installDefaults($context->getId(), $context->getData('supportedLocales'));

		$userGroupDao = \DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->installSettings($context->getId(), 'registry/userGroups.xml');

		$managerUserGroup = $userGroupDao->getDefaultByRoleId($context->getId(), ROLE_ID_MANAGER);
		$userGroupDao->assignUserToGroup($currentUser->getId(), $managerUserGroup->getId());

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new \FileManager();
		foreach ($this->installFileDirs as $dir) {
			$fileManager->mkdir(sprintf($dir, $this->contextsFileDirName, $context->getId()));
		}

		$navigationMenuDao = \DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenuDao->installSettings($context->getId(), 'registry/navigationMenus.xml');

		// Load all plugins so they can hook in and add their installation settings
		\PluginRegistry::loadAllPlugins();

		\HookRegistry::call('Context::add', array($context, $request));

		return $context;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($context, $params, $request) {
		$contextDao = Application::getContextDao();

		// Move uploaded files into place and update the params
		$userId = $request->getUser() ? $request->getUser()->getId() : null;
		$supportedLocales = $context->getSupportedFormLocales();
		$fileUploadParams = ['favicon', 'homepageImage', 'pageHeaderLogoImage'];
		foreach ($fileUploadParams as $fileUploadParam) {
			if (!array_key_exists($fileUploadParam, $params)) {
				continue;
			}
			foreach ($supportedLocales as $localeKey) {
				if (!array_key_exists($localeKey, $params[$fileUploadParam])) {
					continue;
				}
				$params[$fileUploadParam][$localeKey] = $this->_saveFileParam($context, $params[$fileUploadParam][$localeKey], $fileUploadParam, $userId, $localeKey, true);
			}
		}
		if (array_key_exists('styleSheet', $params)) {
			$params['styleSheet'] = $this->_saveFileParam($context, $params['styleSheet'], 'styleSheet', $userId);
		}

		$newContext = $contextDao->newDataObject();
		$newContext->_data = array_merge($context->_data, $params);

		\HookRegistry::call('Context::edit', array($newContext, $context, $params, $request));

		$contextDao->updateObject($newContext);
		$newContext = $this->get($newContext->getId());

		return $newContext;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($context) {
		\HookRegistry::call('Context::delete::before', array($context));

		$contextDao = Application::getContextDao();
		$contextDao->deleteObject($context);

		$userGroupDao = \DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->deleteAssignmentsByContextId($context->getId());
		$userGroupDao->deleteByContextId($context->getId());

		$genreDao = \DAORegistry::getDAO('GenreDAO');
		$genreDao->deleteByContextId($context->getId());

		$announcementDao = \DAORegistry::getDAO('AnnouncementDAO');
		$announcementDao->deleteByAssoc($context->getAssocType(), $context->getId());

		$announcementTypeDao = \DAORegistry::getDAO('AnnouncementTypeDAO');
		$announcementTypeDao->deleteByAssoc($context->getAssocType(), $context->getId());

		Services::get('emailTemplate')->restoreDefaults($context->getId());

		$pluginSettingsDao = \DAORegistry::getDAO('PluginSettingsDAO');
		$pluginSettingsDao->deleteByContextId($context->getId());

		$reviewFormDao = \DAORegistry::getDAO('ReviewFormDAO');
		$reviewFormDao->deleteByAssoc($context->getAssocType(), $context->getId());

		$navigationMenuDao = \DAORegistry::getDAO('NavigationMenuDAO');
		$navigationMenuDao->deleteByContextId($context->getId());

		$navigationMenuItemDao = \DAORegistry::getDAO('NavigationMenuItemDAO');
		$navigationMenuItemDao->deleteByContextId($context->getId());

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new \FileManager($context->getId());
		$contextPath = \Config::getVar('files', 'files_dir') . '/' . $this->contextsFileDirName . '/' . $context->getId();
		$fileManager->rmtree($contextPath);

		\HookRegistry::call('Context::delete', array($context));
	}

	/**
	 * Restore default values for context settings in a specific local
	 *
	 * Updates multilingual values of a context, restoring default values in a
	 * specific context. This may be useful when a new language has been added
	 * after a context has been created, or when translations change and a journal
	 * wants to take advantage of the new values.
	 *
	 * @param $context Context The context to restore default values for
	 * @param $request Request
	 * @param $locale string Locale key to restore defaults for. Example: `en_US`
	 */
	public function restoreLocaleDefaults($context, $request, $locale) {
		\AppLocale::reloadLocale($locale);
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_DEFAULT, LOCALE_COMPONENT_APP_DEFAULT, $locale);

		// Specify values needed to render default locale strings
		$localeParams = array(
			'indexUrl' => $request->getIndexUrl(),
			'contextPath' => $context->getData('urlPath'),
			'journalPath' => $context->getData('urlPath'), // DEPRECATED
			'primaryLocale' => $context->getData('primaryLocale'),
			'journalName' => $context->getData('name', $locale), // DEPRECATED
			'contextName' => $context->getData('name', $locale),
			'contextUrl' => $request->getDispatcher()->url(
				$request,
				ROUTE_PAGE,
				$context->getPath()
			),
		);

		// Allow plugins to extend the $localeParams for new property defaults
		\HookRegistry::call('Context::restoreLocaleDefaults::localeParams', array(&$localeParams, $context, $request, $locale));

		$localeDefaults = Services::get('schema')->getLocaleDefaults(SCHEMA_CONTEXT, $locale, $localeParams);

		$params = [];
		foreach ($localeDefaults as $paramName => $value) {
			$params[$paramName] = array_merge(
				(array) $context->getData($paramName),
				[$locale => $localeDefaults[$paramName]]
			);
		}

		return $this->edit($context, $params, $request);
	}

	/**
	 * Move a temporary file to the context's public directory
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

		$result = $publicFileManager->copyContextFile(
			$context->getId(),
			$temporaryFile->getFilePath(),
			$fileName
		);

		if (!$result) {
			return false;
		}

		$temporaryFileManager->deleteById($temporaryFile->getId(), $userId);

		return $fileName;
	}

	/**
	 * Handle a context setting for an uploaded file
	 *
	 * - Moves the temporary file to the public directory
	 * - Resets the param value to what is expected to be stored in the db
	 * - If a null value is passed, deletes any existing file
	 *
	 * This method is protected because all operations which edit contexts should
	 * go through the add and edit methods in order to ensure that
	 * the appropriate hooks are fired.
	 *
	 * @param $context Context The context being edited
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
	protected function _saveFileParam($context, $value, $settingName, $userId, $localeKey = '', $isImage = false) {
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new \TemporaryFileManager();

		// If the value is null, clean up any existing file in the system
		if (is_null($value)) {
			$setting = $context->getData($settingName, $localeKey);
			if ($setting) {
				$fileName = $isImage ? $setting['uploadName'] : $setting;
				import('classes.file.PublicFileManager');
				$publicFileManager = new \PublicFileManager();
				$publicFileManager->removeContextFile($context->getId(), $fileName);
			}
			return null;
		}

		// Check if there is something to upload
		if (empty($value['temporaryFileId'])) {
			return $value;
		}

		$temporaryFile = $temporaryFileManager->getFile((int) $value['temporaryFileId'], $userId);
		$fileName = $this->moveTemporaryFile($context, $temporaryFile, $settingName, $userId, $localeKey);

		if ($fileName) {
			// Get the details for image uploads
			if ($isImage) {
				import('classes.file.PublicFileManager');
				$publicFileManager = new \PublicFileManager();

				$filePath = $publicFileManager->getContextFilesPath($context->getId());
				list($width, $height) = getimagesize($filePath . '/' . $fileName);
				$altText = !empty($value['altText']) ? $value['altText'] : '';

				return [
					'name' => $temporaryFile->getOriginalFileName(),
					'uploadName' => $fileName,
					'width' => $width,
					'height' => $height,
					'dateUploaded' => \Core::getCurrentDate(),
					'altText' => $altText,
				];
			} else {
				return [
					'name' => $temporaryFile->getOriginalFileName(),
					'uploadName' => $fileName,
					'dateUploaded' => \Core::getCurrentDate(),
				];
			}
		}

		return false;
	}
}

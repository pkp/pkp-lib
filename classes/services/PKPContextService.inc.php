<?php
/**
 * @file classes/services/PKPContextService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
use \ServicesContainer;
use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

import('lib.pkp.classes.db.DBResultRange');

abstract class PKPContextService extends PKPBaseEntityPropertyService {
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
	 * Constructor
	 */
	public function __construct() {
		parent::__construct($this);
	}

	/**
	 * Get contexts
	 *
	 * @param array $args {
	 * 		@option bool isEnabled
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * }
	 * @return array
	 */
	public function getContexts($args = array()) {
		$contextListQB = $this->_buildGetContextsQueryObject($args);
		$contextListQO = $contextListQB->get();
		$range = $this->getRangeByArgs($args);
		$contextDao = Application::getContextDAO();
		$result = $contextDao->retrieveRange($contextListQO->toSql(), $contextListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $contextDao, '_fromRow');

		return $queryResults->toArray();
	}

	/**
	 * Get max count of contexts matching a query request
	 *
	 * @see self::getContexts()
	 * @return int
	 */
	public function getContextsMaxCount($args = array()) {
		$contextListQB = $this->_buildGetContextsQueryObject($args);
		$countQO = $contextListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$contextDao = Application::getContextDAO();
		$countResult = $contextDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $contextDao, '_fromRow');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the contexts query object for getContexts requests
	 *
	 * @see self::getContexts()
	 * @return object Query object
	 */
	private function _buildGetContextsQueryObject($args = array()) {

		$defaultArgs = array(
			'isEnabled' => null,
			'searchPhrase' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$contextListQB = $this->getContextListQueryBuilder();
		$contextListQB
			->filterByIsEnabled($args['isEnabled'])
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('Context::getContexts::queryBuilder', array($contextListQB, $args));

		return $contextListQB;
	}

	/**
	 * Get a single context
	 *
	 * @param int $contextId
	 * @return Context|null
	 */
	public function getContext($contextId) {
		return Application::getContextDAO()->getById($contextId);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($context, $props, $args = null) {
		$slimRequest = $args['slimRequest'];
		$request = $args['request'];
		$router = $request->getRouter();
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
						$arguments = $route->getArguments();
						$values[$prop] = $router->getApiUrl(
							$request,
							$arguments['contextPath'],
							$arguments['version'],
							'contexts',
							$context->getId()
						);
					}
					break;
				default:
					$values[$prop] = $context->getData($prop);
					break;
			}
		}

		$supportedLocales = empty($args['supportedLocales']) ? $context->getSupportedLocales() : $args['supportedLocales'];
		$values = ServicesContainer::instance()->get('schema')->addMissingMultilingualValues(SCHEMA_CONTEXT, $values, $supportedLocales);

		\HookRegistry::call('Context::getProperties', array(&$values, $context, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($context, $args = null) {
		$props = ServicesContainer::instance()
			->get('schema')
			->getSummaryProps(SCHEMA_CONTEXT);

		return $this->getProperties($context, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($context, $args = null) {
		$props = ServicesContainer::instance()
			->get('schema')
			->getFullProps(SCHEMA_CONTEXT);

		return $this->getProperties($context, $props, $args);
	}

	/**
	 * Helper function to return the app-specific context list query builder
	 *
	 * @return \PKP\Services\QueryBuilders\PKPContextListQueryBuilder
	 */
	abstract function getContextListQueryBuilder();

	/**
	 * Validate the properties of a context
	 *
	 * Passes the properties through the SchemaService to validate them, and
	 * performs any additional checks needed to validate a context.
	 *
	 * This does NOT authenticate the current user to perform the action.
	 *
	 * @param $action string The type of action required. One of the
	 *  VALIDATE_ACTION_... constants
	 * @param $props array The data to validate
	 * @param $allowedLocales array Which locales are allowed for this context
	 * @param $primaryLocale string
	 * @return array List of error messages. The array keys are property names
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		\AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_ADMIN,
			LOCALE_COMPONENT_APP_ADMIN,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);
		$schemaService = ServicesContainer::instance()->get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_CONTEXT, $allowedLocales),
			[
				'path.regex' => __('admin.contexts.form.pathAlphaNumeric'),
				'primaryLocale.regex' => __('validator.localeKey'),
				'supportedFormLocales.regex' => __('validator.localeKey'),
				'supportedLocales.regex' => __('validator.localeKey'),
				'supportedSubmissionLocales.*.regex' => __('validator.localeKey'),
			]
		);

		// Check required fields if we're adding a context
		if ($action === VALIDATE_ACTION_ADD) {
			\ValidatorFactory::required(
				$validator,
				$schemaService->getRequiredProps(SCHEMA_CONTEXT),
				$schemaService->getMultilingualProps(SCHEMA_CONTEXT),
				$primaryLocale
			);
		}

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_CONTEXT), $allowedLocales);

		// Don't allow an empty value for the primary locale of the name field
		\ValidatorFactory::requirePrimaryLocale(
			$validator,
			['name'],
			$props,
			$allowedLocales,
			$primaryLocale
		);

		// Ensure that a path, if provided, does not already exist
		$validator->after(function($validator) use ($action, $props) {
			if (isset($props['path']) && !$validator->errors()->get('path')) {
				$contextDao = Application::getContextDAO();
				$contextWithPath = $contextDao->getByPath($props['path']);
				if ($contextWithPath) {
					if (!($action === VALIDATE_ACTION_EDIT
							&& isset($props['id'])
							&& (int) $contextWithPath->getId() === $props['id'])) {
						$validator->errors()->add('path', __('admin.contexts.form.pathExists'));
					}
				}
			}
		});

		// If a new file has been uploaded, check that the temporary file exists and
		// the current user owns it
		$user = Application::getRequest()->getUser();
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
	 * Add a new context
	 *
	 * This does not check if the user is authorized to add a context, or
	 * validate or sanitize this context.
	 *
	 * @param $context Context
	 * @param $request Request
	 * @return Context
	 */
	public function addContext($context, $request) {
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
			'journalPath' => $context->getData('path'),
			'primaryLocale' => $context->getData('primaryLocale'),
			'journalName' => $context->getData('name', $context->getPrimaryLocale()),
			'contextName' => $context->getData('name', $context->getPrimaryLocale()),
			'contextUrl' => $request->getDispatcher()->url(
				$request,
				ROUTE_PAGE,
				$context->getPath()
			),
		);

		// Allow plugins to extend the $localeParams for new property defaults
		\HookRegistry::call('Context::defaults::localeParams', array(&$localeParams, $context, $request));

		$context = ServicesContainer::instance()
			->get('schema')
			->setDefaults(
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

		$context = $this->getContext($context->getId());

		// Move uploaded files into place and update the settings
		$supportedLocales = $context->getSupportedLocales();
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
		$context = $this->editContext($context, $params, $request);

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
	 * Edit a context
	 *
	 * This does not check if the user is authorized to edit a context, or
	 * validate or sanitize the new content.
	 *
	 * @param $context Context The context to edit
	 * @param $params Array Key/value array of new data
	 * @param $request Request
	 * @return Context
	 */
	public function editContext($context, $params, $request) {
		$contextDao = Application::getContextDao();

		// Move uploaded files into place and update the params
		$userId = $request->getUser() ? $request->getUser()->getId() : null;
		$supportedLocales = $context->getSupportedLocales();
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
		$newContext = $this->getContext($newContext->getId());

		return $newContext;
	}

	/**
	 * Delete a context
	 *
	 * This does not check if the user is authorized to delete a context or if the
	 * context exists.
	 *
	 * @param $context Context
	 * @return boolean
	 */
	public function deleteContext($context) {
		$contextDao = Application::getContextDao();
		$contextDao->deleteObject($context);

		$userGroupDao = \DAORegistry::getDAO('UserGroupDAO');
		$userGroupDao->deleteAssignmentsByContextId($context->getId());
		$userGroupDao->deleteByContextId($context->getId());

		$genreDao = \DAORegistry::getDAO('GenreDAO');
		$genreDao->deleteByContextId($context->getId());

		$announcementDao = \DAORegistry::getDAO('AnnouncementDAO');
		$announcementDao->deleteByAssoc(ASSOC_TYPE_JOURNAL, $context->getId());

		$announcementTypeDao = \DAORegistry::getDAO('AnnouncementTypeDAO');
		$announcementTypeDao->deleteByAssoc(ASSOC_TYPE_JOURNAL, $context->getId());

		$emailTemplateDao = \DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->deleteEmailTemplatesByContext($context->getId());

		$pluginSettingsDao = \DAORegistry::getDAO('PluginSettingsDAO');
		$pluginSettingsDao->deleteByContextId($context->getId());

		$reviewFormDao = \DAORegistry::getDAO('ReviewFormDAO');
		$reviewFormDao->deleteByAssoc(ASSOC_TYPE_JOURNAL, $context->getId());

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
	 * @param $locale string Locale key to restore defaults for. Example: `en__US`
	 */
	public function restoreLocaleDefaults($context, $request, $locale) {
		\AppLocale::reloadLocale($locale);
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_DEFAULT, LOCALE_COMPONENT_APP_DEFAULT, $locale);

		// Specify values needed to render default locale strings
		$localeParams = array(
			'indexUrl' => $request->getIndexUrl(),
			'journalPath' => $context->getData('path'),
			'primaryLocale' => $context->getData('primaryLocale'),
			'journalName' => $context->getData('name', $locale),
			'contextName' => $context->getData('name', $locale),
			'contextUrl' => $request->getDispatcher()->url(
				$request,
				ROUTE_PAGE,
				$context->getPath()
			),
		);

		// Allow plugins to extend the $localeParams for new property defaults
		\HookRegistry::call('Context::restoreLocaleDefaults::localeParams', array(&$localeParams, $context, $request, $locale));

		$localeDefaults = ServicesContainer::instance()
			->get('schema')
			->getLocaleDefaults(SCHEMA_CONTEXT, $locale, $localeParams);

		$params = [];
		foreach ($localeDefaults as $paramName => $value) {
			$params[$paramName] = array_merge(
				(array) $context->getData($paramName),
				[$locale => $localeDefaults[$paramName]]
			);
		}

		return $this->editContext($context, $params, $request);
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
			$context->getAssoctype(),
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
	 * go through the addContext and editContext methods in order to ensure that
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
				$publicFileManager->removeContextFile($context->getAssoctype(), $context->getId(), $fileName);
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
		$fileName = $this->moveTemporaryFile($context, $temporaryFile, $settingName, $userId, $localeKey);

		if ($fileName) {
			// Get the details for image uploads
			if ($isImage) {
				import('classes.file.PublicFileManager');
				$publicFileManager = new \PublicFileManager();

				$filePath = $publicFileManager->getContextFilesPath($context->getAssocType(), $context->getId());
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
				return $fileName;
			}
		}

		return false;
	}
}

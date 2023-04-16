<?php
/**
 * @file classes/services/PKPContextService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextService
 *
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for contexts (journals
 *  and presses)
 */

namespace PKP\services;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\services\queryBuilders\ContextQueryBuilder;
use PKP\announcement\AnnouncementTypeDAO;
use PKP\config\Config;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBResultRange;
use PKP\facades\Locale;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\navigationMenu\NavigationMenuItemDAO;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\plugins\PluginSettingsDAO;
use PKP\reviewForm\ReviewFormDAO;
use PKP\security\Role;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityReadInterface;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\submission\GenreDAO;
use PKP\validation\ValidatorFactory;

abstract class PKPContextService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface
{
    /**
     * @var array List of file directories to create on installation. Use %d to
     *  use the context ID in a file path.
     */
    public $installFileDirs;

    /**
     * @var array The file directory where context files are stored. Expects
     *  `journals` or `presses`.
     */
    public $contextsFileDirName;

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::get()
     */
    public function get($contextId)
    {
        return Application::getContextDAO()->getById($contextId);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getCount()
     */
    public function getCount($args = [])
    {
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getIds()
     */
    public function getIds($args = [])
    {
        return $this->getQueryBuilder($args)->getIds();
    }

    /**
     * Get a summary of context information limited, filtered
     * and sorted by $args.
     *
     * This is faster than getMany if you don't need to
     * retrieve all the context settings. It returns the
     * data from the main table and the name of the context
     * in its primary locale.
     *
     * @see self::getMany()
     *
     * @return array
     */
    public function getManySummary($args = [])
    {
        return $this->getQueryBuilder($args)->getManySummary();
    }

    /**
     * Get a collection of Context objects limited, filtered
     * and sorted by $args
     *
     * @param array $args {
     *
     * 		@option bool isEnabled
     * 		@option int userId
     * 		@option string searchPhrase
     * 		@option int count
     * 		@option int offset
     * }
     *
     * @return Iterator
     */
    public function getMany($args = [])
    {
        $range = null;
        if (isset($args['count'])) {
            $range = new DBResultRange($args['count'], null, $args['offset'] ?? 0);
        }
        // Pagination is handled by the DAO, so don't pass count and offset
        // arguments to the QueryBuilder.
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        $contextListQO = $this->getQueryBuilder($args)->getQuery();
        $contextDao = Application::getContextDAO();
        $result = $contextDao->retrieveRange($contextListQO->toSql(), $contextListQO->getBindings(), $range);
        $queryResults = new DAOResultFactory($result, $contextDao, '_fromRow');

        return $queryResults->toIterator();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getMax()
     */
    public function getMax($args = [])
    {
        // Don't accept args to limit the results
        if (isset($args['count'])) {
            unset($args['count']);
        }
        if (isset($args['offset'])) {
            unset($args['offset']);
        }
        return $this->getQueryBuilder($args)->getCount();
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityReadInterface::getQueryBuilder()
     *
     * @return ContextQueryBuilder
     */
    public function getQueryBuilder($args = [])
    {
        $defaultArgs = [
            'isEnabled' => null,
            'userId' => null,
            'searchPhrase' => null,
        ];

        $args = array_merge($defaultArgs, $args);

        $contextListQB = new ContextQueryBuilder();
        $contextListQB
            ->filterByIsEnabled($args['isEnabled'])
            ->filterByUserId($args['userId'])
            ->searchPhrase($args['searchPhrase']);

        Hook::call('Context::getMany::queryBuilder', [&$contextListQB, $args]);

        return $contextListQB;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($context, $props, $args = null)
    {
        $slimRequest = $args['slimRequest'];
        $request = $args['request'];
        $dispatcher = $request->getDispatcher();

        $values = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'url':
                    $values[$prop] = $dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        $context->getPath()
                    );
                    break;
                case '_href':
                    $values[$prop] = null;
                    if (!empty($slimRequest)) {
                        $route = $slimRequest->getAttribute('route');
                        $values[$prop] = $dispatcher->url(
                            $args['request'],
                            PKPApplication::ROUTE_API,
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
        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_CONTEXT, $values, $supportedLocales);

        Hook::call('Context::getProperties', [&$values, $context, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($context, $args = null)
    {
        $props = Services::get('schema')->getSummaryProps(PKPSchemaService::SCHEMA_CONTEXT);

        return $this->getProperties($context, $props, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($context, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_CONTEXT);

        return $this->getProperties($context, $props, $args);
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::validate()
     */
    public function validate($action, $props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema'); /** @var PKPSchemaService $schemaService */

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_CONTEXT, $allowedLocales),
            [
                'urlPath.regex' => __('admin.contexts.form.pathAlphaNumeric'),
                'primaryLocale.regex' => __('validator.localeKey'),
                'supportedFormLocales.regex' => __('validator.localeKey'),
                'supportedLocales.regex' => __('validator.localeKey'),
                'supportedSubmissionLocales.*.regex' => __('validator.localeKey'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_CONTEXT),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_CONTEXT),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_CONTEXT), $allowedLocales);

        // Ensure that a urlPath, if provided, does not already exist
        $validator->after(function ($validator) use ($action, $props) {
            if (isset($props['urlPath']) && !$validator->errors()->get('urlPath')) {
                $contextDao = Application::getContextDAO();
                $contextWithPath = $contextDao->getByPath($props['urlPath']);
                if ($contextWithPath) {
                    if (!($action === EntityWriteInterface::VALIDATE_ACTION_EDIT
                            && isset($props['id'])
                            && (int) $contextWithPath->getId() === $props['id'])) {
                        $validator->errors()->add('urlPath', __('admin.contexts.form.pathExists'));
                    }
                }
            }
        });

        // Ensure that a urlPath is not 0, because this will cause router problems
        $validator->after(function ($validator) use ($props) {
            if (isset($props['urlPath']) && !$validator->errors()->get('urlPath') && $props['urlPath'] == '0') {
                $validator->errors()->add('urlPath', __('admin.contexts.form.pathRequired'));
            }
        });

        // Ensure that the primary locale is one of the supported locales
        $validator->after(function ($validator) use ($action, $props, $allowedLocales) {
            if (isset($props['primaryLocale']) && !$validator->errors()->get('primaryLocale')) {
                // Check against a new supported locales prop
                if (isset($props['supportedLocales'])) {
                    $newSupportedLocales = (array) $props['supportedLocales'];
                    if (!in_array($props['primaryLocale'], $newSupportedLocales)) {
                        $validator->errors()->add('primaryLocale', __('admin.contexts.form.primaryLocaleNotSupported'));
                    }
                // Or check against the $allowedLocales
                } elseif (!in_array($props['primaryLocale'], $allowedLocales)) {
                    $validator->errors()->add('primaryLocale', __('admin.contexts.form.primaryLocaleNotSupported'));
                }
            }
        });

        // Ensure that the supported locales are supported by the site
        $validator->after(function ($validator) use ($action, $props) {
            $siteSupportedLocales = Application::get()->getRequest()->getSite()->getData('supportedLocales');
            $localeProps = ['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales'];
            foreach ($localeProps as $localeProp) {
                if (isset($props[$localeProp]) && !$validator->errors()->get($localeProp)) {
                    $unsupportedLocales = array_diff($props[$localeProp], $siteSupportedLocales);
                    if (!empty($unsupportedLocales)) {
                        $validator->errors()->add($localeProp, __('api.contexts.400.localesNotSupported', ['locales' => join(__('common.commaListSeparator'), $unsupportedLocales)]));
                    }
                }
            }
        });

        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['favicon', 'homepageImage', 'pageHeaderLogoImage', 'styleSheet'],
            ['favicon', 'homepageImage', 'pageHeaderLogoImage'],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        // If sidebar blocks are passed, ensure the block plugin exists and is
        // enabled
        $validator->after(function ($validator) use ($props) {
            if (!empty($props['sidebar']) && !$validator->errors()->get('sidebar')) {
                $plugins = PluginRegistry::loadCategory('blocks', true);
                foreach ($props['sidebar'] as $pluginName) {
                    if (empty($plugins[$pluginName])) {
                        $validator->errors()->add('sidebar', __('manager.setup.layout.sidebar.invalidBlock', ['name' => $pluginName]));
                    }
                }
            }
        });

        // Ensure the theme plugin is installed and enabled
        $validator->after(function ($validator) use ($props) {
            if (!empty($props['themePluginPath']) && !$validator->errors()->get('themePluginPath')) {
                $plugins = PluginRegistry::loadCategory('themes', true);
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

        // Transforming copySubmissionAckAddress from CSV to array
        $validator->after(function ($validator) use ($props) {
            if (!isset($props['copySubmissionAckAddress'])) {
                return;
            }

            $emails = explode(',', $props['copySubmissionAckAddress']);

            if ($emails === []) {
                return;
            }

            foreach ($emails as $currentEmail) {
                $value = trim($currentEmail);

                $emailValidator = ValidatorFactory::make(
                    ['value' => $value],
                    ['value' => ['email_or_localhost']]
                );

                if ($emailValidator->fails()) {
                    $validator->errors()->add('copySubmissionAckAddress', __('manager.setup.notifications.copySubmissionAckAddress.invalid'));
                    break;
                }
            }
        });

        // Only allow admins to modify which user groups are disabled for bulk emails
        if (!empty($props['disableBulkEmailUserGroups'])) {
            $user = Application::get()->getRequest()->getUser();
            $validator->after(function ($validator) use ($user) {
                $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
                if (!$roleDao->userHasRole(\PKP\core\PKPApplication::CONTEXT_ID_NONE, $user->getId(), Role::ROLE_ID_SITE_ADMIN)) {
                    $validator->errors()->add('disableBulkEmailUserGroups', __('admin.settings.disableBulkEmailRoles.adminOnly'));
                }
            });
        }

        // Disallow empty DOI Prefix when enableDois is true
        if (isset($props[Context::SETTING_ENABLE_DOIS]) || isset($props[Context::SETTING_DOI_PREFIX])) {
            $context = Application::get()->getRequest()->getContext();
            $validator->after(function ($validator) use ($context, $props) {
                $enableDois = $props[Context::SETTING_ENABLE_DOIS] ?? $context->getData(Context::SETTING_ENABLE_DOIS);

                if ($enableDois && empty($props[Context::SETTING_DOI_PREFIX])) {
                    $validator->errors()->add(Context::SETTING_DOI_PREFIX, __('doi.manager.settings.doiPrefix.required'));
                }
            });
        }

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Context::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::add()
     */
    public function add($context, $request)
    {
        $site = $request->getSite();
        $currentUser = $request->getUser();
        $contextDao = Application::getContextDAO();

        if (!$context->getData('primaryLocale')) {
            $context->setData('primaryLocale', $site->getPrimaryLocale());
        }
        if (!$context->getData('supportedLocales')) {
            $context->setData('supportedLocales', $site->getSupportedLocales());
        }

        // Specify values needed to render default locale strings
        $localeParams = [
            'submissionGuidelinesUrl' => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_PAGE,
                $context->getPath(),
                'about',
                'submissions'
            ),
            'indexUrl' => $request->getIndexUrl(),
            'primaryLocale' => $context->getData('primaryLocale'),
            'contextName' => $context->getData('name', $context->getPrimaryLocale()),
            'contextPath' => $context->getData('urlPath'),
            'contextUrl' => $request->getDispatcher()->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                $context->getPath()
            ),
        ];

        // Allow plugins to extend the $localeParams for new property defaults
        Hook::call('Context::defaults::localeParams', [&$localeParams, $context, $request]);

        $context = Services::get('schema')->setDefaults(
            PKPSchemaService::SCHEMA_CONTEXT,
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
            $params['styleSheet'] = $this->_saveFileParam($context, $params['styleSheet'], 'styleSheet', $currentUser->getId());
        }
        $context = $this->edit($context, $params, $request);

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genreDao->installDefaults($context->getId(), $context->getData('supportedLocales'));

        Repo::userGroup()->installSettings($context->getId(), 'registry/userGroups.xml');

        $managerUserGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_MANAGER], $context->getId(), true)->firstOrFail();
        Repo::userGroup()->assignUserToGroup($currentUser->getId(), $managerUserGroup->getId());

        $fileManager = new FileManager();
        foreach ($this->installFileDirs as $dir) {
            $fileManager->mkdir(sprintf($dir, $this->contextsFileDirName, $context->getId()));
        }

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao->installSettings($context->getId(), 'registry/navigationMenus.xml');

        Repo::emailTemplate()->dao->installAlternateEmailTemplates($context->getId());

        // Load all plugins so they can hook in and add their installation settings
        PluginRegistry::loadAllPlugins();

        Hook::call('Context::add', [&$context, $request]);

        return $context;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::edit()
     */
    public function edit($context, $params, $request)
    {
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

        Hook::call('Context::edit', [&$newContext, $context, $params, $request]);

        $contextDao->updateObject($newContext);
        $newContext = $this->get($newContext->getId());

        return $newContext;
    }

    /**
     * @copydoc \PKP\services\entityProperties\EntityWriteInterface::delete()
     */
    public function delete($context)
    {
        Hook::call('Context::delete::before', [&$context]);

        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        $announcementTypeDao->deleteByContextId($context->getId());

        Repo::userGroup()->deleteByContextId($context->getId());

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genreDao->deleteByContextId($context->getId());

        Repo::announcement()->deleteMany(
            Repo::announcement()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
        );

        Repo::institution()->deleteMany(
            Repo::institution()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
        );

        Repo::emailTemplate()->restoreDefaults($context->getId());

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao->deleteByContextId($context->getId());

        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewFormDao->deleteByAssoc($context->getAssocType(), $context->getId());

        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */
        $navigationMenuDao->deleteByContextId($context->getId());

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItemDao->deleteByContextId($context->getId());

        $fileManager = new FileManager($context->getId());
        $contextPath = Config::getVar('files', 'files_dir') . '/' . $this->contextsFileDirName . '/' . $context->getId();
        $fileManager->rmtree($contextPath);

        $contextDao = Application::getContextDao();
        $contextDao->deleteObject($context);

        Hook::call('Context::delete', [&$context]);
    }

    /**
     * Restore default values for context settings in a specific local
     *
     * Updates multilingual values of a context, restoring default values in a
     * specific context. This may be useful when a new language has been added
     * after a context has been created, or when translations change and a journal
     * wants to take advantage of the new values.
     *
     * @param Context $context The context to restore default values for
     * @param Request $request
     * @param string $locale Locale key to restore defaults for. Example: `en`
     */
    public function restoreLocaleDefaults($context, $request, $locale)
    {
        Locale::installLocale($locale);

        // Specify values needed to render default locale strings
        $localeParams = [
            'indexUrl' => $request->getIndexUrl(),
            'contextPath' => $context->getData('urlPath'),
            'journalPath' => $context->getData('urlPath'), // DEPRECATED
            'primaryLocale' => $context->getData('primaryLocale'),
            'journalName' => $context->getData('name', $locale), // DEPRECATED
            'contextName' => $context->getData('name', $locale),
            'contextUrl' => $request->getDispatcher()->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                $context->getPath()
            ),
        ];

        // Allow plugins to extend the $localeParams for new property defaults
        Hook::call('Context::restoreLocaleDefaults::localeParams', [&$localeParams, $context, $request, $locale]);

        $localeDefaults = Services::get('schema')->getLocaleDefaults(PKPSchemaService::SCHEMA_CONTEXT, $locale, $localeParams);

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
     * @param Context $context
     * @param TemporaryFile $temporaryFile
     * @param string $fileNameBase Unique identifier to use for the filename. The
     *  Extension and locale will be appended.
     * @param int $userId ID of the user who uploaded the temporary file
     * @param string $localeKey Example: en. Leave empty for a file that is
     *  not localized.
     *
     * @return string|boolean The new filename or false on failure
     */
    public function moveTemporaryFile($context, $temporaryFile, $fileNameBase, $userId, $localeKey = '')
    {
        $publicFileManager = new PublicFileManager();
        $temporaryFileManager = new TemporaryFileManager();

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
     * Checks if a context exists
     */
    public function exists(int $id): bool
    {
        /** @var ContextDAO $contextDao */
        $contextDao = Application::getContextDao();
        return $contextDao->exists($id);
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
     * @param Context $context The context being edited
     * @param mixed $value The param value to be saved. Contains the temporary
     *  file ID if a new file has been uploaded.
     * @param string $settingName The name of the setting to save, typically used
     *  in the filename.
     * @param int $userId ID of the user who owns the temporary file
     * @param string $localeKey Optional. Used in the filename for multilingual
     *  properties.
     * @param bool $isImage Optional. For image files which return alt text,
     *  width, height, etc in the param value.
     *
     * @return string|array|null New param value or null on failure
     */
    protected function _saveFileParam($context, $value, $settingName, $userId, $localeKey = '', $isImage = false)
    {
        $temporaryFileManager = new TemporaryFileManager();

        // If the value is null, clean up any existing file in the system
        if (is_null($value)) {
            $setting = $context->getData($settingName, $localeKey);
            if ($setting) {
                $fileName = $isImage ? $setting['uploadName'] : $setting;
                $publicFileManager = new PublicFileManager();
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
                $publicFileManager = new PublicFileManager();

                $filePath = $publicFileManager->getContextFilesPath($context->getId());
                [$width, $height] = getimagesize($filePath . '/' . $fileName);
                $altText = !empty($value['altText']) ? $value['altText'] : '';

                return [
                    'name' => $temporaryFile->getOriginalFileName(),
                    'uploadName' => $fileName,
                    'width' => $width,
                    'height' => $height,
                    'dateUploaded' => Core::getCurrentDate(),
                    'altText' => $altText,
                ];
            } else {
                return [
                    'name' => $temporaryFile->getOriginalFileName(),
                    'uploadName' => $fileName,
                    'dateUploaded' => Core::getCurrentDate(),
                ];
            }
        }

        return false;
    }
}

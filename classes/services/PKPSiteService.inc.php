<?php
/**
 * @file classes/services/PKPSiteService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for the overall site
 */

namespace PKP\services;

use APP\core\Application;
use APP\core\Services;
use PKP\db\DAORegistry;
use PKP\services\interfaces\EntityPropertyInterface;
use PKP\services\interfaces\EntityWriteInterface;

use PKP\validation\ValidatorFactory;

class PKPSiteService implements EntityPropertyInterface
{
    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getProperties()
     *
     * @param null|mixed $args
     */
    public function getProperties($site, $props, $args = null)
    {
        $request = $args['request'];
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        $values = [];
        foreach ($props as $prop) {
            $values[$prop] = $site->getData($prop);
        }

        $values = Services::get('schema')->addMissingMultilingualValues(PKPSchemaService::SCHEMA_SITE, $values, $site->getSupportedLocales());

        \HookRegistry::call('Site::getProperties', [&$values, $site, $props, $args]);

        ksort($values);

        return $values;
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getSummaryProperties()
     *
     * @param null|mixed $args
     */
    public function getSummaryProperties($site, $args = null)
    {
        return $this->getFullProperties($site, $args);
    }

    /**
     * @copydoc \PKP\services\interfaces\EntityPropertyInterface::getFullProperties()
     *
     * @param null|mixed $args
     */
    public function getFullProperties($site, $args = null)
    {
        $props = Services::get('schema')->getFullProps(PKPSchemaService::SCHEMA_SITE);

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
     * @param array $props The data to validate
     * @param array $allowedLocales Which locales are allowed for this context
     * @param string $primaryLocale
     *
     * @return array List of error messages. The array keys are property names
     */
    public function validate($props, $allowedLocales, $primaryLocale)
    {
        $schemaService = Services::get('schema');

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_SITE, $allowedLocales),
            [
                'primaryLocale.regex' => __('validator.localeKey'),
                'supportedLocales.regex' => __('validator.localeKey'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            EntityWriteInterface::VALIDATE_ACTION_EDIT,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_PUBLICATION),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_PUBLICATION),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales(
            $validator,
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_SITE),
            $allowedLocales
        );

        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['pageHeaderTitleImage', 'styleSheet'],
            ['pageHeaderTitleImage'],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        // If sidebar blocks are passed, ensure the block plugin exists and is
        // enabled
        $validator->after(function ($validator) use ($props) {
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
        $validator->after(function ($validator) use ($props) {
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
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(PKPSchemaService::SCHEMA_SITE), $allowedLocales);
        }

        \HookRegistry::call('Site::validate', [&$errors, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /**
     * Edit the site
     *
     * This does not check if the user is authorized to edit the site, or validate or sanitize
     * the new content.
     *
     * @param Context $site The context to edit
     * @param array $params Key/value array of new data
     * @param Request $request
     *
     * @return Site
     */
    public function edit($site, $params, $request)
    {
        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */

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

        \HookRegistry::call('Site::edit', [&$newSite, $site, $params, $request]);

        $siteDao->updateObject($newSite);
        $newSite = $siteDao->getSite();

        return $newSite;
    }

    /**
     * Move a temporary file to the site's public directory
     *
     * @param Context $context
     * @param TemporaryFile $temporaryFile
     * @param string $fileNameBase Unique identifier to use for the filename. The
     *  Extension and locale will be appended.
     * @param int $userId ID of the user who uploaded the temporary file
     * @param string $localeKey Example: en_US. Leave empty for a file that is
     *  not localized.
     *
     * @return string|boolean The new filename or false on failure
     */
    public function moveTemporaryFile($context, $temporaryFile, $fileNameBase, $userId, $localeKey = '')
    {
        $publicFileManager = new \PublicFileManager();
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
     * @param Site $site The site being edited
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
    protected function _saveFileParam($site, $value, $settingName, $userId, $localeKey = '', $isImage = false)
    {
        $temporaryFileManager = new \TemporaryFileManager();

        // If the value is null, clean up any existing file in the system
        if (is_null($value)) {
            $setting = $site->getData($settingName, $localeKey);
            if ($setting) {
                $fileName = $isImage ? $setting['uploadName'] : $setting;
                $publicFileManager = new \PublicFileManager();
                $publicFileManager->removeSiteFile($fileName);
            }
            return null;
        }

        // Check if there is something to upload
        if (empty($value['temporaryFileId'])) {
            return $value;
        }

        $temporaryFile = $temporaryFileManager->getFile((int) $value['temporaryFileId'], $userId);
        $fileName = $this->moveTemporaryFile($site, $temporaryFile, $settingName, $userId, $localeKey);

        if ($fileName) {
            // Get the details for image uploads
            if ($isImage) {
                $publicFileManager = new \PublicFileManager();

                [$width, $height] = getimagesize($publicFileManager->getSiteFilesPath() . '/' . $fileName);
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
                return [
                    'originalFilename' => $temporaryFile->getOriginalFileName(),
                    'uploadName' => $fileName,
                    'dateUploaded' => \Core::getCurrentDate(),
                ];
            }
        }

        return false;
    }
}

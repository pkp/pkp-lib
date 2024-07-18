<?php

/**
 * @file plugins/importexport/users/PKPUserImportExportPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserImportExportPlugin
 *
 * @ingroup plugins_importexport_users
 *
 * @brief User XML import/export plugin
 */

namespace PKP\plugins\importexport\users;

use APP\facades\Repo;
use APP\template\TemplateManager;
use Exception;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileDAO;
use PKP\file\TemporaryFileManager;
use PKP\filter\Filter;
use PKP\filter\FilterDAO;
use PKP\plugins\ImportExportPlugin;
use PKP\user\User;

abstract class PKPUserImportExportPlugin extends ImportExportPlugin
{
    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category.
     *
     * @return string name of plugin
     */
    public function getName()
    {
        return 'UserImportExportPlugin';
    }

    /**
     * Get the display name.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.importexport.users.displayName');
    }

    /**
     * Get the display description.
     *
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.importexport.users.description');
    }

    /**
     * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
     */
    public function getPluginSettingsPrefix()
    {
        return 'users';
    }

    /**
     * Display the plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function display($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        parent::display($args, $request);

        $templateMgr->assign('plugin', $this);

        switch (array_shift($args)) {
            case 'index':
            case '':
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
            case 'uploadImportXML':
                $user = $request->getUser();
                $temporaryFileManager = new TemporaryFileManager();
                $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
                if ($temporaryFile) {
                    $json = new JSONMessage(true);
                    $json->setAdditionalAttributes([
                        'temporaryFileId' => $temporaryFile->getId()
                    ]);
                } else {
                    $json = new JSONMessage(false, __('common.uploadFailed'));
                }

                header('Content-Type: application/json');
                return $json->getString();
            case 'importBounce':
                if (!$request->checkCSRF()) {
                    throw new Exception('CSRF mismatch!');
                }
                $json = new JSONMessage(true);
                $json->setEvent('addTab', [
                    'title' => __('plugins.importexport.users.results'),
                    'url' => $request->url(null, null, null, ['plugin', $this->getName(), 'import'], ['temporaryFileId' => $request->getUserVar('temporaryFileId'), 'csrfToken' => $request->getSession()->token()]),
                ]);
                header('Content-Type: application/json');
                return $json->getString();
            case 'import':
                if (!$request->checkCSRF()) {
                    throw new Exception('CSRF mismatch!');
                }
                $temporaryFileId = $request->getUserVar('temporaryFileId');
                $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
                $user = $request->getUser();
                $temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());
                if (!$temporaryFile) {
                    $json = new JSONMessage(true, __('plugins.importexport.users.uploadFile'));
                    header('Content-Type: application/json');
                    return $json->getString();
                }
                $temporaryFilePath = $temporaryFile->getFilePath();
                libxml_use_internal_errors(true);

                $filter = $this->getUserImportExportFilter($context, $user);
                $users = $this->importUsers(file_get_contents($temporaryFilePath), $context, $user, $filter);
                $validationErrors = array_filter(libxml_get_errors(), function ($a) {
                    return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
                });
                $templateMgr->assign('validationErrors', $validationErrors);
                libxml_clear_errors();
                if ($filter->hasErrors()) {
                    $templateMgr->assign('filterErrors', $filter->getErrors());
                }
                $templateMgr->assign('users', $users);
                $json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
                header('Content-Type: application/json');
                return $json->getString();
            case 'export':
                $filter = $this->getUserImportExportFilter($request->getContext(), $request->getUser(), false);

                $exportXml = $this->exportUsers(
                    (array) $request->getUserVar('selectedUsers'),
                    $request->getContext(),
                    $request->getUser(),
                    $filter
                );
                $fileManager = new FileManager();
                $exportFileName = $this->getExportFileName($this->getExportPath(), 'users', $context, '.xml');
                $fileManager->writeFile($exportFileName, $exportXml);
                $fileManager->downloadByPath($exportFileName);
                $fileManager->deleteByPath($exportFileName);
                break;
            case 'exportAllUsers':
                $filter = $this->getUserImportExportFilter($request->getContext(), $request->getUser(), false);

                $exportXml = $this->exportAllUsers(
                    $request->getContext(),
                    $request->getUser(),
                    $filter
                );
                $fileManager = new FileManager();
                $exportFileName = $this->getExportFileName($this->getExportPath(), 'users', $context, '.xml');
                $fileManager->writeFile($exportFileName, $exportXml);
                $fileManager->downloadByPath($exportFileName);
                $fileManager->deleteByPath($exportFileName);
                break;
            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
        }
    }

    /**
     * Get the XML for all of users.
     *
     * @param Context $context
     * @param ?User $user
     * @param Filter $filter byRef parameter - import/export filter used
     *
     * @return string XML contents representing the supplied user IDs.
     */
    public function exportAllUsers($context, $user, &$filter = null)
    {
        $users = Repo::user()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        if (!$filter) {
            $filter = $this->getUserImportExportFilter($context, $user, false);
        }

        return $this->exportUsers($users->toArray(), $context, $user, $filter);
    }

    /**
     * Get the XML for a set of users.
     *
     * @param array $ids mixed Array of users or user IDs
     * @param Context $context
     * @param ?User $user
     * @param Filter $filter byRef parameter - import/export filter used
     *
     * @return string XML contents representing the supplied user IDs.
     */
    public function exportUsers($ids, $context, $user, &$filter = null)
    {
        $xml = '';

        if (!$filter) {
            $filter = $this->getUserImportExportFilter($context, $user, false);
        }

        $users = [];
        foreach ($ids as $id) {
            if ($id instanceof User) {
                $users[] = $id;
            } else {
                $user = Repo::user()->get($id, true);
                if ($user) {
                    $users[] = $user;
                }
            }
        }


        $userXml = $filter->execute($users);
        if ($userXml) {
            $xml = $userXml->saveXml();
        } else {
            throw new \Exception('Could not convert users.');
        }
        return $xml;
    }

    /**
     * Get the XML for a set of users.
     *
     * @param string $importXml XML contents to import
     * @param Context $context
     * @param ?User $user
     * @param Filter $filter byRef parameter - import/export filter used
     *
     * @return array Set of imported users
     */
    public function importUsers($importXml, $context, $user, &$filter = null)
    {
        if (!$filter) {
            $filter = $this->getUserImportExportFilter($context, $user);
        }

        return $filter->execute($importXml);
    }

    /**
     * Return user filter for import purposes
     *
     * @param Context $context
     * @param ?User $user
     * @param bool $isImport return Import Filter if true - export if false
     *
     * @return Filter
     */
    public function getUserImportExportFilter($context, $user, $isImport = true)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */

        if ($isImport) {
            $userFilters = $filterDao->getObjectsByGroup('user-xml=>user');
        } else {
            $userFilters = $filterDao->getObjectsByGroup('user=>user-xml');
        }

        assert(count($userFilters) == 1); // Assert only a single unserialization filter
        $filter = array_shift($userFilters);
        $filter->setDeployment(new PKPUserImportExportDeployment($context, $user));

        return $filter;
    }
}

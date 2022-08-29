<?php

/**
 * @file classes/plugins/DOIPubIdExportPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DOIPubIdExportPlugin
 * @ingroup plugins
 *
 * @brief Basis class for DOI XML metadata export plugins
 */

namespace APP\plugins;

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\core\PKPString;

use PKP\doi\Doi;
use PKP\plugins\PluginRegistry;
use PKP\submission\PKPSubmission;

// Configuration errors.
define('DOI_EXPORT_CONFIG_ERROR_DOIPREFIX', 0x01);

// The name of the setting used to save the registered DOI.
define('DOI_EXPORT_REGISTERED_DOI', 'registeredDoi');

abstract class DOIPubIdExportPlugin extends PubObjectsExportPlugin
{
    /**
     * @copydoc ImportExportPlugin::display()
     */
    public function display($args, $request)
    {
        parent::display($args, $request);
        $context = $request->getContext();
        switch (array_shift($args)) {
            case 'index':
            case '':
                $templateMgr = TemplateManager::getManager($request);
                // Check for configuration errors:
                $configurationErrors = $templateMgr->getTemplateVars('configurationErrors');
                // missing DOI prefix
                $doiPrefix = null;
                $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
                if (isset($pubIdPlugins['doipubidplugin'])) {
                    $doiPlugin = $pubIdPlugins['doipubidplugin'];
                    $doiPrefix = $context->getData(Context::SETTING_DOI_PREFIX);
                    $templateMgr->assign([
                        'exportPreprints' => $context->isDoiTypeEnabled(Repo::doi()::TYPE_PUBLICATION),
                        'exportRepresentations' => $context->isDoiTypeEnabled(Repo::doi()::TYPE_REPRESENTATION),
                    ]);
                }
                if (empty($doiPrefix)) {
                    $configurationErrors[] = DOI_EXPORT_CONFIG_ERROR_DOIPREFIX;
                }
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
        }
    }

    /**
     * Get pub ID type
     *
     * @return string
     */
    public function getPubIdType()
    {
        return 'doi';
    }

    /**
     * Get pub ID display type
     *
     * @return string
     */
    public function getPubIdDisplayType()
    {
        return 'DOI';
    }

    /**
     * Mark selected submissions as registered.
     *
     * @param Context $context
     * @param array $objects Array of published submissions or galleys
     */
    public function markRegistered($context, $objects)
    {
        foreach ($objects as $object) {
            $doiId = $object->getData('doiId');

            if ($doiId != null) {
                Repo::doi()->markRegistered($doiId);
            }
        }
    }

    /**
     * Saving object's DOI to the object's
     * "registeredDoi" setting.
     * We prefix the setting with the plugin's
     * id so that we do not get name clashes
     * when several DOI registration plug-ins
     * are active at the same time.
     *
     * @param Context $context
     * @param Submission|Galley $object
     * @param string $testPrefix
     */
    public function saveRegisteredDoi($context, $object, $testPrefix = '10.1234')
    {
        $registeredDoi = $object->getStoredPubId('doi');
        assert(!empty($registeredDoi));
        if ($this->isTestMode($context)) {
            $registeredDoi = PKPString::regexp_replace('#^[^/]+/#', $testPrefix . '/', $registeredDoi);
        }
        $object->setData($this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI, $registeredDoi);
        $this->updateObject($object);
    }

    /**
     * Get a list of additional setting names that should be stored with the objects.
     *
     * @return array
     */
    protected function _getObjectAdditionalSettings()
    {
        return array_merge(parent::_getObjectAdditionalSettings(), [
            $this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI
        ]);
    }

    /**
     * Get published submissions with a DOI assigned from submission IDs.
     *
     * @param array $submissionIds
     * @param Context $context
     *
     * @return array
     */
    public function getPublishedSubmissions($submissionIds, $context)
    {
        $submissions = array_map(function ($submissionId) {
            return Repo::submission()->get($submissionId);
        }, $submissionIds);
        return array_filter($submissions, function ($submission) {
            return $submission->getData('status') === PKPSubmission::STATUS_PUBLISHED;
        });
    }

    /**
     * Get preprint galleys with a DOI assigned from gallley IDs.
     *
     * @param array $galleyIds
     *
     * @return array
     */
    public function getPreprintGalleys($galleyIds)
    {
        $galleys = [];
        foreach ($galleyIds as $galleyId) {
            $preprintGalley = Repo::galley()->get((int) $galleyId);
            if ($preprintGalley && $preprintGalley->getStoredPubId('doi')) {
                $galleys[] = $preprintGalley;
            }
        }
        return $galleys;
    }
}

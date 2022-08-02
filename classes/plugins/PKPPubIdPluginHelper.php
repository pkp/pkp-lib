<?php

/**
 * @file classes/plugins/PKPPubIdPluginHelper.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPubIdPluginHelper
 * @ingroup plugins
 *
 * @brief Helper class for public identifiers plugins
 */

namespace PKP\plugins;

use APP\core\Application;

class PKPPubIdPluginHelper
{
    /**
     * Validate the additional form fields from public identifier plugins.
     *
     * @param int $contextId
     * @param object $form PKPPublicIdentifiersForm
     * @param object $pubObject
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function validate($contextId, $form, $pubObject)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $fieldNames = $pubIdPlugin->getFormFieldNames();
                foreach ($fieldNames as $fieldName) {
                    $fieldValue = $form->getData($fieldName);
                    $errorMsg = '';
                    if (!$pubIdPlugin->verifyData($fieldName, $fieldValue, $pubObject, $contextId, $errorMsg)) {
                        $form->addError($fieldName, $errorMsg);
                    }
                }
            }
        }
    }

    /**
     * Set form link actions.
     *
     * @param int $contextId
     * @param object $form PKPPublicIdentifiersForm
     * @param object $pubObject
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function setLinkActions($contextId, $form, $pubObject)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $linkActions = $pubIdPlugin->getLinkActions($pubObject);
                foreach ($linkActions as $linkActionName => $linkAction) {
                    $form->setData($linkActionName, $linkAction);
                    unset($linkAction);
                }
            }
        }
    }

    /**
     * Add pub id plugins JavaScripts.
     *
     * @param int $contextId
     * @param PKPRequest $request
     * @param PKPTemplateManager $templateMgr
     */
    public function addJavaScripts($contextId, $request, $templateMgr)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $pubIdPlugin->addJavaScript($request, $templateMgr);
            }
        }
    }

    /**
     * Init the additional form fields from public identifier plugins.
     *
     * @param int $contextId
     * @param object $form PKPPublicIdentifiersForm|CatalogEntryFormatMetadataForm
     * @param object $pubObject
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function init($contextId, $form, $pubObject)
    {
        if (isset($pubObject)) {
            $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
            if (!empty($pubIdPlugins)) {
                foreach ($pubIdPlugins as $pubIdPlugin) {
                    $fieldNames = $pubIdPlugin->getFormFieldNames();
                    foreach ($fieldNames as $fieldName) {
                        $form->setData($fieldName, $pubObject->getData($fieldName));
                    }
                }
            }
        }
    }

    /**
     * Read the additional input data from public identifier plugins.
     *
     * @param int $contextId
     * @param object $form PKPPublicIdentifiersForm
     */
    public function readInputData($contextId, $form)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $form->readUserVars($pubIdPlugin->getFormFieldNames());
                $form->readUserVars([$pubIdPlugin->getAssignFormFieldName()]);
            }
        }
    }

    /**
     * Read the the public identifiers' assign form field data.
     *
     * @param object $form Form containing the assign check box
     * 	PKPAssignPublicIdentifiersForm
     * 	OJS IssueEntryPublicationMetadataForm
     */
    public function readAssignInputData($form)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $context->getId());
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $form->readUserVars([$pubIdPlugin->getAssignFormFieldName()]);
            }
        }
    }

    /**
     * Set the additional data from public identifier plugins.
     *
     * @param int $contextId
     * @param object $form PKPPublicIdentifiersForm
     * @param object $pubObject
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function execute($contextId, $form, $pubObject)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                // Public ID data can only be changed as long
                // as no ID has been generated.
                $storedId = $pubObject->getStoredPubId($pubIdPlugin->getPubIdType());
                if (!$storedId) {
                    $fieldNames = $pubIdPlugin->getFormFieldNames();
                    foreach ($fieldNames as $fieldName) {
                        $data = $form->getData($fieldName);
                        $pubObject->setData($fieldName, $data);
                    }
                    if ($form->getData($pubIdPlugin->getAssignFormFieldName())) {
                        $pubId = $pubIdPlugin->getPubId($pubObject);
                        $pubObject->setStoredPubId($pubIdPlugin->getPubIdType(), $pubId);
                    }
                }
            }
        }
    }

    /**
     * Assign public identifier.
     *
     * @param int $contextId
     * @param object $form
     * @param object $pubObject
     * @param bool $save Whether the pub id shall be saved here
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function assignPubId($contextId, $form, $pubObject, $save = false)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                if ($form->getData($pubIdPlugin->getAssignFormFieldName())) {
                    $pubId = $pubIdPlugin->getPubId($pubObject);
                    if ($save) {
                        $pubIdPlugin->setStoredPubId($pubObject, $pubId);
                    } else {
                        $pubObject->setStoredPubId($pubIdPlugin->getPubIdType(), $pubId);
                    }
                }
            }
        }
    }

    /**
     * Clear a pubId from a pubObject.
     *
     * @param int $contextId
     * @param string $pubIdPlugInClassName
     * @param object $pubObject
     * 	Submission, Representation, SubmissionFile + OJS Issue
     */
    public function clearPubId($contextId, $pubIdPlugInClassName, $pubObject)
    {
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $pubIdPlugin) {
                $classNameParts = explode('\\', get_class($pubIdPlugin)); // Separate namespace info from class name
                if (end($classNameParts) == $pubIdPlugInClassName) {
                    // clear the pubId:
                    // delete the pubId from the DB
                    $dao = $pubObject->getDAO();
                    $pubObjectId = $pubObject->getId();
                    if ($pubObject instanceof SubmissionFile) {
                        $pubObjectId = $pubObject->getId();
                    }
                    $dao->deletePubId($pubObjectId, $pubIdPlugin->getPubIdType());
                    // set the object setting/data 'pub-id::...' to null, in order
                    // not to be considered in the DB object update later in the form
                    $settingName = 'pub-id::' . $pubIdPlugin->getPubIdType();
                    $pubObject->setData($settingName, null);
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PKPPubIdPluginHelper', '\PKPPubIdPluginHelper');
}

<?php

/**
 * @file plugins/generic/usageEvent/UsageEventPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageEventPlugin
 * @ingroup plugins_generic_usageEvent
 *
 * @brief Implement application specifics for generating usage events.
 */

namespace APP\plugins\generic\usageEvent;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;

class UsageEventPlugin extends \PKP\plugins\generic\usageEvent\PKPUsageEventPlugin
{
    //
    // Implement methods from PKPUsageEventPlugin.
    //
    /**
     * @copydoc PKPUsageEventPlugin::getEventHooks()
     */
    public function getEventHooks()
    {
        return array_merge(parent::getEventHooks(), [
            'PreprintHandler::download',
            'HtmlArticleGalleyPlugin::articleDownload',
            'HtmlArticleGalleyPlugin::articleDownloadFinished'
        ]);
    }

    /**
     * @copydoc PKPUsageEventPlugin::getDownloadFinishedEventHooks()
     */
    protected function getDownloadFinishedEventHooks()
    {
        return array_merge(parent::getDownloadFinishedEventHooks(), [
            'HtmlArticleGalleyPlugin::articleDownloadFinished'
        ]);
    }

    /**
     * @copydoc PKPUsageEventPlugin::getUSageEventData()
     */
    protected function getUsageEventData($hookName, $hookArgs, $request, $router, $templateMgr, $context)
    {
        [$pubObject, $downloadSuccess, $assocType, $idParams, $canonicalUrlPage, $canonicalUrlOp, $canonicalUrlParams] =
            parent::getUsageEventData($hookName, $hookArgs, $request, $router, $templateMgr, $context);

        if (!$pubObject) {
            switch ($hookName) {
                // Press index page and preprint abstract.
                case 'TemplateManager::display':
                    $page = $router->getRequestedPage($request);
                    $op = $router->getRequestedOp($request);
                    $args = $router->getRequestedArgs($request);

                    $wantedPages = ['preprint'];
                    $wantedOps = ['index', 'view'];

                    if (!in_array($page, $wantedPages) || !in_array($op, $wantedOps)) {
                        break;
                    }

                    // View requests with 1 argument might relate to server
                    // or preprint. With more than 1 is related with other objects
                    // that we are not interested in or that are counted using a
                    // different hook.
                    // If the operation is 'view' and the arguments count > 1
                    // the arguments must be: $submissionId/version/$publicationId.
                    if ($op == 'view' && count($args) > 1) {
                        if ($args[1] !== 'version') {
                            break;
                        } elseif (count($args) != 3) {
                            break;
                        }
                        $publicationId = (int) $args[2];
                    }

                    $server = $templateMgr->getTemplateVars('currentContext');
                    $submission = $templateMgr->getTemplateVars('preprint');

                    // No published objects, no usage event.
                    if (!$server && !$submission) {
                        break;
                    }

                    if ($server) {
                        $pubObject = $server;
                        $assocType = Application::ASSOC_TYPE_SERVER;
                        $canonicalUrlOp = '';
                    }

                    if ($submission) {
                        $pubObject = $submission;
                        $assocType = Application::ASSOC_TYPE_SUBMISSION;
                        $canonicalUrlParams = [$pubObject->getId()];
                        $idParams = ['m' . $pubObject->getId()];
                        if (isset($publicationId)) {
                            // no need to check if the publication exists (for the submisison),
                            // 404 would be returned and the usage event would not be there
                            $canonicalUrlParams = [$pubObject->getId(), 'version', $publicationId];
                        }
                    }

                    $downloadSuccess = true;
                    $canonicalUrlOp = $op;
                    break;

                    // Preprint file.
                case 'PreprintHandler::download':
                case 'HtmlArticleGalleyPlugin::articleDownload':
                    $assocType = Application::ASSOC_TYPE_SUBMISSION_FILE;
                    $preprint = $hookArgs[0];
                    $galley = $hookArgs[1];
                    $submissionFileId = $hookArgs[2];
                    // if file is not a gallay file (e.g. CSS or images), there is no usage event.
                    if ($galley->getData('submissionFileId') != $submissionFileId) {
                        return false;
                    }
                    $canonicalUrlOp = 'download';
                    $canonicalUrlParams = [$preprint->getId(), $galley->getId(), $submissionFileId];
                    $idParams = ['a' . $preprint->getId(), 'g' . $galley->getId(), 'f' . $submissionFileId];
                    $downloadSuccess = false;
                    $pubObject = Repo::submissionFile()->get($submissionFileId);
                    break;
                default:
                    // Why are we called from an unknown hook?
                    assert(false);
            }
        }

        return [$pubObject, $downloadSuccess, $assocType, $idParams, $canonicalUrlPage, $canonicalUrlOp, $canonicalUrlParams];
    }

    /**
     * @see PKPUsageEventPlugin::getHtmlPageAssocTypes()
     */
    protected function getHtmlPageAssocTypes()
    {
        return [
            Application::ASSOC_TYPE_SERVER,
            Application::ASSOC_TYPE_SUBMISSION,
        ];
    }

    /**
     * @see PKPUsageEventPlugin::isPubIdObjectType()
     */
    protected function isPubIdObjectType($pubObject)
    {
        return $pubObject instanceof Submission;
    }
}

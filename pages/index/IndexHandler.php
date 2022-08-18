<?php

/**
 * @file pages/index/IndexHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

namespace APP\pages\index;

use APP\core\Application;
use APP\facades\Repo;
use APP\observers\events\Usage;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Validation;
use PKP\pages\index\PKPIndexHandler;

class IndexHandler extends PKPIndexHandler
{
    //
    // Public handler operations
    //
    /**
     * If no server is selected, display list of servers.
     * Otherwise, display the index page for the selected server.
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function index($args, $request)
    {
        $this->validate(null, $request);
        $server = $request->getServer();

        if (!$server) {
            $server = $this->getTargetContext($request, $hasNoContexts);
            if ($server) {
                // There's a target context but no server in the current request. Redirect.
                $request->redirect($server->getPath());
            }
            if ($hasNoContexts && Validation::isSiteAdmin()) {
                // No contexts created, and this is the admin.
                $request->redirect(null, 'admin', 'contexts');
            }
        }

        $this->setupTemplate($request);
        $router = $request->getRouter();
        $templateMgr = TemplateManager::getManager($request);
        if ($server) {

            // OPS: sections
            $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
            $sections = $sectionDao->getByContextId($server->getId());

            // OPS: categories
            $categories = Repo::category()
                ->getCollector()
                ->filterByContextIds([$server->getId()])
                ->getMany();

            // Latest preprints
            $collector = Repo::submission()->getCollector();
            $publishedSubmissions = Repo::submission()->getMany(
                $collector
                    ->filterByContextIds([$server->getId()])
                    ->filterByStatus([Submission::STATUS_PUBLISHED])
                    ->orderBy($collector::ORDERBY_DATE_PUBLISHED)
                    ->limit(10)
            );

            // Assign header and content for home page
            $templateMgr->assign([
                'additionalHomeContent' => $server->getLocalizedData('additionalHomeContent'),
                'homepageImage' => $server->getLocalizedData('homepageImage'),
                'homepageImageAltText' => $server->getLocalizedData('homepageImageAltText'),
                'serverDescription' => $server->getLocalizedData('description'),
                'sections' => $sections,
                'categories' => iterator_to_array($categories),
                'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true),
                'publishedSubmissions' => $publishedSubmissions->toArray(),
            ]);

            $this->_setupAnnouncements($server, $templateMgr);

            $templateMgr->display('frontend/pages/indexServer.tpl');
            event(new Usage(Application::ASSOC_TYPE_SERVER, $server));
            return;
        } else {
            $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var APP\server\ServerDAO $serverDao */
            $site = $request->getSite();

            if ($site->getRedirect() && ($server = $serverDao->getById($site->getRedirect())) != null) {
                $request->redirect($server->getPath());
            }
            $templateMgr->assign([
                'pageTitleTranslated' => $site->getLocalizedTitle(),
                'about' => $site->getLocalizedAbout(),
                'serverFilesPath' => $request->getBaseUrl() . '/' . Config::getVar('files', 'public_files_dir') . '/contexts/',
                'servers' => $serverDao->getAll(true)->toArray(),
                'site' => $site,
            ]);
            $templateMgr->setCacheability(TemplateManager::CACHEABILITY_PUBLIC);
            $templateMgr->display('frontend/pages/indexSite.tpl');
        }
    }
}

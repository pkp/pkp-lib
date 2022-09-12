<?php

/**
 * @file plugins/generic/webFeed/WebFeedGatewayPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedGatewayPlugin
 * @brief Gateway component of web feed plugin
 *
 */

namespace APP\plugins\generic\webFeed;

use APP\core\Request;
use APP\facades\Repo;
use APP\server\Section;
use APP\server\SectionDAO;
use APP\submission\Collector;
use APP\template\TemplateManager;
use Exception;
use PKP\category\Category;
use PKP\db\DAORegistry;
use PKP\site\VersionDAO;
use PKP\submission\PKPSubmission;

class WebFeedGatewayPlugin extends \PKP\plugins\GatewayPlugin
{
    public const DEFAULT_RECENT_ITEMS = 30;

    /** Parent plugin */
    protected WebFeedPlugin $_parentPlugin;

    public function __construct(WebFeedPlugin $parentPlugin)
    {
        parent::__construct();
        $this->_parentPlugin = $parentPlugin;
    }

    /**
     * Hide this plugin from the management interface (it's subsidiary)
     */
    public function getHideManagement(): bool
    {
        return true;
    }

    /**
     * Get the name of this plugin. The name must be unique within its category.
     */
    public function getName(): string
    {
        return substr(static::class, strlen(__NAMESPACE__) + 1);
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.webfeed.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.webfeed.description');
    }

    /**
     * Override the builtin to get the correct plugin path.
     *
     */
    public function getPluginPath(): string
    {
        return $this->_parentPlugin->getPluginPath();
    }

    /**
     * Get whether or not this plugin is enabled. (Should always return true, as the
     * parent plugin will take care of loading this one when needed)
     *
     * @param int $contextId Context ID (optional)
     *
     */
    public function getEnabled($contextId = null): bool
    {
        return $this->_parentPlugin->getEnabled($contextId);
    }

    /**
     * Handle fetch requests for this plugin.
     *
     * @param array $args Arguments.
     * @param Request $request Request object.
     */
    public function fetch($args, $request): bool
    {
        $server = $request->getServer();
        if (!$server) {
            return false;
        }

        if (!$this->_parentPlugin->getEnabled($server->getId())) {
            return false;
        }

        // Make sure the feed type is specified and valid
        $type = array_shift($args);
        $templateConfig = match ($type) {
            'rss' => ['template' => 'rss.tpl', 'mimeType' => 'application/rdf+xml'],
            'rss2' => ['template' => 'rss2.tpl', 'mimeType' => 'application/rss+xml'],
            'atom' => ['template' => 'atom.tpl', 'mimeType' => 'application/atom+xml'],
            default => throw new Exception('Invalid feed format')
        };

        // Get limit setting from web feeds plugin
        $recentItems = (int) $this->_parentPlugin->getSetting($server->getId(), 'recentItems');
        if ($recentItems < 1) {
            $recentItems = self::DEFAULT_RECENT_ITEMS;
        }

        /** @var SectionDAO */
        $sectionDao = DAORegistry::getDAO('SectionDAO');
        $submissionsIterator = Repo::submission()->getCollector()
            ->filterByContextIds([$server->getId()])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED])
            ->limit($recentItems)
            ->orderBy(Collector::ORDERBY_DATE_PUBLISHED)
            ->getMany();
        $sections = [];
        $submissions = [];
        $latestDate = null;
        /** @var PKPSubmission */
        foreach ($submissionsIterator as $submission) {
            $latestDate = $latestDate ?? $submission->getData('lastModified');
            $identifiers = [];
            /** @var ?Section */
            $section = ($sectionId = $submission->getSectionId())
                ? $sections[$sectionId] ?? $sections[$sectionId] = $sectionDao->getById($sectionId)
                : null;
            if ($section) {
                $identifiers[] = ['type' => 'section', 'value' => $section->getLocalizedTitle()];
            }

            $publication = $submission->getCurrentPublication();
            $categoriesIterator = Repo::category()->getCollector()
                ->filterByPublicationIds([$publication->getId()])
                ->getMany();
            /** @var Category */
            foreach ($categoriesIterator as $category) {
                $identifiers[] = ['type' => 'category', 'value' => $category->getLocalizedTitle()];
            }

            foreach (['keywords', 'subjects', 'disciplines'] as $type) {
                $values = $publication->getLocalizedData($type) ?? [];
                foreach ($values as $value) {
                    $identifiers[] = ['type' => $type, 'value' => $value];
                }
            }

            $submissions[] = [
                'submission' => $submission,
                'identifiers' => $identifiers
            ];
        }

        /** @var VersionDAO */
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $version = $versionDao->getCurrentVersion();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'systemVersion' => $version->getVersionString(),
            'submissions' => $submissions,
            'server' => $server,
            'latestDate' => $latestDate,
            'feedUrl' => $request->getRequestUrl()
        ]);

        $templateMgr->display($this->_parentPlugin->getTemplateResource($templateConfig['template']), $templateConfig['mimeType']);
        return true;
    }
}

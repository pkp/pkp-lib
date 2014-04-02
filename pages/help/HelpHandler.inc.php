<?php

/**
 * @file pages/help/HelpHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HelpHandler
 * @ingroup pages_help
 *
 * @brief Handle requests for viewing help pages.
 */



define('HELP_DEFAULT_TOPIC', 'index/topic/000000');
define('HELP_DEFAULT_TOC', 'index/toc/000000');

import('lib.pkp.classes.help.HelpToc');
import('lib.pkp.classes.help.HelpTocDAO');
import('lib.pkp.classes.help.HelpTopic');
import('lib.pkp.classes.help.HelpTopicDAO');
import('lib.pkp.classes.help.HelpTopicSection');
import('classes.handler.Handler');

class HelpHandler extends Handler {
	/**
	 * Constructor
	 */
	function HelpHandler() {
		parent::Handler();
	}

	/**
	 * Display help table of contents.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, &$request) {
		$this->view(array('index', 'topic', '000000'), $request);
	}

	/**
	 * Display help table of contents.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function toc($args, &$request) {
		$this->validate();
		$this->setupTemplate();

		$templateMgr =& TemplateManager::getManager();
		import('classes.help.Help');
		$help =& Help::getHelp();
		$templateMgr->assign_by_ref('helpToc', $help->getTableOfContents());
		$templateMgr->display('help/helpToc.tpl');
	}

	/**
	 * Display the selected help topic.
	 * @param $args array first parameter is the ID of the topic to display
	 * @param $request PKPRequest
	 */
	function view($args, $request) {
		$this->validate();
		$this->setupTemplate();

		$topicId = implode("/",$args);
		$keyword = trim(String::regexp_replace('/[^\w\s\.\-]/', '', strip_tags($request->getUserVar('keyword'))));
		$result = (int) $request->getUserVar('result');

		$topicDao =& DAORegistry::getDAO('HelpTopicDAO');
		$topic = $topicDao->getTopic($topicId);

		if ($topic === false) {
			// Invalid topic, use default instead
			$topicId = HELP_DEFAULT_TOPIC;
			$topic = $topicDao->getTopic($topicId);
		}

		$tocDao =& DAORegistry::getDAO('HelpTocDAO');
		$toc = $tocDao->getToc($topic->getTocId());

		if ($toc === false) {
			// Invalid toc, use default instead
			$toc = $tocDao->getToc(HELP_DEFAULT_TOC);
		}

		if ($topic->getSubTocId() != null) {
			$subToc = $tocDao->getToc($topic->getSubTocId());
		} else {
			$subToc =  null;
		}

		$relatedTopics = $topic->getRelatedTopics();

		$topics = $toc->getTopics();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('currentTopicId', $topic->getId());
		$templateMgr->assign_by_ref('topic', $topic);
		$templateMgr->assign('toc', $toc);
		$templateMgr->assign('subToc', $subToc);
		$templateMgr->assign('relatedTopics', $relatedTopics);
		$templateMgr->assign('locale', AppLocale::getLocale());
		$templateMgr->assign('breadcrumbs', $toc->getBreadcrumbs());
		if (!empty($keyword)) {
			$templateMgr->assign('helpSearchKeyword', $keyword);
		}
		if (!empty($result)) {
			$templateMgr->assign('helpSearchResult', $result);
		}
		$templateMgr->display('help/view.tpl');
	}

	/**
	 * Display search results for a topic search by keyword.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function search($args, &$request) {
		$this->validate();
		$this->setupTemplate();

		$searchResults = array();

		$keyword = trim(String::regexp_replace('/[^\w\s\.\-]/', '', strip_tags($request->getUserVar('keyword'))));

		if (!empty($keyword)) {
			$topicDao =& DAORegistry::getDAO('HelpTopicDAO');
			$topics = $topicDao->getTopicsByKeyword($keyword);

			$tocDao =& DAORegistry::getDAO('HelpTocDAO');
			foreach ($topics as $topic) {
				$searchResults[] = array('topic' => $topic, 'toc' => $tocDao->getToc($topic->getTocId()));
			}
		}

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('showSearch', true);
		$templateMgr->assign('pageTitle', __('help.searchResults'));
		$templateMgr->assign('helpSearchKeyword', $keyword);
		$templateMgr->assign('searchResults', $searchResults);
		$templateMgr->display('help/searchResults.tpl');
	}

	/**
	 * Initialize the template
	 */
	function setupTemplate() {
		parent::setupTemplate();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
	}
}

?>

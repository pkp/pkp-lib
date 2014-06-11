<?php

/**
 * @file pages/links/LinksHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LinksHandler
 * @ingroup pages_links
 *
 * @brief Handle link info requests.
 */


import('classes.handler.Handler');

class LinksHandler extends Handler {
	/**
	 * Constructor
	 */
	function LinksHandler() {
		parent::Handler();
	}


	//
	// Public handler operations
	//
	/**
	 * Display the link info page.
	 * @param $args array
	 * @param $request Request
	 */
	function link($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$path = $args[0];
		if ($path != '') {
			$context = $request->getContext();
			$footerCategoryDao = DAORegistry::getDAO('FooterCategoryDAO');
			$category = $footerCategoryDao->getByPath($path, $context->getId());
			if ($category) {
				$templateMgr->assign('category', $category);
				$footerLinkDao = DAORegistry::getDAO('FooterLinkDAO');
				$links = $footerLinkDao->getByCategoryId($category->getId(), $context->getId());
				$templateMgr->assign('links', $links->toArray());
				return $templateMgr->display('links/link.tpl');
			}
		}
	}
}

?>

<?php

/**
 * @file classes/controllers/grid/feature/CollapsibleGridFeature.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CollapsibleGridFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Add collapse and expand functionality to grids.
 *
 */

import('lib.pkp.classes.controllers.grid.feature.GridFeature');
import('lib.pkp.classes.linkAction.request.NullAction');

class CollapsibleGridFeature extends GridFeature {

	/**
	 * @copydoc GridFeature::GridFeature()
	 * Constructor.
	 */
	function CollapsibleGridFeature($id = 'collapsible') {
		parent::GridFeature($id);
	}

	/**
	 * @copyDoc GridFeature::getJSClass()
	 */
	function getJSClass() {
		return '$.pkp.classes.features.CollapsibleGridFeature';
	}

	/**
	 * @copyDoc GridFeature::fetchUIElement()
	 */
	function fetchUIElements($request, $grid) {
		$controlLink = new LinkAction(
			'expandGridControlLink',
			new NullAction(),
			null,
			'expand_all'
		);

		$templateMgr = TemplateManager::getManager();
		$templateMgr->assign('controlLink', $controlLink);
		$markup = $templateMgr->fetch('controllers/grid/feature/collapsibleGridFeature.tpl');

		return array('collapsibleLink' => $markup);
	} 
}

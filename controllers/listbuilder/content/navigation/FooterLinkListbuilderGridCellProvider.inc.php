<?php

/**
 * @file controllers/listbuilder/content/navigation/FooterLinkListbuilderGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FooterLinkListbuilderGridCellProvider
 * @ingroup controllers_listbuilder_content_navigation
 *
 * @brief Provide labels for footer link listbuilder.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class FooterLinkListbuilderGridCellProvider extends GridCellProvider {
	/**
	 * Constructor
	 */
	function FooterLinkListbuilderGridCellProvider() {
		parent::GridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$footerLink =& $row->getData(); /* @var $footerLink FooterLink */
		$columnId = $column->getId();
		assert((is_a($footerLink, 'FooterLink')) && !empty($columnId));

		switch ($columnId) {
			case 'title':
				return array('labelKey' => $footerLink->getId(), 'label' => $footerLink->getData('title'));
			case 'url':
				return array('labelKey' => $footerLink->getId(), 'label' => $footerLink->getUrl());
		}
	}
}

?>

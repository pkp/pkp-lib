<?php

/**
 * @file plugins/importexport/users/PKPUserImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserImportExportDeployment
 * @ingroup plugins_importexport_user
 *
 * @brief Class configuring the user import/export process to this
 * application's specifics.
 */

import('lib.pkp.classes.plugins.importexport.PKPImportExportDeployment');

class PKPUserImportExportDeployment extends PKPImportExportDeployment {
	/** @var Site */
	var $_site;

	/**
	 * Constructor
	 * @param $context Context
	 * @param $user User
	 */
	function __construct($context, $user) {
		parent::__construct($context, $user);
		$site = Application::get()->getRequest()->getSite();
		$this->setSite($site);
	}

	/**
	 * Set the site.
	 * @param $site Site
	 */
	function setSite($site) {
		$this->_site = $site;
	}

	/**
	 * Get the site.
	 * @return Site
	 */
	function getSite() {
		return $this->_site;
	}

	/**
	 * Get the schema filename.
	 * @return string
	 */
	function getSchemaFilename() {
		return 'pkp-users.xsd';
	}

	/**
	 * Get the namespace URN
	 * @return string
	 */
	function getNamespace() {
		return 'http://pkp.sfu.ca';
	}
}



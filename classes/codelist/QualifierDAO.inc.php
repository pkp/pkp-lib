<?php

/**
 * @file classes/codelist/QualifierDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QualifierDAO
 * @ingroup codelist
 * @see Qualifier
 *
 * @brief Operations for retrieving and modifying Subject Qualifier objects.
 *
 */

import('lib.pkp.classes.codelist.Qualifier');
import('lib.pkp.classes.codelist.CodelistItemDAO');


class QualifierDAO extends CodelistItemDAO {

	/**
	 * Constructor.
	 */
	function QualifierDAO() {
		parent::CodelistItemDAO();
	}

	/**
	 * Get the filename of the qualifier database
	 * @param $locale string
	 * @return string
	 */
	function getFilename($locale) {
		if (!AppLocale::isLocaleValid($locale)) {
			$locale = AppLocale::MASTER_LOCALE;
		}
		return "lib/pkp/locale/$locale/bic21qualifiers.xml";
	}

	/**
	 * Get the base node name particular codelist database
	 * This is also the node name in the XML.
	 * @return string
	 */
	function getName() {
		return 'qualifier';
	}

	/**
	 * Get the name of the CodelistItem subclass.
	 * @return String
	 */
	function newDataObject() {
		return new Qualifier();
	}
}

?>

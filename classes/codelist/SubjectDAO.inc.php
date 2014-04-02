<?php

/**
 * @file classes/codelist/SubjectDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubjectDAO
 * @ingroup codelist
 * @see Subject
 *
 * @brief Operations for retrieving and modifying Subject Subject objects.
 *
 */

import('lib.pkp.classes.codelist.Subject');
import('lib.pkp.classes.codelist.CodelistItemDAO');


class SubjectDAO extends CodelistItemDAO {

	/**
	 * Constructor.
	 */
	function SubjectDAO() {
		parent::CodelistItemDAO();
	}

	/**
	 * Get the filename of the subject database
	 * @param $locale string
	 * @return string
	 */
	function getFilename($locale) {
		if (!AppLocale::isLocaleValid($locale)) {
			$locale = AppLocale::MASTER_LOCALE;
		}
		return "lib/pkp/locale/$locale/bic21subjects.xml";
	}

	/**
	 * Get the base node name particular codelist database
	 * This is also the node name in the XML.
	 * @return string
	 */
	function getName() {
		return 'subject';
	}

	/**
	 * Get the name of the CodelistItem class.
	 * @return String
	 */
	function newDataObject() {
		return new Subject();
	}
}

?>

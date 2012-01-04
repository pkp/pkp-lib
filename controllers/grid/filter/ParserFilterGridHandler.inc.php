<?php

/**
 * @file lib/pkp/controllers/grid/filter/ParserFilterGridHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ParserFilterGridHandler
 * @ingroup controllers_grid_filter
 *
 * @brief Defines the filters that will be configured in this grid.
 */

import('classes.controllers.grid.filter.FilterGridHandler');

class ParserFilterGridHandler extends FilterGridHandler {
	/**
	 * Constructor
	 */
	function ParserFilterGridHandler() {
		parent::FilterGridHandler();
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		// Set the input and output sample which
		// define the filters configured in this
		// grid.
		$inputSample = 'arbitrary strings';
		$this->setInputSample($inputSample);

		$outputSample = new MetadataDescription('lib.pkp.classes.metadata.nlm.NlmCitationSchema', ASSOC_TYPE_CITATION);
		$this->setOutputSample($outputSample);

		// Set the title of this grid
		$this->setTitle('manager.setup.filter.parser.grid.title');
		$this->setFormDescription('manager.setup.filter.parser.grid.description');

		parent::initialize($request);
	}
}

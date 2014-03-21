<?php
/**
 * @file controllers/listbuilder/settings/reviewForms/ReviewFormElementResponseItemListbuilderHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementResponseItemListbuilderHandler
 * @ingroup controllers_listbuilder_settings_reviewForms
 *
 * @brief Review form element response item listbuilder handler
 */

import('lib.pkp.controllers.listbuilder.settings.SetupListbuilderHandler');

class ReviewFormElementResponseItemListbuilderHandler extends SetupListbuilderHandler {

	/** @var int **/
	var $_reviewFormElementId;

	/**
	 * Constructor
	 */
	function ReviewFormElementResponseItemListbuilderHandler() {
		parent::SetupListbuilderHandler();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			array('fetchOptions')
		);
	}


	//
	// Overridden template methods
	//
	/**
	 * @see SetupListbuilderHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		$this->_reviewFormElementId = (int)$request->getUserVar('reviewFormElementId');
		// make sure that reviewFormElement exists? Probably not necessary.

		// Basic configuration
		$this->setTitle($request->getUserVar('title'));
		$this->setSourceType(LISTBUILDER_SOURCE_TYPE_TEXT);
		$this->setSaveType(LISTBUILDER_SAVE_TYPE_EXTERNAL);
		$this->setSaveFieldName('order'); # FIXME should this be 'options' or 'responses'?

		// Possible response column
		$responseColumn = new MultilingualListbuilderGridColumn($this, 'possibleResponse', 'manager.reviewFormElements.possibleResponse', null, null, null, null, array('tabIndex' => 1));
		import('lib.pkp.controllers.listbuilder.settings.reviewForms.ReviewFormElementResponseItemListbuilderGridCellProvider');
	 	$responseColumn->setCellProvider(new ReviewFormElementResponseItemListbuilderGridCellProvider());	
		$this->addColumn($responseColumn);
	}

	/**
	 * @see GridHandler::loadData()
	 */
	function loadData($request) {
		// FIXME this is problematic because in the case of possible reponses, we're not dealing with a regular data row object. Tough to figure out what exactly we want to return. The various grid/listbuilder functions expect data of a certain format, and possible responses don't fit into that format!
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
		$reviewFormElement = $reviewFormElementDao->getById($this->_reviewFormElementId);
		#$locales = AppLocale::getSupportedFormLocales();
		$locale = "en_US"; // how do we know which locale we are using? what if there are multiple?
		if($reviewFormElement) {
			$possibleResponses = $reviewFormElement->getPossibleResponses($locale);
			return $possibleResponses;
		}
	}

	/**
	 * @see GridHandler::getRowDataElement
	 * Get the data element that corresponds to the current request
	 * Allow for a blank $rowId for when creating a not-yet-persisted row
	 */
	function getRowDataElement($request, $rowId) {
		// fallback on the parent if a rowId is found
		if ( !empty($rowId) ) {
			return parent::getRowDataElement($request, $rowId); 
		}

		// Otherwise return from the $newRowId
		$rowData = $this->getNewRowId($request); // this contains the new data just entered in the listbuilder, if any
		// FIXME need to figure out exactly what to return here. We want an array containing the new response, the equivalent of getRowDataElement, or one row worth of data. 
		// need to write a function that returns $rowData given either an empty row or a not-yet-persisted row
		$rowData = array('order' => '', 'content' => '');
		//return $rowData;
		// wrapping the data in another array to try to fix the truncation problem, but doesn't seem to help.
		return array(array('order' =>  '', 'content' =>  $rowData['possibleResponse']));
	}

	/**
	 * Fetch the review form element ID for this listbuilder.
	 * @return int
	 */
	function _getReviewFormElementId() {
		return $this->_reviewFormElementId;
	}
}
?>

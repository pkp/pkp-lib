<?php

/**
 * @file controllers/grid/settings/reviewForms/ReviewFormElementsGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementsGridHandler
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Handle review form element grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.settings.reviewForms.ReviewFormElementGridRow');
import('lib.pkp.controllers.grid.settings.reviewForms.form.ReviewFormElementForm');

class ReviewFormElementsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function ReviewFormElementsGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(array(
			ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow', 'saveSequence',
				'createReviewFormElement', 'editReviewFormElement', 'deleteReviewFormElement', 'updateReviewFormElement')
		);
	}

	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_ADMIN,
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_USER
		);

		// Grid actions.
		$router = $request->getRouter();

		import('lib.pkp.classes.linkAction.request.AjaxModal');

		// Create Review Form Element link
		$reviewFormId = (int) $request->getUserVar('reviewFormId');
		$this->addAction(
			new LinkAction(
				'createReviewFormElement',
				new AjaxModal(
					$router->url($request, null, null, 'createReviewFormElement', null, array('reviewFormId' => $reviewFormId)),
					__('manager.reviewFormElements.create'),
					'modal_add_item',
					true
					),
				__('manager.reviewFormElements.create'),
				'add_item'
			)
		);


		//
		// Grid columns.
		//
		import('lib.pkp.controllers.grid.settings.reviewForms.ReviewFormElementGridCellProvider');
		$reviewFormElementGridCellProvider = new ReviewFormElementGridCellProvider();

		// Review form element name.
		$this->addColumn(
			new GridColumn(
				'question',
				'manager.reviewFormElements.question',
				null,
				'controllers/grid/gridCell.tpl',
				$reviewFormElementGridCellProvider
			)
		);

	       // Basic grid configuration.
	       $this->setTitle('manager.reviewFormElements');
	}

	//
	// Implement methods from GridHandler.
	//
	/**
	 * @see GridHandler::addFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		return array(new OrderGridItemsFeature());
	}

	/**
	 * @see GridHandler::getRowInstance()
	 * @return UserGridRow
	 */
	function getRowInstance() {
		return new ReviewFormElementGridRow();
	}

	/**
	 * @see GridHandler::loadData()
	 * @param $request PKPRequest
	 * @return array Grid data.
	 */
	function loadData($request) {
		// Get review form elements.
		//$rangeInfo = $this->getRangeInfo('reviewFormElements');
		$reviewFormId = $request->getUserVar('reviewFormId');
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
		$reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId, null); //FIXME add range info?

		return $reviewFormElements->toAssociativeArray();
	}


	/**
	 * @see lib/pkp/classes/controllers/grid/GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, &$reviewForm, $newSequence) {
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /* @var $reviewFormElementDao ReviewFormElementDAO */
		$reviewFormElement->setSequence($newSequence);
		$reviewFormElementDao->updateObject($reviewFormElement);
	}


	//
	// Public grid actions.
	//
	/**
	 * Add a new review form element.
	* @param $args array
	 * @param $request PKPRequest
	 */
	function createReviewFormElement($args, $request) {
		// Identify the review form Id
		$reviewFormId = (int) $request->getUserVar('reviewFormId');

		// Form handling
		$reviewFormElementForm = new ReviewFormElementForm($reviewFormId);
		$reviewFormElementForm->initData($request);
		$json = new JSONMessage(true, $reviewFormElementForm->fetch($args, $request));

		return $json->getString();
	}

	/**
	 * Edit an existing review form element.
	* @param $args array
	 * @param $request PKPRequest
	 */
	function editReviewFormElement($args, $request) {
		// Identify the review form Id
		$reviewFormId = (int) $request->getUserVar('reviewFormId');

		// Identify the review form element Id
		$reviewFormElementId = (int) $request->getUserVar('rowId');

		// Display form
		$reviewFormElementForm = new ReviewFormElementForm($reviewFormId, $reviewFormElementId);
		$reviewFormElementForm->initData($request);
		$json = new JSONMessage(true, $reviewFormElementForm->fetch($args, $request));

		return $json->getString();
	}

	/**
	 * Save changes to a review form element.
	 */
	function updateReviewFormElement($args, $request) {
		$reviewFormId = (int) $request->getUserVar('reviewFormId');
		$reviewFormElementId = (int) $request->getUserVar('reviewFormElementId');

		$context = $request->getContext();
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
		$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');

		$reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());

		if (!$reviewFormDao->unusedReviewFormExists($reviewFormId, Application::getContextAssocType(), $context->getId()) || ($reviewFormElementId && !$reviewFormElementDao->reviewFormElementExists($reviewFormElementId, $reviewFormId))) {
			fatalError('Invalid review form information!');
		}

		import('lib.pkp.controllers.grid.settings.reviewForms.form.ReviewFormElementForm');
		$reviewFormElementForm = new ReviewFormElementForm($reviewFormId, $reviewFormElementId);
		$reviewFormElementForm->readInputData();

		if ($reviewFormElementForm->validate()) {
			$reviewFormElementId = $reviewFormElementForm->execute($request);

			// Create the notification.
			$notificationMgr = new NotificationManager();
			$user = $request->getUser();
			$notificationMgr->createTrivialNotification($user->getId());

			return DAO::getDataChangedEvent($reviewFormElementId);
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}

	/**
	 * Delete a review form element.
	 * @param $args array ($reviewFormId, $reviewFormElementId)
	 */
	function deleteReviewFormElement($args, $request) {
		$reviewFormId = (int) $request->getUserVar('reviewFormId');
		$reviewFormElementId = (int) $request->getUserVar('rowId');

		$context = $request->getContext();
		$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');

		if ($reviewFormDao->unusedReviewFormExists($reviewFormId, Application::getContextAssocType(), $context->getId())) {
			$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
			$reviewFormElementDao->deleteById($reviewFormElementId);
			return DAO::getDataChangedEvent($reviewFormElementId);
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}
}

?>

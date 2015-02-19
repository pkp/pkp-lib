<?php

/**
 * @file controllers/grid/settings/genre/GenreGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GenreGridHandler
 * @ingroup controllers_grid_settings_genre
 *
 * @brief Handle Genre grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');
import('lib.pkp.controllers.grid.settings.genre.GenreGridRow');

class GenreGridHandler extends SetupGridHandler {
	/**
	 * Constructor
	 */
	function GenreGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(array(ROLE_ID_MANAGER), array(
			'fetchGrid', 'fetchRow',
			'addGenre', 'editGenre', 'updateGenre',
			'deleteGenre', 'restoreGenres', 'saveSequence'
		));
	}


	//
	// Overridden template methods
	//
	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load language components
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_GRID,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_DEFAULT
		);

		// Set the grid title.
		$this->setTitle('grid.genres.title');

		$this->setInstructions('grid.genres.description');

		// Add grid-level actions
		$router = $request->getRouter();
		$actionArgs = array('gridId' => $this->getId());

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addGenre',
				new AjaxModal(
					$router->url($request, null, null, 'addGenre', null, $actionArgs),
					__('grid.action.addGenre'),
					'modal_add_item',
					true),
				__('grid.action.addGenre'),
				'add_item')
		);

		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		$this->addAction(
			new LinkAction(
				'restoreGenres',
				new RemoteActionConfirmationModal(
					__('grid.action.restoreDefaults.confirm'),
					null,
					$router->url($request, null, null, 'restoreGenres', null, $actionArgs), 'modal_delete'),
				__('grid.action.restoreDefaults'),
				'reset_default')
		);

		// Columns
		$cellProvider = new DataObjectGridCellProvider();
		$cellProvider->setLocale(AppLocale::getLocale());
		$this->addColumn(
			new GridColumn('name',
				'common.name',
				null,
				null,
				$cellProvider
			)
		);

		$this->addColumn(
			new GridColumn(
				'designation',
				'common.designation',
				null,
				null,
				$cellProvider
			)
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		// Elements to be displayed in the grid
		$context = $request->getContext();
		$genreDao = DAORegistry::getDAO('GenreDAO');
		return $genreDao->getEnabledByContextId($context->getId(), self::getRangeInfo($request, $this->getId()));
	}

	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
		return array(new OrderGridItemsFeature());
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return GenreGridRow
	 */
	function getRowInstance() {
		return new GenreGridRow();
	}

	/**
	 * @copydoc GridHandler::getDataElementSequence()
	 */
	function getDataElementSequence($row) {
		return $row->getSequence();
	}

	/**
	 * @copydoc GridHandler::setDataElementSequence()
	 */
	function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence) {
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$context = $request->getContext();
		$genre = $genreDao->getById($rowId, $context->getId());
		$genre->setSequence($newSequence);
		$genreDao->updateObject($genre);
	}

	//
	// Public Genre Grid Actions
	//
	/**
	 * An action to add a new Genre
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addGenre($args, $request) {
		// Calling editGenre with an empty row id will add a new Genre.
		return $this->editGenre($args, $request);
	}

	/**
	 * An action to edit a Genre
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editGenre($args, $request) {
		$genreId = isset($args['genreId']) ? (int) $args['genreId'] : null;

		$this->setupTemplate($request);

		import('lib.pkp.controllers.grid.settings.genre.form.GenreForm');
		$genreForm = new GenreForm($genreId);

		$genreForm->initData($args, $request);

		return new JSONMessage(true, $genreForm->fetch($request));
	}

	/**
	 * Update a Genre
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function updateGenre($args, $request) {
		$genreId = isset($args['genreId']) ? (int) $args['genreId'] : null;
		$context = $request->getContext();

		import('lib.pkp.controllers.grid.settings.genre.form.GenreForm');
		$genreForm = new GenreForm($genreId);
		$genreForm->readInputData();

		$router = $request->getRouter();

		if ($genreForm->validate()) {
			$genreForm->execute($args, $request);
			return DAO::getDataChangedEvent($genreForm->getGenreId());
		} else {
			return new JSONMessage(false);
		}
	}

	/**
	 * Delete a Genre.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteGenre($args, $request) {
		// Identify the Genre to be deleted
		$genre =& $this->_getGenreFromArgs($request, $args);

		$genreDao = DAORegistry::getDAO('GenreDAO');
		$result = $genreDao->deleteObject($genre);

		if ($result) {
			return DAO::getDataChangedEvent($genre->getId());
		} else {
			return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
		}
	}

	/**
	 * Restore the default Genre settings for the context.
	 * All default settings that were available when the context instance was created will be restored.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function restoreGenres($args, $request) {
		$context = $request->getContext();

		// Restore all the genres in this context form the registry XML file
		$genreDao = DAORegistry::getDAO('GenreDAO');
		$genreDao->restoreByContextId($context->getId());
		return DAO::getDataChangedEvent();
	}

	//
	// Private helper function
	//
	/**
	 * This will retrieve a Genre object from the
	 * grids data source based on the request arguments.
	 * If no Genre can be found then this will raise
	 * a fatal error.
	 * @param $args array
	 * @return Genre
	 */
	function &_getGenreFromArgs($request, $args) {
		// Identify the Genre Id and retrieve the
		// corresponding element from the grid's data source.
		if (!isset($args['genreId'])) {
			fatalError('Missing Genre Id!');
		} else {
			$genre =& $this->getRowDataElement($request, $args['genreId']);
			if (is_null($genre)) fatalError('Invalid Genre Id!');
		}
		return $genre;
	}
}

?>

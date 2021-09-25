<?php

/**
 * @file controllers/grid/settings/genre/GenreGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreGridHandler
 * @ingroup controllers_grid_settings_genre
 *
 * @brief Handle Genre grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('lib.pkp.controllers.grid.settings.genre.GenreGridRow');

use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Role;

class GenreGridHandler extends SetupGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER], [
            'fetchGrid', 'fetchRow',
            'addGenre', 'editGenre', 'updateGenre',
            'deleteGenre', 'restoreGenres', 'saveSequence'
        ]);
    }


    //
    // Overridden template methods
    //
    /**
     * Configure the grid
     *
     * @see SetupGridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

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

        // Add grid-level actions
        $router = $request->getRouter();
        $actionArgs = ['gridId' => $this->getId()];

        $this->addAction(
            new LinkAction(
                'addGenre',
                new AjaxModal(
                    $router->url($request, null, null, 'addGenre', null, $actionArgs),
                    __('grid.action.addGenre'),
                    'modal_add_item',
                    true
                ),
                __('grid.action.addGenre'),
                'add_item'
            )
        );

        $this->addAction(
            new LinkAction(
                'restoreGenres',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('grid.action.restoreDefaults.confirm'),
                    null,
                    $router->url($request, null, null, 'restoreGenres', null, $actionArgs),
                    'modal_delete'
                ),
                __('grid.action.restoreDefaults'),
                'reset_default'
            )
        );

        // Columns
        $cellProvider = new DataObjectGridCellProvider();
        $cellProvider->setLocale(Locale::getLocale());
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $cellProvider
            )
        );
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Elements to be displayed in the grid
        $context = $request->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        return $genreDao->getEnabledByContextId($context->getId(), self::getRangeInfo($request, $this->getId()));
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderGridItemsFeature()];
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return GenreGridRow
     */
    protected function getRowInstance()
    {
        return new GenreGridRow();
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($row)
    {
        return $row->getSequence();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
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
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addGenre($args, $request)
    {
        // Calling editGenre with an empty row id will add a new Genre.
        return $this->editGenre($args, $request);
    }

    /**
     * An action to edit a Genre
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editGenre($args, $request)
    {
        $genreId = isset($args['genreId']) ? (int) $args['genreId'] : null;

        $this->setupTemplate($request);

        import('lib.pkp.controllers.grid.settings.genre.form.GenreForm');
        $genreForm = new GenreForm($genreId);

        $genreForm->initData($args);

        return new JSONMessage(true, $genreForm->fetch($request));
    }

    /**
     * Update a Genre
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateGenre($args, $request)
    {
        $genreId = isset($args['genreId']) ? (int) $args['genreId'] : null;
        $context = $request->getContext();

        import('lib.pkp.controllers.grid.settings.genre.form.GenreForm');
        $genreForm = new GenreForm($genreId);
        $genreForm->readInputData();

        $router = $request->getRouter();

        if ($genreForm->validate()) {
            $genreForm->execute();
            return \PKP\db\DAO::getDataChangedEvent($genreForm->getGenreId());
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Delete a Genre.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteGenre($args, $request)
    {
        $genreId = isset($args['genreId']) ? (int) $args['genreId'] : null;
        $context = $request->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genre = $genreDao->getById($genreId, $context->getId());
        if ($genre && $request->checkCSRF()) {
            $genreDao->deleteObject($genre);
            return \PKP\db\DAO::getDataChangedEvent($genre->getId());
        }
        return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
    }

    /**
     * Restore the default Genre settings for the context.
     * All default settings that were available when the context instance was created will be restored.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function restoreGenres($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        // Restore all the genres in this context form the registry XML file
        $context = $request->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genreDao->installDefaults($context->getId(), $context->getSupportedFormLocales());
        return \PKP\db\DAO::getDataChangedEvent();
    }
}

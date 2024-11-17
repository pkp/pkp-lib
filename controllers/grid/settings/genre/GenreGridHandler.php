<?php

/**
 * @file controllers/grid/settings/genre/GenreGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenreGridHandler
 *
 * @ingroup controllers_grid_settings_genre
 *
 * @brief Handle Genre grid requests.
 */

namespace PKP\controllers\grid\settings\genre;

use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\settings\genre\form\GenreForm;
use PKP\controllers\grid\settings\SetupGridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Role;
use PKP\submission\GenreDAO;

class GenreGridHandler extends SetupGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], [
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
                    'side-modal',
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
                    'primary'
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

        $genreForm = new GenreForm($genreId);
        $genreForm->readInputData();

        if ($genreForm->validate()) {
            $genreForm->execute();
            return DAO::getDataChangedEvent($genreForm->getGenreId());
        }

        return new JSONMessage(false);
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

        if (!$request->checkCSRF()) {
            return new JSONMessage(false, __('form.csrfInvalid'));
        }

        if (!$genre) {
            return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
        }

        $submissionsByGenre = Repo::submissionFile()
            ->getCollector()
            ->filterByGenreIds([$genreId])
            ->getCount();

        // Block the removal of genres that have at least one assigned submission file
        if ($submissionsByGenre) {
            return new JSONMessage(false, __('manager.genres.alertDelete'));
        }

        $genreDao->deleteObject($genre);
        return DAO::getDataChangedEvent($genre->getId());
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
        return DAO::getDataChangedEvent();
    }
}

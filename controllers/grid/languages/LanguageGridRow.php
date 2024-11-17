<?php

/**
 * @file controllers/grid/languages/LanguageGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LanguageGridRow
 *
 * @ingroup controllers_grid_languages
 *
 * @brief Language grid row definition
 */

namespace PKP\controllers\grid\languages;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Validation;

class LanguageGridRow extends GridRow
{
    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $rowId = $this->getId();
        $rowData = $this->getData();

        if (!empty($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = [
                'gridId' => $this->getGridId(),
                'rowId' => $rowId
            ];

            if (Validation::isSiteAdmin()) {
                if (!$request->getContext() && !$rowData['primary']) {
                    $this->addAction(
                        new LinkAction(
                            'uninstall',
                            new RemoteActionConfirmationModal(
                                $request->getSession(),
                                __('admin.languages.confirmUninstall'),
                                __('grid.action.remove'),
                                $router->url($request, null, null, 'uninstallLocale', null, $actionArgs),
                                'negative'
                            ),
                            __('grid.action.remove'),
                            'delete'
                        )
                    );
                }
                if ($request->getContext()) {
                    $this->addAction(
                        new LinkAction(
                            'reload',
                            new RemoteActionConfirmationModal(
                                $request->getSession(),
                                __('manager.language.confirmDefaultSettingsOverwrite'),
                                __('manager.language.reloadLocalizedDefaultSettings'),
                                $router->url($request, null, null, 'reloadLocale', null, $actionArgs),
                                'primary'
                            ),
                            __('manager.language.reloadLocalizedDefaultSettings')
                        )
                    );
                }
            }
        }
    }
}

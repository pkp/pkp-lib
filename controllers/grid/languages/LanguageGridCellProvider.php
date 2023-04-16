<?php

/**
 * @file controllers/grid/languages/LanguageGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LanguageGridCellProvider
 *
 * @ingroup controllers_grid_languages
 *
 * @brief Subclass for a language grid column's cell provider
 */

namespace PKP\controllers\grid\languages;

use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class LanguageGridCellProvider extends GridCellProvider
{
    /**
     * @copydoc GridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();

        switch ($columnId) {
            case 'enable':
                return ['selected' => $element['supported'],
                    'disabled' => false];
            case 'locale':
                $label = $element['name'];
                $returnArray = ['label' => $label];
                if (isset($element['incomplete'])) {
                    $returnArray['incomplete'] = $element['incomplete'];
                }
                return $returnArray;
            case 'code':
                $label = $element['code'];
                $returnArray = ['label' => $label];
                return $returnArray;
            case 'sitePrimary':
                return ['selected' => $element['primary'],
                    'disabled' => !$element['supported']];
            case 'contextPrimary':
                return ['selected' => $element['primary'],
                    'disabled' => !$element['supported']];
            case 'uiLocale':
                return ['selected' => $element['supportedLocales'],
                    'disabled' => !$element['supported']];
            case 'formLocale':
                return ['selected' => $element['supportedFormLocales'],
                    'disabled' => !$element['supported']];
            case 'submissionLocale':
                return ['selected' => $element['supportedSubmissionLocales'],
                    'disabled' => !$element['supported']];
            default:
                assert(false);
                break;
        }
    }

    /**
     * @copydoc GridCellProvider::getCellActions()
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $element = $row->getData();
        $router = $request->getRouter();
        $actions = [];
        $actionArgs = ['rowId' => $row->getId()];

        $action = null;
        $actionRequest = null;

        switch ($column->getId()) {
            case 'enable':
                $enabled = $element['supported'];
                if ($enabled) {
                    $action = 'disable-' . $row->getId();
                    $actionRequest = new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('admin.languages.confirmDisable'),
                        __('common.disable'),
                        $router->url($request, null, null, 'disableLocale', null, $actionArgs)
                    );
                } else {
                    $action = 'enable-' . $row->getId();
                    $actionRequest = new AjaxAction($router->url($request, null, null, 'enableLocale', null, $actionArgs));
                }
                break;
            case 'sitePrimary':
                $primary = $element['primary'];
                if (!$primary) {
                    $action = 'setPrimary-' . $row->getId();
                    $actionRequest = new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('admin.languages.confirmSitePrimaryLocaleChange'),
                        __('locale.primary'),
                        $router->url($request, null, null, 'setPrimaryLocale', null, $actionArgs)
                    );
                }
                break;
            case 'contextPrimary':
                $primary = $element['primary'];
                if (!$primary) {
                    $action = 'setPrimary-' . $row->getId();
                    $actionRequest = new AjaxAction($router->url($request, null, null, 'setContextPrimaryLocale', null, $actionArgs));
                }
                break;
            case 'uiLocale':
                $action = 'setUiLocale-' . $row->getId();
                $actionArgs['setting'] = 'supportedLocales';
                $actionArgs['value'] = !$element['supportedLocales'];
                $actionRequest = new AjaxAction($router->url($request, null, null, 'saveLanguageSetting', null, $actionArgs));
                break;
            case 'formLocale':
                $action = 'setFormLocale-' . $row->getId();
                $actionArgs['setting'] = 'supportedFormLocales';
                $actionArgs['value'] = !$element['supportedFormLocales'];
                $actionRequest = new AjaxAction($router->url($request, null, null, 'saveLanguageSetting', null, $actionArgs));
                break;
            case 'submissionLocale':
                $action = 'setSubmissionLocale-' . $row->getId();
                $actionArgs['setting'] = 'supportedSubmissionLocales';
                $actionArgs['value'] = !$element['supportedSubmissionLocales'];
                $actionRequest = new AjaxAction($router->url($request, null, null, 'saveLanguageSetting', null, $actionArgs));
                break;
        }

        if ($action && $actionRequest) {
            $linkAction = new LinkAction($action, $actionRequest, null, null);
            $actions = [$linkAction];
        }

        return $actions;
    }
}

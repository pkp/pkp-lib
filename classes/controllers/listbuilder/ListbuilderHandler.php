<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderHandler
 *
 * @ingroup controllers_listbuilder
 *
 * @brief Class defining basic operations for handling Listbuilder UI elements
 */

namespace PKP\controllers\listbuilder;

use APP\core\Request;
use APP\template\TemplateManager;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\LinkActionRequest;
use PKP\linkAction\request\NullAction;

class ListbuilderHandler extends GridHandler
{
    /** @var int Listbuilder source types: text-based, pulldown, ... */
    public const LISTBUILDER_SOURCE_TYPE_TEXT = 0;
    public const LISTBUILDER_SOURCE_TYPE_SELECT = 1;

    /** @var int Listbuilder save types */
    public const LISTBUILDER_SAVE_TYPE_EXTERNAL = 0;
    public const LISTBUILDER_SAVE_TYPE_INTERNAL = 1;

    /** @var string String to identify optgroup in the returning options data. If you want to use
     * optgroup in listbuilder select, return the options data in a multidimensional array
     * array[columnIndex][optgroupId][selectItemId] and also with
     * array[columnIndex][LISTBUILDER_OPTGROUP_LABEL][optgroupId] */
    public const LISTBUILDER_OPTGROUP_LABEL = 'optGroupLabel';

    /** @var int Definition of the type of source LISTBUILDER_SOURCE_TYPE_... */
    public $_sourceType;

    /** @var int Constant indicating the save approach for the LB LISTBUILDER_SAVE_TYPE_... */
    public $_saveType = self::LISTBUILDER_SAVE_TYPE_INTERNAL;

    /** @var string Field for LISTBUILDER_SAVE_TYPE_EXTERNAL naming the field used to send the saved contents of the LB */
    public $_saveFieldName = null;

    /**
     * @copydoc GridHandler::initialize
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        if ($this->canAddItems()) {
            $this->addAction($this->getAddItemLinkAction(new NullAction()));
        }
    }


    //
    // Getters and Setters
    //
    /**
     * Get the listbuilder template.
     *
     * @return string
     */
    public function getTemplate()
    {
        if (is_null($this->_template)) {
            $this->setTemplate('controllers/listbuilder/listbuilder.tpl');
        }

        return $this->_template;
    }

    /**
     * Set the type of source (Free text input, select from list, autocomplete)
     *
     * @param int $sourceType LISTBUILDER_SOURCE_TYPE_...
     */
    public function setSourceType($sourceType)
    {
        $this->_sourceType = $sourceType;
    }

    /**
     * Get the type of source (Free text input, select from list, autocomplete)
     *
     * @return int LISTBUILDER_SOURCE_TYPE_...
     */
    public function getSourceType()
    {
        return $this->_sourceType;
    }

    /**
     * Set the save type (using this handler or another external one)
     */
    public function setSaveType($saveType)
    {
        $this->_saveType = $saveType;
    }

    /**
     * Get the save type (using this handler or another external one)
     *
     * @return int LISTBUILDER_SAVE_TYPE_...
     */
    public function getSaveType()
    {
        return $this->_saveType;
    }

    /**
     * Set the save field name for LISTBUILDER_SAVE_TYPE_EXTERNAL
     *
     * @param string $fieldName
     */
    public function setSaveFieldName($fieldName)
    {
        $this->_saveFieldName = $fieldName;
    }

    /**
     * Set the save field name for LISTBUILDER_SAVE_TYPE_EXTERNAL.
     * This will be the HTML field that receives the data upon submission.
     *
     * @return string
     */
    public function getSaveFieldName()
    {
        assert(isset($this->_saveFieldName));
        return $this->_saveFieldName;
    }

    /**
     * Get the "add item" link action.
     *
     * @param LinkActionRequest $actionRequest
     *
     * @return LinkAction
     */
    public function getAddItemLinkAction($actionRequest)
    {
        return new LinkAction(
            'addItem',
            $actionRequest,
            __('grid.action.addItem'),
            'add_item'
        );
    }

    /**
     * Get the new row ID from the request. For multi-column listbuilders,
     * this is an array representing the row. For single-column
     * listbuilders, this is a single piece of data (i.e. a string or int)
     *
     * @param PKPRequest $request
     */
    public function getNewRowId($request)
    {
        return $request->getUserVar('newRowId');
    }

    /**
     * Delete an entry.
     *
     * @param Request $request object
     * @param mixed $rowId ID of row to modify
     *
     * @return bool
     */
    public function deleteEntry($request, $rowId)
    {
        fatalError('ABSTRACT METHOD');
    }

    /**
     * Persist an update to an entry.
     *
     * @param Request $request object
     * @param mixed $rowId ID of row to modify
     * @param mixed $newRowId ID of the new entry
     *
     * @return bool
     */
    public function updateEntry($request, $rowId, $newRowId)
    {
        // This may well be overridden by a subclass to modify
        // an existing entry, e.g. to maintain referential integrity.
        // If not, we can simply delete and insert.
        if (!$this->deleteEntry($request, $rowId)) {
            return false;
        }
        return $this->insertEntry($request, $newRowId);
    }

    /**
     * Persist a new entry insert.
     *
     * @param Request $request object
     * @param mixed $newRowId ID of row to modify
     *
     * @return bool
     */
    public function insertEntry($request, $newRowId)
    {
        fatalError('ABSTRACT METHOD');
    }

    /**
     * Fetch the options for a LISTBUILDER_SOURCE_TYPE_SELECT LB
     * Should return a multidimensional array:
     * array(
     * 	array('column 1 option 1', 'column 2 option 1'),
     * 	array('column 1 option 2', 'column 2 option 2'
     * );
     *
     * @param Request $request
     *
     * @return array
     */
    public function getOptions($request)
    {
        return [];
    }

    //
    // Publicly (remotely) available listbuilder functions
    //
    /**
     * Fetch the listbuilder.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function fetch($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $options = $this->getOptions($request);
        $availableOptions = false;
        if (is_array($options) && !empty($options)) {
            $firstColumnOptions = current($options);
            $optionsCount = count($firstColumnOptions);
            if (is_array(current($firstColumnOptions))) { // Options with opt group, count only the selectable options.
                unset($firstColumnOptions[self::LISTBUILDER_OPTGROUP_LABEL]);
                $optionsCount--;
                $optionsCount = count($firstColumnOptions, COUNT_RECURSIVE) - $optionsCount;
            }

            $listElements = $this->getGridDataElements($request);
            if (count($listElements) < $optionsCount) {
                $availableOptions = true;
            }
        }

        $templateMgr->assign('availableOptions', $availableOptions);

        return $this->fetchGrid($args, $request);
    }

    /**
     * Unpack data to save using an external handler.
     *
     * @param string $data (the json encoded data from the listbuilder itself)
     * @param array $deletionCallback callback to be used for each deleted element
     * @param array $insertionCallback callback to be used for each updated element
     * @param array $updateCallback callback to be used for each updated element
     */
    public static function unpack($request, $data, $deletionCallback, $insertionCallback, $updateCallback)
    {
        $data = json_decode($data);
        $status = true;

        // Handle deletions
        if (isset($data->deletions) && $data->deletions !== '') {
            foreach (explode(' ', trim($data->deletions)) as $rowId) {
                if (!call_user_func($deletionCallback, $request, $rowId, $data->numberOfRows)) {
                    $status = false;
                }
            }
        }

        // Handle changes and insertions
        if (isset($data->changes)) {
            foreach ($data->changes as $entry) {
                // Get the row ID, if any, from submitted data
                if (isset($entry->rowId)) {
                    $rowId = $entry->rowId;
                    unset($entry->rowId);
                } else {
                    $rowId = null;
                }

                // $entry should now contain only submitted modified or new rows.
                // Go through each and unpack the data in prep for application.
                $changes = [];
                foreach ($entry as $key => $value) {
                    // Match the column name and localization data, if any.
                    if (!preg_match('/^newRowId\[([a-zA-Z]+)\](\[([a-z][a-z](_[A-Z][A-Z])?(@([A-Za-z0-9]{5,8}|\d[A-Za-z0-9]{3}))?)\])?$/', $key, $matches)) {
                        assert(false);
                    }

                    // Get the column name
                    $column = $matches[1];

                    // If this is a multilingual input, fetch $locale; otherwise null
                    $locale = $matches[3] ?? null;

                    if ($locale) {
                        $changes[$column][$locale] = $value;
                    } else {
                        $changes[$column] = $value;
                    }
                }

                // $changes should now contain e.g.:
                // array ('localizedColumnName' => array('en' => 'englishValue'),
                // 'nonLocalizedColumnName' => 'someNonLocalizedValue');
                if (is_null($rowId)) {
                    if (!call_user_func($insertionCallback, $request, $changes)) {
                        $status = false;
                    }
                } else {
                    if (!call_user_func($updateCallback, $request, $rowId, $changes)) {
                        $status = false;
                    }
                }
            }
        }
        return $status;
    }

    /**
     * Save the listbuilder using the internal handler.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function save($args, $request)
    {
        // The ListbuilderHandler will post a list of changed
        // data in the "data" post var. Need to go through it
        // and reconcile the data against this list, adding/
        // updating/deleting as needed.
        $data = $request->getUserVar('data');
        self::unpack(
            $request,
            $data,
            $this->deleteEntry(...),
            $this->insertEntry(...),
            $this->updateEntry(...)
        );
    }


    /**
     * Load the set of options for a select list type listbuilder.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchOptions($args, $request)
    {
        $options = $this->getOptions($request);
        return new JSONMessage(true, $options);
    }


    /**
     * Can items be added to this list builder?
     *
     * @return bool
     */
    public function canAddItems()
    {
        return true;
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::getRowInstance()
     *
     * @return ListbuilderGridRow
     */
    protected function getRowInstance()
    {
        // Return a citation row
        return new ListbuilderGridRow();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\listbuilder\ListbuilderHandler', '\ListbuilderHandler');
    foreach ([
        'LISTBUILDER_SOURCE_TYPE_TEXT',
        'LISTBUILDER_SOURCE_TYPE_SELECT',
        'LISTBUILDER_SAVE_TYPE_EXTERNAL',
        'LISTBUILDER_SAVE_TYPE_INTERNAL',
        'LISTBUILDER_OPTGROUP_LABEL',
    ] as $constantName) {
        define($constantName, constant('\ListbuilderHandler::' . $constantName));
    }
}

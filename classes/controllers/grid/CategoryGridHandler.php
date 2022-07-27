<?php

/**
 * @file classes/controllers/grid/CategoryGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridHandler
 * @ingroup controllers_grid
 *
 * @brief Class defining basic operations for handling HTML grids with categories.
 */

namespace PKP\controllers\grid;

use APP\template\TemplateManager;

use Illuminate\Support\LazyCollection;
use PKP\core\JSONMessage;

class CategoryGridHandler extends GridHandler
{
    /** @var string empty category row locale key */
    public $_emptyCategoryRowText = 'grid.noItems';

    /** @var array The category grid's data source. */
    public $_categoryData;

    /** @var string The category id that this grid is currently rendering. */
    public $_currentCategoryId = null;


    /**
     * Constructor.
     *
     * @param null|mixed $dataProvider
     */
    public function __construct($dataProvider = null)
    {
        parent::__construct($dataProvider);

        $this->addColumn(new GridColumn(
            'indent',
            null,
            null,
            null,
            new NullGridCellProvider(),
            ['indent' => true, 'width' => 2]
        ));
    }


    //
    // Getters and setters.
    //
    /**
     * Get the empty rows text for a category.
     *
     * @return string
     */
    public function getEmptyCategoryRowText()
    {
        return $this->_emptyCategoryRowText;
    }

    /**
     * Set the empty rows text for a category.
     *
     * @param string $translationKey
     */
    public function setEmptyCategoryRowText($translationKey)
    {
        $this->_emptyCategoryRowText = $translationKey;
    }

    /**
     * Get the category id that this grid is currently rendering.
     *
     * @return int
     */
    public function getCurrentCategoryId()
    {
        return $this->_currentCategoryId;
    }

    /**
     * Override to return the data element sequence value
     * inside the passed category, if needed.
     *
     * @param int $categoryId The data element category id.
     * @param mixed $gridDataElement The element to return the
     * sequence.
     *
     * @return int
     */
    public function getDataElementInCategorySequence($categoryId, &$gridDataElement)
    {
        assert(false);
    }

    /**
     * Override to set the data element new sequence inside
     * the passed category, if needed.
     *
     * @param int $categoryId The data element category id.
     * @param mixed $gridDataElement The element to set the
     * new sequence.
     * @param int $newSequence The new sequence value.
     */
    public function setDataElementInCategorySequence($categoryId, &$gridDataElement, $newSequence)
    {
        assert(false);
    }

    /**
     * Override to define whether the data element inside the passed
     * category is selected or not.
     *
     * @param int $categoryId
     */
    public function isDataElementInCategorySelected($categoryId, &$gridDataElement)
    {
        assert(false);
    }

    /**
     * Get the grid category data.
     *
     * @param PKPRequest $request
     * @param mixed $categoryElement The category element.
     *
     * @return array
     */
    public function &getGridCategoryDataElements($request, $categoryElement)
    {
        $filter = $this->getFilterSelectionData($request);

        // Get the category element id.
        $categories = $this->getGridDataElements($request);
        $categoryElementId = array_search($categoryElement, $categories);
        assert($categoryElementId !== false);

        // Try to load data if it has not yet been loaded.
        if (!is_array($this->_categoryData) || !array_key_exists($categoryElementId, $this->_categoryData)) {
            $data = $this->loadCategoryData($request, $categoryElement, $filter);

            if (is_null($data)) {
                // Initialize data to an empty array.
                $data = [];
            }

            $this->setGridCategoryDataElements($request, $categoryElementId, $data);
        }

        return $this->_categoryData[$categoryElementId];
    }

    /**
     * Check whether the passed category has grid rows.
     *
     * @param mixed $categoryElement The category data element
     * that will be checked.
     * @param PKPRequest $request
     *
     * @return bool
     */
    public function hasGridDataElementsInCategory($categoryElement, $request)
    {
        $data = & $this->getGridCategoryDataElements($request, $categoryElement);
        assert(is_array($data));
        return (bool) count($data);
    }

    /**
     * Get the number of elements inside the passed category element.
     *
     * @param PKPRequest $request
     *
     * @return int
     */
    public function getCategoryItemsCount($categoryElement, $request)
    {
        $data = $this->getGridCategoryDataElements($request, $categoryElement);
        assert(is_array($data));
        return count($data);
    }

    /**
     * Set the grid category data.
     *
     * @param string $categoryElementId The category element id.
     * @param mixed $data an array or ItemIterator with category elements data.
     */
    public function setGridCategoryDataElements($request, $categoryElementId, $data)
    {
        // Make sure we have an array to store all categories elements data.
        if (!is_array($this->_categoryData)) {
            $this->_categoryData = [];
        }

        // FIXME: We go to arrays for all types of iterators because
        // iterators cannot be re-used, see #6498.
        if (is_array($data)) {
            $this->_categoryData[$categoryElementId] = $data;
        } elseif ($data instanceof \PKP\db\DAOResultFactory) {
            $this->_categoryData[$categoryElementId] = $data->toAssociativeArray();
        } elseif ($data instanceof \PKP\core\ItemIterator) {
            $this->_categoryData[$categoryElementId] = $data->toArray();
        } elseif ($data instanceof LazyCollection) {
            $this->_categoryData[$categoryElementId] = iterator_to_array($data);
        } else {
            assert(false);
        }
    }


    //
    // Public handler methods
    //
    /**
     * Render a category with all the rows inside of it.
     *
     * @param array $args
     * @param Request $request
     *
     * @return string the serialized row JSON message or a flag
     *  that indicates that the row has not been found.
     */
    public function fetchCategory($args, $request)
    {
        // Instantiate the requested row (includes a
        // validity check on the row id).
        $row = $this->getRequestedCategoryRow($request, $args);

        $json = new JSONMessage(true);
        if (is_null($row)) {
            // Inform the client that the category does no longer exist.
            $json->setAdditionalAttributes(['elementNotFound' => (int)$args['rowId']]);
        } else {
            // Render the requested category
            $this->setFirstDataColumn();
            $json->setContent($this->_renderCategoryInternally($request, $row));
        }
        return $json;
    }


    //
    // Extended methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        if (!is_null($request->getUserVar('rowCategoryId'))) {
            $this->_currentCategoryId = (string) $request->getUserVar('rowCategoryId');
        }
    }

    /**
     * @see GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $args = parent::getRequestArgs();

        // If grid is rendering grid rows inside category,
        // add current category id value so rows will also know
        // their parent category.
        if (!is_null($this->_currentCategoryId)) {
            if ($this->getCategoryRowIdParameterName()) {
                $args[$this->getCategoryRowIdParameterName()] = $this->_currentCategoryId;
            }
        }

        return $args;
    }


    /**
     * @copydoc GridHandler::getJSHandler()
     */
    public function getJSHandler()
    {
        return '$.pkp.controllers.grid.CategoryGridHandler';
    }

    /**
     * @copydoc GridHandler::setUrls()
     */
    public function setUrls($request, $extraUrls = [])
    {
        $router = $request->getRouter();
        $extraUrls['fetchCategoryUrl'] = $router->url($request, null, null, 'fetchCategory', null, $this->getRequestArgs());
        parent::setUrls($request, $extraUrls);
    }

    /**
     * @copydoc GridHandler::getRowsSequence()
     */
    protected function getRowsSequence($request)
    {
        return array_keys($this->getGridCategoryDataElements($request, $this->getCurrentCategoryId()));
    }

    /**
     * @see GridHandler::doSpecificFetchGridActions()
     */
    protected function doSpecificFetchGridActions($args, $request, $templateMgr)
    {
        // Render the body elements (category groupings + rows inside a <tbody>)
        $gridBodyParts = $this->_renderCategoriesInternally($request);
        $templateMgr->assign('gridBodyParts', $gridBodyParts);
    }

    /**
     * @copydoc GridHandler::getRowDataElement()
     */
    protected function getRowDataElement($request, &$rowId)
    {
        $rowData = parent::getRowDataElement($request, $rowId);
        $rowCategoryId = $request->getUserVar('rowCategoryId');

        if (is_null($rowData) && !is_null($rowCategoryId)) {
            // Try to get row data inside category.
            $categoryRowData = parent::getRowDataElement($request, $rowCategoryId);
            if (!is_null($categoryRowData)) {
                $categoryElements = $this->getGridCategoryDataElements($request, $categoryRowData);

                assert(is_array($categoryElements));
                if (!isset($categoryElements[$rowId])) {
                    return null;
                }

                // Let grid (and also rows) knowing the current category id.
                // This value will be published by the getRequestArgs method.
                $this->_currentCategoryId = $rowCategoryId;

                return $categoryElements[$rowId];
            }
        } else {
            return $rowData;
        }
    }

    /**
     * @see GridHandler::setFirstDataColumn()
     */
    protected function setFirstDataColumn()
    {
        $columns = & $this->getColumns();
        reset($columns);
        // Category grids will always have indent column firstly,
        // so we need to consider the first column the second one.
        $secondColumn = next($columns); /** @var GridColumn $secondColumn */
        $secondColumn->addFlag('firstColumn', true);
    }

    /**
     * @see GridHandler::renderRowInternally()
     */
    protected function renderRowInternally($request, $row)
    {
        if ($this->getCategoryRowIdParameterName()) {
            $param = $this->getRequestArg($this->getCategoryRowIdParameterName());
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('categoryId', $param);
        }

        return parent::renderRowInternally($request, $row);
    }

    /**
     * Tries to identify the data element in the grids
     * data source that corresponds to the requested row id.
     * Raises a fatal error if such an element cannot be
     * found.
     *
     * @param PKPRequest $request
     * @param array $args
     *
     * @return GridRow the requested grid row, already
     *  configured with id and data or null if the row
     *  could not been found.
     */
    protected function getRequestedCategoryRow($request, $args)
    {
        if (isset($args['rowId'])) {
            // A row ID was specified. Fetch it
            $elementId = $args['rowId'];

            // Retrieve row data for the requested row id
            // (we can use the default getRowData element, works for category grids as well).
            $dataElement = $this->getRowDataElement($request, $elementId);
            if (is_null($dataElement)) {
                // If the row doesn't exist then
                // return null. It may be that the
                // row has been deleted in the meantime
                // and the client does not yet know about this.
                $nullVar = null;
                return $nullVar;
            }
        }

        // Instantiate a new row
        return $this->_getInitializedCategoryRowInstance($request, $elementId, $dataElement);
    }


    //
    // Protected methods to be overridden/used by subclasses
    //
    /**
     * Get a new instance of a category grid row. May be
     * overridden by subclasses if they want to
     * provide a custom row definition.
     *
     * @return CategoryGridRow
     */
    protected function getCategoryRowInstance()
    {
        //provide a sensible default category row definition
        return new GridCategoryRow();
    }

    /**
     * Get the category row id parameter name.
     *
     * @return string
     */
    protected function getCategoryRowIdParameterName()
    {
        // Must be implemented by subclasses.
        return null;
    }

    /**
     * Implement this method to load category data into the grid.
     *
     * @param PKPRequest $request
     * @param null|mixed $filter
     *
     * @return array
     */
    protected function loadCategoryData($request, &$categoryDataElement, $filter = null)
    {
        $gridData = [];
        $dataProvider = $this->getDataProvider();
        if ($dataProvider instanceof \PKP\controllers\grid\CategoryGridDataProvider) {
            // Populate the grid with data from the
            // data provider.
            $gridData = $dataProvider->loadCategoryData($request, $categoryDataElement, $filter);
        }
        return $gridData;
    }


    //
    // Private helper methods
    //
    /**
     * Instantiate a new row.
     *
     * @param Request $request
     * @param string $elementId
     *
     * @return GridRow
     */
    private function _getInitializedCategoryRowInstance($request, $elementId, $element)
    {
        // Instantiate a new row
        $row = $this->getCategoryRowInstance();
        $row->setGridId($this->getId());
        $row->setId($elementId);
        $row->setData($element);
        $row->setRequestArgs($this->getRequestArgs());

        // Initialize the row before we render it
        $row->initialize($request);
        $this->callFeaturesHook(
            'getInitializedCategoryRowInstance',
            ['request' => $request,
                'grid' => $this,
                'categoryId' => $this->_currentCategoryId,
                'row' => $row]
        );
        return $row;
    }

    /**
     * Render all the categories internally
     *
     * @param PKPRequest $request
     */
    private function _renderCategoriesInternally($request)
    {
        // Iterate through the rows and render them according
        // to the row definition.
        $renderedCategories = [];

        $elements = $this->getGridDataElements($request);
        foreach ($elements as $key => $element) {

            // Instantiate a new row
            $categoryRow = $this->_getInitializedCategoryRowInstance($request, $key, $element);

            // Render the row
            $renderedCategories[] = $this->_renderCategoryInternally($request, $categoryRow);
        }

        return $renderedCategories;
    }

    /**
     * Render a category row and its data.
     *
     * @param PKPRequest $request
     * @param GridCategoryRow $categoryRow
     *
     * @return string HTML for all the rows (including category)
     */
    private function _renderCategoryInternally($request, $categoryRow)
    {
        // Prepare the template to render the category.
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('grid', $this);
        $columns = $this->getColumns();
        $templateMgr->assign('columns', $columns);

        $categoryDataElement = $categoryRow->getData();
        $rowData = $this->getGridCategoryDataElements($request, $categoryDataElement);

        // Render the data rows
        $templateMgr->assign('categoryRow', $categoryRow);

        // Let grid (and also rows) knowing the current category id.
        // This value will be published by the getRequestArgs method.
        $this->_currentCategoryId = $categoryRow->getId();

        $renderedRows = $this->renderRowsInternally($request, $rowData);
        $templateMgr->assign('rows', $renderedRows);

        $renderedCategoryRow = $this->renderRowInternally($request, $categoryRow);

        // Finished working with this category, erase the current id value.
        $this->_currentCategoryId = null;

        $templateMgr->assign('renderedCategoryRow', $renderedCategoryRow);
        return $templateMgr->fetch('controllers/grid/gridBodyPartWithCategory.tpl');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\CategoryGridHandler', '\CategoryGridHandler');
}

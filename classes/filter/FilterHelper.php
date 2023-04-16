<?php
/**
 * @file classes/filter/FilterHelper.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterHelper
 *
 * @ingroup filter
 *
 * @brief Class that provides filter-related helper methods.
 */

namespace PKP\filter;

use PKP\core\PKPString;
use PKP\db\DAORegistry;

class FilterHelper
{
    /**
     * @verbatim
     * Helper method that installs filter groups based on
     * the given XML node which represents a <filterGroups>
     * element.
     *
     * @endverbatim
     *
     * @param XMLNode $filterGroupsNode
     */
    public function installFilterGroups($filterGroupsNode)
    {
        // Install filter groups.
        $filterGroupDao = DAORegistry::getDAO('FilterGroupDAO'); /** @var FilterGroupDAO $filterGroupDao */

        foreach ($filterGroupsNode->getChildren() as $filterGroupNode) { /** @var XMLNode $filterGroupNode */
            $filterGroupSymbolic = $filterGroupNode->getAttribute('symbolic');

            // Make sure that the filter group has not been
            // installed before to guarantee idempotence.
            $existingFilterGroup = $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic);
            if (!is_null($existingFilterGroup)) {
                continue;
            }

            // Instantiate and configure the filter group.
            $filterGroup = new FilterGroup();
            $filterGroup->setSymbolic($filterGroupSymbolic);
            $filterGroup->setDisplayName($filterGroupNode->getAttribute('displayName'));
            $filterGroup->setDescription($filterGroupNode->getAttribute('description'));
            $filterGroup->setInputType($filterGroupNode->getAttribute('inputType'));
            $filterGroup->setOutputType($filterGroupNode->getAttribute('outputType'));

            // Install the filter group.
            $installedGroupId = $filterGroupDao->insertObject($filterGroup);
            assert(is_integer($installedGroupId));

            unset($filterGroup);
        }
    }

    /**
     * @verbatim
     * Helper method that configures and optionally
     * installs a filter based on the given XML node
     * which represents a <filter> element.
     *
     * @endverbatim
     *
     * @param XMLNode $filterNode
     * @param bool $persist whether to install the filter
     *
     * @return PersistableFilter the installed filter.
     */
    public function &configureFilter($filterNode, $persist = true)
    {
        // Install filters.
        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */

        $filterGroupSymbolic = $filterNode->getAttribute('inGroup');
        $filterClassName = $filterNode->getAttribute('class');
        $isTemplate = $filterNode->getAttribute('isTemplate');

        // We have to include the filter class before going on
        // so that all required constants are defined before they
        // might be used in settings.
        if (PKPString::regexp_match('/^[a-zA-Z0-9.]+$/', $filterClassName)) {
            import($filterClassName);
        }

        // Go through the filter sub-nodes. This can be nested
        // filters or filter settings.
        $subNodes = $filterNode->getChildren();
        $settings = [];
        $subFilters = [];
        foreach ($subNodes as $subNode) { /** @var XMLNode $subNode */
            switch ($subNode->getName()) {
                case 'setting':
                    // Get the filter setting.
                    [$name, $value] = $this->getFilterSetting($subNode);
                    $settings[$name] = $value;
                    unset($name, $value);
                    break;

                case 'filter':
                    // Recursively configure sub-filters.
                    $subFilter = & $this->configureFilter($subNode, false);
                    $subFilters[] = & $subFilter;
                    unset($subFilter);
                    break;
            }
        }

        // We ensure idempotence of plug-in installation by checking
        // for existing identical filters.
        $similarFilters = $filterDao->getObjectsByGroupAndClass($filterGroupSymbolic, $filterClassName, 0, $isTemplate)->toArray();

        if (count($similarFilters) > 0) {
            // Go through similar filters and eliminate them if they don't have the exact same settings.
            foreach ($similarFilters as $index => $similarFilter) { /** @var PersistableFilter $similarFilter */
                if (!$this->compareFilters($similarFilter, $settings, $subFilters)) {
                    unset($similarFilters[$index]);
                }
            }

            // There can be a maximum of exactly one identical transformation
            // in the database otherwise we've somehow installed a duplicate filter.
            assert(count($similarFilters) <= 1);

            // If the filter has been installed before then return the existing filter.
            if (count($similarFilters) == 1) {
                $existingFilter = array_pop($similarFilters);
                return $existingFilter;
            }
        }

        // Configure (and optionally install) the filter.
        $installedFilter = $filterDao->configureObject($filterClassName, $filterGroupSymbolic, $settings, $isTemplate, 0, $subFilters, $persist);
        if (!$installedFilter instanceof \PKP\filter\PersistableFilter) {
            throw new \Exception($filterClassName . ' expected to be an instance of PersistableFilter');
        }

        return $installedFilter;
    }

    /**
     * Recursively compares two filters (filter A and filter B)
     * based on their settings and sub-filters.
     *
     * @param PersistableFilter $filterA
     * @param array $filterBSettings an array of key/value pairs
     * @param array $filterBSubfilters an array of filters
     *
     * @return bool true if the two transformations are identical, false otherwise
     */
    public function compareFilters(&$filterA, $filterBSettings, &$filterBSubfilters)
    {
        // Compare settings.
        foreach ($filterBSettings as $name => $value) {
            if (!($filterA->hasSetting($name) || in_array($name, $filterA->getInternalSettings()))
                || $filterA->getData($name) != $value) {
                return false;
            }
        }

        // Compare sub-filters.
        if ($filterA instanceof \PKP\filter\CompositeFilter) {
            // Compare sub-filters of composite filters.
            foreach ($filterBSubfilters as $filterBSubfilter) { /** @var PersistableFilter $filterBSubfilter */
                $seq = $filterBSubfilter->getSequence();
                $filterASubfilter = & $filterA->getFilter($seq);
                if (!$filterASubfilter || !$filterBSubfilter || get_class($filterASubfilter) != get_class($filterBSubfilter)) {
                    return false;
                }

                // Extract sub-filter settings.
                $filterBSubfilterSettings = [];
                foreach ($filterBSubfilter->getSettingNames() as $filterBSubfilterSettingName) {
                    if ($filterBSubfilter->hasData($filterBSubfilterSettingName)) {
                        $filterBSubfilterSettings[$filterBSubfilterSettingName] = $filterBSubfilter->getData($filterBSubfilterSettingName);
                    }
                }

                // Extract sub-filter sub-filters.
                if ($filterBSubfilter instanceof \PKP\filter\CompositeFilter) {
                    $filterBSubfilterSubfilters = & $filterBSubfilter->getFilters();
                } else {
                    $filterBSubfilterSubfilters = [];
                }

                // Recurse.
                if (!$this->compareFilters($filterASubfilter, $filterBSubfilterSettings, $filterBSubfilterSubfilters)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @verbatim
     * Helper method that extracts filter settings
     * from the children of a <filter> element.
     *
     * @endverbatim
     *
     * @param XMLNode $settingNode
     *
     * @return $setting array a key-value pair.
     */
    public function getFilterSetting($settingNode)
    {
        // Retrieve the setting name.
        $nameNode = & $settingNode->getChildByName('name');
        assert($nameNode instanceof \PKP\xml\XMLNode);
        $name = $nameNode->getValue();

        // Retrieve the setting value.
        $type = $settingNode->getAttribute('type');
        $valueNode = & $settingNode->getChildByName('value');
        assert($valueNode instanceof \PKP\xml\XMLNode);
        switch ($type) {
            case 'string':
                $value = (string)$valueNode->getValue();
                break;

            case 'bool':
                $value = (bool)$valueNode->getValue();
                break;

            case 'int':
                $value = (int)$valueNode->getValue();
                break;

            case 'const':
                $constName = $valueNode->getValue();
                assert(defined($constName));
                $value = constant($constName);
                break;

            case 'object':
                $value = [];
                $arrayNode = & $valueNode->getChildByName('array');
                $value = $this->readArraySetting($arrayNode);
                break;

            default:
                // Unknown type.
                assert(false);
                $value = null;
        }

        // Add the setting to the list.
        $setting = [$name, $value];
        return $setting;
    }

    /**
     * Recursively read an array from an XML element list.
     *
     * @param XMLNode $arrayNode
     *
     * @return array
     */
    public function readArraySetting($arrayNode)
    {
        $resultArray = [];
        foreach ($arrayNode->getChildren() as $elementNode) { /** @var XMLNode $elementNode */
            $key = $elementNode->getAttribute('key');

            $subArrayNode = $elementNode->getChildByName('array');
            if ($subArrayNode instanceof \PKP\xml\XMLNode) {
                // Recurse into sub-array.
                $value = $this->readArraySetting($subArrayNode);
            } else {
                // Read the element value.
                $value = $elementNode->getValue();
            }

            // Add the element to the result set.
            if (isset($key)) {
                $resultArray[$key] = $value;
            } else {
                $resultArray[] = $value;
            }
        }

        return $resultArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\FilterHelper', '\FilterHelper');
}

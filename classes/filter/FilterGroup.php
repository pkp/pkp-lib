<?php

/**
 * @file classes/filter/FilterGroup.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterGroup
 *
 * @ingroup filter
 *
 * @see PersistableFilter
 *
 * @brief Class that represents filter groups.
 *
 * A filter group is a category of filters that all accept the exact same input
 * and output type and execute semantically very similar tasks (e.g. all citation
 * parsers or all citation output filters).
 *
 * Distinct filter groups can have define the same input and output types if they
 * do semantically different things (e.g. two XSL operations that both take
 * XML as input and output but do different things).
 *
 * A transformation can only be part of exactly one filter group. If you find that
 * you want to add the same transformation (same input/output type and same
 * parameterization) to two different filter groups then this indicates that the
 * semantics of the two groups has been defined ambivalently.
 *
 * The rules for defining filter groups are like this:
 * 1) Describe what the transformation does and not in which context the transformation
 *    is being used (e.g. "NLM-3.0 citation-element to plaintext citation output conversion"
 *    rather than "Reading tool citation filter").
 * 2) Make sure that the name is really unique with respect to input type, output type
 *    and potential parameterizations of filters in the group. Otherwise you can expect
 *    to get name clashes later (e.g. use "NLM-3.0 ... conversion" and not "NLM ... conversion"
 *    otherwise you'll get a name clash when NLM 4.0 or 3.1 comes out.
 *
 * It can be difficult to change filter group names later as we expect community
 * contributions to certain filter groups (e.g. citation parsers).
 */

namespace PKP\filter;

class FilterGroup extends \PKP\core\DataObject
{
    //
    // Setters and Getters
    //
    /**
     * Set the symbolic name
     *
     * @param string $symbolic
     */
    public function setSymbolic($symbolic)
    {
        $this->setData('symbolic', $symbolic);
    }

    /**
     * Get the symbolic name
     *
     * @return string
     */
    public function getSymbolic()
    {
        return $this->getData('symbolic');
    }

    /**
     * Set the display name
     *
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->setData('displayName', $displayName);
    }

    /**
     * Get the display name
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->getData('displayName');
    }

    /**
     * Set the description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->setData('description', $description);
    }

    /**
     * Get the description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getData('description');
    }

    /**
     * Set the input type
     *
     * @param string $inputType a string representation of a TypeDescription
     */
    public function setInputType($inputType)
    {
        $this->setData('inputType', $inputType);
    }

    /**
     * Get the input type
     *
     * @return string a string representation of a TypeDescription
     */
    public function getInputType()
    {
        return $this->getData('inputType');
    }

    /**
     * Set the output type
     *
     * @param string $outputType a string representation of a TypeDescription
     */
    public function setOutputType($outputType)
    {
        $this->setData('outputType', $outputType);
    }

    /**
     * Get the output type
     *
     * @return string a string representation of a TypeDescription
     */
    public function getOutputType()
    {
        return $this->getData('outputType');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\FilterGroup', '\FilterGroup');
}

<?php

/**
 * @file classes/filter/GenericMultiplexerFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenericMultiplexerFilter
 *
 * @ingroup filter
 *
 * @brief A generic filter that is configured with a number of
 *  equal type filters. It takes the input argument, applies all
 *  given filters to it and returns an array of outputs as a result.
 *
 *  The result can then be sent to either an iterator filter or
 *  to a de-multiplexer filter.
 */

namespace PKP\filter;

class GenericMultiplexerFilter extends CompositeFilter
{
    /**
     * @var bool whether some sub-filters can fail as long as at least one
     *  filter returns a result.
     */
    public $_tolerateFailures = false;


    //
    // Setters and Getters
    //
    /**
     * Set to true if sub-filters can fail as long as
     * at least one filter returns a result.
     *
     * @param bool $tolerateFailures
     */
    public function setTolerateFailures($tolerateFailures)
    {
        $this->_tolerateFailures = $tolerateFailures;
    }

    /**
     * Returns true when sub-filters can fail as long
     * as at least one filter returns a result.
     *
     * @return bool
     */
    public function getTolerateFailures()
    {
        return $this->_tolerateFailures;
    }

    //
    // Implementing abstract template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @return array
     */
    public function &process(&$input)
    {
        // Iterate over all filters and return the results
        // as an array.
        $output = [];
        foreach ($this->getFilters() as $filter) {
            // Make a copy of the input so that the filters don't interfere
            // with each other.
            if (is_object($input)) {
                $clonedInput = clone($input);
            } else {
                $clonedInput = $input;
            }

            // Execute the filter
            $intermediateOutput = & $filter->execute($clonedInput);

            // Propagate errors of sub-filters (if any)
            foreach ($filter->getErrors() as $errorMessage) {
                $this->addError($errorMessage);
            }

            // Handle sub-filter failure.
            if (is_null($intermediateOutput)) {
                if ($this->getTolerateFailures()) {
                    continue;
                } else {
                    // No need to go on as the filter will fail
                    // anyway out output validation so we better
                    // safe time and return immediately.
                    $output = null;
                    break;
                }
            } else {
                // Add the output to the output array.
                $output[] = & $intermediateOutput;
            }
            unset($clonedInput, $intermediateOutput);
        }

        // Fail in any case if all sub-filters failed.
        if (empty($output)) {
            $output = null;
        }

        return $output;
    }
}

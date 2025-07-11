<?php

/**
 * @file classes/filter/GenericSequencerFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GenericSequencerFilter
 *
 * @ingroup filter
 *
 * @brief A generic filter that is configured with a number of
 *  ordered filters. It takes the input argument of the first filter,
 *  passes its output to the next filter and so on and finally returns
 *  the result of the last filter in the chain to the caller.
 */

namespace PKP\filter;

class GenericSequencerFilter extends CompositeFilter
{
    //
    // Implementing abstract template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     */
    public function &process(&$input)
    {
        // Iterate over all filters and always feed the
        // output of one filter as input to the next
        // filter.
        $previousOutput = null;
        foreach ($this->getFilters() as $filter) {
            if (is_null($previousOutput)) {
                // First filter
                $previousOutput = & $input;
            }
            $output = $filter->execute($previousOutput);

            // Propagate errors of sub-filters (if any)
            foreach ($filter->getErrors() as $errorMessage) {
                $this->addError($errorMessage);
            }

            // If one filter returns null then we'll abort
            // execution of the filter chain.
            if (is_null($output)) {
                break;
            }

            unset($previousOutput);
            $previousOutput = $output;
        }
        return $output;
    }
}

<?php

/**
 * @file classes/cliTool/traits/HasParameterList.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @trait HasParameterList
 *
 * @brief A helper trait manage the params and flags on CLI interface
 */

namespace PKP\cliTool\traits;

trait HasParameterList
{
    /**
     * Parameters and arguments from CLI
     */
    protected ?array $parameterList = null;

    /**
     * Save the parameter list passed on CLI
     *
     * @param array $items Array with parameters and arguments passed on CLI
     */
    public function setParameterList(array $items): static
    {
        $parameters = [];

        foreach ($items as $param) {
            if (strpos($param, '=')) {
                [$key, $value] = explode('=', ltrim($param, '-'));
                $parameters[$key] = $value;

                continue;
            }

            $parameters[] = $param;
        }

        $this->parameterList = $parameters;

        return $this;
    }

    /**
     * Get the parameter list passed on CLI
     */
    public function getParameterList(): ?array
    {
        return $this->parameterList;
    }

    /**
     * Get the value of a specific parameter
     */
    protected function getParameterValue(string $parameter, mixed $default = null): mixed
    {
        if (!isset($this->getParameterList()[$parameter])) {
            return $default;
        }

        return $this->getParameterList()[$parameter];
    }

    /**
     * Determined if the given flag set on CLI
     */
    protected function hasFlagSet(string $flag): bool
    {
        return in_array($flag, $this->getParameterList());
    }
}

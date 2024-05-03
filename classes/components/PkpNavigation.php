<?php
/**
 * @file classes/components/PkpNavigation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PkpNavigation
 *
 * @ingroup classes_components
 *
 * @brief A Panel component for rendering navigation menu
 */

namespace PKP\components;

class PkpNavigation
{
    /**
     * Aria-label for the navigation
     *
     * @var string
     */
    public $ariaLabel;
    
    /**
     * Navigation Links
     *
     * @var array
     */
    public $links;

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * @return array Configuration data
     */
    public function getConfig()
    {
        $config = [];
        $config['ariaLabel'] = $this->ariaLabel;
        $config['links'] = $this->links;

        return $config;
    }

}

<?php

namespace PKP\view;

use Closure;

/**
 * A class to define a content block to be displayed on the homepage
 * in the reader-facing UI.
 *
 * The `loader` callback function does not receive any params:
 *
 * function() {
 *     view()->share('myPluginData', 'My test data');
 * }
 */
class HomepageBlock extends Block
{
    public function __construct(
        public string $component,
        public string $title,
        public string $id = '',
        public ?Closure $loader = null,

        /**
         * Whether or not this block should be available
         * for the context homepage (journal, press, server)
         */
        public bool $forContext = true,

        /**
         * Whether or not this block should be available
         * for the site-wide homepage
         */
        public bool $forSite = true,
    ) {
        parent::__construct(
            component: $component,
            title: $title,
            id: $id,
            loader: $loader,
        );
    }
}
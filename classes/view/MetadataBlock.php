<?php

namespace PKP\view;

/**
 * A class to define a block of metadata for an article, book
 * or preprint, that will be displayed when viewing the landing
 * page in the reader-facing UI.
 *
 * The `loader` callback function should accept the following params:
 *
 * function(Publication $publication, Submission $submission) {
 *     view()->share('myPluginData', 'My test data');
 * }
 */
class MetadataBlock extends Block
{
    //
}
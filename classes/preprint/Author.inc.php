<?php

/**
 * @file classes/preprint/Author.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Author
 * @ingroup preprint
 *
 * @see AuthorDAO
 *
 * @brief Preprint author metadata class.
 */

namespace APP\preprint;

use PKP\submission\PKPAuthor;

class Author extends PKPAuthor
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\preprint\Author', '\Author');
}

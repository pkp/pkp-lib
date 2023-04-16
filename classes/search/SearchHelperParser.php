<?php

/**
 * @file classes/search/SearchHelperParser.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHelperParser
 *
 * @ingroup search
 *
 * @brief Class to extract text from a file using an external helper program.
 */

namespace PKP\search;

use PKP\config\Config;

class SearchHelperParser extends SearchFileParser
{
    /** @var string Type should match an index[$type] setting in the "search" section of config.inc.php */
    public $type;

    public function __construct($type, $filePath)
    {
        parent::__construct($filePath);
        $this->type = $type;
    }

    public function open()
    {
        $prog = Config::getVar('search', 'index[' . $this->type . ']');

        if (isset($prog)) {
            $exec = sprintf($prog, escapeshellarg($this->getFilePath()));
            $this->fp = @popen($exec, 'r');
            return $this->fp ? true : false;
        }

        return false;
    }

    public function close()
    {
        pclose($this->fp);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SearchHelperParser', '\SearchHelperParser');
}

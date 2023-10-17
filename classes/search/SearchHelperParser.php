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

use Exception;
use PKP\config\Config;

class SearchHelperParser extends SearchFileParser
{
    /** @var string Type should match an index[$type] setting in the "search" section of config.inc.php */
    public $type;

    private $command;

    public function __construct($type, $filePath)
    {
        parent::__construct($filePath);
        $this->type = $type;
    }

    public function open()
    {
        $prog = Config::getVar('search', 'index[' . $this->type . ']');
        if (isset($prog)) {
            $this->command = sprintf($prog, escapeshellarg($this->getFilePath()));
            if (!($this->fp = @popen($this->command, 'r'))) {
                throw new Exception("Failed to parse file {$this->getFilePath()} through the command: {$this->command}\nLast error: " . error_get_last());
            }
            return true;
        }

        return false;
    }

    public function close()
    {
        if ($this->fp && ($exitCode = pclose($this->fp))) {
            throw new Exception("The indexation process exited with the code \"{$exitCode}\", perhaps the command failed: {$this->command}");
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SearchHelperParser', '\SearchHelperParser');
}

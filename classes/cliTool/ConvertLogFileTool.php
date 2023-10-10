<?php

/**
 * @file lib/pkp/classes/cliTool/ConvertLogFileTool.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConvertLogFileTool
 *
 * @ingroup tools
 *
 * @brief Tool to convert usage stats log file (used in releases < 3.4) into the new format.
 *
 */

namespace PKP\cliTool;

use PKP\cliTool\traits\ConvertLogFile;

abstract class ConvertLogFileTool extends \PKP\cliTool\CommandLineTool
{
    use ConvertLogFile;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments (see usage)
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);
        $this->__constructTrait();
    }
}

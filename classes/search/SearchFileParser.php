<?php

/**
 * @defgroup search Search
 * Implements search tools, such as file parsers, workflow integration,
 * indexing, querying, etc.
 */

/**
 * @file classes/search/SearchFileParser.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchFileParser
 *
 * @ingroup search
 *
 * @brief Abstract class to extract search text from a given file.
 */

namespace PKP\search;

use Exception;
use PKP\config\Config;
use PKP\submissionFile\SubmissionFile;

class SearchFileParser
{
    /** @var string the complete path to the file */
    public $filePath;

    /** @var resource file handle */
    public $fp;

    /**
     * Constructor.
     *
     * @param string $filePath
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Return the path to the file.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Change the file path.
     *
     * @param string $filePath
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Open the file.
     *
     * @return bool
     */
    public function open()
    {
        if (!($this->fp = @fopen($this->filePath, 'rb'))) {
            throw new Exception("Failed to parse the file \"{$this->filePath}\". Last error: " . error_get_last());
        }
        return true;
    }

    /**
     * Close the file.
     */
    public function close()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    /**
     * Read and return the next block/line of text.
     *
     * @return string (false on EOF)
     */
    public function read()
    {
        if (!$this->fp || feof($this->fp)) {
            return false;
        }
        return $this->doRead();
    }

    /**
     * Read from the file pointer.
     *
     * @return string
     */
    public function doRead()
    {
        return fgets($this->fp);
    }


    //
    // Static methods
    //

    /**
     * Create a text parser for a file.
     *
     * @param SubmissionFile $submissionFile
     *
     * @return ?SearchFileParser
     */
    public static function fromFile($submissionFile)
    {
        $fullPath = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $submissionFile->getData('path');
        return static::fromFileType($submissionFile->getData('mimetype'), $fullPath);
    }

    /**
     * Create a text parser for a file.
     *
     * @param string $type
     * @param string $path
     *
     * @return ?SearchFileParser
     */
    public static function fromFileType($type, $path)
    {
        if (Config::getVar('search', "index[{$type}]")) {
            $parserType = 'process';
        } else {
            // If an indexer definition exists, but its value is falsy, we assume the user wants to disable the default handler
            $parserType = Config::hasVar('search', "index[{$type}]") ? 'disabled' : $type;
        }
        return match ($parserType) {
            // External process
            'process' => new SearchHelperParser($type, $path),
            // Text processor
            'text/plain' => new static($path),
            // HTML/XML processor
            'text/html', 'text/xml', 'application/xhtml', 'application/xml' => new SearchHTMLParser($path),
            // Disabled/no suitable parser
            default => null
        };
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SearchFileParser', '\SearchFileParser');
}

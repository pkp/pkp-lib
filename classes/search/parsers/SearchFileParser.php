<?php

/**
 * @file classes/search/parsers/SearchFileParser.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchFileParser
 *
 * @brief Abstract class to extract search text from a given file.
 */

namespace PKP\search\parsers;

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
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Change the file path.
     */
    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    /**
     * Open the file.
     */
    public function open(): bool
    {
        if (!($this->fp = @fopen($this->filePath, 'rb'))) {
            throw new Exception("Failed to parse the file \"{$this->filePath}\". Last error: " . error_get_last());
        }
        return true;
    }

    /**
     * Close the file.
     */
    public function close(): void
    {
        if ($this->fp) {
            fclose($this->fp);
            $this->fp = null;
        }
    }

    /**
     * Read and return the next block/line of text (or false on EOF).
     */
    public function read(): string|bool
    {
        if (!$this->fp || feof($this->fp)) {
            return false;
        }
        return $this->doRead();
    }

    /**
     * Read a string from the file pointer (or false on EOF)
     */
    public function doRead(): string|bool
    {
        return fgets($this->fp);
    }


    //
    // Static methods
    //

    /**
     * Create a text parser for a file.
     */
    public static function fromFile(SubmissionFile $submissionFile): ?SearchFileParser
    {
        $fullPath = rtrim(Config::getVar('files', 'files_dir'), '/') . '/' . $submissionFile->getData('path');
        return static::fromFileType($submissionFile->getData('mimetype'), $fullPath);
    }

    /**
     * Create a text parser for a file.
     *
     * @param string $type MIME type
     */
    public static function fromFileType(string $type, string $path): ?SearchFileParser
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

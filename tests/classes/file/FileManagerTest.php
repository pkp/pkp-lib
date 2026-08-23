<?php

/**
 * @file tests/classes/file/FileManagerTest.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileManagerTest
 *
 * @ingroup tests_classes_file
 *
 * @see FileManager
 *
 * @brief Tests for the FileManager class, in particular the extension/MIME
 *   type safety checks performed on uploaded files.
 */

namespace PKP\tests\classes\file;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\file\FileManager;
use PKP\tests\PKPTestCase;

#[CoversClass(FileManager::class)]
class FileManagerTest extends PKPTestCase
{
    private FileManager $fileManager;

    /** @var string[] Temporary files created during a test, cleaned up in tearDown() */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileManager = new FileManager();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            @unlink($tempFile);
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    /**
     * Create a temporary file with the given contents and track it for cleanup.
     */
    private function createTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtest');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;
        return $path;
    }

    public static function safeExtensionProvider(): array
    {
        return [
            'plain text' => ['notes.txt', 'txt'],
            'word document' => ['manuscript.docx', 'docx'],
            'pdf' => ['report.pdf', 'pdf'],
            'uppercase extension' => ['IMAGE.JPG', 'JPG'],
            'no extension' => ['README', 'txt'],
            'tar.gz archive' => ['archive.tar.gz', 'tar.gz'],
        ];
    }

    #[DataProvider('safeExtensionProvider')]
    public function testParseFileExtensionAllowsSafeExtensions(string $fileName, string $expectedExtension): void
    {
        $this->assertEquals($expectedExtension, $this->fileManager->parseFileExtension($fileName));
    }

    public static function dangerousExtensionProvider(): array
    {
        return [
            'php' => ['shell.php'],
            'uppercase PHP' => ['shell.PHP'],
            'php5' => ['shell.php5'],
            // Regression coverage: these bypassed the old stristr($ext, 'php')
            // check because they don't contain the substring "php".
            'phtml' => ['shell.phtml'],
            'pht' => ['shell.pht'],
            'phar' => ['shell.phar'],
            'phps' => ['shell.phps'],
            'cgi' => ['shell.cgi'],
            'perl script' => ['shell.pl'],
            'python script' => ['shell.py'],
            'shell script' => ['shell.sh'],
            'jsp' => ['shell.jsp'],
            'asp' => ['shell.asp'],
            'apache config' => ['x.htaccess'],
            'windows executable' => ['payload.exe'],
            'path traversal chars' => ['evil.p;hp'],
            'overly long extension' => ['file.abcdefg'],
        ];
    }

    #[DataProvider('dangerousExtensionProvider')]
    public function testParseFileExtensionBlocksDangerousExtensions(string $fileName): void
    {
        $this->assertEquals('txt', $this->fileManager->parseFileExtension($fileName));
    }

    /**
     * A PHP webshell renamed with a "safe" extension must still be caught
     * once the real file content is available for inspection -- this is
     * the core of the fix for the file upload vulnerability where a
     * disguised executable could be uploaded under an innocuous name.
     */
    public function testParseFileExtensionDetectsDisguisedPhpContentByRealMimeType(): void
    {
        $webshell = $this->createTempFile("<?php system(\$_GET['cmd']); ?>");

        $this->assertEquals(
            'txt',
            $this->fileManager->parseFileExtension('vacation-photo.jpg', $webshell)
        );
    }

    /**
     * A file with a genuinely safe extension and matching real content
     * must still be accepted once content inspection is enabled.
     */
    public function testParseFileExtensionAllowsGenuineContentMatchingExtension(): void
    {
        $pdf = $this->createTempFile("%PDF-1.4\n%real pdf-ish content\n");

        $this->assertEquals(
            'pdf',
            $this->fileManager->parseFileExtension('report.pdf', $pdf)
        );
    }

    /**
     * When no file path is supplied, parseFileExtension() must fall back to
     * the extension-only check without erroring.
     */
    public function testParseFileExtensionWithoutFilePathStillWorks(): void
    {
        $this->assertEquals('docx', $this->fileManager->parseFileExtension('manuscript.docx'));
    }

    public function testIsDangerousMimeTypeDetectsScriptAndExecutableTypes(): void
    {
        $this->assertTrue($this->fileManager->isDangerousMimeType('text/x-php'));
        $this->assertTrue($this->fileManager->isDangerousMimeType('application/x-httpd-php'));
        $this->assertTrue($this->fileManager->isDangerousMimeType('application/x-sh'));
        $this->assertTrue($this->fileManager->isDangerousMimeType('application/x-dosexec'));
    }

    public function testIsDangerousMimeTypeAllowsOrdinaryDocumentTypes(): void
    {
        $this->assertFalse($this->fileManager->isDangerousMimeType('application/pdf'));
        $this->assertFalse($this->fileManager->isDangerousMimeType('image/jpeg'));
        $this->assertFalse($this->fileManager->isDangerousMimeType('text/plain'));
        $this->assertFalse($this->fileManager->isDangerousMimeType(null));
    }
}

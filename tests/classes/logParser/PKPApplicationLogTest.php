<?php

/**
 * @file tests/classes/logParser/PKPApplicationLogTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPApplicationLogTest
 *
 * @brief Tests the application log parser against both the default Monolog line format
 *   and the JSON format produced by [logs] log_formatter = Monolog\Formatter\JsonFormatter
 */

namespace PKP\tests\classes\logParser;

use Illuminate\Mail\Transport\LogTransport;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Opcodes\LogViewer\LogFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\logParser\PKPApplicationLog;
use PKP\tests\PKPTestCase;
use Symfony\Component\Mime\Email;

class PKPApplicationLogTest extends PKPTestCase
{
    private const LINE_ENTRY = '[2026-09-02 05:27:55] production.ERROR: Something broke {"exception":"[object] (TypeError(code: 0))"}';
    private const JSON_ENTRY = '{"message":"User logged in successfully","context":{"userId":1},"level":200,"level_name":"INFO","channel":"production","datetime":"2026-08-30T08:35:23.346361+00:00","extra":{"pid":42}}';

    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            @unlink($path);
            @rmdir(dirname($path));
        }

        parent::tearDown();
    }

    public function testMatchesTheDefaultLineFormat(): void
    {
        $timestamp = $level = null;

        self::assertTrue(PKPApplicationLog::matches(self::LINE_ENTRY, $timestamp, $level));
        self::assertSame('ERROR', strtoupper((string) $level));
        self::assertNotNull($timestamp);
    }

    public function testMatchesTheJsonFormat(): void
    {
        $timestamp = $level = null;

        self::assertTrue(PKPApplicationLog::matches(self::JSON_ENTRY, $timestamp, $level));
        self::assertSame('INFO', $level);
        self::assertSame(strtotime('2026-08-30T08:35:23+00:00'), $timestamp);
    }

    public function testDoesNotMatchForeignJsonOrGarbage(): void
    {
        // Usage-stats records are also JSON per line, but key on "time" rather than "datetime".
        self::assertFalse(PKPApplicationLog::matches('{"time":"2026-03-31 05:47:46","canonicalUrl":"http://x"}'));
        self::assertFalse(PKPApplicationLog::matches('#0 /some/stack/trace/frame.php(21): foo()'));
        self::assertFalse(PKPApplicationLog::matches('not json at all'));
    }

    public function testParsesAJsonEntry(): void
    {
        $log = new PKPApplicationLog(self::JSON_ENTRY);

        self::assertSame('INFO', $log->level);
        self::assertSame('User logged in successfully', $log->message);
        self::assertSame('2026-08-30 08:35:23', $log->datetime?->toDateTimeString());
        self::assertSame(1, $log->context['userId'] ?? null);
        self::assertSame(42, $log->context['extra']['pid'] ?? null);
        self::assertSame('production', $log->extra['environment'] ?? null);
    }

    public function testParsesALineEntryExactlyAsBefore(): void
    {
        $log = new PKPApplicationLog(self::LINE_ENTRY);

        self::assertSame('ERROR', $log->level);
        self::assertSame('2026-09-02 05:27:55', $log->datetime?->toDateTimeString());
        self::assertStringStartsWith('Something broke', $log->message);
        self::assertSame('production', $log->extra['environment'] ?? null);
    }

    /**
     * The regression this parser exists for: before it, a file holding both shapes yielded a
     * single entry, because every JSON line failed to match and was either swallowed into the
     * preceding entry or dropped outright.
     */
    public function testReadsAFileThatMixesBothFormats(): void
    {
        $path = $this->writeTempLog([
            self::JSON_ENTRY,
            self::LINE_ENTRY,
            '#0 /a/stack/frame.php(21): foo()',   // continuation of the line entry
            '#1 {main}',
            self::JSON_ENTRY,
        ]);

        $logs = (new LogFile($path))->logs()->scan()->get();

        self::assertCount(3, $logs, 'Expected both JSON entries plus the line entry');

        // Entry order is the reader's concern (log_sorting_order), so compare the tally.
        $levels = array_map(fn ($log) => $log->level, iterator_to_array($logs));
        sort($levels);
        self::assertSame(['ERROR', 'INFO', 'INFO'], $levels);
    }

    /**
     * With mail sent to the log, the viewer renders each email's HTML part in an iframe that runs
     * in the site's origin, so markup that can execute must be gone before it reaches the browser.
     */
    #[DataProvider('mailLogFileNames')]
    public function testStripsUnsafeHtmlFromLoggedMailPreviews(string $fileName): void
    {
        $dir = sys_get_temp_dir() . '/pkp-log-' . uniqid();
        mkdir($dir);
        $path = $this->tempPaths[] = $dir . '/' . $fileName;

        $logger = new Logger('production', [
            (new StreamHandler($path))->setFormatter(new LineFormatter(null, 'Y-m-d H:i:s', true, true, true)),
        ]);
        $email = (new Email())
            ->from('journal@example.org')
            ->to('editor@example.org')
            ->subject('New discussion')
            ->text('Hello')
            ->html('<p>Hello <strong>editor</strong></p><img src="x" onerror="alert(1)"><script>alert(2)</script>');
        (new LogTransport($logger))->send($email);

        $logs = array_values(iterator_to_array((new LogFile($path))->logs()->scan()->get()));

        self::assertCount(1, $logs);
        $html = $logs[0]->extra['mail_preview']['html'] ?? null;
        self::assertIsString($html, 'The email preview should still be offered');
        self::assertStringContainsString('<strong>editor</strong>', $html);
        self::assertStringNotContainsStringIgnoringCase('<script', $html);
        self::assertStringNotContainsStringIgnoringCase('onerror', $html);
    }

    public static function mailLogFileNames(): array
    {
        return [
            'application log' => ['app.log'],
            'laravel log' => ['laravel.log'],
        ];
    }

    private function writeTempLog(array $lines): string
    {
        $dir = sys_get_temp_dir() . '/pkp-log-' . uniqid();
        mkdir($dir);
        $path = $dir . '/app.log';
        file_put_contents($path, implode("\n", $lines) . "\n");

        return $this->tempPaths[] = $path;
    }
}

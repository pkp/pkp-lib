<?php

/**
 * @file tests/classes/core/PKPLoggingTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLoggingTest
 *
 * @brief Behavioural tests for the Laravel logging integration
 */

namespace PKP\tests\classes\core;

use Exception;
use FilesystemIterator;
use Illuminate\Contracts\Log\ContextLogProcessor as ContextLogProcessorContract;
use Illuminate\Log\Context\ContextLogProcessor;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\config\Config;
use PKP\core\PKPExceptionHandler;
use PKP\tests\PKPTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;

class PKPLoggingTest extends PKPTestCase
{
    protected string $tmpDir;
    protected $tmpErrorLog;
    protected string $errorLogPath;
    protected string $originalErrorLog;
    protected ?string $originalSinglePath;
    protected $originalLog;
    protected array $originalLogsConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/pkp-logging-test-' . uniqid();
        $this->originalSinglePath = config('logging.channels.single.path');
        $this->originalLog = Log::getFacadeRoot();

        // Snapshot the [logs] config section so tests can toggle keys (e.g.
        // log_exception) and have them restored verbatim in tearDown.
        $this->originalLogsConfig = Config::getData()['logs'] ?? [];

        // Redirect PHP's error_log to a per-test temp file  so the errorlog channel
        // and the exception-handler fallback write somewhere inspectable.
        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();
        $this->errorLogPath = stream_get_meta_data($this->tmpErrorLog)['uri'];
        ini_set('error_log', $this->errorLogPath);
    }

    protected function tearDown(): void
    {
        // Restore PHP's error_log destination + any redirected channel config
        ini_set('error_log', $this->originalErrorLog);
        if (is_resource($this->tmpErrorLog)) {
            fclose($this->tmpErrorLog);
        }

        // Restore the Log facade FIRST (a report() test may have swapped in a
        // mock) so the channel/config cleanup below runs on the real LogManager.
        Log::swap($this->originalLog);
        config(['logging.channels.single.path' => $this->originalSinglePath]);
        Log::forgetChannel('single');
        Log::forgetChannel('errorlog');
        Log::forgetChannel('stack');

        // Restore the [logs] config section verbatim.
        $data = & Config::getData();
        $data['logs'] = $this->originalLogsConfig;

        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
    }

    /**
     * Set a key in the in-memory [logs] config section (restored in tearDown).
     */
    private function setLogsVar(string $key, mixed $value): void
    {
        $data = & Config::getData();
        $data['logs'][$key] = $value;
    }

    /**
     * Build an on-demand logger from one of our configured channels, redirecting
     * the file path (and optionally other keys) to a temp location so the test
     * never touches the real {files_dir}/logs.
     */
    private function buildChannel(string $channel, array $overrides = []): Logger
    {
        return Log::build(array_merge(config("logging.channels.{$channel}"), $overrides));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /**
     * The log file (and its parent directory) is created on first write.
     */
    public function testLogFileIsAutoCreatedOnWrite(): void
    {
        $path = $this->tmpDir . '/nested/app.log';
        self::assertDirectoryDoesNotExist(dirname($path));

        $this->buildChannel('single', ['path' => $path])->error('auto-create-check');

        self::assertFileExists($path);
    }

    /**
     * The message and its level are written to the log file.
     */
    public function testLogWritesMessageAndLevelToFile(): void
    {
        $path = $this->tmpDir . '/app.log';

        $this->buildChannel('single', ['path' => $path])->error('hello-from-test');

        $contents = file_get_contents($path);
        self::assertStringContainsString('hello-from-test', $contents);
        self::assertStringContainsString('ERROR', $contents);
    }

    /**
     * The daily channel writes a date-stamped file and configures retention from
     * the [logs] log_daily_days setting.
     */
    public function testDailyChannelWritesDatedFileAndConfiguresRetention(): void
    {
        $path = $this->tmpDir . '/app.log';
        $logger = $this->buildChannel('daily', ['path' => $path]);

        $logger->error('daily-channel-check');

        $datedFile = $this->tmpDir . '/app-' . date('Y-m-d') . '.log';
        self::assertFileExists($datedFile);

        // Retention: the rotating handler's maxFiles must match our configured days.
        /** @disregard P1013 PHP Intelephense error suppression */
        $handler = current($logger->getLogger()->getHandlers());

        self::assertInstanceOf(RotatingFileHandler::class, $handler);
        $maxFiles = (new ReflectionProperty(RotatingFileHandler::class, 'maxFiles'))->getValue($handler);
        self::assertSame((int) config('logging.channels.daily.days'), $maxFiles);
    }

    /**
     * The stack channel fans a single record out to both the single file channel
     * and the errorlog channel (which writes through PHP's error_log()).
     */
    public function testStackChannelFansOutToSingleAndErrorlog(): void
    {
        $singlePath = $this->tmpDir . '/stack-single.log';

        // Redirect the 'single' channel's file and rebuild it (error_log is redirected in setUp).
        config(['logging.channels.single.path' => $singlePath]);
        Log::forgetChannel('single');

        Log::stack(['single', 'errorlog'])->error('stack-fan-out-check');

        self::assertStringContainsString('stack-fan-out-check', file_get_contents($singlePath));
        self::assertStringContainsString('stack-fan-out-check', file_get_contents($this->errorLogPath));
    }

    /**
     * A channel only records entries at or above its configured level.
     */
    public function testChannelRespectsConfiguredLevel(): void
    {
        $path = $this->tmpDir . '/level.log';
        $logger = $this->buildChannel('single', ['path' => $path, 'level' => 'warning']);

        $logger->debug('debug-line');
        $logger->info('info-line');
        $logger->warning('warning-line');
        $logger->error('error-line');

        $contents = file_get_contents($path);
        self::assertStringNotContainsString('debug-line', $contents);
        self::assertStringNotContainsString('info-line', $contents);
        self::assertStringContainsString('warning-line', $contents);
        self::assertStringContainsString('error-line', $contents);
    }

    /**
     * Regression guard for the Laravel 12 fix: the ContextLogProcessor contract
     * must be bound (via ContextServiceProvider) so Monolog channels resolve
     * normally instead of throwing and falling back to the emergency logger.
     */
    public function testContextLogProcessorContractIsBound(): void
    {
        self::assertTrue(app()->bound(ContextLogProcessorContract::class));
        self::assertInstanceOf(ContextLogProcessor::class, app()->make(ContextLogProcessorContract::class));
    }

    public function testMonologChannelResolvesWithoutEmergencyFallback(): void
    {
        $logger = app('log')->channel('single');

        self::assertInstanceOf(Logger::class, $logger);

        // The emergency fallback logger is named 'laravel'; a normally-resolved channel is not.
        /** @disregard P1013 PHP Intelephense error suppression */
        self::assertNotSame('laravel', $logger->getLogger()->getName());
    }

    /**
     * PKPExceptionHandler::report() logs the exception to the configured channel,
     * passing the throwable under the PSR-3 'exception' context key.
     *
     * Forces the log_exception=On branch so the test is independent of whatever
     * [logs] log_exception the operator has in config.inc.php.
     */
    public function testExceptionHandlerReportLogsToLogChannel(): void
    {
        $this->setLogsVar('log_exception', true);

        $exception = new Exception('boom');

        $captured = [];
        Log::shouldReceive('error')
            ->once()
            ->andReturnUsing(function ($message, $context = []) use (&$captured) {
                $captured = ['message' => $message, 'context' => $context];
            });

        (new PKPExceptionHandler())->report($exception);

        self::assertSame('boom', $captured['message']);
        self::assertSame($exception, $captured['context']['exception'] ?? null);
    }

    /**
     * If the logger itself throws, report() must not propagate the exception; it
     * falls back to PHP's error_log().
     *
     * Forces the log_exception=On branch so the test is independent of whatever
     * [logs] log_exception the operator has in config.inc.php.
     */
    public function testExceptionHandlerReportSwallowsLoggingFailure(): void
    {
        $this->setLogsVar('log_exception', true);

        $exception = new Exception('boom');

        // The error_log() fallback writes to the temp file redirected in setUp().
        Log::shouldReceive('error')
            ->once()
            ->andThrow(new Exception('logger unavailable'));

        // Must not throw.
        (new PKPExceptionHandler())->report($exception);

        $fallback = file_get_contents($this->errorLogPath);
        self::assertStringContainsString('boom', $fallback);
        self::assertStringContainsString('Logging failed', $fallback);
    }

    /**
     * With [logs] log_exception = Off, report() does not write to the Laravel log
     * channel. On CLI (PHPUnit runs in console) it falls back to PHP's error_log so
     * the exception is still recorded; on the web path that fallback is skipped because
     * PHP's native fatal handler already records the re-thrown exception.
     */
    public function testReportSkipsChannelAndFallsBackToErrorLogWhenDisabledOnCli(): void
    {
        $this->setLogsVar('log_exception', false);

        // The channel logger must not be touched when exception logging is disabled.
        Log::shouldReceive('error')->never();

        (new PKPExceptionHandler())->report(new Exception('disabled-cli-boom'));

        // runningInConsole() is true under PHPUnit, so the error_log fallback fires.
        self::assertStringContainsString('disabled-cli-boom', file_get_contents($this->errorLogPath));
    }

    /**
     * usesErrorLogChannel() reports whether PHP's error_log is an active log destination
     * (directly as the channel, or via the stack). PKPApplication::execute() relies on it
     * to skip the explicit report() when errorlog is active — otherwise the re-thrown
     * exception (which PHP's native fatal handler records in error_log) and report()'s
     * Log::error would BOTH land in PHP's error_log. This locks that decision down so an
     * unintentional change to the condition is caught by the suite.
     */
    #[DataProvider('usesErrorLogChannelProvider')]
    public function testUsesErrorLogChannelReflectsActiveErrorlogDestination(
        string $logChannel,
        string $logStacks,
        bool $expected
    ): void {
        $this->setLogsVar('log_channel', $logChannel);
        $this->setLogsVar('log_stacks', $logStacks);

        self::assertSame($expected, app()->usesErrorLogChannel());
    }

    public static function usesErrorLogChannelProvider(): array
    {
        // [log_channel, log_stacks, expected]
        return [
            'errorlog as the single channel' => ['errorlog', 'single', true],
            'errorlog listed in the stack' => ['stack', 'single,errorlog', true],
            'stack without errorlog' => ['stack', 'single,daily', false],
            'non-stack, non-errorlog channel' => ['single', 'single', false],
        ];
    }

    /**
     * Laravel's default LineFormatter (used when a channel carries no 'formatter')
     * produces human-readable output that includes the exception stack trace and
     * suppresses the empty 'extra' array (no trailing ' []').
     *
     * The channel is built with an explicit formatter => null override so the test
     * exercises the default formatter regardless of the app-level [logs] log_formatter:
     * LogManager::prepareHandler() applies the default formatter when the 'formatter'
     * key is not set, and isset(null) === false, so null forces that default path.
     */
    public function testDefaultFormatterKeepsStackTraceWithoutEmptyExtra(): void
    {
        $path = $this->tmpDir . '/default-formatter.log';
        $this->buildChannel('single', ['path' => $path, 'formatter' => null])
            ->error('default-formatter-check', ['exception' => new Exception('boom')]);

        $line = rtrim(file_get_contents($path));
        self::assertStringContainsString('default-formatter-check', $line);
        self::assertStringContainsString('[stacktrace]', $line);
        self::assertStringEndsNotWith('[]', $line);
    }

    /**
     * Regression guard for the JSON-drops-trace finding: with a JsonFormatter and the
     * shared formatter_with => includeStacktraces, the record is emitted as one JSON
     * line whose exception payload still carries a populated trace.
     */
    public function testJsonFormatterEmitsJsonWithStackTrace(): void
    {
        $path = $this->tmpDir . '/json-formatter.log';
        $this->buildChannel('single', [
            'path' => $path,
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
            'formatter_with' => ['includeStacktraces' => true],
        ])->error('json-formatter-check', ['exception' => new Exception('boom')]);

        $line = trim(file_get_contents($path));
        $decoded = json_decode($line, true);
        self::assertSame(JSON_ERROR_NONE, json_last_error(), 'log line should be valid JSON');
        self::assertIsArray($decoded);
        self::assertSame('json-formatter-check', $decoded['message']);
        self::assertSame('ERROR', $decoded['level_name']);
        self::assertArrayHasKey('exception', $decoded['context']);
        self::assertNotEmpty($decoded['context']['exception']['trace'] ?? null);
    }
}

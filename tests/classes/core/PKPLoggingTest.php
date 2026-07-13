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
use Illuminate\Contracts\Log\ContextLogProcessor as ContextLogProcessorContract;
use Illuminate\Log\Context\ContextLogProcessor;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use PKP\config\Config;
use PKP\core\PKPExceptionHandler;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\tests\PKPTestCase;
use ReflectionProperty;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class PKPLoggingTest extends PKPTestCase
{
    protected string $tmpDir;
    protected $tmpErrorLog;
    protected string $errorLogPath;
    protected string $originalErrorLog;
    protected ?string $originalSinglePath;
    protected $originalLog;
    protected $originalFilesDir;
    protected $originalLogChannel;
    protected $originalLogStacks;
    protected $originalLoggingDefault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/pkp-logging-test-' . uniqid();
        $this->originalSinglePath = config('logging.channels.single.path');
        $this->originalLog = Log::getFacadeRoot();

        // Redirect PHP's error_log to a per-test temp file
        $this->originalErrorLog = ini_get('error_log');
        $this->tmpErrorLog = tmpfile();
        $this->errorLogPath = stream_get_meta_data($this->tmpErrorLog)['uri'];
        ini_set('error_log', $this->errorLogPath);

        // Snapshot config keys the new tests mutate; restored in tearDown().
        $this->originalFilesDir = Config::getVar('files', 'files_dir');
        $this->originalLogChannel = Config::getVar('logs', 'log_channel');
        $this->originalLogStacks = Config::getVar('logs', 'log_stacks');
        $this->originalLoggingDefault = config('logging.default');
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
        config(['logging.default' => $this->originalLoggingDefault]);
        Log::forgetChannel('single');
        Log::forgetChannel('errorlog');
        Log::forgetChannel('stack');

        // Restore [logs]/files_dir config mutated by the scheduled-task + errorlog tests.
        $this->setConfigVar('files', 'files_dir', $this->originalFilesDir);
        $this->setConfigVar('logs', 'log_channel', $this->originalLogChannel);
        $this->setConfigVar('logs', 'log_stacks', $this->originalLogStacks);

        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
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

    /**
     * Override a [config] var for the duration of a test (restored in tearDown from the
     * setUp snapshots).
     */
    private function setConfigVar(string $section, string $key, mixed $value): void
    {
        $data = & Config::getData();
        $data[$section][$key] = $value;
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
     * Regression guard : the ContextLogProcessor contract must be bound
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
     */
    public function testExceptionHandlerReportLogsToLogChannel(): void
    {
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
     */
    public function testExceptionHandlerReportSwallowsLoggingFailure(): void
    {
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
        self::assertIsArray($decoded, 'log line should be valid JSON');
        self::assertSame('json-formatter-check', $decoded['message']);
        self::assertArrayHasKey('exception', $decoded['context']);
        self::assertNotEmpty($decoded['context']['exception']['trace'] ?? null);
    }

    /**
     * Running a scheduled task (execute()) writes its execution log to the per-task file under
     * {files_dir}/scheduledTaskLogs/ (on-demand single channel).
     */
    public function testScheduledTaskWritesExecutionLogToFile(): void
    {
        // The execution-log path is derived from Config('files','files_dir') via
        // PKPContainer::logFilePath(); point it at the temp dir.
        $this->setConfigVar('files', 'files_dir', $this->tmpDir);

        $task = new LoggingTestScheduledTask();
        self::assertTrue($task->execute());

        $files = glob($this->tmpDir . '/scheduledTaskLogs/*.log');
        self::assertCount(1, $files);

        $contents = file_get_contents($files[0]);
        self::assertStringContainsString('scheduled-task-log-line', $contents);
        self::assertStringContainsString('NOTICE', $contents);
    }

    /**
     * PKPExceptionHandler::usesErrorLogChannel() reports whether PHP's error_log is an
     * active destination — directly (log_channel = errorlog) or via the stack.
     */
    public function testUsesErrorLogChannelDetectsActiveErrorlog(): void
    {
        $this->setConfigVar('logs', 'log_channel', 'errorlog');
        self::assertTrue(PKPExceptionHandler::usesErrorLogChannel());

        $this->setConfigVar('logs', 'log_channel', 'daily');
        self::assertFalse(PKPExceptionHandler::usesErrorLogChannel());

        $this->setConfigVar('logs', 'log_channel', 'stack');
        $this->setConfigVar('logs', 'log_stacks', 'single,errorlog');
        self::assertTrue(PKPExceptionHandler::usesErrorLogChannel());

        $this->setConfigVar('logs', 'log_stacks', 'single,daily');
        self::assertFalse(PKPExceptionHandler::usesErrorLogChannel());
    }

    /**
     * Test when errorlog is the active channel, a reported exception is written to PHP's
     * error_log and NOT to the file (single) channel.
     */
    public function testExceptionReportGoesToErrorLogNotLogFileWhenErrorlogChannel(): void
    {
        // report() routes Log::error() through the default channel; make it errorlog.
        config(['logging.default' => 'errorlog']);
        Log::forgetChannel('errorlog');

        // Point the file channel at a temp path so we can assert it stays unwritten.
        $singlePath = $this->tmpDir . '/app.log';
        config(['logging.channels.single.path' => $singlePath]);
        Log::forgetChannel('single');

        (new PKPExceptionHandler())->report(new Exception('exception-goes-to-errorlog'));

        // The errorlog channel writes via PHP error_log(), redirected to a temp file in setUp().
        self::assertStringContainsString('exception-goes-to-errorlog', file_get_contents($this->errorLogPath));
        self::assertFileDoesNotExist($singlePath);
    }
}

/**
 * Minimal concrete ScheduledTask used to exercise execution-log file writing. getHelper() returns a
 * stub whose non-empty contacts skip the Site-context lookup in ScheduledTaskHelper's constructor
 * (unavailable in unit tests) and whose no-op notifyExecutionResult() keeps the result email from firing.
 */
class LoggingTestScheduledTask extends ScheduledTask
{
    protected function executeActions(): bool
    {
        $this->addExecutionLogEntry('scheduled-task-log-line', ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        return true;
    }

    public function getHelper(): ScheduledTaskHelper
    {
        return new class('noreply@example.com', 'Scheduled Task Test') extends ScheduledTaskHelper {
            public function notifyExecutionResult(string $id, string $name, bool $result, string $executionLogFile = ''): bool
            {
                return false;
            }
        };
    }
}

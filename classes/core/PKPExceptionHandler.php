<?php

/**
 * @file classes/core/PKPExceptionHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPExceptionHandler
 *
 * @brief The application-wide exception handler bound to the
 *   \Illuminate\Contracts\Debug\ExceptionHandler contract. Reports exceptions
 *   to the configured log channel and renders API error responses.
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use PKP\config\Config;
use Throwable;

class PKPExceptionHandler implements ExceptionHandler
{
    /**
     * @copydoc \Illuminate\Contracts\Debug\ExceptionHandler::shouldReport()
     */
    public function shouldReport(Throwable $exception)
    {
        return true;
    }

    /**
     * @copydoc \Illuminate\Contracts\Debug\ExceptionHandler::report()
     */
    public function report(Throwable $exception)
    {
        if (!$this->shouldReport($exception)) {
            return;
        }

        if (Config::getVar('logs', 'log_exception', true)) {
            try {
                Log::error($exception->getMessage(), ['exception' => $exception]);
            } catch (Throwable $loggingException) {
                // Fall back to PHP's error log if Laravel logging itself fails
                // (e.g. log file not writable, channel misconfigured, or invoked
                // before the logging service provider is registered).
                error_log($exception->__toString());
                error_log('Logging failed: ' . $loggingException->__toString());
            }
            return;
        }

        // log_exception is disabled: keep the exception out of the configured log
        // channel. On CLI (queue/scheduler) there is no PHP native fatal handler to
        // record the throwable, so write it to PHP's error_log; on the web path the
        // re-thrown exception is already recorded there by the native handler, so
        // writing here too would duplicate the entry.
        if (app()->runningInConsole()) {
            error_log($exception->__toString());
        }
    }

    /**
     * @copydoc \Illuminate\Contracts\Debug\ExceptionHandler::render()
     */
    public function render($request, Throwable $exception)
    {
        $pkpRouter = Application::get()->getRequest()->getRouter();

        if ($pkpRouter instanceof APIRouter && app('router')->getRoutes()->count()) {
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                return response()
                    ->json($exception->errors(), $exception->status);
            }

            return response()->json(
                [
                    'error' => $exception->getMessage()
                ],
                in_array($exception->getCode(), array_keys(Response::$statusTexts))
                    ? $exception->getCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return null;
    }

    /**
     * @copydoc \Illuminate\Contracts\Debug\ExceptionHandler::renderForConsole()
     */
    public function renderForConsole($output, Throwable $exception)
    {
        echo (string) $exception;
    }
}

<?php

/**
 * @file api/v1/jobs/PKPJobController.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPJobController
 *
 * @ingroup api_v1_jobs
 *
 * @brief Handle API requests for jobs
 *
 */

namespace PKP\API\v1\jobs;

use APP\facades\Repo;
use PKP\security\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class PKPJobController extends PKPBaseController
{
    public function isSiteWide(): bool
    {
        return true;
    }

    public function getHandlerPath(): string
    {
        return 'jobs';
    }

    public function getRouteGroupMiddleware(): array
    {
        $roles = implode('|', [Role::ROLE_ID_SITE_ADMIN]);

        return [
            "has.user",
            "has.roles:{$roles}",
        ];
    }

    public function getGroupRoutes(): Void
    {       
        Route::get('all', $this->getJobs(...))
            ->name('job.getJobs');
        
        Route::get('failed/all', $this->getFailedJobs(...))
            ->name('job.getFailedJobs');
        
        Route::post('redispatch/all', $this->redispatchAllFailedJob(...))
            ->name('job.redispatchAllFailedJob');

        Route::post('redispatch/{jobId}', $this->redispatchFailedJob(...))
            ->name('job.redispatchFailedJob');
        
        Route::delete('failed/delete/{jobId}', $this->deleteFailedJob(...))
            ->name('job.deleteFailedJob');
    }

    /**
     * Get all pending jobs in the queue waiting to get executed
     */
    public function getJobs(Request $request): JsonResponse
    {
        $params = $request->query();

        $jobs = Repo::job()
            ->setOutputFormat(Repo::failedJob()::OUTPUT_HTTP)
            ->setPage($params['page'] ?? 1)
            ->showJobs();

        return response()->json([
            'data' => $jobs->all(),
            'total' => Repo::job()->total(),
            'pagination' => [
                'lastPage' => $jobs->lastPage(),
                'currentPage' => $jobs->currentPage(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Get all failed jobs in the failed list
     */
    public function getFailedJobs(Request $request): JsonResponse
    {
        $params = $request->query();

        $failedJobs = Repo::failedJob()
            ->setOutputFormat(Repo::failedJob()::OUTPUT_HTTP)
            ->setPage($params['page'] ?? 1)
            ->showJobs();

        return response()->json([
            'data' => $failedJobs->all(),
            'total' => Repo::failedJob()->total(),
            'pagination' => [
                'lastPage' => $failedJobs->lastPage(),
                'currentPage' => $failedJobs->currentPage(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Redispatch all failed jobs back to queue
     * It will only redispatch failed jobs that has valid payload attribute
     */
    public function redispatchAllFailedJob(Request $request): JsonResponse
    {
        if (Repo::failedJob()->total() <= 0) {
            return response()->json([
                'errorMessage' => __('api.jobs.406.failedJobEmpty')
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $redispatchableFailedJobs = Repo::failedJob()->getRedispatchableJobsInQueue(null, ['id']);

        return Repo::failedJob()->redispatchToQueue(null, $redispatchableFailedJobs->pluck('id')->toArray())
            ? response()->json(['message' => __('api.jobs.200.allFailedJobRedispatchedSucceed')], Response::HTTP_OK)
            : response()->json(['errorMessage' => __('api.jobs.400.failedJobRedispatchedFailed')], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Redispatch a failed job back to queue
     */
    public function redispatchFailedJob(Request $request): JsonResponse
    {
        $failedJob = Repo::failedJob()->get($request->route('jobId'));

        if (!$failedJob) {
            return response()->json([
                'errorMessage' => __('api.jobs.404.failedJobNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$failedJob->payload) {
            return response()->json([
                'errorMessage' => __('api.jobs.406.failedJobPayloadMissing')
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        return Repo::failedJob()->redispatchToQueue(null, [$failedJob->id])
            ? response()->json(['message' => __('api.jobs.200.failedJobRedispatchedSucceed')], Response::HTTP_OK)
            : response()->json(['errorMessage' => __('api.jobs.400.failedJobRedispatchedFailed')], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Delete a failed job from failed list
     */
    public function deleteFailedJob(Request $request): JsonResponse
    {
        $failedJob = Repo::failedJob()->get($request->route('jobId'));

        if (!$failedJob) {
            return response()->json([
                'errorMessage' => __('api.jobs.404.failedJobNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        return $failedJob->delete()
            ? response()->json(['message' => __('api.jobs.200.failedJobDeleteSucceed')], Response::HTTP_OK)
            : response()->json(['errorMessage' => __('api.jobs.400.failedJobDeleteFailed')], Response::HTTP_BAD_REQUEST);
    }
}
<?php

/**
 * @file classes/task/UpdateIPGeoDB.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateIPGeoDB
 *
 * @ingroup tasks
 *
 * @brief Class responsible to monthly update of the DB-IP city lite database used for Geo statistics.
 */

namespace PKP\task;

use APP\core\Application;
use Exception;
use PKP\file\FileManager;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\statistics\PKPStatisticsHelper;

class UpdateIPGeoDB extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName(): string
    {
        return __('admin.scheduledTask.updateGeoDB');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions(): bool
    {
        $dbipCityLiteFileName = 'dbip-city-lite-' . date('Y') . '-' . date('m') . '.mmdb.gz';
        $dbipCityLiteFile = 'https://download.db-ip.com/free/' . $dbipCityLiteFileName;

        $fileMgr = new PrivateFileManager();
        $downloadedFile = PKPStatisticsHelper::getUsageStatsDirPath() . '/' . $dbipCityLiteFileName;
        $finalFileName = PKPStatisticsHelper::getGeoDBPath();

        try {
            $client = Application::get()->getHttpClient();
            $client->request('GET', $dbipCityLiteFile, ['sink' => $downloadedFile]);
        } catch (Exception $e) {
            $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        try {
            $decompressedFile = $fileMgr->gzDecompressFile($downloadedFile);
        } catch (Exception $e) {
            $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        if (!rename($decompressedFile, $finalFileName)) {
            $this->addExecutionLogEntry(__('admin.scheduledTask.updateGeoDB.fileRename.error', ['sourceFilename' => $decompressedFile,
                'targetFilename' => $finalFileName]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        return $fileMgr->setMode($finalFileName, FileManager::FILE_MODE_MASK);
    }
}

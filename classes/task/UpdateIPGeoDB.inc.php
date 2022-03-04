<?php

/**
 * @file classes/task/UpdateIPGeoDB.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateIPGeoDB
 * @ingroup tasks
 *
 * @brief Class responsible to monthly update of the DB-IP city lite database used for Geo statistics.
 */

namespace PKP\task;

use APP\core\Application;
use PKP\file\FileManager;
use PKP\file\PrivateFileManager;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;

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
        $dbipCityLiteFileName = 'https://download.db-ip.com/free/dbip-city-lite-' . date('Y') . '-' . date('m') . '.mmdb.gz';

        $fileMgr = new PrivateFileManager();
        $usageStatsDirPath = realpath($fileMgr->getBasePath()) . '/usageStats';
        $downloadedFile = $usageStatsDirPath . '/' . date('Y') . '-' . date('m') . '.mmdb.gz';
        $finalFileName = $usageStatsDirPath . '/IPGeoDB.mmdb';

        try {
            $client = Application::get()->getHttpClient();
            $client->request('GET', $dbipCityLiteFileName, ['sink' => $downloadedFile]);
        } catch (\Exception $e) {
            $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        try {
            $decompressedFile = $fileMgr->gzDecompressFile($downloadedFile);
        } catch (\Exception $e) {
            $this->addExecutionLogEntry($e->getMessage(), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        if (rename($decompressedFile, $finalFileName)) {
            return $fileMgr->setMode($finalFileName, FileManager::FILE_MODE_MASK);
        } else {
            $this->addExecutionLogEntry(__('admin.scheduledTask.updateGeoDB.fileRename.error', ['sourceFilename' => $decompressedFile,
                'targetFilename' => $finalFileName]), ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return false;
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\UpdateIPGeoDB', '\UpdateIPGeoDB');
}

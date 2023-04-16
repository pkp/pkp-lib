<?php

/**
 * @file tools/setVersionTool.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SetVersionTool
 *
 * @ingroup tools
 *
 * @brief CLI tool to set a version number for each publication.
 */

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class SetVersionTool extends \PKP\cliTool\CommandLineTool
{
    /**
     * Set the version numbers
     */
    public function execute()
    {
        $request = Application::get()->getRequest();
        $contextIds = Services::get('context')->getIds();
        foreach ($contextIds as $contextId) {
            $submissions = Repo::submission()
                ->getCollector()
                ->filterByContextIds([$contextId])
                ->getIds();

            foreach ($submissions as $submission) {
                $version = 1;
                foreach ($submission->getData('publications') as $publication) {
                    Repo::publication()->edit($publication, ['version' => $version]);
                    $version++;
                }
            }
        }
    }
}

$tool = new SetVersionTool($argv ?? []);
$tool->execute();

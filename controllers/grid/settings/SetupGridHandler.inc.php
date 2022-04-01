<?php

/**
 * @file controllers/grid/settings/SetupGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SetupGridHandler
 * @ingroup controllers_grid_settings
 *
 * @brief Base class for setup grid handlers
 */

use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class SetupGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER],
            ['uploadImage']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     *
     * @param bool $contextRequired
     */
    public function authorize($request, &$args, $roleAssignments, $contextRequired = true)
    {
        if ($contextRequired) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Handle file uploads for cover/image art for things like Series and Categories.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function uploadImage($args, $request)
    {
        $router = $request->getRouter();
        $context = $request->getContext();
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
        if ($temporaryFile) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes([
                'temporaryFileId' => $temporaryFile->getId()
            ]);
            return $json;
        } else {
            return new JSONMessage(false, __('common.uploadFailed'));
        }
    }
}

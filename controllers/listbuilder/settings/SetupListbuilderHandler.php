<?php

/**
 * @file controllers/listbuilder/settings/SetupListbuilderHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SetupListbuilderHandler
 *
 * @ingroup listbuilder
 *
 * @brief Base class for setup listbuilders
 */

namespace PKP\controllers\listbuilder\settings;

use PKP\controllers\listbuilder\ListbuilderHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class SetupListbuilderHandler extends ListbuilderHandler
{
    /** @var Context */
    public $_context;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetch', 'fetchRow', 'save']
        );
    }

    /**
     * Set the current context
     *
     * @param Context $context
     */
    public function setContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Get the current context
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc ListbuilderHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        $this->setContext($request->getContext());
        return parent::initialize($request, $args);
    }
}

<?php
/**
 * @file classes/security/authorization/internal/PluginLevelRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginLevelRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Class to test the plugin level.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;

class PluginLevelRequiredPolicy extends AuthorizationPolicy
{
    /** @var bool */
    public $_contextPresent;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param bool $contextPresent
     */
    public function __construct($request, $contextPresent)
    {
        parent::__construct('user.authorization.pluginLevel');
        $this->_contextPresent = $contextPresent;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the plugin.
        $plugin = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PLUGIN);
        if (!$plugin instanceof \PKP\plugins\Plugin) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        if (!$this->_contextPresent) { // Site context
            return $plugin->isSitePlugin() ? AuthorizationPolicy::AUTHORIZATION_PERMIT : AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        return $plugin->isSitePlugin() ? AuthorizationPolicy::AUTHORIZATION_DENY : AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\PluginLevelRequiredPolicy', '\PluginLevelRequiredPolicy');
}

<?php
/**
 * @file classes/security/authorization/internal/PluginRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Class to make sure we have a valid plugin in request.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\core\Request;
use PKP\core\PKPRequest;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\AuthorizationPolicy;

class PluginRequiredPolicy extends AuthorizationPolicy
{
    /** @var Request */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.pluginRequired');
        $this->_request = $request;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the plugin request data.
        $category = $this->_request->getUserVar('category');
        $pluginName = $this->_request->getUserVar('plugin');

        // Load the plugin.
        $plugins = PluginRegistry::loadCategory($category);
        $foundPlugin = null;
        foreach ($plugins as $plugin) { /** @var Plugin $plugin */
            if ($plugin->getName() == $pluginName) {
                $foundPlugin = $plugin;
                break;
            }
        }
        if (!$foundPlugin instanceof Plugin) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Add the plugin to the authorized context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_PLUGIN, $foundPlugin);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\PluginRequiredPolicy', '\PluginRequiredPolicy');
}

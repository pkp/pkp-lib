<?php
/**
 * @defgroup plugins_auth_shibboleth Shibboleth Authentication Plugin
 */
 
/**
 * @file plugins/auth/shibboleth/index.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_auth_shibboleth
 * @brief Wrapper for loading the Shibboleth authentication plugin.
 *
 */

require_once('ShibbolethAuthPlugin.inc.php');

return new ShibbolethAuthPlugin();

?>

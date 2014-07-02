{**
 * controllers/grid/plugins/viewPlugin.tpl
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * View a plugin gallery plugin's details.
 *}
<h3>{translate key="common.description}</h3>
<p>{$plugin->getLocalizedDescription()|strip_unsafe_html}</p>
<p><a href="{$plugin->getHomepage()|escape}">{translate key="manager.plugins.pluginGallery.homepage"}</a></p>
<h3>{translate key="manager.plugins.pluginGallery.maintainer"}</h3>
<p id="maintainer">
	<div id="maintainerContact">
		{if $plugin->getContactEmail()}<a href="mailto:{$plugin->getContactEmail()|escape}">{/if}{$plugin->getContactName()|escape}{if $plugin->getContactEmail()}</a>{/if}<br/>
	</div>
	<div id="maintainerInstitution">{$plugin->getContactInstitutionName()|escape}</div>
</p>

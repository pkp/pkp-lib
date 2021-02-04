{**
 * templates/user/profile.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User profile tabset.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="user.profile"}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#profileTabs').pkpHandler('$.pkp.controllers.TabHandler');
		{rdelim});
	</script>
	<div id="profileTabs" class="pkp_controllers_tab">
		<ul>
			<li><a name="identity" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="identity"}">{translate key="user.profile.identity"}</a></li>
			<li><a name="contact" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="contact"}">{translate key="user.profile.contact"}</a></li>
			<li><a name="roles" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="roles"}">{translate key="user.roles"}</a></li>
			<li><a name="publicProfile" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="publicProfile"}">{translate key="user.profile.public"}</a></li>
			<li><a name="changePassword" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="changePassword"}">{translate key="user.password"}</a></li>
			<li><a name="notificationSettings" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="notificationSettings"}">{translate key="notification.notifications"}</a></li>
			<li><a name="apiSettings" href="{url router=$smarty.const.ROUTE_COMPONENT component="tab.user.ProfileTabHandler" op="apiProfile"}">{translate key="user.apiKey"}</a></li>
		</ul>
	</div>
{/block}

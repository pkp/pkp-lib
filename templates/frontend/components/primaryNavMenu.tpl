{**
 * templates/frontend/components/primaryNavMenu.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Primary navigation menu list for OPS
 *}
<ul id="navigationPrimary" class="pkp_navigation_primary pkp_nav_list">

	{if $enableAnnouncements}
		<li>
			<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="announcement"}">
				{translate key="announcement.announcements"}
			</a>
		</li>
	{/if}

	{if $currentServer}

		<li>
			<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about"}">
				{translate key="navigation.about"}
			</a>
			<ul>
				<li>
					<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about"}">
						{translate key="about.aboutContext"}
					</a>
				</li>
				<li>
					<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="editorialMasthead"}">
						{translate key="common.editorialMasthead"}
					</a>
				</li>
				<li>
					<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="submissions"}">
						{translate key="about.submissions"}
					</a>
				</li>
				{if $currentServer->getData('mailingAddress') || $currentServer->getData('contactName')}
					<li>
						<a href="{url router=PKP\core\PKPApplication::ROUTE_PAGE page="about" op="contact"}">
							{translate key="about.contact"}
						</a>
					</li>
				{/if}
			</ul>
		</li>
	{/if}
</ul>

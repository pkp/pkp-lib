{**
 * controllers/tab/settings/users.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * User management.
 *
 *}

{* Help Link *}
{help file="users-and-roles.md" section="users" class="pkp_help_tab"}

{assign var="uuid" value=""|uniqid|escape}
<div id="users-list-{$uuid}">
	<script type="text/javascript">
		pkp.registry.init('users-list-{$uuid}', 'UsersListPanel', {$usersListData});
	</script>
</div>

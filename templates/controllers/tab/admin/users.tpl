{**
 * templates/controllers/tab/admin/users.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Show control to view and manage users across all contexts
 *}
{assign var="uuid" value=""|uniqid|escape}
<div id="users-list-{$uuid}">
	<script type="text/javascript">
		pkp.registry.init('users-list-{$uuid}', 'UsersListPanel', {$usersListData});
	</script>
</div>

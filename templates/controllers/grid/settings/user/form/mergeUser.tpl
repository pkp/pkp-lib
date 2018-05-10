{**
 * templates/controllers/grid/settings/user/form/mergeUser.tpl
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Display list of users to merge into
 *}
{assign var="uuid" value=""|uniqid|escape}
<div id='merge-users-list-{$uuid}'>
	<script type="text/javascript">
		pkp.registry.init('merge-users-list-{$uuid}', 'UsersListPanel', {$mergeUsersListData});
	</script>
</div>

{**
 * templates/controllers/modals/publish/publish.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Final publishing confirmation for a publication
 *}


{assign var="uuid" value=""|uniqid|escape}
<div id="publish-{$uuid}" class="pkpWorkflow__publishModal">
  <pkp-form v-bind="components.{$smarty.const.FORM_PUBLISH}" @set="set" />
	<script type="text/javascript">
		pkp.registry.init('publish-{$uuid}', 'Container', {$publishData|json_encode});
	</script>
</div>
<template>
	<div class="pkp-vue-list-panel-count">
		{{ __('item_count', { count: this.count }) }}
	</div>
</template>

<script>
export default {
	name: 'ListPanelCount',
	props: ['count', 'i18n'],
}
</script>

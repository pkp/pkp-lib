<template>
	<div class="pkpListPanelCount">
		{{ __('itemCount', { count: this.count }) }}
	</div>
</template>

<script>
export default {
	name: 'ListPanelCount',
	props: ['count', 'i18n'],
}
</script>

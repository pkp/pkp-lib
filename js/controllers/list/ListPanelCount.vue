<template>
	<div class="pkpListPanelCount">
		{{ countString }}
	</div>
</template>

<script>
export default {
	name: 'ListPanelCount',
	props: ['count', 'total', 'i18n'],
	computed: {
		countString: function() {
			if (this.total) {
				return this.__('itemsOfTotal', { count: this.count, total: this.total });
			} else {
				return this.__('itemCount', { count: this.count });
			}
		}
	}
}
</script>

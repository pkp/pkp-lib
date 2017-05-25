<template>
	<li class="pkpListPanelItem" :class="{'--isFeatured': isFeatured}">
		<div class="pkpListPanelItem--submission__item"></div>
	</li>
</template>

<script>
export default {
	name: 'ListPanelItem',
	methods: {
		/**
		 * Pass an itemOrderUp event up to the list panel
		 *
		 * This event emerges from a ListPanelItemOrderer component.
		 */
		itemOrderUp: function() {
			this.$emit('itemOrderUp', this.submission);
		},

		/**
		 * Pass an itemOrderDown event up to the list panel
		 *
		 * This event emerges from a ListPanelItemOrderer component.
		 */
		itemOrderDown: function() {
			this.$emit('itemOrderDown', this.submission);
		},
	},
};
</script>

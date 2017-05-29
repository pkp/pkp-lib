<template>
	<li class="pkpListPanelItem" :class="{'--has-focus': isFocused}">
		<div class="pkpListPanelItem--submission__item"></div>
	</li>
</template>

<script>
export default {
	name: 'ListPanelItem',
	data: function() {
		return {
			// ListPanelItem components must have an id for ordering to work
			id: null,
			isFocused: false,
		}
	},
	methods: {
		/**
		 * Update the isFocused property
		 */
		focusItem: function() {
			this.isFocused = true;
		},

		/**
		 * Update the isFocused property
		 */
		blurItem: function() {
			this.isFocused = false;
		},

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

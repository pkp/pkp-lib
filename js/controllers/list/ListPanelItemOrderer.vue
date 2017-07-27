<template>
	<div class="pkpListPanelItem__orderer">
		<button tabindex="-1" class="pkpListPanelItem__ordererDragDrop">
			<span class="fa fa-sort"></span>
		</button>
		<button class="pkpListPanelItem__ordererUp" @click.prevent="orderUp">
			<span class="fa fa-chevron-up"></span>
			<span class="pkp_screen_reader">{{ __('itemOrdererUp', {itemTitle: itemTitle}) }}</span>
		</button>
		<button class="pkpListPanelItem__ordererDown" @click.prevent="orderDown">
			<span class="fa fa-chevron-down"></span>
			<span class="pkp_screen_reader">{{ __('itemOrdererDown', {itemTitle: itemTitle}) }}</span>
		</button>
	</div>
</template>

<script>
export default {
	name: 'ListPanelItemOrderer',
	props: ['i18n', 'itemTitle'],
	methods: {
		/**
		 * Emit an event to move this item up
		 */
		orderUp: function() {
			this.$emit('itemOrderUp');
		},

		/**
		 * Emit an event to move this item down
		 */
		orderDown: function() {
			this.$emit('itemOrderDown');
		},

		/**
		 * Set the focus on one of the up/down buttons
		 */
		setFocus: function(dir)  {
			var selector = dir === 'up' ? '.pkpListPanelItem__ordererUp' : '.pkpListPanelItem__ordererDown';
			this.$el.querySelector(selector).focus();
		}
	}
}
</script>

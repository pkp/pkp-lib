<template>
	<li class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--select" :class="{'--hasFocus': isFocused}">
		<div class="pkpListPanelItem__selectItem" @click.prevent="toggleSelection">
			<input type="checkbox" :id="inputId" :name="inputName" value="inputValue" v-model="selected" @click.stop @focus="focusItem" @blur="blurItem">
		</div>
		<label :for="inputId" class="pkpListPanelItem__item">
			<div v-if="item.author" class="pkpListPanelItem--submission__author">
				{{ item.author.authorString }}
			</div>
			<div class="pkpListPanelItem--submission__title">
				{{ item.title }}
			</div>
		</label>
		<a :href="item.urlWorkflow" class="pkpListPanelItem--submission__link" target="_blank" @focus="focusItem" @blur="blurItem">
			{{ __('viewSubmission', {title: item.title}) }}
		</a>
	</li>
</template>

<script>
import ListPanelItem from '../ListPanelItem.vue';

export default {
	extends: ListPanelItem,
	name: 'SelectSubmissionsListItem',
	props: ['item', 'i18n', 'inputName'],
	data: function() {
		return {
			selected: false,
		};
	},
	computed: {
		/**
		 * Map the submission id to the list item id
		 */
		id: function() {
			return this.item.id;
		},

		/**
		 * The input value to use for this item
		 *
		 * @return string
		 */
		inputValue: function() {
			return this.item.id;
		},

		/**
		 * The id attribute of the checkbox
		 *
		 * @return string
		 */
		inputId: function() {
			return this.inputName + this.inputValue;
		}
	},
	methods: {
		/**
		 * Toggle the checkbox when clicked
		 */
		toggleSelection: function(e) {
			this.selected = !this.selected;
		}
	},
};
</script>

<template>
	<li class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--select" :class="{'--has-focus': isFocused}">
		<div class="pkpListPanelItem__selectItem" @click.prevent="toggleSelection">
			<input type="checkbox" :id="inputId" :name="inputName" :value="inputValue" :checked="selected" @click.stop @focus="focusItem" @blur="blurItem">
		</div>
		<label :for="inputId" class="pkpListPanelItem--submission__item">
			<div class="pkpListPanelItem--submission__title">
				{{ submission.title }}
			</div>
			<div v-if="submission.author" class="pkpListPanelItem--submission__author">
				{{ submission.author.authorString }}
			</div>
		</label>
		<a :href="submission.urlWorkflow" class="pkpListPanelItem--submission__link" target="_blank" @focus="focusItem" @blur="blurItem">
			{{ __('viewSubmission', {title: submission.title}) }}
		</a>
	</li>
</template>

<script>
import ListPanelItem from '../ListPanelItem.vue';

export default _.extend({}, ListPanelItem, {
	name: 'SelectSubmissionsListItem',
	props: ['submission', 'i18n', 'inputName'],
	data: function() {
		return _.extend({}, ListPanelItem.data(), {
			selected: false,
		});
	},
	computed: {
		/**
		 * Map the submission id to the list item id
		 */
		id: function() {
			return this.submission.id;
		},

		/**
		 * The input value to use for this item
		 *
		 * @return string
		 */
		inputValue: function() {
			return this.submission.id;
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
	methods: _.extend({}, ListPanelItem.methods, {
		/**
		 * Toggle the checkbox when clicked
		 */
		toggleSelection: function(e) {
			this.selected = !this.selected;
		}
	}),
});
</script>

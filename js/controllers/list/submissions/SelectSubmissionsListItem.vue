<template>
	<li class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--select">
		<div class="pkpListPanelItem__selectItem" @click.prevent="toggleSelection">
			<input type="checkbox" :id="inputId" :name="inputName" :value="inputValue" :checked="selected" @click.stop>
		</div>
		<label :for="inputId" class="pkpListPanelItem--submission__item">
			<div class="pkpListPanelItem--submission__title">
				{{ submission.title }}
			</div>
			<div v-if="submission.author" class="pkpListPanelItem--submission__author">
				{{ submission.author.authorString }}
			</div>
		</label>
		<a :href="submission.urlWorkflow" class="pkpListPanelItem--submission__link" target="_blank">
			{{ __('viewSubmission', {title: submission.title}) }}
		</a>
	</li>
</template>

<script>
import SubmissionsListItem from './SubmissionsListItem.vue';

export default _.extend({}, SubmissionsListItem, {
	name: 'SelectSubmissionsListItem',
	props: ['submission', 'i18n', 'inputName'],
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
	methods: _.extend({}, SubmissionsListItem.methods, {
		/**
		 * Toggle the checkbox when clicked
		 */
		toggleSelection: function(e) {
			this.selected = !this.selected;
		}
	}),
});
</script>

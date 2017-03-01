<template>
	<div class="pkpListPanel pkpSubmissionsListPanel" :class="classLoading">
		<div class="pkpListPanel__header">
			<div class="pkpListPanel__title">{{ i18n.title }}</div>
			<ul class="pkpListPanel__actions">
				<li>
					<a :href="addUrl">{{ i18n.add }}</a>
				</li>
			</ul>
			<list-panel-search
				@searchPhraseChanged="set"
				:isSearching="isSearching"
				:searchPhrase="searchPhrase"
				:i18n="i18n"
			/>
		</div>
		<ul class="pkpListPanel__items">
			<submissions-list-item
				v-for="item in collection.items"
				@deleteSubmission="deleteSubmission"
				@openInfoCenter="openInfoCenter"
				:submission="item"
				:i18n="i18n"
			/>
		</ul>
		<div class="pkpListPanel__footer">
			<list-panel-load-more
				v-if="canLoadMore"
				@loadMore="loadMore"
				:isLoading="isLoading"
				:i18n="i18n"
			/>
			<list-panel-count
				:count="itemCount"
				:total="this.collection.maxItems"
				:i18n="i18n"
			/>
		</div>
	</div>
</template>

<script>
import ListPanel from './../ListPanel.vue';
import SubmissionsListItem from './SubmissionsListItem.vue';

export default _.extend({}, ListPanel, {
	name: 'SubmissionsListPanel',
	components: _.extend({}, ListPanel.components, {
		SubmissionsListItem,
	}),
	methods: _.extend({}, ListPanel.methods, {
		/**
		 * Delete a submission
		 */
		deleteSubmission: function(submissionId) {

			if (!_.has(this.config.routes, 'delete')) {
				return;
			}

			// Initialize a confirmation modal before deleting
			var opts = {
				title: this.i18n.delete,
				okButton: this.i18n.ok,
				cancelButton: this.i18n.cancel,
				dialogText: this.i18n.confirmDelete,
				remoteAction: this.config.routes.delete.url,
				csrfToken: this.csrfToken,
				postData: {
					id: submissionId,
				},
			};

			$('<div id="' + $.pkp.classes.Helper.uuid() + '" ' +
					'class="pkp_modal pkpModalWrapper" tabindex="-1"></div>')
				.pkpHandler('$.pkp.controllers.modal.RemoteActionConfirmationModalHandler', opts);
		},

		/**
		 * Load a modal displaying history and notes of a submission
		 */
		openInfoCenter: function(submissionId, submissionTitle) {

			var opts = {
				title: submissionTitle,
				url: this.infoUrl.replace('__id__', submissionId),
			};

			$('<div id="' + $.pkp.classes.Helper.uuid() + '" ' +
					'class="pkp_modal pkpModalWrapper" tabindex="-1"></div>')
				.pkpHandler('$.pkp.controllers.modal.AjaxModalHandler', opts);
		},
	}),
	mounted: function() {

		// Call the mounted function on parent component
		ListPanel.mounted.call(this);

		// Store a reference to this component for global event callbacks
		var self = this;

		// Remove a submission from the list when it is deleted
		pkp.eventBus.$on('submissionDeleted', function(data) {

			if (!_.has(data, 'id')) {
				return;
			}

			self.items = _.filter(self.items, function(item) {
				return item.id != data.id;
			});
		});
	},
});
</script>

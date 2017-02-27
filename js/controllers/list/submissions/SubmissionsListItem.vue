<template>
	<li class="pkpListPanelItem pkpSubmissionsListItem assigned">
		<a :href="submission.urlWorkflow">
			<div class="pkpSubmissionsListItem__item">
				<div class="pkpSubmissionsListItem__item__title">
					{{ submission.title }}
				</div>
				<div class="pkpSubmissionsListItem__item__author">
					{{ submission.author.authorString }}
				</div>
				<div v-if="notice" class="pkpSubmissionsListItem__item__activity">
					<span class="fa fa-exclamation-triangle"></span>
					{{ notice }}
				</div>
			</div>
		</a>
		<div class="pkpSubmissionsListItem__stage">
			<div class="pkpSubmissionsListItem__stage__row">
				<div class="pkpSubmissionsListItem__stage__label">
					{{ submission.stage.label }}
				</div>
				<div class="pkpSubmissionsListItem__flags">
					<span v-if="isReviewStage"  class="pkpSubmissionsListItem__flags--reviews" v-bind:class="classHighlightReviews">
						<span class="count">{{ completedReviewsCount }} / {{ submission.stage.reviews.length }}</span>
					</span>
					<span v-if="submission.stage.files.count" class="pkpSubmissionsListItem__flags--files" v-bind:class="classHighlightFiles">
						<span class="count">{{ submission.stage.files.count }}</span>
					</span>
					<span v-if="openQueryCount" class="pkpSubmissionsListItem__flags--discussions">
						<span class="count">{{ openQueryCount }}</span>
					</span>
				</div>
			</div>
			<div class="pkpSubmissionsListItem__actions">
				<a href="#" class="delete" @click="emitDelete">
					{{ i18n.delete }}
				</a>
				<a href="#" @click="emitInfoCenter">
					{{ i18n.infoCenter }}
				</a>
			</div>
		</div>
	</li>
</template>

<script>
export default {
	name: 'SubmissionsListItem',
	props: ['submission', 'i18n'],
	computed: {
		/**
		 * Compile a notice depending on the stage status
		 *
		 * Only stage status' that have pending work for the current user should
		 * result in a notice.
		 *
		 * @todo Set different notice priorities for different users. Current
		 *  set up is for editors.
		 * @return string
		 */
		notice: function() {
			var notice = '';

			// Submission
			if (this.submission.stage.id === 1) {
				switch (this.submission.stage.statusId) {
					case 1: // @todo g
						notice = this.submission.stage.status;
						break;
				}

			// Review
			// @todo account for multiple review stages in OMP
			} else if (this.isReviewStage) {

				switch (this.submission.stage.statusId) {
					case 6: // REVIEW_ROUND_STATUS_PENDING_REVIEWERS
					case 8: // REVIEW_ROUND_STATUS_REVIEWS_READY
					case 9: // REVIEW_ROUND_STATUS_REVIEWS_COMPLETED
					case 10: // REVIEW_ROUND_STATUS_REVIEWS_OVERDUE
					case 11: // REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED
						notice = this.submission.stage.status;
						break;
				}
			}

			return notice;
		},

		/**
		 * Compile the count of open discussions
		 *
		 * @return int
		 */
		openQueryCount: function() {
			return _.where(this.submission.stage.queries, {closed: "0"}).length;
		},

		/**
		 * Is this the review stage?
		 *
		 * @return bool
		 */
		isReviewStage: function() {
			return this.submission.stage.id === 3;
		},

		/**
		 * Compile the count of completed reviews
		 *
		 * @return int
		 */
		completedReviewsCount: function() {
			if (!this.isReviewStage) {
				return 0;
			}
			return _.filter(this.submission.stage.reviews, function(review) {
				return review.statusId >= 7; // REVIEW_ASSIGNMENT_STATUS_RECEIVED and above
			}).length;
		},

		/**
		 * Return a class to highlight the reviews icon
		 *
		 * @return string
		 */
		classHighlightReviews: function() {
			if (!this.isReviewStage) {
				return '';
			}

			// REVIEW_ROUND_STATUS_REVIEWS_OVERDUE
			if (this.submission.stage.statusId == 10) {
				return '--warning';
			}

			// No reviews have been assigned
			if (!this.submission.stage.reviews.length) {
				return '--warning';
			}

			// REVIEW_ROUND_STATUS_REVIEWS_READY
			if (this.submission.stage.statusId == 8) {
				return '--notice';
			}

			return '';
		},

		/**
		 * Return a class to highlight the files icon when revisions have been
		 * submitted.
		 *
		 * @return string
		 */
		classHighlightFiles: function() {
			if (this.submission.stage.files.count) {
				return '--notice';
			}

			return '';
		},
	},
	methods: {
		/**
		 * Load the history and notes modal
		 */
		emitInfoCenter: function(e) {

			if (e instanceof Event) {
				e.preventDefault();
			}

			this.$emit('openInfoCenter', this.submission.id, this.submission.title);
		},

		/**
		 * Load the delete confirmation modal
		 */
		emitDelete: function(e) {

			if (e instanceof Event) {
				e.preventDefault();
			}

			this.$emit('deleteSubmission', this.submission.id);
		},
	},
}
</script>

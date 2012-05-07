/**
 * @defgroup js_classes_features
 */
// Define the namespace
$.pkp.classes.features = $.pkp.classes.features || {};


/**
 * @file js/classes/features/Feature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Feature
 * @ingroup js_classes_features
 *
 * @brief A feature is a type of plugin specific to the grid widgets. It
 * provides several hooks to allow injection of additional grid widgets
 * functionality. This class implements template methods to be extendeded by
 * subclasses.
 *
 * We use the features concept of the ext js framework:
 * http://docs.sencha.com/ext-js/4-0/#!/api/Ext.grid.feature.Feature
 */
(function($) {


	/**
	 * @constructor
	 * @param {Handler} gridHandler The grid handler object.
	 * @param {Array} options Associated options.
	 */
	$.pkp.classes.features.Feature =
			function(gridHandler, options) {
		this.gridHandler_ = gridHandler;
		this.options_ = options;
		// FIXME #7379# Pass the rendered html to this method when
		// the html is in the options.
		this.addFeatureHtml(this.getGridHtmlElement());
	};


	//
	// Private properties.
	//
	/**
	 * The grid that this feature is attached to.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.Feature.prototype.gridHandler_ = null;


	/**
	 * This feature configuration options.
	 * @private
	 * @type {object}
	 */
	$.pkp.classes.features.Feature.prototype.options_ = null;


	//
	// Public template methods.
	//
	/**
	 * Initialize this feature. Needs to be extended to implement
	 * specific initialization. This method will always be called
	 * by the components that this feature is attached to, in the
	 * moment of the attachment.
	 */
	$.pkp.classes.features.Feature.prototype.init =
			function() {
		throw Error('Abstract method!');
	};


	//
	// Template methods (hooks into grid widgets).
	//
	/**
	 * Hook into the append new row grid functionality.
	 * @param {jQuery} $newRow The new row to be appended.
	 * @return {boolean} Always returns false.
	 */
	$.pkp.classes.features.Feature.prototype.appendRow =
			function($newRow) {
		return false;
	};


	/**
	 * Hook into the replace row content grid functionality.
	 * @param {jQuery} $newRow The row new content to be shown.
	 * @return {boolean} Always returns false.
	 */
	$.pkp.classes.features.Feature.prototype.appendRow =
			function($newRow) {
		return false;
	};


	/**
	 * Hook into the replace row content grid functionality.
	 * @param {jQuery} $newContent The row new content to be shown.
	 * @return {boolean} Always returns false.
	 */
	$.pkp.classes.features.Feature.prototype.replaceRow =
			function($newContent) {
		return false;
	};


	//
	// Protected methods.
	//
	/**
	 * Extend to add extra html elements in the component
	 * that this feature is attached to.
	 * @param {jQuery} $gridElement Grid element to add elements to.
	 */
	$.pkp.classes.features.Feature.prototype.addFeatureHtml =
			function($gridElement) {
		// Default implementation does nothing.
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

/**
 * @defgroup js_controllers_tab_settings_archiving_form
 */
/**
 * @file js/controllers/tab/settings/archiving/form/ArchivingSettingsFormHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArchivingSettingsFormHandler
 * @ingroup js_controllers_tab_settings_archiving_form
 *
 * @brief Handle the press archiving settings form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.tab.settings.archiving =
			$.pkp.controllers.tab.settings.archiving || { form: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.tab.settings.archiving.form.
			ArchivingSettingsFormHandler = function($form, options) {

		this.parent($form, options);

		$('.expand-others').click(function() {
			$('#otherLockss').slideToggle('fast');
		});

		var plnInstalled = $('#isPLNPluginInstalled').val();
		if (plnInstalled == '1') {
			$('#otherLockss').hide();
		}
	};

	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.tab.settings.archiving.form.
			ArchivingSettingsFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);
/** @param {jQuery} $ jQuery closure. */
}(jQuery));

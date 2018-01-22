/**
 * @file js/controllers/grid/navigationMenus/form/NavigationMenuFormHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuFormHandler
 * @ingroup js_controllers_grid_navigationMenus_form
 *
 * @brief NavigationMenuItems form handler.
 */
(function($) {

	/**
	 * Define the namespace
	 */
	$.pkp.controllers.grid.navigationMenus =
			$.pkp.controllers.grid.navigationMenus ||
			{ form: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $formElement The form element
	 * @param {Object} options Modal options.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler =
			function($formElement, options) {

		this.okButton_ = options.okButton;
		this.warningModalTitle_ = options.warningModalTitle;
		this.submenuWarning_ = options.submenuWarning;
		this.itemTypeConditionalWarnings_ = options.itemTypeConditionalWarnings;

		$formElement.on('click', '.btnConditionalDisplay',
				this.callbackWrapper(this.showConditionalDisplayWarning));
		$formElement.on('click', '.btnSubmenuWarning',
				this.callbackWrapper(this.showSubmenuWarning));

		this.parent($formElement, options);
		this.initSorting();
	};


	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//


	/**
	 * The label for the ok button on the modals displaying submenuWarning and
	 * conditionalWarnings.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.okButton_ = null;


	/**
	 * The title of the modals displaying submenuWarning and conditionalWarnings.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.warningModalTitle_ = null;


	/**
	 * The warning message to display about submenus
	 * @private
	 * @type {string|undefined}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.submenuWarning_ = undefined;


	/**
	 * Warnings about the conditions of display for each item type.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.itemTypeConditionalWarnings_ = null;


	/**
	 * Initialize the .sortable() lists, limit nesting to one level deep and
	 * ensure lists are formatted properly the CSS styles
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler.
			prototype.initSorting = function() {
		var self = this;

		// Remove any submenu warning buttons
		$('.btnSubmenuWarning', this.getHtmlElement()).remove();

		// Limit nesting to one level deep and ensure nested lists are formatted
		// properly for appropriate styles
		$('#pkpNavAssigned > li').each(
				function() {
					var $childList = $(this).children('ul'),
							$children = $childList.children(),
							$grandchildren = $children.find('li');

					if (!$childList.length) {
						$(this).append('<ul></ul>');
						return;
					}

					if (!$children.length) {
						// Ensure it's just an empty ul (with no spaces) so the CSS
						// :empty psuedo class can be used
						$childList.replaceWith('<ul></ul>');
					} else {
						// Prevent nesting two levels deep by moving any items
						// nested at that level up one level.
						if ($grandchildren.length) {
							$grandchildren.each(function() {
								$(this).appendTo($childList);
							});
						}

						// Add a submenu warning button
						if (!$(this).find(
								'> .item > .item_buttons .btnSubmenuWarning').length) {
							$(this).find('> .item > .item_buttons').prepend(
									$('<button></button>')
									.addClass('btnSubmenuWarning')
									.append(
									$('<span></span>')
									.addClass('fa fa-exclamation-triangle')
									)
									.append(
									$('<span></span>')
									.addClass('-screenReader')
									.text(self.submenuWarning_)
									)
							);
						}
					}
				}
		);

		// Reset any nesting that's been carried over from the assigned list
		$('#pkpNavUnassigned > li').each(
				function() {
					var $childList = $(this).children('ul');
					if ($childList.length) {
						$childList.find('li').each(function() {
							$(this).appendTo($('#pkpNavUnassigned'));
						});
					}
					$childList.remove();
				}
		);

		// Initialize the sortable controls
		$('#pkpNavManagement ul').sortable({
			placeholder: 'pkp_nav_item_placeholder',
			delay: 250,
			connectWith: '#pkpNavManagement ul',
			update: this.callbackWrapper(this.updateSorting),
			start: function() {
				$('#pkpNavAssigned').addClass('pkp_is_sorting');
			},
			stop: function() {
				$('#pkpNavAssigned').removeClass('pkp_is_sorting');
			}
		});
	};


	/**
	 * Re-initialize the .sortable() components and generate the form fields
	 * whenever the list is re-sorted.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler.
			prototype.updateSorting = function() {
		var $navManagement = $('#pkpNavManagement'),
				seq = 0,
				parent = null,
				currentName = '';

		// Re-intialize the sortable component after adjusting sort order
		this.initSorting();

		// Remove existing hidden fields
		$('input', $navManagement).remove();

		// Generate new hidden form fields
		$('#pkpNavAssigned > li').each(function() {
			currentName = 'menuTree[' + $(this).data('id') + ']';
			$navManagement.append('<input type="hidden" name="' +
					currentName + '[seq]" value="' +
					seq + '">');
			seq++;

			var parentId = $(this).data('id');
			$(this).find('li').each(function() {
				currentName = 'menuTree[' + $(this).data('id') + ']';
				$navManagement.append('<input type="hidden" name="' +
						currentName + '[seq]" value="' +
						seq + '">');
				$navManagement.append('<input type="hidden" name="' +
						currentName + '[parentId]" value="' +
						parentId + '">');
				seq++;
			});
		});
	};


	/**
	 * Show the conditional display warning message
	 * @param {jQueryObject} htmlElement The html element
	 * @return {boolean}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler.
			prototype.showConditionalDisplayWarning = function(htmlElement) {
		var itemType = $(htmlElement).closest('li').data('type'),
				opts = {
					title: this.warningModalTitle_,
					okButton: this.okButton_,
					cancelButton: false,
					dialogText: this.itemTypeConditionalWarnings_[itemType]
				};

		if (this.itemTypeConditionalWarnings_[itemType] !== null) {
			$('<div id="' + $.pkp.classes.Helper.uuid() + '" ' +
					'class="pkp_modal pkpModalWrapper" tabindex="-1"></div>')
					.pkpHandler('$.pkp.controllers.modal.ConfirmationModalHandler', opts);
		}

		return false;
	};


	/**
	 * Show the submenu link warning message
	 * @return {boolean}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler.
			prototype.showSubmenuWarning = function() {

		var opts = {
			title: this.warningModalTitle_,
			okButton: this.okButton_,
			cancelButton: false,
			dialogText: this.submenuWarning_
		};

		$('<div id="' + $.pkp.classes.Helper.uuid() + '" ' +
				'class="pkp_modal pkpModalWrapper" tabindex="-1"></div>')
				.pkpHandler('$.pkp.controllers.modal.ConfirmationModalHandler', opts);

		return false;
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));

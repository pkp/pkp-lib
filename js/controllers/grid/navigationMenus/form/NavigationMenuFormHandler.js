/**
 * @file js/controllers/grid/navigationMenus/form/NavigationMenuFormHandler.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuFormHandler
 * @ingroup js_controllers_grid_navigationMenus_form
 *
 * @brief NavigationMenuItems form handler.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.navigationMenus =
			$.pkp.controllers.grid.navigationMenus ||
			{ form: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $formElement A wrapped HTML element that
	 *  represents the approved proof form interface element.
	 * @param {Object} options Modal options.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler =
			function ($form, options) {

		this.parent($form, options);
		this.initSorting();
	};

	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);

	/**
	 * Initialize the .sortable() lists, limit nesting to one level deep and
	 * ensure lists are formatted properly the CSS styles
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.initSorting = function() {

		// Limit nesting to one level deep and ensure nested lists are formatted
		// properly for appropriate styles
		$('#pkpNavAssigned > li').each(
			function() {
				var $childList = $(this).children('ul');
				if (!$childList.length) {
					$(this).append('<ul></ul>');
					return;
				}

				var $children = $childList.children();
				if (!$children.length) {
					// Ensure it's just an empty ul (with no spaces) so the CSS
					// :empty psuedo class can be used
					$childList.replaceWith('<ul></ul>');
				} else {
					// Prevent nesting two levels deep by moving any items
					// nested at that level up one level.
					var $grandchildren = $children.find('li');
					if ($grandchildren.length) {
						$grandchildren.each(function() {
							$(this).appendTo($childList);
						});
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
					})
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
			},
		});
	};

	/**
	 * Re-initialize the .sortable() components and generate the form fields
	 * whenever the list is re-sorted.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuFormHandler
			.prototype.updateSorting = function() {
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
			$navManagement.append('<input type="hidden" name="' + currentName + '[seq]" value="' + seq + '">');
			seq++;

			parentId = $(this).data('id');
			$(this).find('li').each(function() {
				currentName = 'menuTree[' + $(this).data('id') + ']';
				$navManagement.append('<input type="hidden" name="' + currentName + '[seq]" value="' + seq + '">');
				$navManagement.append('<input type="hidden" name="' + currentName + '[parentId]" value="' + parentId + '">');
				seq++;
			});
		});
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

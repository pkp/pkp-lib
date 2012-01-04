/**
 * listbuilder.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Implementation of listbuilder interface elements for OMP.
 *
 * $Id:
 */

/**
 * addItem
 * Add an item to the list
 * @param $handler URL handle the routine
 * @param $listbuilderId DOM id to the listbuilder being used
 */
function addItem(handler, listbuilderId, localizedButtons) {
	$(document).ready(function() {
		var form = '#source-' + listbuilderId;

		$('#add-' + listbuilderId).click(function() {
			newItem = $('#source-' + listbuilderId + ' *').serialize();
			$.post(
				handler,
				newItem,
				function(returnString) {
					if (returnString.status) {
						$(returnString.content).hide().prependTo('#listGrid-' + listbuilderId).fadeIn('slow').css("display","");
						$('#listGrid-' + listbuilderId + ' tr.empty').hide();

						// Remove the item from the source
						$("#source-" + listbuilderId + " .text").val(""); //If source is text input
						$("#source-" + listbuilderId + " option:selected").remove(); //If source is list

						// If applicable, add the result to source lists elsewhere on the page
						if(returnString.addToSources) {
							var sourceIdString = returnString.sourceIds;
							var sourceIds = new Array();
							sourceIds = sourceIdString.split(',');

							$.each(sourceIds, function(key, value) {
								$("#"+value).append(returnString.sourceHtml);
							});
						}
					} else {
						// Alert that the action failed
						modalAlert(returnString.content, localizedButtons);
					}
				}, 'json'
			);
		});
	});
}

/**
 * deleteItems
 * Delete selected items from the list
 * @param $handler URL handle the routine
 * @param $listbuilderId DOM id to the listbuilder being used
 */
function deleteItems(handler, listbuilderId) {
	$(document).ready(function() {
		$('#delete-' + listbuilderId).click(function() {
			var selectedItems = [];
			$('#listGrid-' + listbuilderId + ' .selected').each(function(i, selected){
				selectedItems.push('item_' + i + '=' + $(selected).attr('id'));
			});

			$.post(
				handler,
				selectedItems.join('&'),
				function(returnString) {
					if (returnString.status) {
						// Remove the select items from the list
						$('#listGrid-' + listbuilderId + ' .selected').each(function(i, selected){
							$(selected).remove();
						});

						// If applicable, remove the result from source lists elsewhere on the page
						if(returnString.removeFromSources) {
							var sourceIdString = returnString.sourceIds;
							var sourceIds = new Array();
							sourceIds = sourceIdString.split(',');

							var itemIdString = returnString.itemIds;
							var itemIds = new Array();
							itemIds = itemIdString.split(',');

							$.each(sourceIds, function(sourceKey, sourceValue) {
								$.each(itemIds, function(itemKey, itemValue) {
									$("#" + sourceValue+" option[value='" + itemValue + "']").remove();
								})
							});
						}
					} else {
						// Alert that the action failed
						alert('DELETING ITEM FAILED');	// FIXME:  Need to translate.  Make this a modal?
					}
				}, 'json'
			);
		});
	});
}

/**
 * selectRow
 * Select a row in a listbuilder grid
 */
function selectRow(listbuilderGridId) {
	$('#results-'+listbuilderGridId)
		.css("cursor","pointer")
		.click(function(e) {
			var clicked = $(e.target);
			clicked.parent().toggleClass('selected');
			return false;
		});
}

/**
 * getAutocompleteSource
 * Load either an array of data for a local autocomplete interface, or a URL for a server-based autcomplete
 * @param $handler URL handle the routine
 * @param $listbuilderId DOM id to the listbuilder being used
 */
function getAutocompleteSource(handler, id) {
	$(document).ready(function(){
		var data = null;
		$.getJSON(
			handler,
			function(returnString) {
				if (returnString.elementId == 'local') {
					// Set the data source to an array (for smaller data sets only)
					data = eval("("+returnString.content+")");
				} else if (returnString.elementId == 'url') {
					// Set the data to the url
					data = returnString.content;
				}
				
				// Initialize the autocomplete field
				$("#sourceTitle-" + id).autocomplete({
					source: data,
					minLength: 0,
					focus: function(event, ui) {
						$("#sourceTitle-" + id).val(ui.item.label);
						return false;
					},
					select: function (event, ui) {
						$("#sourceId-" + id).val(ui.item.value);
						return false;
					}
				});
			});
	});
}

/* Helper function for getAutocompleteSource */
function formatItem(row) {
	return row[0] + " (<strong>id: " + row[1] + "</strong>)";
}
/* Helper function for getAutocompleteSource */
function formatResult(row) {
	return row[0].replace(/(<.+?>)/gi, '');
}

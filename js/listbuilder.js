/**
 * listbuilder.js
 *
 * Copyright (c) 2003-2010 John Willinsky
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
function addItem(handler, listbuilderId) {
	$(document).ready(function() {
		var form = '#source-' + listbuilderId;
		
		$('#add-' + listbuilderId).click(function() {
			newItem = $('#source-' + listbuilderId + ' *').serialize();
			$.post(
				handler, 
				newItem, 
				function(returnString) {
					if (returnString.status) {
						$(returnString.content).hide().prependTo('#listGrid-' + listbuilderId).fadeIn("slow");
						$('#listGrid-' + listbuilderId + ' tr.empty').hide();
					} else {
						// Alert that the action failed
						alert('ADDING ITEM FAILED');	// FIXME: Need to translate.  Make this a modal?  
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
	$('#'+listbuilderGridId)
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

					$("#sourceTitle-" + id).autocomplete(data, {
						minChars: 0,
						formatItem: function(row, i, max) {
							return row.content;
						},
						formatResult: function(row) {
							return row.content;
						}
					}).result(function (evt, data, formatted) {
					    $("#sourceId-" + id).val(data.elementId);
					});

				} else if (returnString.elementId == 'url') {
					// Set the data to the url
					data = returnString.content;
					$("#sourceTitle-" + id).autocomplete(data, {
						minChars: 0,
						formatItem: function(row, i, max) {
							return row.content;
						},
						formatResult: function(row) {
							return row.content;
						}
					}).result(function (evt, data, formatted) {
					    $("#sourceId-" + id).val(data.elementId);
					});
				}
			}
		);
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

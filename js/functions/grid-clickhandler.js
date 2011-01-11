/**
 * functions/grid-clickhandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Hide/show row level actions in standard grids.
 */

// Grid settings button handler
$(function(){
	$('a.settings').live("click", (function() {
		$(this).parent().siblings('.row_controls').toggle(300);
	}));
});

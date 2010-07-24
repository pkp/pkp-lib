/**
 * omp.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Miscelaneous javascript and jQuery for the Open Monograph Press
 */

$(document).ready(function(){
    $("a.openHelp").each(function(){
        $(this).click(function() {openHelp($(this).attr('href')); return false;})
    });
})
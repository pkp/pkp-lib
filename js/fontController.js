/**
 * Font Controller
 * For creating a font size changer interface with minimum effort
 * Copyright (c) 2009 Hafees (http://cool-javascripts.com)
 * License: Free to use, modify, distribute as long as this header is kept :)
 *
 */

/**
 * Required: jQuery 1.x library, 
 * Optional: jQuery Cookie Plugin (if used, the last used font size will be saved)
 * Usage: (For more details visit 
 * This function can be called inside a $(document).ready()
 * Eg: fontSize("#controls", "#content", 9, 12, 20); where,
 * #controls - where control is the element id, where the controllers will be created.
 * #content - for which element the font size changes to apply. In this case font size of content div will be changed
 * 9 - minimum font size
 * 12 - default font size
 * 20 - maximum font size
 * 
 */

function fontSize(container, target, minSize, defSize, maxSize, baseUrl) {
	/*Editable settings*/
	var minCaption = "Make font size smaller"; //title for smallFont button
	var defCaption = "Make font size default"; //title for defaultFont button
	var maxCaption = "Make font size larger"; //title for largefont button
	
	var cookie = 'font-size';
	
	//Now we'll add the font size changer interface in container
	smallFontHtml = "<a href='javascript:void(0);' class='smallFont' title='" + minCaption +"'>" + minCaption + "</a> ";
	defFontHtml = "<a href='javascript:void(0);' class='defaultFont' title='" + defCaption +"'>" + defCaption + "</a> ";
	largeFontHtml = "<a href='javascript:void(0);' class='largeFont' title='" + maxCaption +"'>" + maxCaption + "</a> ";
	$(container).html(smallFontHtml + defFontHtml + largeFontHtml);
	
	//Read cookie & sets the fontsize
	if ($.cookie != undefined) {
		var value = $.cookie(cookie);
		if (value !=null) {
			$(target).css('font-size', parseInt(value));
		}
	}
		
	//on clicking small font button, font size is decreased by 1px
	$(container + " .smallFont").click(function(){ 
		curSize = parseInt($(target).css("font-size"));
		newSize = curSize - 1;
		if (newSize >= minSize) {
			$(target).css('font-size', newSize);
		} 
		if (newSize <= minSize) {
			$(container + " .smallFont").addClass("sdisabled");
		}
		if (newSize < maxSize) {
			$(container + " .largeFont").removeClass("ldisabled");
		}
		updatefontCookie(newSize, baseUrl); //sets the cookie 
		
	});

	//on clicking default font size button, font size is reset
	$(container + " .defaultFont").click(function(){
		$(target).css('font-size', defSize);
		$(container + " .smallFont").removeClass("sdisabled");
		$(container + " .largeFont").removeClass("ldisabled");
		updatefontCookie(defSize, baseUrl);
	});

	//on clicking large font size button, font size is incremented by 1 to the maximum limit
	$(container + " .largeFont").click(function(){
		curSize = parseInt($(target).css("font-size"));
		newSize = curSize + 1;
		if (newSize <= maxSize) {
			$(target).css('font-size', newSize);
		} 
		if (newSize > minSize) {
			$(container + " .smallFont").removeClass("sdisabled");
		}
		if (newSize >= maxSize) {
			$(container + " .largeFont").addClass("ldisabled");
		}
		updatefontCookie(newSize, baseUrl);
	});

	function updatefontCookie(size, baseUrl) {
		if ($.cookie != undefined) { //If cookie plugin available, set a cookie
			var cookie = 'font-size';
			$.cookie(cookie, size, { path: baseUrl });
		} 
	}
}
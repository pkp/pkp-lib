/*
 * imgPreview jQuery plugin
 * Copyright (c) 2009 James Padolsey
 * j@qd9.co.uk | http://james.padolsey.com
 * Dual licensed under MIT and GPL.
 * Updated: 08/02/09
 * @author James Padolsey
 * @version 0.2
 *
 * Modifications made by Matt Crider to allow for OMP-style links to images
 *  The original javascript required image extensions in the URL.
 *
 */
(function($){
    // MC Customization: Removed $.expr definition which ensures files end with typical image extensions
    $.fn.imgPreview = function(userDefinedSettings){

        var s = $.extend({
            imgCSS: {},
            distanceFromCursor: {top:10, left:10},
            preloadImages: true,
            onShow: function(){},
            onHide: function(){},
            onLoad: function(){},
            containerID: 'imgPreviewContainer',
            containerLoadingClass: 'loading',
            thumbPrefix: '',
            srcAttr: 'href'
        }, userDefinedSettings);

		// MC Customization: Re-use the container if it is already in the DOM
		if($("#"+s.containerID).length) {
			$("#"+s.containerID).html(''); // Clear out container
			$container = $("#"+s.containerID)
					.append('<img/>').hide();
		} else {
			$container = $('<div/>').attr('id', s.containerID)
					.append('<img/>').hide()
					.css('position','absolute')
					.css('z-index','10005')
					.appendTo('body');
		}

        $img = $('img', $container).css(s.imgCSS);

        // MC customization: Removed more suffix checking code
        if (s.preloadImages) {
            this.each(function(){

                    (new Image()).src = $(this).attr(s.srcAttr).replace(/(\/?)([^\/]+)$/,'$1' + s.thumbPrefix + '$2');

            });
        }

        this.mousemove(function(e){
                $container.css({
                    top: e.pageY + s.distanceFromCursor.top + 'px',
                    left: e.pageX + s.distanceFromCursor.left + 'px'
                   });
            })
            .hover(function(){
                var link = this;
                $container.addClass(s.containerLoadingClass).show();
                $img.load(function(){
                    $container.removeClass(s.containerLoadingClass);
                    s.onLoad.call($img[0], link);
                }).attr('src', $(link).attr(s.srcAttr).replace(/\/([^\/]+)$/,'/' + s.thumbPrefix + '$1'));
                s.onShow.call($container[0], link);
            }, function(){
                $container.hide();
                $img.unbind('load').attr('src','');
                s.onHide.call($container[0], this);
            });

        return this;

    };

})(jQuery);

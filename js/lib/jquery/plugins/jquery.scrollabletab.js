/**
 * jQuery.ScrollableTab - Scrolling multiple tabs.
 * @copyright (c) 2010 Astun Technology Ltd - http://www.astuntechnology.com
 * Dual licensed under MIT and GPL.
 * Date: 28/04/2010
 * @author Aamir Afridi - aamirafridi(at)gmail(dot)com | http://www.aamirafridi.com
 * @version 1.0
 */

;(function($){
	//Global plugin settings
	var settings = {
		'animationSpeed' : 100, //The speed in which the tabs will animate/scroll
		'closable' : false, //Make tabs closable
		'resizable' : false, //Alow resizing the tabs container
		'resizeHandles' : 'e,s,se', //Resizable in North, East and NorthEast directions
		'loadLastTab':false, //When tabs loaded, scroll to the last tab - default is the first tab
		'easing':'swing' //The easing equation
	}

	/**
	 * Adds horizontal scrolling to tabs.
	 *  retrieve the HTML to be inserted.
	 * @param {Object} options Handler options.
	 * @return {Object} The scrolling tabs
	 */
	$.fn.scrollabletab = function(options){
		//Check if scrollto plugin is available - (pasted the plugin at the end of this plugin)
		//if(!$.fn.scrollTo) return alert('Error:\nScrollTo plugin not available.');

		return this.each(function(){
			var	o = $.extend({}, settings, options), //Extend the options if any provided
			$tabs = $(this),
			$tabsNav = $tabs.find('.ui-tabs-nav'),
			$nav;//will save the refrence for the wrapper having next and previous buttons

			//Adjust the css class
			//$tabsNav.removeClass('ui-corner-all').addClass('ui-corner-top');
			$tabs.css({'padding':2, 'position':'relative'});
			//$tabsNav.css('position','inherit');

			//Wrap inner items
			$tabs.wrap('<div id="stTabswrapper" class="stTabsMainWrapper" style="position:relative"/>').find('.ui-tabs-nav').css('overflow','hidden').wrapInner('<div class="stTabsInnerWrapper" style="width:30000px"><span class="stWidthChecker"/></div>');
			var $widthChecker = $tabs.find('.stWidthChecker'),
				$itemContainer = $tabs.find('.stTabsInnerWrapper'),
				$tabsWrapper = $tabs.parents('#stTabswrapper').width($tabs.outerWidth(true));
				//Fixing safari bug
				if (/safari/.test(navigator.userAgent.toLowerCase()))
				{
					$tabsWrapper.width($tabs.width()+6);
				}
				//alert($tabsWrapper.width());
			if(o.resizable)
			{
				if(!!$.fn.resizable)
				{
					$tabsWrapper.resizable({
						minWidth : $tabsWrapper.width(),
						maxWidth : $tabsWrapper.width()*2,
						minHeight : $tabsWrapper.height(),
						maxHeight : $tabsWrapper.height()*2,
						handles : o.resizeHandles,
						alsoResize: $tabs,
						//start : function(){  },
						resize: function(){
							$tabs.trigger('resized');
						}
						//stop: function(){ $tabs.trigger('scrollToTab',$tabsNav.find('li.ui-tabs-selected')); }
					});
				}
				else
				{
					alert('Error:\nCannot be resizable because "jQuery.resizable" plugin is not available.');
				}
			}


			//Add navigation icons
				//Total height of nav/2 - total height of arrow/2
			var arrowsTopMargin = (parseInt(parseInt($tabsNav.innerHeight(true)/2)-8)),
				arrowsCommonCss={'cursor':'pointer','z-index':100,'position':'absolute','top':3,'height':$tabsNav.outerHeight()-(/safari/.test(navigator.userAgent.toLowerCase()) ? 2 : 1)};
			$tabsWrapper.prepend(
			  $nav = $('<div/>')
			  		.disableSelection()
					.css({'position':'relative','z-index':100,'display':'none'})
					.append(
						$('<span/>')
							.disableSelection()
							.attr('title','')
							.css(arrowsCommonCss)
							.addClass('ui-state-active ui-corner-tl ui-corner-bl stPrev stNav')
							.css('left',3)
							.append($('<span/>').disableSelection().addClass('ui-icon ui-icon-carat-1-w').html('').css('margin-top',arrowsTopMargin))
							.click(function(){
								//Check if disabled
								if($(this).hasClass('ui-state-disabled')) return;
								//Just select the previous tab and trigger scrollToTab event
								prevIndex = $tabsNav.find('li.ui-tabs-selected').prevAll().length-1
								//Now select the tab
								$tabsNav.find('li').eq(prevIndex).find('a').trigger('click');
								return false;
							}),
						$('<span/>')
							.disableSelection()
							.attr('title','')
							.css(arrowsCommonCss)
							.addClass('ui-state-active ui-corner-tr ui-corner-br stNext stNav')
							.css({'right':3})
							.append($('<span/>').addClass('ui-icon ui-icon-carat-1-e').html('').css('margin-top',arrowsTopMargin))
							.click(function(){
								//Just select the previous tab and trigger scrollToTab event
								nextIndex = $tabsNav.find('li.ui-tabs-selected').prevAll().length+1
								//Now select the tab
								$tabsNav.find('li').eq(nextIndex).find('a').trigger('click');
								return false;
							})
					)
			);

			//Bind events to the $tabs
			$tabs
			.bind('tabsremove', function(){
  				$tabs.trigger('scrollToTab').trigger('navHandler').trigger('navEnabler');
			})
			.bind('addCloseButton',function(){
				//Add close button if require
				if(!o.closable) return;
				$(this).find('.ui-tabs-nav li').each(function(){
					if($(this).find('.ui-tabs-close').length>0) return; //Already has close button
					var closeTopMargin = parseInt(parseInt($tabsNav.find('li:first').innerHeight()/2,10)-8);
					$(this).disableSelection().append(
						$('<span style="float:left;cursor:pointer;margin:'+closeTopMargin+'px 2px 0 -11px" class="ui-tabs-close ui-icon ui-icon-close" title="Close this tab"></span>')
							.click(function()
							{
								$tabs.tabs('remove',$(this).parents('li').prevAll().length);
								//If one tab remaining than hide the close button
								if($tabs.tabs('length')==1)
								{
									$tabsNav.find('.ui-icon-close').hide();
								}
								else
								{
									$tabsNav.find('.ui-icon-close').show();
								}
								//Call the method when tab is closed (if any)
								if($.isFunction(o.onTabClose))
								{
									o.onTabClose();
								}
								return false;
							})
					);
					//Show all close buttons if any hidden
					$tabsNav.find('.ui-icon-close').show();
				});
			}).bind('bindTabClick',function(){
				//Handle scroll when user manually click on a tab
				$tabsNav.find('a').click(function(){
					var $liClicked = $(this).parents('li');
					var navWidth = $nav.find('.stPrev').outerWidth(true);
					//debug('left='+($liClicked.offset().left)+' and tabs width = '+ ($tabs.width()-navWidth));
					if(($liClicked.position().left-navWidth)<0)
					{
						$tabs.trigger('scrollToTab',[$liClicked,'tabClicked','left'])
					}
					else if(($liClicked.outerWidth()+$liClicked.position().left)>($tabs.width()-navWidth))
					{
						$tabs.trigger('scrollToTab',[$liClicked,'tabClicked','right'])
					}
					//Enable or disable next and prev arrows
					$tabs.trigger('navEnabler');
					return false;
				});
			})
			//Bind the event to act when tab is added
			.bind('scrollToTab',function(event,$tabToScrollTo,clickedFrom,hiddenOnSide){
				//If tab not provided than scroll to the last tab
				$tabToScrollTo = (typeof $tabToScrollTo!='undefined') ? $($tabToScrollTo) : $tabsNav.find('li.ui-tabs-selected');
				//Scroll the pane to the last tab
				var navWidth = $nav.is(':visible') ? $nav.find('.stPrev').outerWidth(true) : 0;
				//debug($tabToScrollTo.prevAll().length)

				offsetLeft = -($tabs.width()-($tabToScrollTo.outerWidth(true)+navWidth+parseInt($tabsNav.find('li:last').css('margin-right'),10)));
				offsetLeft = (clickedFrom=='tabClicked' && hiddenOnSide=='left') ? -navWidth : offsetLeft;
				offsetLeft = (clickedFrom=='tabClicked' && hiddenOnSide=='right') ? offsetLeft : offsetLeft;
				//debug(offsetLeft);
				var scrollSettings = { 'axis':'x', 'margin':true, 'offset': {'left':offsetLeft}, 'easing':o.easing||'' }
				//debug(-($tabs.width()-(116+navWidth)));
				$tabsNav.scrollTo($tabToScrollTo,o.animationSpeed,scrollSettings);
			})
			.bind('navEnabler',function(){
				setTimeout(function(){
					//Check if last or first tab is selected than disable the navigation arrows
					var isLast = $tabsNav.find('.ui-tabs-selected').is(':last-child'),
						isFirst = $tabsNav.find('.ui-tabs-selected').is(':first-child'),
						$ntNav = $tabsWrapper.find('.stNext'),
						$pvNav = $tabsWrapper.find('.stPrev');
					//debug('isLast = '+isLast+' - isFirst = '+isFirst);
					if(isLast)
					{
						$pvNav.removeClass('ui-state-disabled');
						$ntNav.addClass('ui-state-disabled');
					}
					else if(isFirst)
					{
						$ntNav.removeClass('ui-state-disabled');
						$pvNav.addClass('ui-state-disabled');
					}
					else
					{
						$ntNav.removeClass('ui-state-disabled');
						$pvNav.removeClass('ui-state-disabled');
					}
				},o.animationSpeed);
			})
			//Now check if tabs need navigation (many tabs out of sight)
			.bind('navHandler',function(){
				//Check the width of $widthChecker against the $tabsNav. If widthChecker has bigger width than show the $nav else hide it
				if($widthChecker.width()>$tabsNav.width())
				{
					$nav.show();
					//Put some margin to the first tab to make it visible if selected
					$tabsNav.find('li:first').css('margin-left',$nav.find('.stPrev').outerWidth(true));
				}
				else
				{
					$nav.hide();
					//Remove the margin from the first element
					$tabsNav.find('li:first').css('margin-left',0);
				}
			})
			.bind('tabsselect', function() {
				//$tabs.trigger('navEnabler');
			})
			.bind('resized', function() {
				$tabs.trigger('navHandler');
				$tabs.trigger('scrollToTab',$tabsNav.find('li.ui-tabs-selected'));
			})
			//To add close buttons to the already existing tabs
			.trigger('addCloseButton')
			.trigger('bindTabClick')
			//For the tabs that already exists
			.trigger('navHandler')
			.trigger('navEnabler');

			//Select last tab if option is true
			if(o.loadLastTab)
			{
				setTimeout(function(){$tabsNav.find('li:last a').trigger('click')},o.animationSpeed);
			}
		});

		//Just for debuging
		function debug(obj)
		{console.log(obj)}
	}
})(jQuery);



/**
 * jQuery.ScrollTo - Easy element scrolling using jQuery.
 * Copyright (c) 2007-2009 Ariel Flesler - aflesler(at)gmail(dot)com | http://flesler.blogspot.com
 * Dual licensed under MIT and GPL.
 * Date: 5/25/2009
 * @author Ariel Flesler
 * @version 1.4.2
 *
 * http://flesler.blogspot.com/2007/10/jqueryscrollto.html
 */
;(function(d){var k=d.scrollTo=function(a,i,e){d(window).scrollTo(a,i,e)};k.defaults={axis:'xy',duration:parseFloat(d.fn.jquery)>=1.3?0:1};k.window=function(a){return d(window)._scrollable()};d.fn._scrollable=function(){return this.map(function(){var a=this,i=!a.nodeName||d.inArray(a.nodeName.toLowerCase(),['iframe','#document','html','body'])!=-1;if(!i)return a;var e=(a.contentWindow||a).document||a.ownerDocument||a;return /safari/.test(navigator.userAgent.toLowerCase())||e.compatMode=='BackCompat'?e.body:e.documentElement})};d.fn.scrollTo=function(n,j,b){if(typeof j=='object'){b=j;j=0}if(typeof b=='function')b={onAfter:b};if(n=='max')n=9e9;b=d.extend({},k.defaults,b);j=j||b.speed||b.duration;b.queue=b.queue&&b.axis.length>1;if(b.queue)j/=2;b.offset=p(b.offset);b.over=p(b.over);return this._scrollable().each(function(){var q=this,r=d(q),f=n,s,g={},u=r.is('html,body');switch(typeof f){case'number':case'string':if(/^([+-]=)?\d+(\.\d+)?(px|%)?$/.test(f)){f=p(f);break}f=d(f,this);case'object':if(f.is||f.style)s=(f=d(f)).offset()}d.each(b.axis.split(''),function(a,i){var e=i=='x'?'Left':'Top',h=e.toLowerCase(),c='scroll'+e,l=q[c],m=k.max(q,i);if(s){g[c]=s[h]+(u?0:l-r.offset()[h]);if(b.margin){g[c]-=parseInt(f.css('margin'+e))||0;g[c]-=parseInt(f.css('border'+e+'Width'))||0}g[c]+=b.offset[h]||0;if(b.over[h])g[c]+=f[i=='x'?'width':'height']()*b.over[h]}else{var o=f[h];g[c]=o.slice&&o.slice(-1)=='%'?parseFloat(o)/100*m:o}if(/^\d+$/.test(g[c]))g[c]=g[c]<=0?0:Math.min(g[c],m);if(!a&&b.queue){if(l!=g[c])t(b.onAfterFirst);delete g[c]}});t(b.onAfter);function t(a){r.animate(g,j,b.easing,a&&function(){a.call(this,n,b)})}}).end()};k.max=function(a,i){var e=i=='x'?'Width':'Height',h='scroll'+e;if(!d(a).is('html,body'))return a[h]-d(a)[e.toLowerCase()]();var c='client'+e,l=a.ownerDocument.documentElement,m=a.ownerDocument.body;return Math.max(l[h],m[h])-Math.min(l[c],m[c])};function p(a){return typeof a=='object'?a:{top:a,left:a}}})(jQuery);

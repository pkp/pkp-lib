/*
 * jQuery UI Throbber 0.1
 *
 * Copyright (c) 2009 Jeremy Lea <reg@openpave.org>
 * Dual licensed under the MIT and GPL licenses.
 *
 * http://docs.jquery.com/Licensing
 *
 * Based loosely on the Raphael spinner demo.
 */
(function($){

// Set up IE for VML if we have not done so already...
if ($.browser.msie) {
	// IE6 background flicker fix
	try	{
		document.execCommand("BackgroundImageCache", false, true);
	} catch (e) {}

	if (!document.namespaces["v"]) {
		$("head").prepend("<xml:namespace ns='urn:schemas-microsoft-com:vml' prefix='v' />");
		$("head").prepend("<?import namespace='v' implementation='#default#VML' ?>");
	}
}

$.widget("ui.throbber", {
	_init: function() {

		var o = this.options, e = this.element, a, i, w, tmp;

		if ($.browser.msie) {
			this.element[0].insertAdjacentHTML("afterBegin",
				"<v:group></v:group>");
			this._wrapper = this.element[0].firstChild;
			this._wrapper.addBehavior("#default#VML");
			this._wrapper.coordorigin = "-100 -100";
			this._wrapper.coordsize = "200 200";
			this._wrapper.style.display = "inline-block";
			this._wrapper.style.position = "absolute";
			w = Math.min(e.innerWidth(),e.innerHeight());
			this._wrapper.style.left = (e.innerWidth()-w)/2;
			this._wrapper.style.top = (e.innerHeight()-w)/2;
			this._wrapper.style.width = w;
			this._wrapper.style.height = w;
			this._wrapper.insertAdjacentHTML("afterBegin",
				"<v:roundrect arcsize='"+o.bgcorner/200+"'></v:roundrect>");
			tmp = this._wrapper.childNodes[0];
			tmp.addBehavior("#default#VML");
			tmp.fillcolor = o.bgcolor;
			tmp.stroked = "false";
			tmp.style.top = "-100";
			tmp.style.left = "-100";
			tmp.style.width = "200";
			tmp.style.height = "200";
			tmp.insertAdjacentHTML("afterBegin","<v:fill></v:fill>");
			tmp.firstChild.addBehavior("#default#VML");
			tmp.firstChild.opacity = o.bgopacity;
			this._wrapper.insertAdjacentHTML("beforeEnd",
				"<v:group></v:group>");
			tmp = this._wrapper.childNodes[1];
			tmp.addBehavior("#default#VML");
			tmp.style.top = "-100";
			tmp.style.left = "-100";
			tmp.style.width = "200";
			tmp.style.height = "200";
			tmp.style.rotation = "0";
			for (i = 0; i < o.segments; ++i) {
				a = -i*360/o.segments;
				this._wrapper.childNodes[1].insertAdjacentHTML("beforeEnd",
					"<v:shape></v:shape>");
				tmp = this._wrapper.childNodes[1].childNodes[i];
				tmp.addBehavior("#default#VML");
				tmp.stroked = "false";
				tmp.fillcolor = o.fgcolor;
				tmp.style.top = "-100";
				tmp.style.left = "-100";
				tmp.style.width = "200";
				tmp.style.height = "200";
				tmp.style.rotation = a;
				tmp.path = o.path(i,o.segments)
					.replace(/[mlcz]/g, function(s) {
						switch (s) {
						case "m": return "t";
						case "l": return "r";
						case "c": return "v";
						case "z": return "x";
						}
					}) + " e";
				tmp.insertAdjacentHTML("afterBegin","<v:fill></v:fill>");
				tmp.firstChild.addBehavior("#default#VML");
				tmp.firstChild.opacity = o.opacity(i,o.segments);
			}
		} else {
			var NS = "http://www.w3.org/2000/svg";
			this._wrapper = this.element[0].insertBefore(
				document.createElementNS(NS,"svg"),this.element[0].firstChild);
			tmp = this._wrapper;
			tmp.setAttribute("viewBox","-100 -100 200 200");
			tmp.style.position = "absolute";
			tmp.style.width = "100%";
			tmp.style.height = "100%";
			tmp = this._wrapper.appendChild(
				document.createElementNS(NS,"rect"));
			tmp.setAttribute("x","-100px");
			tmp.setAttribute("y","-100px");
			tmp.setAttribute("width","200px");
			tmp.setAttribute("height","200px");
			tmp.setAttribute("rx",o.bgcorner);
			tmp.setAttribute("fill",o.bgcolor);
			tmp.setAttribute("fill-opacity",o.bgopacity);
			tmp = this._wrapper.appendChild(document.createElementNS(NS,"g"));
			tmp.setAttribute("fill",o.fgcolor);
			for (i = 0; i < o.segments; ++i) {
				a = -i*360/o.segments;
				tmp = this._wrapper.childNodes[1]
					.appendChild(document.createElementNS(NS,"path"));
				tmp.setAttribute("d",o.path(i,o.segments));
				tmp.setAttribute("fill-opacity",o.opacity(i,o.segments));
				tmp.setAttribute("transform","rotate("+a+")");
			}
		}
	},
	destroy: function() {
		this._wrapper.remove();

		$.widget.prototype.destroy.apply(this, arguments);
	},
	reset: function() {
		this._pos = -1;
		this._update();
	},

	_timer: false,
	_setData: function(key, value) {
		var self = this, o = this.options;

		this.options[key] = value;
		if (key == "disabled") {
			if (!value) {
				if (this._timer) {
					this._timer = clearInterval(this._timer);
				}
				this._timer = setInterval(function() {
					self._update();
				}, 1000/this.options.segments/this.options.speed);
				if (o.show) {
					o.show.call(this.element);
				}
			} else {
				if (!o.hide) {
					o.hide = function(callback) { callback(); };
				}
				o.hide.call(this.element, function() {
					if (self._timer) {
						self._timer = clearInterval(self._timer);
					}
				});
			}
		}
	},
	_pos: 0,
	_update: function() {
		var o = this.options;
		this._pos = (this._pos+1)%o.segments;
		var a = this._pos*360/o.segments;
		if ($.browser.msie) {
			this._wrapper.childNodes[1].disabled = true;
			this._wrapper.childNodes[1].rotation = a;
			this._wrapper.childNodes[0].rotation = 0; // rendering bug in 6&7
			this._wrapper.childNodes[1].disabled = false;
		} else {
			this._wrapper.childNodes[1]
				.setAttribute("transform","rotate("+a+")");
		}
	}
});
$.ui.throbber.defaults = {
	bgcolor: "#F00",
	bgopacity: 0.1,
	bgcorner: 10,
	fgcolor: "#000",
	segments: 12,
	path: function(i, segments) {
		return "M 40,8 c -4,0 -8,-4 -8,-8 c 0,-4 4,-8 8,-8 " +
			"l "+(30-i)+",0 c 4,0 8,4 8,8 c 0,4 -4,8 -8,8 " +
			"l -"+(30-i)+",0 z";
	},
	opacity: function(i, segments) {
		return Math.cos(Math.PI/2*(i%segments)/segments);
	},
	speed: 1
};

})(jQuery);
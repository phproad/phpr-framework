//
// Phpr request
//

(function($) {

	window.PHPR = { };

	$.phpr = window.PHPR;

	$.fn.phpr = function() {

		var self = this;

		var o = {};

		o.form = function() {
			return $.phpr.form(self);
		};

		o.validate = function() {
			return $.phpr.validate(self);
		};

		o.post = function(handler, options) {
			return $.phpr.post(handler, options).setFormElement(self);
		}
		
		o.indicator = function(options) {
			options = $.extend(true, options, { element: self });
			return $.phpr.indicator(options);
		}

		return o;
	};

	/**
	 * Returns the parent DOM element of the current element.
	 * @type Function
	 * @return none
	 */
	$.fn.getForm = function() {
		return $(this).closest('form');
	};

	/**
	 * Sends a POST request.
	 * @type Function
	 * @param Object options Options to customize the request.
	 * @return Boolean
	 */
	$.fn.sendRequest = $.fn.sendPhpr = function(handler, options) {
		return $(this).phpr().post(handler, options).send();
	};

})(jQuery);

//
// URL functions
// 

function root_url(url) {
	if (typeof application_root_dir === 'undefined' || !application_root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return application_root_dir + url;
}

function phpr_url(url) {
	if (typeof phpr_root_dir === 'undefined' || !phpr_root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return phpr_root_dir + url;	
}

function var_dump(obj, use_alert) {
	var out = '';
	for (var i in obj) {
		out += i + ": " + obj[i] + "\n";
	}

	if (use_alert)
		alert(out);
	else 
		jQuery('<pre />').html(out).appendTo(jQuery('body'));
	
};

//
// Lock Manager
// 

LockManager = function() {

	this.locks = {};
	this.set = function(name) {
		this.locks[name] = true;
	};
	this.get = function(name) {
		return (this.locks[name]);
	};
	this.remove = function(name) {
		this.locks[name] = null;
	}
	
};

lockManager = new LockManager();


//
// Cookies
// 

jQuery.cookie = function(name, value, options) {
	if (typeof value != 'undefined') { // name and value given, set cookie
		options = options || {};
		if (value === null) {
			value = '';
			options.expires = -1;
		}
		var expires = '';
		if (options.expires && (typeof options.expires == 'number' || options.expires.toUTCString)) {
			var date;
			if (typeof options.expires == 'number') {
				date = new Date();
				date.setTime(date.getTime() + (options.expires * 24 * 60 * 60 * 1000));
			} else {
				date = options.expires;
			}
			expires = '; expires=' + date.toUTCString(); // use expires attribute, max-age is not supported by IE
		}
		// CAUTION: Needed to parenthesize options.path and options.domain
		// in the following expressions, otherwise they evaluate to undefined
		// in the packed version for some reason...
		var path = options.path ? '; path=' + (options.path) : '';
		var domain = options.domain ? '; domain=' + (options.domain) : '';
		var secure = options.secure ? '; secure' : '';
		document.cookie = [name, '=', encodeURIComponent(value), expires, path, domain, secure].join('');
	} else { // only name given, get cookie
		var cookieValue = null;
		if (document.cookie && document.cookie != '') {
			var cookies = document.cookie.split(';');
			for (var i = 0; i < cookies.length; i++) {
				var cookie = jQuery.trim(cookies[i]);
				// Does this cookie string begin with the name we want?
				if (cookie.substring(0, name.length + 1) == (name + '=')) {
					cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
					break;
				}
			}
		}
		return cookieValue;
	}
};

//
// Hot key bindings
//

(function($) {
	$.fn.bindkey = function(sBind, oFunction) {
		if (this.length == 0)
			return;
		
		if (typeof sBind != "string")
			return;

		var _sBind = sBind.toLowerCase();
		var _oCallback = oFunction;
		var _oPressed = {shift: false, ctrl: false, alt: false};
		var _oWaited = {shift: false, ctrl: false, alt: false, specific: -1};
		var _oKeys = {'esc':27, 'tab':9, 'space':32, 'return':13, 'enter':13, 'backspace':8, 'scroll':145, 'capslock':20, 'numlock':144, 'pause':19,
					'break':19, 'insert':45, 'home':36, 'delete':46, 'suppr':46, 'end':35, 'pageup':33, 'pagedown':34, 'left':37, 'up':38, 'right':39, 'down':40,
					'f1':112, 'f2':113, 'f3':114, 'f4':115, 'f5':116, 'f6':117, 'f7':118, 'f8':119, 'f9':120, 'f10':121, 'f11':122, 'f12':123}

		function init () {
			aKeys = _sBind.split ('+');
			iKeysCount = aKeys.length;
			for (i = 0; i < iKeysCount; i++) {
				switch (aKeys[i]) {
					case 'shift':
						_oWaited.shift = true;
						break;
					case 'ctrl':
						_oWaited.ctrl = true;
						break;
					case 'alt':
						_oWaited.alt = true;
						break;
				}
			}
			_oWaited.specific = _oKeys[aKeys[aKeys.length-1]];

			if (typeof (_oWaited.specific) == 'undefined')
				_oWaited.specific = String.charCodeAt (aKeys[aKeys.length-1]);
		}

		this.keydown (function (oEvent) {
			_oPressed.shift = oEvent.originalEvent.shiftKey;
			_oPressed.ctrl = oEvent.originalEvent.ctrlKey;
			_oPressed.alt = oEvent.originalEvent.altKey;

			if (oEvent.which == _oWaited.specific
					&& _oPressed.shift == _oWaited.shift
					&& _oPressed.ctrl == _oWaited.ctrl
					&& _oPressed.alt == _oWaited.alt) {

				_oCallback (this);
				_oPressed.shift = false;
				_oPressed.ctrl = false;
				_oPressed.alt = false;
			}
		});

		this.keyup (function (oEvent) {
			_oPressed.shift = oEvent.originalEvent.shiftKey;
			_oPressed.ctrl = oEvent.originalEvent.ctrlKey;
			_oPressed.alt = oEvent.originalEvent.altKey;
		});

		init ();

		return $(this);
	}
})(jQuery);

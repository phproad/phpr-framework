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
		
		o.indicator = function() {
			return $.phpr.indicator();
		}

		return o;
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
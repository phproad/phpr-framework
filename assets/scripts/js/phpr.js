//
// Phpr request
//
(function($) {

	window.PHPR = { };

	PHPR.doSomething = function(element) {
	 
	};

	$.phpr = PHPR;

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
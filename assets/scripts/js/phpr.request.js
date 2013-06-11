/**
 * PHPR Request
 * 
 * This object is used internally by PHPR Post. It should not
 * be used unless you are pro.
 * 
 * Usage:
 * 
 * $.phpr.request('http://mysite.com', 'user:on_login', { data: {username:'', password:''}  }).send();
 */

(function($) {

	PHPR.requestDefaults = {
		data: {},
		update: {},
		lock: true,
		cmsMode: true,
		evalScripts: true,
		execScriptsOnFail: true
	}

	PHPR.request = function(url, handler, options) {
		var o = {},
			_deferred = $.Deferred(),
			_url = url,
			_handler = handler,
			_options = options,
			_locked = false;
		
		o.postObj = null;

		o.text = '';
		o.html = '';
		o.javascript = '';
		o.errorMessage = '';
		o.status = '';

		o.setDefaultOptions = function(defaultOptions) {
			PHPR.requestDefaults = $.extend(true, PHPR.requestDefaults, defaultOptions);
		}

		o.buildOptions = function() {
			var options = $.extend(true, PHPR.requestDefaults, _options);
			return _options = options;
		}

		o.send = function() {
			if (_locked)
				return;

			var options = o.buildOptions(),
				ajax = _get_ajax_object();

			// On Complete
			ajax.always(function(){
				_locked = false;
				o.onComplete();
			});

			// On Success
			ajax.done(function(data) {
				o.parseResponse(data);

				if (o.isSuccess()) {
					o.onSuccess();
				} else {
					o.onFailure('error', o.html.replace('@AJAX-ERROR@', ''));
				}
			});

			// On Failure
			ajax.fail(function(data, status, message) {
				o.parseResponse(data);
				o.onFailure(status, message);
			});

			if (options.lock)
				_locked = true;

			return false;
		}

		o.onComplete = function() {
			$(PHPR).trigger('complete.request', [o]);
		}

		o.onSuccess = function() {
			if (_options.evalScripts) 
				eval(o.javascript);
			
			$(PHPR).trigger('success.request', [o]);

			o.status = 'success';

			if (o.processAssetIncludes() === false)
				_deferred.resolve(o);
		}

		o.onFailure = function(status, message) {
			if (_options.execScriptsOnFail)
				eval(o.javascript);

			o.errorMessage = message;
			o.status = status;
			
			$(PHPR).trigger('error.request', [o]);

			_deferred.reject(o);
		}

		o.isSuccess = function() {
			return o.text.search('@AJAX-ERROR@') == -1;
		}

		o.parseResponse = function(data) {
			o.text = data;

			if (typeof(o.text) !== 'string')
				o.text = '';

			o.html = _strip_scripts(o.text, function(javascript){
				o.javascript = javascript;
			});
		}

		//
		// Asset management
		// 

		o.processAssetIncludes = function() {
			var phpr_css_list = phpr_js_list = [];

			_strip_scripts(o.text, function(javascript){}, function(script){
				if (/phpr_resource_list_marker/.test(script)) {
					if (script.length > 0)
						eval(script);
				}
			});	

			if (phpr_css_list.length == 0 && phpr_js_list.length == 0)
				return false;
			else {
				_load_assets(phpr_js_list, phpr_css_list, function(){ _deferred.resolve(o); });	
				return true;
			}
		}

		var _load_assets = function(javascript, css, callback) {
			
			var js_list = javascript.filter(function(item){
				return $('head script[src="'+item+'"]').length == 0;
			});

			var css_list = css.filter(function(item){
				return $('head link[href="'+item+'"]').length == 0;
			});

			var css_counter = 0,
				js_loaded = false;
			
			if (js_list.length === 0 && css_list.length === 0) {
				callback();
				return;
			}
			
			_load_javascript_in_sequence(js_list, function(){
				js_loaded = true;
				if (css_counter == css_list.length)
					callback();
			});

			css_list.each(function(source){
				_load_css_file(source, function(){
						css_counter++;
						if (js_loaded && css_counter == css_list.length)
							callback();
					}
				)
			});
		}

		var _load_css_file = function(source, callback) {
			var cssFile = document.createElement('link');
			cssFile.setAttribute('rel', 'stylesheet');
			cssFile.setAttribute('type', 'text/css');
			cssFile.setAttribute('href', source);

			if (typeof cssFile != 'undefined') {
				document.getElementsByTagName('head')[0].appendChild(cssFile);
			}

			$.getScript(source, callback);
			return cssFile;
		}

		var _load_javascript_in_sequence = function(sources, callback) {
			var source = sources.shift();

			var jsFile = document.createElement('script')
			jsFile.setAttribute('type', 'text/javascript')
			jsFile.setAttribute('src', source)
			
			if (typeof jsFile != 'undefined') {
				document.getElementsByTagName('head')[0].appendChild(jsFile);
			}

			$.getScript(source, function() {
				if (sources.length > 0)
					_load_javascript_in_sequence(sources, callback);
				else
					callback();
			});
		}		
	
		//
		// Internals
		//

		var _get_ajax_object = function() {
			
			var _head_handler = (_options.cmsMode) ? 'on_handle_request' : _handler;

			var ajaxObj = {
				url: _url,
				type: 'POST',
				dataType: 'html', // Always force plaintext
				beforeSend: function(xhr) {
					xhr.setRequestHeader('PHPR-REMOTE-EVENT', '1');
					xhr.setRequestHeader('PHPR-POSTBACK', '1');
					xhr.setRequestHeader('PHPR-EVENT-HANDLER', 'ev{' + _head_handler + '}');
				},
				data: {}
			};

			if (_options.cmsMode) {
				ajaxObj.data.cms_handler_name = _handler;
			}

			ajaxObj.data = $.extend(true, ajaxObj.data, _options.data);

			if (_options.update && $.isArray(_options.update))
				ajaxObj.data.cms_update_elements = _options.update;

			return $.ajax(ajaxObj);
		}

		var _strip_scripts = function(data, option, script_callback) {
			var scripts = '';

			var text = data.replace(/<script[^>]*>([^\b]*?)<\/script>/gi, function() {
				scripts += arguments[1] + '\n';
				
				script_callback && script_callback(arguments[1]);

				return '';
			});

			if (option === true)
				eval(scripts);
			else if (typeof(option) == 'function')
				option(scripts, text);
			
			return text;
		}

		// Promote the request object with a promise
		return _deferred.promise(o);
	}


})(jQuery);

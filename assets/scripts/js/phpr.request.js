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
		cmsMode: false
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
		o.error = '';
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
			});

			// On Success
			ajax.done(function(data) {
				o.parseResponse(data);

				if (o.isSuccess()) {
					_deferred.resolve(o);
				} else {
					o.error = o.html.replace('@AJAX-ERROR@', '');
					o.status = 'error';
					_deferred.reject(o);
				}
			});

			// On Failure
			ajax.fail(function(data, status, message) {
				o.parseResponse(data);
				o.error = message;
				o.status = status;
				_deferred.reject(o);
			});

			if (options.lock)
				_locked = true;

			return false;
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

		var _strip_scripts = function(data, option) {
			var scripts = '';

			var text = data.replace(/<script[^>]*>([^\b]*?)<\/script>/gi, function() {
				scripts += arguments[1] + '\n';
				
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

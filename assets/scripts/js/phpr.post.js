/* Placeholder for phpr().post() */

/*
Proposed syntax 1 (winner):

$(‘#form’).phpr().post(‘blog:on_comment’, { });

$(‘.serialize_everything_inside_me’).phpr().post(‘blog:on_comment’, { });

$.phpr().post(‘blog:on_comment’, { });
$.phpr({override_phpr_options}).post(‘blog:on_comment’).send({override_post_options});
*/


/*
Parameters: {

action: ‘blog:on:comment’,

success: function,

data: { },

fail: function,

done: function,

complete: function,

prepare: function

}

Methods: (# functions provided by $.deferred)

	action(name:String)

	form(selector:String) - (NREQ - passed from framework)

	prepare(callback:Function) - “onBeforePost” equivalent

	update(selector:*, partialName:String) - can be object or called many for multiples

	data(field:*, value:String) - can be object for called many multiples

	getData() - returns serialized and supplied data values

	getForm() - returns form object

	queue(value:Boolean) - allow this request to queue if locked

	#done(callback:Function) - Gets a jQuery promise, passing callback to success

	#fail(callback:Function)

	#always(callback:Function) - Fires if success or fail

	complete(callback:Function) - Fires after partials update

	loadingIndicator(param:Bool|Obj)

	post(params:Object = {})

	put(params:Object = {})

	delete(params:Object = {}) Note: conflict < es5.

	animation(callback:Function<element:jQuery, html:String>) - Allow custom handler to show the new content returned via AJAX

	lock(value:Boolean) - Blocks the UI from multiple requests. Sets busy=true.

	promise - Returns a jQuery promise extended with PHPR

*/

(function($) {

	PHPR.post = function(handler, options) {

		var o = {},
			_deferred = $.Deferred(),
			_handler = handler,
			_options = options || {},
			_data = {},
			_update = {},
			_form = null;

		o.requestObj = null;

		//
		// Services
		// 

		o.success = function(func) {
			_deferred.done(func);
			return this;
		}

		o.error = function(func) {
			_deferred.fail(func);
			return this;
		}

		o.complete = function(func) {
			_deferred.always(func);
			return this;
		}

		o.afterUpdate = function(func) {
			_options.afterUpdate = func;
			return this;
		}

		o.handler = o.action = function(value) {
			_handler = handler;
			return this;
		}

		o.confirm = function(value) {
			_options.confirm = value;
			return this;
		}

		o.alert = function(value) {
			_options.alert = value;
			return this;
		}

		o.prepare = function(value) {
			_options.prepare = value;
			return this;
		}

		o.update = function(element, partial) {
			if (partial)
				_update[element] = partial;
			else
				_update = element;
			return this;
		}

		o.data = function(field, value) {
			if (typeof field == "object")
				_data = field;
			else if (typeof value != "undefined")
				_data[field] = value;
			return this;
		}

		o.queue = function(value) {
			_options.lock = !value;
			return this;
		}

		o.loadingIndicator = function(data) {
			if (typeof data == "boolean")
				_options.loadingIndicator = { show: data };
			else
				_options.loadingIndicator = $.extend(true, _options.loadingIndicator, data);
			return this;
		}

		//
		// Options
		// 

		o.getDefaultOptions = function() {
			return {
				action: 'core:on_null',
				data: {},
				update: {},
				done: null,
				fail: null,
				afterUpdate: null,
				always: null,
				prepare: null,
				selectorMode: true,
				lock: true,
				alert: null,
				confirm: null,
				evalScripts: true,
				loadingIndicator: { show: true },
				animation: function(element, html) { element.html(html); }
			};
		}

		o.setOption = function(option, value) {
			_options[option] = value;
			return this;
		}

		o.getOption = function(option) {
			return _options[option];
		}

		o.buildOptions = function() {
			var options = $.extend(true, o.getDefaultOptions(), _options);

			if (_handler)
				options.action = _handler;

			// Build post back data
			options.data = (_form) 
				? $.extend(true, _serialize_params(_form), _data)
				: _data;

			// Build partials to update
			options.update = $.extend(true, options.update, _update);

			return _options = options;
		}

		//
		// Form Element
		// 

		o.setFormElement = function(form) {
			form = (!form) ? jQuery('<form></form>') : form;
			form = (form instanceof jQuery) ? form : jQuery(form);
			form = (form.is('form')) ? form : form.closest('form');
			form = (form.attr('id')) ? jQuery('form#'+form.attr('id')) : form.attr('id', 'form_element');
			_form = form;
			return this;
		}

		o.getFormElement = function() {
			return _form;
		}

		o.getFormUrl = function() {
			return (_form) ? _form.attr('action') : window.location.href;
		}

		//
		// Send request
		// 

		o.send = function() {
			var options = o.buildOptions();

			options.prepare && options.prepare();

			if (options.alert)
				alert(options.alert);
			
			if (options.confirm && !confirm(options.confirm))
				return;

			// Show loading indicator
			if (PHPR.indicator && options.loadingIndicator.show)
				PHPR.indicator.showIndicator(options.loadingIndicator);

			// Prepare the request
			o.requestObj = PHPR.request(o.getFormUrl(), _handler, options);

			// On Complete
			o.requestObj.always(function(requestObj){
				
				// Hide loading indicator
				if (PHPR.indicator && options.loadingIndicator.show)
					PHPR.indicator.hideIndicator();

				options.always && options.always(requestObj);
			});

			// On Success
			o.requestObj.done(function(requestObj){
				o.updatePartials();

				if (options.evalScripts) 
					eval(requestObj.javascript);

				options.done && options.done(requestObj);
			});

			// On Failure
			o.requestObj.fail(function(requestObj){
				if (options.fail && !options.fail(requestObj))
					return;

				if (requestObj.error)
					o.popupError(requestObj);
			});

			// Execute the request
			o.requestObj.send();
			return false;
		}

		//
		// Error Popup
		// 

		o.popupError = function(requestObj) {
			alert(requestObj.error);
		}

		//
		// Update partials
		// 

		o.updatePartials = function() {
			var oHtml = o.requestObj.html,
				pattern = />>[^<>]*<</g,
				patches = oHtml.match(pattern) || [],
				updateElements = [];

			for (var i = 0, l = patches.length; i < l; ++i) {
				var index = oHtml.indexOf(patches[i]) + patches[i].length;

				var html = (i < patches.length-1) 
					? oHtml.slice(index, oHtml.indexOf(patches[i+1])) 
					: oHtml.slice(index);

				var id = patches[i].slice(2, patches[i].length-2);

				if (id) {
					var element;
				
					if (_options.selectorMode)
						element = $(id);
					else
						element = $('#' + id);
						
					if (!_options.animation(element, html))
						element.html(html);
						
					updateElements.push(id);
				}
			}

			// If update element is a string, set update element to self.text
			_options.update && typeof(_options.update) === 'string' && $('#' + _options.update).html(self.text);

			$.each(updateElements, function(k, v) {
				$(window).trigger('onAfterAjaxUpdate', v);
			});

			_options.afterUpdate && _options.afterUpdate();
		}

		//
		// Internals
		// 

		var _serialize_params = function(element) {
			var params = {};

			$.each(element.serializeArray(), function(index, value) {
				if (value.name.substr(value.name.length - 2, 2) === '[]') {
					var name = value.name.substr(0, value.name.length - 2);
					
					if (!params[name]) {
						params[name] = [];
					}
					
					params[name].push(value.value);
				} 
				else {
					params[value.name] = value.value;
				}
			});

			return params;
		}

		// Extend the post object with DOM
		o = $.extend(true, o, PHPR.post || {});

		// Promote the post object with a promise
		return _deferred.promise(o);
	}

})(jQuery);

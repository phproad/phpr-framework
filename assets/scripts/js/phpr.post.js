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
			_options = options,
			_data = {},
			_form = null;

		o.requestObj = null;

		// 
		// Static
		// 

		o.handleError = function(message) {
			alert(message);
		}

		//
		// Options
		// 

		o.setOption = function(option, value) {
			_options[option] = value;
			return this;
		}

		o.getOption = function(option) {
			return _options[option];
		}

		o.getDefaultOptions = function() {
			return {
				action: 'core:on_null',
				data: {},
				update: {},
				done: null,
				fail: null,
				always: null,
				prepare: null,
				selectorMode: true,
				lock: true,
				alert: null,
				confirm: null,
				animation: function(element, html) {
					element.html(html);
				}
			};
		}	

		o.getOptions = function() {
			var options = $.extend(true, o.getDefaultOptions(), _options);

			if (_handler)
				options.action = _handler;

			options.data = (_form) 
				? $.extend(true, _serialize_params(_form), _data)
				: _data;

			return options;
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
			var options = o.getOptions();

			if (options.prepare && !context.prepare())
				return;

			if (options.alert)
				return alert(options.alert);
			
			if (options.confirm && !confirm(options.confirm))
				return;

			// @todo Show loading indicator

			// Prepare and execute the request
			o.requestObj = new PHPR.request(o.getFormUrl(), _handler, options);

			// On Complete
			o.requestObj.always(function(requestObj){
				
				// @todo Hide loading indicator
				 
				options.always && options.always(requestObj);
			});

			// On Success
			o.requestObj.done(function(requestObj){
				o.updatePartials();
				options.done && options.done(requestObj);
			});

			// On Failure
			o.requestObj.fail(function(requestObj){
				if (options.fail && !options.fail(requestObj))
					return;

				if (requestObj.error)
					o.handleError(requestObj.error);
			});
		}

		//
		// Update partials
		// 

		o.updatePartials = function() {
			var options = o.getOptions(),
				oHtml = o.requestObj.html,
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
				
					if (options.selectorMode)
						element = $(id);
					else
						element = $('#' + id);
						
					if (!options.animation(element, html))
						element.html(html);
						
					updateElements.push(id);
				}
			}

			// If update element is a string, set update element to self.text
			options.update && typeof(options.update) === 'string' && $('#' + options.update).html(self.text);

			$.each(updateElements, function(k, v) {
				$(window).trigger('onAfterAjaxUpdate', v);
			});
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

		// Promote the post object with a promise
		return _deferred.promise(o);
	}

})(jQuery);

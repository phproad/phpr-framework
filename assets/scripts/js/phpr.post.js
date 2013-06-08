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
				prepare: null
			};
		}	

		o.getOptions = function() {
			var options = $.extend(true, o.getDefaultOptions(), _options);

			if (_handler)
				options.action = _handler;

			options.data = $.extend(true, _serialize_params(_form), _data);

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
			return _form.attr('action');
		}

		//
		// Send request
		// 

		o.send = function() {
			var request = new PHPR.request(o.getFormUrl(), _handler, o.getOptions());
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

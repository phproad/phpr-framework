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
			deferred = $.Deferred(),
			_form = null,
			_options = $.extend(true, {
				action: 'core:on_null',
				data: {},
				success: null,
				fail: null,
				complete: null,
				prepare: null
			}, options);

		if (handler)
			_options.action = handler;

		// Options

		o.setOption = function(option, value) {
			_options[option] = value;
			return this;
		}

		o.getOption = function(option) {
			return _options[option];
		}

		// Form

		o.setForm = function(element) {
			_form = element;
			return this;
		}

		o.getForm = function() {
			return _form;
		}

		// Service

		o.send = function() {			
			deferred.resolve();
		}

		// Helpers

		o.serializeParams = function(element) {
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
		return deferred.promise(o);
	}

})(jQuery);

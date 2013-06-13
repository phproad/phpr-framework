/**
 * PHPR Post
 * 
 * This object is used for communicating with AJAX handlers in PHPR.
 * 
 * Usage:
 * 
 * $.phpr.post('user:on_login', { data: {username:'', password:''}  });
 * 
 * $.phpr.post('user:on_login').send({ data: {username:'', password:''}  });
 * 
 * $.phpr.post('user:on_login').data({username:'', password:''}).send();
 * 
 * 
 * Usage with form:
 * 
 * $('#myform').phpr().post('user:on_login', { data: {username:'', password:''}  });
 * 
 * $.phpr.post('user:on_login').form('#myform').data({username:'', password:''}).send();
 * 
 * 
 * Usage with PHPR.validate:
 * 
 * $('#myform').phpr().form()
 *   .defineFields(function(){ 
 *     this.defineField('username').required('What is your username?');
 *     this.defineField('password').required('You must enter a password!');
 *   })
 *   .validate()
 *   .post('user:on_signup', { success: function(){ alert('You are now logged in!'); }  }); 
 * 
 */

(function($) {

	PHPR.postDefaults = {
		action: 'core:on_null',
		data: {},
		update: {},
		success: null, // On Success
		error: null, // On Failure
		complete: null, // On Complete
		afterUpdate: null, // On After Update
		beforeSend: null, // On Before Send
		selectorMode: true,
		lock: true,
		alert: null,
		confirm: null,
		evalScripts: true,
		execScriptsOnFail: true,
		loadIndicator: { show: true },
		customIndicator: null,
		animation: function(element, html) { element.html(html); }
	};

	PHPR.post = function(handler, context) {

		var o = {},
			_deferred = $.Deferred(),
			_handler = handler,
			_context = context || {},
			_data = {},
			_update = {},
			_form = null,
			_events = { 
				beforeSend: [],
				afterUpdate: []
			};

		o.requestObj = null;
		o.indicatorObj = null;

		//
		// Services
		// 

		o.form = function(element) {
			o.setFormElement(element);
			return this;
		}

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

		o.handler = o.action = function(value) {
			_handler = handler;
			return this;
		}

		o.confirm = function(value) {
			_context.confirm = value;
			return this;
		}

		o.alert = function(value) {
			_context.alert = value;
			return this;
		}

		o.beforeSend = function(value) {
			if ($.isArray(_events.beforeSend))
				_events.beforeSend.push(value);
			else
				_events.beforeSend = value;

			return this;
		}

		o.afterUpdate = function(func) {
			if ($.isArray(_events.afterUpdate))
				_events.afterUpdate.push(value);
			else
				_events.afterUpdate = value;

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
			_context.lock = !value;
			return this;
		}

		o.loadIndicator = function(data) {
			if (typeof data == "boolean")
				_context.loadIndicator = { show: data };
			else
				_context.loadIndicator = $.extend(true, _context.loadIndicator, data);
			return this;
		}

		//
		// Options
		// 

		o.setDefaultOptions = function(defaultOptions) {
			PHPR.postDefaults = $.extend(true, PHPR.postDefaults, defaultOptions);
		}

		o.setOption = function(option, value) {
			_context[option] = value;
			return this;
		}

		o.getOption = function(option) {
			return _context[option];
		}

		o.buildOptions = function(context) {
			context = $.extend(true, {}, PHPR.postDefaults, _context, context);
			
			if (_handler)
				context.action = _handler;

			// Build partials to update
			if (typeof context.update == 'object' && typeof _update == 'object')
				context.update = $.extend(true, context.update, _update);
			else if (typeof _update == 'string')
				context.update = _update;

			return _context = context;
		}

		o.buildPostData = function(context) {
			if (context.data instanceof jQuery)
				context.data = o.serializeElement(context.data);

			context.data = $.extend(true, context.data, _data);

			if (_form)
				context.data = $.extend(true, context.data, o.serializeElement(_form));

			return _context = context;
		}

		//
		// Form Element
		// 

		o.setFormElement = function(form) {
			form = (!form) ? jQuery('<form></form>') : form;
			form = (form instanceof jQuery) ? form : jQuery(form);
			form = (form.is('form')) ? form : form.closest('form');
			form = (form.attr('id')) ? form : form.attr('id', 'form-element');
			_form = form;
			return this;
		}

		o.getFormElement = function() {
			return _form;
		}

		o.getFormUrl = function() {
			return (_form) ? _form.attr('action') : location.pathname;
		}

		o.serializeElement = function(element) {
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

		//
		// Send request
		// 

		o.send = function(context) {
			context = o.buildOptions(context);

			if (context.beforeSend)
				_execute_event('beforeSend');

			context = o.buildPostData(context);
			
			if (context.alert)
				alert(context.alert);
			
			if (context.confirm && !o.popupConfirm(context))
				return;

			// Show loading indicator
			if (context.loadIndicator.show) {
				if (_form)
					context.loadIndicator.element = _form;

				o.indicatorObj = (context.customIndicator) 
					? context.customIndicator(context.loadIndicator) 
					: PHPR.indicator(context.loadIndicator);

				o.indicatorObj.show();
			}
			
			// Execute javascript after partials have loaded
			var tmpOptions = $.extend(true, {}, context, { evalScripts: false });

			// Prepare the request
			o.requestObj = new PHPR.request(o.getFormUrl(), context.action, tmpOptions);
			o.requestObj.postObj = o;

			// On Complete
			o.requestObj.always(o.onComplete);

			// On Success
			o.requestObj.done(o.onSuccess);

			// On Failure
			o.requestObj.fail(o.onFailure);

			// Execute the request
			o.requestObj.send();
			return false;
		}

		o.onComplete = function(requestObj) {
			// Hide loading indicator
			if (_context.loadIndicator.show && o.indicatorObj && (_context.customIndicator || _context.loadIndicator.hideOnSuccess)) {
				o.indicatorObj.hide();
			} 

			_context.complete && _context.complete(requestObj);

			$(PHPR).trigger('complete.post', [requestObj]);
		}

		o.onSuccess = function(requestObj) {
			o.updatePartials();

			_context.success && _context.success(requestObj);

			if (_context.evalScripts)
				$.globalEval(requestObj.javascript);

			$(PHPR).trigger('success.post', [requestObj]);
			$(window).trigger('onAfterAjaxUpdateGlobal');
		}

		o.onFailure = function(requestObj) {
			if (_context.error && !_context.error(requestObj))
				return;

			if (requestObj.errorMessage)
				o.popupError(requestObj);

			$(PHPR).trigger('error.post', [requestObj]);
		}

		//
		// Behaviors
		// 

		o.popupError = function(requestObj) {
			alert(requestObj.errorMessage);
		}

		o.popupConfirm = function(postObj) {
			return confirm(postObj.confirm);
		}

		//
		// Update partials
		// 

		o.updatePartials = function() {
			if (/window.location=/.test(o.requestObj.javascript))
				return;

			if ($.isArray(_context.update) || _context.update == 'multi')
				o.updatePartialsMulti();
			else
				o.updatePartialsSingle();

			if (_context.afterUpdate)
				_execute_event('afterUpdate');
		}

		o.updatePartialsSingle = function() {
			var element = null;
			if (_context.update instanceof jQuery)
				element = _context.update;
			else
				element = $(_context.update);

			if (element && element.length) {
				element.html(o.requestObj.html);
				$(window).trigger('onAfterAjaxUpdate', element);
			}
		}
		
		o.updatePartialsMulti = function() {
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
				
					if (_context.selectorMode)
						element = $(id);
					else
						element = $('#' + id);
						
					if (!_context.animation(element, html))
						element.html(html);
						
					updateElements.push(element);
				}
			}

			$.each(updateElements, function(k, v) {
				$(window).trigger('onAfterAjaxUpdate', v);
			});
		}

		//
		// Internals
		// 

		var _execute_event = function(eventName, params) {
			var eventObj;

			// Fire chained in events
			eventObj = _events[eventName];

			if ($.isArray(eventObj)) {
				$.each(eventObj, function(index, func){
					if (typeof func == 'function')
						func();
				});
			} 
			else if (typeof eventObj == 'function') {
				eventObj();
			}

			// Fire contextual event
			eventObj = _context[eventName];
			if (eventObj && typeof eventObj == 'function') {
				eventObj();
			}
		}

		// Extend the post object with DOM
		o = $.extend(true, o, PHPR.post);

		// (This creates too much unpredictability)
		// If the second parameter is present, then automatically fire the request
		// if (options)
		// 	return o.send();

		// Promote the post object with a promise
		return _deferred.promise(o);
	}

})(jQuery);

/**
 * PHPR Validate
 * 
 * Usage:
 * 
 *   Page.loginForm = $('#login-form').phpr().form();
 *   Page.loginForm.defineFields(function(){
 *   	this.defineField('login', 'Login Name').required('You must enter a login name');
 *   	this.defineField('password', 'Password').required();
 *   });
 *   
 *   if (Page.loginForm.valid())
 *   	doSomething();
 * 
 *   Page.loginForm.validate().action('on_action')
 *   	.success(function() { alert('Done!'); });
 *   	
 *   	
 * Deferring:
 *   
 *   Page.loginForm = $.phpr.form();
 *   Page.loginForm.defineField('name', 'Name').required('You must enter a name');
 *   
 *   (later)
 *   
 *   Page.loginForm.validate('#login-form').action('on_action')
 *   	.success(function() { alert('Done!'); });
 * 
 */

(function($) {

	PHPR.validateDefaults = {
		ignore:":not(:visible):not(:disabled)",
		onkeyup: false,
		submitHandler: function(form) {
			form.submit();
		},
		errorClass: 'help-block',
		controlErrorClass: "error"
	};

	PHPR.validate = function(element, options) {
		var o = {},
			_options = {},
			_form = element,
			_on_success = null,
			_jv_object,
			_jv_defaults = PHPR.validateDefaults;

		if (element.length == 0)
			throw 'PHPR Validate: Unable to find form element';

		_options = $.extend(true, _jv_defaults, options);
		_jv_object = _form.validate(_options);

		o.valid = function() {
			return _jv_object.valid();
		}

		o.success = function(func) {
			_jv_object.settings.submitHandler = _on_success = func;
			return this;
		}

		o.action = function(handler) {
			var postObj = Ahoy.post(_form).action(handler);

			this.success(function() {
				postObj.send();
			});

			return postObj;
		}

		o.resetFormFields = function() {
			_jv_object.settings.rules = null;
			_jv_object.settings.messages = null;
			return this;
		}

		o.addFormFields = function(formObj) {
			_jv_object.settings.rules = $.extend(true, _jv_object.settings.rules, formObj.getFieldRules());
			_jv_object.settings.messages = $.extend(true, _jv_object.settings.messages, formObj.getFieldMessages());
			return this;
		}

		o.removeFormFields = function(formObj) {
			$.each(formObj.getFieldRules(), function(field, fieldObj){
				if (_jv_object.settings.rules[field])
					_jv_object.settings.rules[field] = null;
			});

			$.each(formObj.getFieldMessages(), function(field, fieldObj){
				if (_jv_object.settings.messages[field])
					_jv_object.settings.messages[field] = null;
			});
			return this;
		}

		return o;
	}

})(jQuery);

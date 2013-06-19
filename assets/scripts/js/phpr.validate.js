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

		_options = $.extend(true, {}, _jv_defaults, options);
		_jv_object = _form.validate(_options);

		o.setDefaultOptions = function(defaultOptions) {
			PHPR.validateDefaults = $.extend(true, PHPR.validateDefaults, defaultOptions);
		}

		o.valid = function() {
			return _jv_object.valid();
		}

		o.success = function(func) {
			_jv_object.settings.submitHandler = _on_success = func;
			return this;
		}

		o.post = o.action = function(handler, options) {
			var postObj = PHPR.post(handler, options).setFormElement(_form);

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

//
// jQuery.validate extensions: PHPR specific
//

jQuery.validator.addMethod("phprDate", function(value, element) { 
	return Date.parse(value, "dd/mm/yy");
}, "Please enter a valid date");

// Remote using AJAX engine
jQuery.validator.addMethod("phprRemote", function(value, element, param) {

	if (jQuery(element).hasClass('ignore_validate'))
		return true;

	if (this.optional(element))
		return "dependency-mismatch";

	// Defaults
	param = jQuery.extend(true, {
		action: null
	}, param);

	var previous = this.previousValue(element);
	if (!this.settings.messages[element.name] )
		this.settings.messages[element.name] = {};
	previous.originalMessage = this.settings.messages[element.name].remote;
	this.settings.messages[element.name].remote = previous.message;

	param = typeof param == "string" && {action:param} || param;

	if (this.pending[element.name]) {
		return "pending";
	}

	if (previous.old === value) {
		return previous.valid;
	}

	previous.old = value;
	var validator = this;
	this.startRequest(element);

	jQuery(element).phpr().post(param.action)
		.data('jquery_validate', true)
		.data(element.name, value)
		.success(function(requestObj) {
			validator.settings.messages[element.name].remote = previous.originalMessage;
			var valid = requestObj.html == "true";
			if (valid) {
				var submitted = validator.formSubmitted;
				validator.prepareElement(element);
				validator.formSubmitted = submitted;
				validator.successList.push(element);
				validator.showErrors();
			} else {
				var errors = {};
				var message = requestObj.html || validator.defaultMessage(element, "phprRemote");
				errors[element.name] = previous.message = $.isFunction(message) ? message(value) : message;
				validator.showErrors(errors);
			}
			previous.valid = valid;
			validator.stopRequest(element, valid);
		})
	.send();
	return "pending";
}, "Please fix this field.");


//
// jQuery.validate extensions: General goodes
// 

jQuery.validator.addMethod("phoneUS", function (phone_number, element) {
	phone_number = phone_number.replace(/\s+/g, "");
	return this.optional(element) || phone_number.length >= 5 && phone_number.match(/^(1-?)?(\([2-9]\d{2}\)|[2-9]\d{2})-?[2-9]\d{2}-?\d{4}$/);
}, "Please specify a valid phone number");

jQuery.validator.addMethod('phoneUK', function (phone_number, element) {
	phone_number = phone_number.replace(" ", "");
	return this.optional(element) || phone_number.length > 9 && (phone_number.match(/^(\(?(0|\+?44)[1-9]{1}\d{1,4}?\)?\s?\d{3,4}\s?\d{3,4})$/) || phone_number.match(/^((0|\+?44)7(5|6|7|8|9){1}\d{2}\s?\d{6})$/));
}, 'Please specify a valid phone number');

jQuery.validator.addMethod("phoneAU", function (phone_number, element) {
	phone_number = phone_number.split(' ').join('');
	return this.optional(element) || phone_number.length >= 5 && phone_number.match(/(^1300\d{6}$)|(^1800|1900|1902\d{6}$)|(^0[2|3|7|8]{1}[0-9]{8}$)|(^13\d{4}$)|(^04\d{2,3}\d{6}$)/);
}, "Please specify a valid phone number");

jQuery.validator.addMethod("phone", function (phone_number, element) {
	phone_number = phone_number.replace(/[\s()+-]|ext\.?/gi, "");
	return this.optional(element) || phone_number.length >= 5 && phone_number.match(/\d{6,}/);
}, "Please specify a valid phone number");

jQuery.validator.addMethod("postCode", function (post_code, element) {
	post_code = post_code.replace(/\s+/g, "");
	return this.optional(element) || post_code.length < 8 && post_code.length > 4 && post_code.match(/^[a-zA-Z]{1,2}[0-9]{1,3}([a-zA-Z]{1}[0-9]{1})*[a-zA-Z]{2}$/);
}, "Please specify a valid post/zip code");

jQuery.validator.addMethod("minWord", function (value, element, param) {
	var words = value.split(/\s/gi);
	var result = this.optional(element) || words.length >= param;
	/* Don't be too picky */
	if ($(element).attr("minword") == "true") return true; 
	if (!result) $(element).attr("minword", "true");
	return result;
}, "Please type more words");

jQuery.validator.addMethod("maxWord", function (value, element, param) {
	var words = value.split(/\s/gi);
	return this.optional(element) || words.length <= param;
}, "Please type less words");

jQuery.validator.addMethod("regExp", function (value, element, regexp) {
	var re = new RegExp(regexp);
	return this.optional(element) || value.match(re);
}, "Please check your input.");

jQuery.validator.addMethod("fullUrl", function(val, elem) {
	if (val.length == 0) { return true; }
	if(!/^(https?|ftp):\/\//i.test(val)) { val = 'http://'+val; $(elem).val(val);  }
	 return /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&amp;'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&amp;'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&amp;'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&amp;'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&amp;'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(val);
}, "Please enter a valid URL");

jQuery.validator.addMethod('required_multi', function(value, element, options) {
	numberRequired = options[0];
	selector = options[1];
	var numberFilled = $(selector, element.form).filter(function() {
		return $(this).val();
	}).length;    

	var valid = numberFilled >= numberRequired;
	return valid;
});

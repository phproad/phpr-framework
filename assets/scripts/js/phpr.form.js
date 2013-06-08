/**
 * PHPR Form
 * 
 * Usage: See PHPR Validate
 */


(function($) {

	PHPR.form = function(element) {

		var o = {},
			_form = element,
			_fields = {};

		o.defineFields = function(object) {
			object.prototype.defineField = function(field, name) {
				return o.defineField(field, name);
			}
			new object();
			return this;
		}

		o.defineField = function(field, name) {
			var fieldObj = new PHPR.formFieldDefinition(_form, field, name);
			_fields[field] = fieldObj;
			return fieldObj;
		};

		o.validate = function(element, options) {
			var form = element ? $(element) : _form;
			
			options = $.extend(true, {
				rules: this.getFieldRules(),
				messages: this.getFieldMessages()
			}, options);

			return new PHPR.validate(form, options);
		}

		// Shorthand helper 
		o.valid = function(element) {
			return o.validate(element).valid();
		};

		o.getFieldRules = function() {
			var allRules = {};
			$.each(_fields, function(field, fieldObj){
				allRules[field] = fieldObj.getRules();
			});
			return allRules;
		};

		o.getFieldMessages = function() {
			var allMessages = {};
			$.each(_fields, function(field, fieldObj){
				allMessages[field] = fieldObj.getMessages();
			});
			return allMessages;
		};	

		return o;
	}


	PHPR.formFieldDefinition = function(element, field, name) {

		var _rules = {},
			_messages = {},
			_field = field,
			_name = name,
			_form = element;

		// Validation Rules
		// 
		this.required = function(message) {
			_rules.required = true;
			_messages.required = message;
			return this;
		};

		// Requires at least X (minFilled) inputs (element class) populated 
		this.requiredMulti = function(minFilled, elementClass, message) {
			_rules.required_multi = [minFilled, elementClass];
			_messages.required_multi = message;
			return this;
		};

		this.number = function(message) {
			_rules.number = true;
			_messages.number = message;
			return this;
		};

		this.phone = function(message) {
			_rules.phone = true;
			_messages.phone = message;
			return this;
		};

		this.email = function(message) {
			_rules.email = true;
			_messages.email = message;
			return this;
		};

		this.date = function(message) {
			_rules.ahoyDate = true;
			_messages.ahoyDate = message;
			return this;
		};

		this.url = function(message) {
			_rules.fullUrl = true;
			_messages.fullUrl = message;
			return this;
		};

		this.range = function(range, message) {
			_rules.range = range;
			_messages.range = message;
			return this;
		};

		this.min = function(min, message) {
			_rules.min = min;
			_messages.min = message;
			return this;
		};

		this.max = function(max, message) {
			_rules.max = max;
			_messages.max = message;
			return this;
		};

		this.minWords = function(words, message) {
			_rules.minWord = words;
			_messages.minWord = message;
			return this;
		};

		this.matches = function(field, message) {
			_rules.equalTo = field;
			_messages.equalTo = message;
			return this;
		};

		// Submits a remote action using phpr().post()
		this.action = function(action, message) {
			_rules.ahoyRemote = { action:action };
			_messages.ahoyRemote = message;
			return this;
		};

		// Setters
		// 

		// Manually sets a rule
		this.setRule = function(rule, value) {
			_rules[rule] = value;
			return this;
		};

		// Manually sets a message
		this.setMessage = function(rule, value) {
			_messages[rule] = value;
			return this;
		};
		
		// Manually sets rules
		this.setRules = function(rules) {
			_rules = $.extend(true, _rules, rules);
			return this;
		};

		// Manually sets messages
		this.setMessages = function(messages) {
			_messages = $.extend(true, _messages, messages);
			return this;
		};

		// Getters
		// 

		this.getRules = function() {
			return _rules;
		}

		this.getMessages = function() {
			return _messages;
		}

	}

})(jQuery);

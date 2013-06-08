var Admin_Page = (function(page, $){

	var _field, 
		_container,
		_set_value;

	//
	// Public
	// 

	page.multiTextInit = function(fieldId, containerId) {

		_field = $('#'+fieldId);
		_container = $('#'+containerId);
		_set_value = _field.val();

		// Spool exisiting data
		try {
			if (_set_value != "")
				jQuery.each(jQuery.parseJSON(_set_value), function(k,v){ multiTextBuildShell(v); });
		} catch(e) { };

		// Bind events
		_container.find('.multitext-field').live('change', function(){		
			multiTextChange();
		});     
	}

	page.multiTextAddField = function() {
		var shell = _container.find('.multitext-shell:first'),
			newField = shell.clone()
				.removeClass('multitext-shell')
				.addClass('multitext-object')
				.show();
			
		shell.after(newField);

		var datePickerInput = newField.find('.datePickerHolder > input:first');
		if (datePickerInput.length > 0)
			page.multiTextInitDatePicker(datePickerInput);

		return newField;
	}

	page.multiTextRemoveField = function(el) {
		jQuery(el).closest('.multitext-object').remove(); 
		multiTextChange();
		return false;
	}

	// 
	// Internals
	// 

	var multiTextChange = function() {
		var objData = multiTextCompile();
		_field.val(jQuery.stringify(objData));

	}

	var multiTextCompile = function() {
		
		var arr = [];
		_container.find('.multitext-object').each(function(){

			var obj = {};
			jQuery(this).find('.multitext-field').each(function() {

				var objName = jQuery(this).attr('data-object-name');
				obj[objName] = jQuery(this).val();
			});
			arr.push(obj);
		});

		return arr;
	}

	var multiTextBuildShell = function(data) {
		var shell = page.multiTextAddField();
		jQuery.each(data, function(k,v) {
			shell.find('[data-object-name="'+k+'"]').val(v);
		});
	}

	return page;
}(Admin_Page || {}, jQuery));



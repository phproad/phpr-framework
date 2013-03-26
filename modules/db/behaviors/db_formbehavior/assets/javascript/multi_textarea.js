function multi_textarea_init(field_id, set_value) {
    // Spool exisiting data
    try {
        if (set_value != "")
            jQuery.each(jQuery.parseJSON(set_value), function(k,v){ multi_textarea_build_shell(field_id, v); });
    } catch(e) { };

    // Bind events
    jQuery('#multi_textarea'+field_id+' .multi_textarea_field').live('change', function(){
        multi_textarea_change(field_id);
    });     
}

function multi_textarea_change(field_id) {

    var field = jQuery('#'+field_id);
    var obj_data = multi_textarea_compile(field_id);
    field.val(jQuery.stringify(obj_data));

}
function multi_textarea_compile(field_id) {

    var field_container = jQuery('#multi_textarea' + field_id);
    var arr = [];

    field_container.find('.multi_textarea_object').each(function(){

        var obj = {};
        jQuery(this).find('.multi_textarea_field').each(function() {

            var obj_name = jQuery(this).attr('data-object-name');
            obj[obj_name] = jQuery(this).val();
        });
        arr.push(obj);

    });

    return arr;
}

function multi_textarea_add_field(field_id) {
    return jQuery('#multi_textarea'+field_id+' .multi_textarea_shell')
        .clone()
        .removeClass('multi_textarea_shell')
        .addClass('multi_textarea_object')
        .show()
        .appendTo('#multi_textarea'+field_id);
}

function multi_textarea_build_shell(field_id, data) {
    var shell = multi_textarea_add_field(field_id);
    jQuery.each(data, function(k,v) {
        shell.find('[data-object-name="'+k+'"]').val(v);
    });
}


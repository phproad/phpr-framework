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

    #success(callback:Function) - Gets a jQuery promise, passing callback to success

    #fail(callback:Function)

    #done(callback:Function) - Fires if success or fail

    complete(callback:Function) - Fires after partials update

    loadingIndicator(param:Bool|Obj)

    post(params:Object = {})

    put(params:Object = {})

    delete(params:Object = {}) Note: conflict < es5.

    animation(callback:Function<element:jQuery, html:String>) - Allow custom handler to show the new content returned via AJAX

    lock(value:Boolean) - Blocks the UI from multiple requests. Sets busy=true.

    promise - Returns a jQuery promise extended with PHPR

*/
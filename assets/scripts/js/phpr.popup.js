
/**
 * Popups Widget
 */

window.PopupWindows = [];

// Popup public class definition
var PopupForm = function(ajax_handler, options) {
	(function($){
		options = $.extend(true, options, { handler: ajax_handler });
		$('body').popup(options);
	})(jQuery);
}

function cancelPopup() {
	if (window.PopupWindows.length)
		window.PopupWindows[window.PopupWindows.length-1].cancel();
		
	if (!window.PopupWindows.length)
		jQuery(window).trigger('popupHide');

	return false;
}

function cancelPopups() {
	while (window.PopupWindows.length) {
		cancelPopup();
	}
}

function addPopup() {
	(function($){
		if (window.PopupWindows.length == 0) {
			$(window).trigger('popupDisplay');
		}
	})(jQuery);
}

function realignPopups() {
	(function($){
		$.each(window.PopupWindows, function(key, popup){ popup.alignForm(); });
	})(jQuery);
}

;(function ($, window, document, undefined) {

	$.widget("admin.popup", {
		version: '1.0.0',
		options: { 
			ajaxPage: null,
			ajaxData: null,
			handler: null,
			opacity: 0.1,
			className: 'popupForm',
			background: '',
			zIndex: 2019,
			closeByClick: false,
			ajaxFields: {},
			closeByEsc: true,
			autoCenter: true,
			content: null
		},

		contentContainer: '#content',
		formLoadHandler: null,
		overlay: null,
		formContainer: null,
		tmp: null,
		lockName: false,

		_init: function () { var self = this;

			if (this.element.is('body')) {
				var new_element = $('<div />').appendTo('body');
				new_element.popup(this.options);
				return;
			}

			this.formLoadHandler = this.options.handler;
			
			var lockName = 'popup' + this.formLoadHandler;
			if (lockManager.get(lockName))
				return;

			lockManager.set(lockName);
			this.lockName = lockName;
			
			this.show();
			window.PopupWindows.push(this);
		},

		show: function() { var self = this;

			this.overlay = this.element.overlay({
				onClose: cancelPopup,
				onResize: $.proxy(this.alignForm, this),
				closeByClick: this.options.closeByClick,
				zIndex: 2020 + window.PopupWindows.length,
				autoCenter: this.options.autoCenter
			});
			
			$(window).trigger('popupDisplay');
			
			this.overlay.overlay('show');

			var contentContainer = $(this.contentContainer);
			
			this.formContainer = $('<div />').addClass('popupLoading').css({
				'position': 'absolute',
				'visibility': 'hidden',
				'z-index': this.options.zIndex + window.PopupWindows.length + 1,
				'padding': '10px'
			});

			this.tmp = $('<div />').css({'visibility': 'hidden', 'position': 'absolute'});
			
			this.tmp.prepend($('<div />').addClass('popupForm'));

			contentContainer.prepend(this.formContainer);
			contentContainer.prepend(this.tmp);

			this.alignForm();
			
			this.formContainer.css('visibility', 'visible');
			
			if (this.options.closeByEsc)
				$(document).on('keydown.popup', $.proxy(this.cancelByKey, this));

			// Populate the popup
			if (this.options.content) {
				var contentElement = $(this.options.content);
				var content = $(contentElement.clone().show());
				this.tmp.find('>*:first').append(content);
				this.formLoaded();
			} else if (this.options.ajaxPage) {
				jQuery.post(this.options.ajaxPage, this.options.ajaxData).success(function(html){
					self.tmp.find('>*:first').html(html);
					jQuery(window).trigger('onAfterAjaxUpdateGlobal');
					self.formLoaded();
				}).error(function() {
					alert('Unable to load AJAX page: ' + self.options.ajaxPage); 
					cancelPopup();
				});				
			} else {
				new PHPR.post(this.formLoadHandler, {
					data: $.extend(true, this.options.ajaxFields, { phpr_popup_form_request: 1 }),
					update: this.tmp.find('>*:first'), 
					loadIndicator: { show: false }, 
					done: function(requestObj) {
						self.formLoaded();
					}
				}).send();
			}
		},
		
		_get_popup_inner: function() {
			var el = this.formContainer.find('>div:first').find('>div:first');
			return (el.length > 0) ? el : false;
		},

		cancelByKey: function(event)  {
			var code = event.keyCode ? event.keyCode : event.which;

			if (code == 27) {
				this.cancel();
				event.preventDefault();
			}
		},
		
		cancel: function()  {
			var allow_close = true;

			try {
				var inner_element = this._get_popup_inner();
				if (inner_element) {
					try {
						inner_element.trigger('onClosePopup');
					} catch (e) {
						allow_close = false;
					}
				}
			} catch (e) {}
			
			if (allow_close) {
				this.destroy();
				$(document).off('keydown.popup', $.proxy(this.cancelByKey, this));
				return false;
			}
		},

		formLoaded: function() { var self = this;
			var newHeight = this.tmp.height(),
				newWidth = this.tmp.width();

			this.formContainer.animate({
					'height': newHeight,
					'width': newWidth+1
			}, {
				progress: $.proxy(self.alignForm, this),
				complete: $.proxy(self.loadComplete, this),
				duration: 500
			});
		},
		
		loadComplete: function() {
			var first = this.tmp.find('>*:first');
			if (first.length > 0)
				first.appendTo(this.formContainer);	

			this.tmp.remove();
			
			this.formContainer
				.removeClass('popupLoading')
				.css({'width': 'auto', 'height': 'auto'});
			
			var inner_element = this._get_popup_inner();
			
			if (inner_element) {
				inner_element.trigger('popupLoaded');
				$(window).trigger('popupLoaded', [inner_element]);
				inner_element.addClass('popup_content');
				
				var a = $('<a />').addClass('popup-close')
					.attr('title', 'Close')
					.attr('href', '#')
					.click(function() { cancelPopup(); return false; })
					.prependTo(inner_element);
			}
		},
		
		alignForm: function() {
			if (this.formContainer === null || this.formContainer.length == 0)
				return;

			var windowHeight = $(window).height(),
				windowWidth = $(window).width(),
				formHeight = this.formContainer.height(),
				formWidth = this.formContainer.width(),
				scrollTop = $(window).scrollTop();

			var top = 0;
			if (formHeight > windowHeight)
				top = Math.round((windowHeight / 2) - (formHeight / 2));
			else
				top = Math.round(scrollTop + (windowHeight / 2) - (formHeight / 2));
				
			var left = Math.round((windowWidth / 2) - (formWidth / 2));

			if (top < 0)
				top = 0;
				
			if (left < 0)
				left = 0;
				
			var contentContainer = this.formContainer.find('.content');
			var popupContent = this.formContainer.find('.popup_content');

			this.formContainer.css({
				'left': left,
				'top': top
			});
		},
		
		destroy: function() {
			hide_tooltips();
			lockManager.remove(this.lockName);

			this.overlay.overlay('destroy');
			this.formContainer.remove();
			window.PopupWindows.pop();
			$.Widget.prototype.destroy.call(this);
		}		


	});

})( jQuery, window, document );


/**
 * Overlay Widget
 */

window.PopupWindows = [];

;(function ($, window, document, undefined) {

	$.widget("admin.overlay", {
		version: '1.0.0',
		options: { 
			opacity: 0.5,
			className: 'overlay',
			background: '',
			zIndex: 2020,
			closeByClick: false,
			autoCenter: true,
			onShow: null,
			onClose: null,
			onHide: null,
			onClick: null,
			onResize: null
		},

		overlay: null,

		_init: function () { 

			if (this.element.is('body')) {
				var new_element = $('<div />').appendTo('body');
				new_element.overlay(this.options);
				return;
			}

		},

		toggleListeners: function(state) {

			if (state) {
				$(window).on('resize.overlay', $.proxy(this.resize, this));

				if (this.options.autoCenter)
					$(window).on('scroll.overlay', $.proxy(this.resize, this));
			} else {

				$(window).off('resize.overlay', $.proxy(this.resize, this));
				
				if (this.options.autoCenter)
					$(window).off('scroll.overlay', $.proxy(this.resize, this));
			}

		},

		build: function()  {
			if (this.overlay === null) {

				this.overlay = $('<div />')
					.addClass(this.options.className)
					.css({
						opacity:    0,
						visibility: 'hidden',
						'z-index':  this.options.zIndex
					});

				$('body').prepend(this.overlay)
					.addClass('ui-overlay');
				
				if (this.options.background != '')
					this.overlay.css('background', this.options.background);

				this.overlay.on('click', $.proxy(this.click, this));
				this.overlay.on('mouseup', function(event) { event.preventDefault(); } );
			}
		},

		resize: function()  {
			this.options.onResize && this.options.onResize();

			if (this.overlay !== null) {
				this.overlay.css({
					'width': $(window).width(),
					'height': $(window).height()
				});
			}
		},

		show: function() {

			if (this.overlay !== null && this.overlay.is(':visible')) 
				return;

			this.options.onShow && this.options.onShow();

			this.build();

			if (this.overlay.length > 0) {
				this.resize();
				this.toggleListeners(true);
				this.overlay.css('visibility', 'visible')
					.animate({opacity: this.options.opacity}, 250);
			}
		},

		hide: function() { var self = this;
			if (!this.overlay.length || this.overlay.is(':hidden')) 
				return;
				
			this.options.onHide && this.options.onHide();

			if (this.overlay !== null) {
				this.toggleListeners(false);
				this.overlay.animate({opacity: 0}, {
					duration: 250,
					complete: function() {
						self.overlay.css({
							visibility: 'hidden',
							width: 0,
							height: 0
						});
					}
				})
			}
		},

		click: function()  {
			this.options.onClick && this.options.onClick();
			if (this.options.closeByClick) {
				this.options.onClose && this.options.onClose();

				this.hide();
			}
		},

		destroy: function() {
			if (this.overlay === null)
				return;

			this.toggleListeners(false);
			this.overlay.off('click.overlay', $.proxy(this.click, this));
			this.overlay.remove();
			this.overlay = null;

			$.Widget.prototype.destroy.call(this);
		}

	});

})( jQuery, window, document );



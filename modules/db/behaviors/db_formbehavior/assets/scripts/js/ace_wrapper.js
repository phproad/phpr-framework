
/**
 * Ace Editor Wrapper
 */

var phpr_active_code_editor = null;
var phpr_code_editors = [];

function init_code_editor(field_id, language, options) {
	options = $.extend(true, options, { language: language });
	return jQuery('#'+field_id).aceWrapper(options);
}

function find_code_editor(field_id) {
	var found_editor = null;
	jQuery.each(phpr_code_editors, function(k, obj){
		if (obj.id == field_id)
			found_editor = obj.editor;
	});
		
	return found_editor;
}

;(function ($, window, document, undefined) {

	$.widget("phpr.aceWrapper", {
		
		version: '1.0.0',

		options: {
			showInvisibles: true,
			highlightActiveLine: true,
			showGutter: true,
			showPrintMargin: true,
			highlightSelectedWord: false,
			hScrollBarAlwaysVisible: false,
			useSoftTabs: true,
			tabSize: 4,
			fontSize: 12,
			wrapMode: 'off',
			readOnly: false,
			theme: 'textmate',
			folding: 'manual',
			language: 'php'
		},
	
		fullscreen_title_off: 'Enter fullscreen mode: <strong>ctrl+alt+f</strong>',
		fullscreen_title_on: 'Exit fullscreen mode: <strong>ctrl+alt+f</strong> or <strong>esc</strong>',

		pre:             null, // Code element
		textarea:        null, // Textarea element
		editor:          null, // Ace editor object
		code_wrapper:    null, // Code wrapper
		field_container: null, // Form field container
		
		hide_toolbar_id: null, // Timer 

		_init: function () { var self = this;

			this.textarea = this.element;
			this.textarea.hide();
			this.bound_resize = $.proxy(this._update_size_fullscreen, this);
			this.bound_keydown = $.proxy(this._keydown, this);

			this.code_wrapper = this.textarea.parent();
			this.field_container = this.code_wrapper.parent();

			var language = this.options.language, 
				ui_wrapper = this.ui_wrapper = $('<div />').addClass('code_editor_wrapper').appendTo(this.field_container),
				height = jQuery.cookie(this.field_container.attr('id')+'editor_size');

			this.code_wrapper.appendTo(this.ui_wrapper);

			this.pre = $('<pre />').css({'font-size': this.options.fontSize + 'px'})
				.addClass('form-ace-editor')
				.attr('id', this.textarea.attr('id') + 'pre')
				.appendTo(this.textarea.parent());

			this.editor = ace.edit(this.pre.attr('id'));

			new PHPR.request().loadAssets([phpr_url('vendor/ace/theme-'+this.options.theme+'.js')], [], function() {
				self.editor.setTheme('ace/theme/'+self.options.theme);
			});

			this.editor.getSession().setValue(this.textarea.val());

			if (language.length) {
				new PHPR.request().loadAssets([phpr_url('vendor/ace/mode-'+language+'.js')], [], function() {
					self._language_loaded(language);
				});
			}
				
			var textarea_id = this.textarea.attr('id');

			this.editor.getSession().on('change', function(){
				$(window).trigger('phpr_codeeditor_changed', textarea_id);
			});

			this.editor.on('focus', function(){
				ui_wrapper.addClass('focused');
				phpr_active_code_editor = textarea_id;
			});

			this.editor.on('blur', function(){ui_wrapper.removeClass('focused');});
			
			phpr_code_editors.push({'id': textarea_id, 'editor': this.editor});

			$(window).trigger('phpr_codeeditor_initialized', [textarea_id, this.editor])
			
			/*
			 * Configure
			 */
			
			this.editor.wrapper = this;

			this.editor.setShowInvisibles(this.options.showInvisibles);
			this.editor.setHighlightActiveLine(this.options.highlightActiveLine);
			this.editor.renderer.setShowGutter(this.options.showGutter);
			this.editor.renderer.setShowPrintMargin(this.options.showPrintMargin);
			this.editor.setHighlightSelectedWord(this.options.highlightSelectedWord);
			this.editor.renderer.setHScrollBarAlwaysVisible(this.options.hScrollBarAlwaysVisible);
			this.editor.getSession().setUseSoftTabs(this.options.useSoftTabs);
			this.editor.getSession().setTabSize(this.options.tabSize);
			this.editor.setReadOnly(this.options.readOnly);
		    this.editor.getSession().setFoldStyle(this.options.folding);
			
			var session = this.editor.getSession(),
				renderer = this.editor.renderer;
			
			switch (this.options.wrapMode) {
				case "off":
					session.setUseWrapMode(false);
					renderer.setPrintMarginColumn(80);
				break;
				case "40":
					session.setUseWrapMode(true);
					session.setWrapLimitRange(40, 40);
					renderer.setPrintMarginColumn(40);
				break;
				case "80":
					session.setUseWrapMode(true);
					session.setWrapLimitRange(80, 80);
					renderer.setPrintMarginColumn(80);
				break;
				case "free":
					session.setUseWrapMode(true);
					session.setWrapLimitRange(null, null);
					renderer.setPrintMarginColumn(80);
				break;
			}		
			
			$(window).on('phprformsave', $.proxy(this._save, this));
			
			this.code_wrapper.bindkey('ctrl+alt+f', function(event) {
				self._fullscreen_mode();
			});
			
			/*
			 * Create the footer 
			 */

			var footer = $('<div />').addClass('code_editor_footer').appendTo(this.ui_wrapper);
			this.resize_handle = $('<div />').addClass('resize_handle').appendTo(footer);
			
			// @todo Convert to jQ
			// new Drag(this.code_wrapper, {
			// 	'handle': this.resize_handle,
			// 	'modifiers': {'x': '', 'y': 'height'},
			// 	'limit': {'y': [100, 3000]},
			// 	onDrag: function(){ 
			// 		this.fireEvent('resize', this);
			// 		this.editor.resize();
			// 	}.apply(this),
			// 	onComplete: function(){
			// 		this.editor.resize();
			// 		window.fireEvent('phpr_editor_resized');
			// 		Cookie.write(this.field_container.get('id')+'editor_size', this.code_wrapper.getSize().y, {duration: 365, path: '/'});
			// 	}.apply(this)
			// });
			
			/*
			 * Create the toolbar
			 */

			this.toolbar = $('<div />').addClass('code_editor_toolbar').prependTo(this.code_wrapper);
			
			this._toolbar_effect_complete();

			this.displaying_toolbar = false;
			this.ui_wrapper.bind('mousemove', $.proxy(this._display_toolbar, this));

			/*
			 * Create buttons
			 */

			var list = $('<ul />').appendTo(this.toolbar);

			this.fullscreen_btn = $('<li />').addClass('fullscreen').appendTo(list);
			var fullscreen_btn_link = $('<a />').addClass('has-tooltip').attr('title', this.fullscreen_title_off).attr('href', 'javascript:;').appendTo(this.fullscreen_btn);
			fullscreen_btn_link.bind('click', $.proxy(this._fullscreen_mode, this));

			update_tooltips();
			
			/*
			 * Update height from cookies
			 */
			
			if (height !== null) {
				this.code_wrapper.css('height', height + 'px');
				$(window).trigger('phpr_editor_resized');
			}			
		},

	
		setFontSize: function(size) {
			this.pre.css('font-size', size + 'px');
		},
		
		updateSize: function() {
			this.editor.resize();
		},

		_language_loaded: function(language) {
			var mode = require('ace/mode/'+language).Mode;
			this.editor.getSession().setMode(new mode());
		},
		
		_save: function() {
			this.textarea.val(this.editor.getSession().getValue());
		},
		
		_fullscreen_mode: function() {
			if (!this.field_container.hasClass('fullscreen')) {

				this.normal_scroll = { x: $(window).scrollLeft(), y: $(window).scrollTop() };				
				this.original_container_parent = this.field_container.parent();

				this.field_container.addClass('fullscreen');
				$('body').css('overflow', 'hidden');
				$(window).scrollTop(0).scrollLeft(0);

				// Fix for Chrome
				this.field_container.hide();
				var redrawFix = this.field_container.outerHeight();
				this.field_container.show();

				this._update_size_fullscreen();
				$(window).bind('resize', this.bound_resize);
				
				$(document).bind('keydown', this.bound_keydown);
				this.fullscreen_btn.attr('title', this.fullscreen_title_on);
			} else {
				$(document).unbind('keydown', this.bound_keydown);

				this.field_container.removeClass('fullscreen')
				$('body').css('overflow', 'visible');
				$(window).unbind('resize', this.bound_resize);
				this.pre.css('height', 'auto');
				
				$(window).scrollTop(this.normal_scroll.y);
				$(window).scrollLeft(this.normal_scroll.x);				

				this.editor.resize();
				this.editor.focus();
				this.fullscreen_btn.attr('title', this.fullscreen_title_off);
			}
		},
		
		_keydown: function(event) {
			if (event.key == 'esc') {
				this._fullscreen_mode();
				event.stop();
				return false;
			}
		},
		
		_update_size_fullscreen: function() {
			var window_size = { x: $(window).innerWidth(), y: $(window).height() };
			
			this.pre.css('height', window_size.y + 'px');
			this.editor.resize();
		},
		
		_display_toolbar: function() { var self = this;
			if (this.displaying_toolbar) {
				if (this.hide_toolbar_id !== undefined)
					clearInterval(this.hide_toolbar_id);

				this.hide_toolbar_id = setInterval($.proxy(this._hide_toolbar, this), 2000);
				return;
			}

			this.displaying_toolbar = true;
			this.hiding_toolbar = false;
			this.toolbar.show().css('opacity', 0).fadeTo(500, 1);
		},
		
		_hide_toolbar: function() {
			this.hiding_toolbar = true;

			// this.toolbar.fadeTo(500, 0);
			// this.toolbar.morph({'opacity': [1, 0]});
		},
		
		_toolbar_effect_complete: function() {
			if (this.hiding_toolbar) {
				this.toolbar.hide();
				this.displaying_toolbar = false;
			}
		}

	});

})( jQuery, window, document );

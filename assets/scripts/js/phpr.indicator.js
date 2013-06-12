(function($) {

	PHPR.indicatorDefaults = {
		element: null,
		indicatorElement: null,
		show: true,
		src: null,
		posX: 'center',
		posY: 'center',
		zIndex: 9999,
		absolutePosition: false,
		injectInElement: false,
		injectPosition: 'bottom',
		overlayClass: 'ajax-loading-indicator',
		overlayOpacity: 1,
		noImage: true,
		hideOnSuccess: true,
		loadingText: '<span>Loading...</span>'
	};

	PHPR.indicator = function(options) {
		var o = {},
			_options = options || {};

		o.indicatorElement = null;
		o.imageElement = null;

		o.setDefaultOptions = function(defaultOptions) {
			PHPR.indicatorDefaults = $.extend(true, PHPR.indicatorDefaults, defaultOptions);
		}

		/**
		 * Shows the loading indicator.
		 * @type Function
		 * @return none
		 */
		o.show = function(options) {
			options = $.extend(true, {}, PHPR.indicatorDefaults, _options, options);

			if (!options.show)
				return this;

			if (!options.src && !options.noImage)
				throw "PHPR.indicator: options.src is null";
			
			if (typeof options.element == 'string')
				options.element = $(options.element);

			var element = options.element.length > 0 ? options.element : $('body'),
				container = options.injectInElement ? element : $('body'),
				position = options.absolutePosition ? 'absolute' : 'fixed';

			if (options.indicatorElement) {
				o.indicatorElement = $(options.indicatorElement);
				if (!o.indicatorElement.length)
					o.indicatorElement = null;
			}

			if (o.indicatorElement === null) {

				// Set up overlay
				var overlay = $('<div />');
				o.indicatorElement = overlay.css({
						position: position,
						opacity: options.overlayOpacity,
						zIndex: options.zIndex
					})
					.addClass(options.overlayClass);

				// Loading text
				if (options.noImage) {
					o.indicatorElement.html(options.loadingText);
				}

				// Set up image
				if (!options.noImage) {
					o.imageElement = $('<img />').attr('src', options.src).css({
							position: 'absolute',
							'z-index': (options.zIndex+1)
						})
						.wrap('<span />')
						.hide();
					o.indicatorElement.append(o.imageElement);

					// Position the image
					o.imageElement.on('load', function() { 
						var img = $(this);
						
						switch (options.posX) {
							case 'center': img.css({ left: '50%', 'margin-left': -(img.outerWidth() / 2) + "px" }); break;
							case 'left': img.css({ left: 0 }); break;
							case 'right': img.css({ right: 0 }); break;
						}

						switch (options.posY) {
							case 'center': img.css({ 'top': '50%', 'margin-top': -(img.outerHeight() / 2) + "px" }); break;
							case 'top': img.css({ top: 0 }); break;
							case 'bottom': img.css({ bottom: 0 }); break;
						}

						img.show();
					});
				}

				// Set indicator size
				var overlayTop = element.offset().top,
					overlayLeft = element.offset().left;

				if (options.injectInElement) {
  					if (element.is('body') || element.css('position') == 'relative') {
						overlayTop = 0;
						overlayLeft	= 0;
  					} else {
						var offsetParent = element.offsetParent();
						overlayTop -= offsetParent.offset().top;
						overlayLeft -= offsetParent.offset().left;
  					}
				}

				o.indicatorElement
					.css({
						top: overlayTop,
						left: overlayLeft,
						width: element.outerWidth() + 'px',
						height: element.outerHeight() + 'px'
					});

				container.prepend(overlay);
			}
			
			return this;
		}
		
		/**
		 * Hides the loading indicator.
		 * @type Function
		 * @return none
		 */
		o.hide = function() {
			if (!o.indicatorElement)
				return this;

			o.indicatorElement.hide();
			return this;
		}

		// Extend the indicator object with DOM
		o = $.extend(true, o, PHPR.indicator);
		return o;
	}

})(jQuery);

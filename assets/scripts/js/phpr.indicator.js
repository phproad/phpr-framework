(function($) {

	PHPR.indicatorDefaults = {
		show: true,
		hideOnSuccess: true,
		overlayClass: 'ajax_loading_indicator',
		posX: 'center',
		posY: 'center',
		src: null,
		injectInElement: false,
		noImage: false,
		zIndex: 9999,
		element: null,
		absolutePosition: false,
		injectPosition: 'bottom',
		overlayOpacity: 1,
		hideElement: false
	};

	PHPR.indicator = function() {
		var o = {};

		o.indicatorElement = null;

		o.setDefaultOptions = function(defaultOptions) {
			PHPR.indicatorDefaults = $.extend(true, PHPR.indicatorDefaults, defaultOptions);
		}

		/**
		 * Shows the loading indicator.
		 * @type Function
		 * @return none
		 */
		o.showIndicator = function(options) {
			options = $.extend(true, PHPR.indicatorDefaults, options);

			if (!options.show)
				return;
			
			var container = options.injectInElement && options.form ? options.form : $('body'),
				position = options.absolutePosition ? 'absolute' : 'fixed',
				visibility = options.hideElement ? 'hidden' : 'visible';
			
			if (o.indicatorElement === null) {
				var element = options.element ? $('#' + options.element) : $('<p />');
				
				o.indicatorElement = element
					.css({
						visibility: visibility,
						position: position,
						opacity: options.overlayOpacity,
						zIndex: options.zIndex
					})
					.addClass(options.overlayClass)
					.html("<span>Loading...</span>")
					.prependTo(container);
			}
			
			o.indicatorElement.show();
		}
		
		/**
		 * Hides the loading indicator.
		 * @type Function
		 * @return none
		 */
		o.hideIndicator = function() {
			if (!o.indicatorElement)
				return;

			o.indicatorElement.hide();
		}

		// Extend the indicator object with DOM
		o = $.extend(true, o, PHPR.indicator);
		return o;
	}

})(jQuery);

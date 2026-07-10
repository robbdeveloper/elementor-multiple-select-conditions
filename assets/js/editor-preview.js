(function ($) {
	'use strict';

	$(window).on('elementor/frontend/init', function () {
		if (window.EDS && typeof window.EDS.initDynamicSelects === 'function') {
			window.EDS.initDynamicSelects(document);
		}
	});
})(jQuery);

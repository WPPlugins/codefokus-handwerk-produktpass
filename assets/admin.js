/* Handwerk Produktpass – Backend */
(function ($) {
	'use strict';

	// Klick markiert Text komplett (z. B. Schlüssel, Systembericht).
	$(document).on('focus click', '.hwpp-select-all', function () { this.select(); });

})(jQuery);

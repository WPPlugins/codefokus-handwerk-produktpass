/* Handwerk Produktpass – Werkstatt-Fotos im Produkt-Tab */
(function ($) {
	'use strict';
	$(document).on('click', '.hwpp-gallery-select', function (e) {
		e.preventDefault();
		var $wrap = $(this).closest('.hwpp-gallery-field');
		var $input = $wrap.find('input[type=hidden]');
		var frame = wp.media({ title: $wrap.data('title'), library: { type: 'image' }, multiple: 'add' });
		frame.on('open', function () {
			var sel = frame.state().get('selection');
			($input.val() || '').split(',').forEach(function (id) {
				if (id) { var a = wp.media.attachment(id); a.fetch(); sel.add(a); }
			});
		});
		frame.on('select', function () {
			var ids = [], $thumbs = $wrap.find('.hwpp-gallery-thumbs').empty();
			frame.state().get('selection').each(function (a) {
				a = a.toJSON(); ids.push(a.id);
				var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				$thumbs.append($('<img>', { src: url, width: 60, height: 60 }));
			});
			$input.val(ids.join(','));
		});
		frame.open();
	});
})(jQuery);

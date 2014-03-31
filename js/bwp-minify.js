jQuery(function($) {
	function toggle_input(t, toggle) {
		var $t = $(t);
		var position = $t.data('position');
		var $w = $t.parents('.bwp-sidebar');
		var $s = $t.find('.bwp-sign');
		toggle = typeof toggle !== 'undefined' ? toggle : true;

		$w.find('.position-handle').not(t).find('.bwp-sign').html('+');
		$w.find(':input[name!="' + position + '"]').hide();

		if (false === toggle && '+' != $s.html()) {
			return false;
		}

		$w.find(':input[name="' + position + '"]').toggle();
		if ('+' == $s.html()) {
			$s.html('>>');
		} else {
			$s.html('+');
		}
	}

	$('.bwp-sidebar').on('click', '.position-handle', function(e) {
		e.preventDefault();
		toggle_input(this);
	});

	$('.bwp-minify-detector-table').on('click', '.action-toggle-handle', function(e) {
		e.preventDefault();

		var $t = $(this);
		var $i = $t.parent('td');
		var c  = $i.find('.action-handles')[0];
		var $w = $t.parents('.bwp-minify-detector-table');

		$w.find('.action-handles').not(c).hide();
		$(c).toggle();
	})

	$('.bwp-minify-detector-table').on('click', '.action-handle', function(e) {
		e.preventDefault();

		var $t = $(this);
		var position = $t.data('position');
		var handle = $t.parent().find('.data-handle').text();
		var input = 'input_' + position;
		var $i = $('.bwp-sidebar :input[name="' + input + '"]');
		var input_handle = $i.parent().find('.position-handle')[0];
		var input_val = '' == $i.val() ? '' : $i.val() + "\r\n";

		$i.val(input_val + handle);
		$t.parent().hide();
		toggle_input(input_handle, false);
	})
});

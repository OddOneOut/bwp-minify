jQuery(function($) {
	// toggle server rewrite rules
	$('.fly-show-rules-handle').on('click', function(e) {
		e.preventDefault();

		// hide all current rules
		$('.fly-min-rules').hide();

		var server = $(this).data('server');
		var divClass = 'fly-' + server + '-rules';

		$('.' + divClass).show();
	})

	// manage enqueued files
	function toggle_input(t, toggle) {
		var $t     = $(t);
		var field  = $t.data('field');
		var $w     = $t.parents('.bwp-sidebar');
		var $s     = $t.find('.bwp-sign');

		toggle = typeof toggle !== 'undefined' ? toggle : true;

		$w.find('.input-handle').not(t).find('.bwp-sign').html('+');
		$w.find(':input[name!="' + field + '"]').hide();

		if (false === toggle && '+' != $s.html()) {
			return false;
		}

		$w.find(':input[name="' + field + '"]').toggle();
		if ('+' == $s.html()) {
			$s.html('>>');
		} else {
			$s.html('+');
		}
	}

	$('.bwp-sidebar').on('click', '.input-handle', function(e) {
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

		var $t     = $(this);
		var action = $t.data('action');
		var handle = $t.parent().find('.data-handle').text();

		var input        = 'input_' + action;
		var $i           = $('.bwp-sidebar :input[name="' + input + '"]');
		var input_handle = $i.parent().find('.input-handle')[0];
		var input_val    = '' == $i.val() ? '' : $i.val() + "\r\n";

		$i.val(input_val + handle);
		$t.parent().hide();
		toggle_input(input_handle, false);
	})
});

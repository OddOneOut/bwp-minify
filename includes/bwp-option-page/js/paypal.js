jQuery(document).ready(function(){
	/* Paypal form */
	jQuery('.paypal-form select[name="amount"]').change(function() {
		if (jQuery(this).val() == '100.00')
		{
			jQuery(this).hide();
			jQuery('.paypal-alternate-input').append('<input type="text" style="padding: 3px; width: 70px; text-align: right; line-height: 1;" name="amount" value="15.00" /> <code>$</code>');
			jQuery('.paypal-alternate-input').show();
		}
	});
});
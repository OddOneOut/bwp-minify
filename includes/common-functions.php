<?php
/**
 * Get the absolute filesystem path to the root of the WordPress installation
 *
 * @since 1.3.0
 * @uses get_home_path wordpress/wp-admin/includes/files.php:81
 * @return string Full filesystem path to the root of the WordPress installation
 */
if (!function_exists('bwp_get_home_path')) :

function bwp_get_home_path() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	return get_home_path();
}

function bwp_is_maintenance_on() {
	// this is from the Maintenance plugin, function `load_maintenance_page` in
	// `load/functions.php`
	if (function_exists('mt_get_plugin_options') && !is_user_logged_in())
	{
		$mt_options = mt_get_plugin_options(true);

		if ($mt_options['state'])
		{
			if (!empty($mt_options['expiry_date_start'])
				&& !empty($mt_options['expiry_date_end'])
			) {
				$current_time = strtotime(current_time('mysql', 1));
				$start        = strtotime($mt_options['expiry_date_start']);
				$end          = strtotime($mt_options['expiry_date_end']);

				if ($current_time < $start
					|| ($current_time >= $end && !empty($mt_options['is_down']))
				) {
					return false;
				}
			}

			return true;
		}
	}
}

endif;

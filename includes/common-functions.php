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

endif;

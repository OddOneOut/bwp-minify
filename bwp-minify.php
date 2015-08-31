<?php
/*
Plugin Name: Tonjoo Better WordPress Minify
Plugin URI: http://betterwp.net/wordpress-plugins/bwp-minify/
Description: Allows you to minify your CSS and JS files for faster page loading for visitors. This plugin uses the PHP library <a href="http://code.google.com/p/minify/">Minify</a> and relies on WordPress's enqueueing system rather than the output buffer (will not break your website in most cases). This plugin is very customizable and easy to use.
Version: 1.3.3
Text Domain: bwp-minify
Domain Path: /languages/
Author: Khang Minh
Author URI: http://betterwp.net
License: GPLv3 or later
*/

// In case someone integrates this plugin in a theme or calling this directly
if (class_exists('BWP_MINIFY') || !defined('ABSPATH'))
	return;

// Init the plugin
require_once dirname(__FILE__) . '/includes/class-bwp-minify.php';
$bwp_minify = new BWP_MINIFY();

// Disable plugin in web if not admin
add_filter('bwp_minify_is_loadable','bpw_check_admin');
function bpw_check_admin($status) {
	// Not on dashboard
	if(!is_admin())
	{
		if ( current_user_can( 'manage_options' ) ) {
		    /* A user with admin privileges */
		    return false;
		} else {
		    /* A user without admin privileges */
		    return true;
		}
	}
	return $status;
}
<?php
/*
Plugin Name: Better WordPress Minify
Plugin URI: http://betterwp.net/wordpress-plugins/bwp-minify/
Description: Allows you to minify your CSS and JS files for faster page loading for visitors. This plugin uses the PHP library <a href="http://code.google.com/p/minify/">Minify</a> and relies on WordPress's enqueueing system rather than the output buffer (will not break your website in most cases). This plugin is very customizable and easy to use.
Version: 1.2.3
Text Domain: bwp-minify
Domain Path: /languages/
Author: Khang Minh
Author URI: http://betterwp.net
License: GPLv3
*/

// In case someone integrates this plugin in a theme
if (class_exists('BWP_MINIFY'))
	return;

// Frontend
require_once('includes/class-bwp-minify.php');
$bwp_minify = new BWP_MINIFY();

// Backend
add_action('admin_menu', 'bwp_minify_init_admin', 1);

function bwp_minify_init_admin()
{
	global $bwp_minify;
	$bwp_minify->init_admin();
}
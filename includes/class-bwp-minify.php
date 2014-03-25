<?php
/**
 * Main BWP Minify class that provides all logics.
 *
 * Copyright (c) 2014 Khang Minh <betterwp.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Khang Minh <contact@betterwp.net>
 * @link http://betterwp.net/wordpress-plugins/bwp-minify/
 * @link https://github.com/OddOneOut/Better-WordPress-Minify/
 */

/**
 * This is a wrapper function to help you convert a normal source to a minified source.
 * Please do not use this function before WordPress has been initialized. Otherwise, you might get a fatal error.
 *
 * @param string $src The source file you would like to convert
 * @link http://betterwp.net/wordpress-plugins/bwp-minify/ for more information
 */
function bwp_minify($src)
{
	global $bwp_minify;

	return $bwp_minify->get_minify_src($bwp_minify->process_media_source($src));
}

if (!class_exists('BWP_FRAMEWORK'))
	require_once('class-bwp-framework.php');

class BWP_MINIFY extends BWP_FRAMEWORK
{
	/**
	 * Positions to put scripts/styles in
	 */
	var $print_positions = array();

	/**
	 * Internal _Todo_ lists
	 *
	 * @since 1.3.0
	 */
	var $todo_scripts = array(), $todo_styles = array(), $todo_l10n = array(), $todo_inline_styles = array();

	/**
	 * Minify groups
	 *
	 * @since 1.3.0
	 */
	var $min_scripts = array(), $min_styles = array();

	/**
	 * Special handling for unusually printed scripts/styles
	 *
	 * @since 1.3.0
	 */
	var $late_script_order = 1, $todo_late_scripts = '';
	var $late_style_order = 1, $todo_late_styles = '';

	/**
	 * Other options
	 */
	var $ver = '', $base = '', $buster = '', $cache_age = 7200;

	/**
	 * Holds the extracted HTTP Host
	 *
	 * @since 1.3.0
	 */
	var $http_host = '', $min_url = '', $min_dir = '';

	/**
	 * Constructor
	 */
	function __construct($version = '1.2.3')
	{
		// Plugin's title
		$this->plugin_title = 'BetterWP Minify';
		// Plugin's version
		$this->set_version($version);
		$this->set_version('5.1.6', 'php');
		// Basic version checking
		if (!$this->check_required_versions())
			return;

		// The default options
		$options = array(
			'input_minurl' => '', // @deprecated 1.3.0 replaced by input_minpath
			'input_minpath' => '',
			'input_cache_dir' => '', // super admin, Minify
			'input_doc_root' => '', // @since 1.3.0 super admin, Minify
			'input_maxfiles' => 10,
			'input_maxage' => 2, // super admin, Minify
			'input_ignore' => 'admin-bar',
			'input_header' => '',
			'input_direct' => '',
			'input_footer' => '',
			'input_oblivion' => '',
			'input_style_ignore' => 'admin-bar' . "\n" . 'dashicons',
			'input_style_direct' => '',
			'input_style_oblivion' => '',
			'input_custom_buster' => '',
			'enable_min_js' => 'yes',
			'enable_min_css' => 'yes',
			'enable_bloginfo' => '',
			'enable_css_bubble' => 'yes', // @since 1.3.0 super admin, Minify
			'enable_cache_file_lock' => 'yes', // @since 1.3.0 super admin, Minify
			'select_buster_type' => 'none',
			'select_time_type' => 3600 // super admin, Minify
		);

		// super admin only options
		$this->site_options = array(
			'input_cache_dir',
			'input_doc_root',
			'input_maxage',
			'enable_css_bubble',
			'enable_cache_file_lock',
			'select_time_type'
		);

		$this->build_properties('BWP_MINIFY', 'bwp-minify', $options,
			'BetterWP Minify', dirname(dirname(__FILE__)) . '/bwp-minify.php',
			'http://betterwp.net/wordpress-plugins/bwp-minify/', false
		);
		$this->add_option_key('BWP_MINIFY_OPTION_GENERAL', 'bwp_minify_general',
			__('General Options', 'bwp-minify')
		);
		$this->add_extra_option_key('BWP_MINIFY_MANAGE', 'bwp_minify_manage',
			__('Manage enqueued Files', 'bwp-minify')
		);

		// define hidden option keys
		define('BWP_MINIFY_DETECTOR_LOG', 'bwp_minify_detector_log');

		// backward compatible with older frameword version,
		// `plugin_wp_url` is only available since framework version 1.1.0
		if (version_compare(BWP_FRAMEWORK_VERSION, '1.1.0', '<'))
		{
			$this->plugin_folder = basename(dirname($this->plugin_file));
			add_action('init', array($this, 'set_plugin_wp_url'));
		}

		add_action('init', array($this, 'default_minpath'));
		add_action('init', array($this, 'init'));
		add_action('init', array($this, 'enqueue_media'));
		add_action('upgrader_process_complete', array($this, 'check_minify_config_file'), 10, 2);
	}

	function set_plugin_wp_url()
	{
		if (defined('BWP_USE_SYMLINKS'))
			$this->plugin_wp_url = trailingslashit(plugins_url($this->plugin_folder));
		else
			$this->plugin_wp_url = trailingslashit(plugin_dir_url($this->plugin_file));
	}

	/**
	 * Sets a default Minify path when WordPress finishes initializing its core
	 *
	 * @return void
	 */
	function default_minpath()
	{
		// @since 1.3.0 we get a path relative to root for Minify instead of an
		// absolute URL to add compatibility to staging or mirror site.
		$plugin_wp_url = $this->plugin_wp_url;

		$http_host = '';
		$matches = array();

		if (false !== preg_match('#^https?://[^/]+#ui', $plugin_wp_url, $matches))
		{
			$http_host = $matches[0];
		}
		else
		{
			$url = @parse_url($plugin_wp_url);
			$http_host = $url['scheme'] . '://' . $url['host'];
			$http_host = !empty($url['port'])
				? $http_host . ':' . $url['port']
				: $http_host;
		}

		$this->http_host = $http_host;
		$min_path = str_replace($http_host, '', $plugin_wp_url);
		$min_path = apply_filters('bwp_minify_min_dir', $min_path . 'min/');

		$this->options_default['input_minpath'] = apply_filters(
			'bwp_minify_min_path', $min_path
		);
	}

	function init_properties()
	{
		$this->get_base();
		$this->parse_positions();
		$this->ver = get_bloginfo('version');

		$this->options['input_cache_dir'] = empty($this->options['input_cache_dir'])
			? $this->get_cache_dir()
			: $this->options['input_cache_dir'];

		$this->buster = $this->get_buster($this->options['select_buster_type']);
		$this->min_url  = trailingslashit($this->http_host) . ltrim($this->options['input_minpath'], '/');

		// load and init the detector class
		require_once dirname(__FILE__) . '/class-bwp-enqueued-detector.php';
		$this->detector = new BWP_Enqueued_Detector($this->options, 'bwp-minify');
		$this->detector->set_log(BWP_MINIFY_DETECTOR_LOG);
	}

	private static function is_loadable()
	{
		if (!did_action('template_redirect'))
			return true;

		// Ignore Geomashup
		if (!empty($_GET['geo_mashup_content']) && 'render-map' == $_GET['geo_mashup_content'])
			return false;

		// Ignore AEC (Ajax Edit Comment)
		if (!empty($_GET['aec_page']))
			return false;

		// Ignore Simple:Press forum plugin
		if (defined('SPVERSION') && function_exists('sp_get_option')) {
			$sp_page = sp_get_option('sfpage');
			if (is_page($sp_page)) {
				return false;
			}
		}

		return true;
	}

	function add_conditional_hooks()
	{
		// Certain plugins use a single file to show contents, which doesn't
		// make use of wp_head and wp_footer action and certain plugins should
		// just be excluded :-)
		if (false == apply_filters('bwp_minify_is_loadable', self::is_loadable()))
			return;

		// Allow other developers to use BWP Minify inside wp-admin, be very careful :-)
		$allowed_in_admin = apply_filters('bwp_minify_allowed_in_admin', false);

		if ((!is_admin() || (is_admin() && $allowed_in_admin)))
		{
			/**
			 * Priorities of these hooks below greatly affects compatibility
			 * with other plugins, especially plugins that output additional
			 * scripts directly via actions attached to `wp_footer` hook. Those
			 * plugins will likely register their output functions at a much
			 * lower priority than `wp_print_head_scripts` or similar
			 * hooks' priorities (bbpress uses 50, for e.g.).  BWP Minify
			 * registers its output functions at 1 priority higher than
			 * WordPress's hooks, to make sure (well not that sure) additional
			 * scripts are printed after their main JS files are printed.
			 */

			// minify styles if needed
			if ('yes' == $this->options['enable_min_css'])
			{
				// build a list of style groups to print
				add_filter('print_styles_array', array($this, 'add_styles'), 999);
				// build a list of very late styles
				add_action('wp_print_styles', array($this, 'add_late_styles'), 999);
				// hook to common head and footer actions, as late as possible
				add_action('wp_head', array($this, 'print_header_styles'), 9);
				add_action('login_head', array($this, 'print_header_styles'), 9);
				add_action('wp_print_footer_scripts', array($this, 'print_footer_styles'), 21);
				add_action('admin_print_styles', array($this, 'print_header_styles'), 9);
			}

			// minify scripts if needed
			if ('yes' == $this->options['enable_min_js'])
			{
				// build a list of script groups to print
				add_filter('print_scripts_array', array($this, 'add_scripts'), 999);
				// build a list of very late scripts
				add_action('wp_print_scripts', array($this, 'add_late_scripts'), 999);
				// hook to common head and footer actions, as late as possible
				add_action('wp_head', array($this, 'print_header_scripts'), 10);
				add_action('login_head', array($this, 'print_header_scripts'), 10);
				add_action('wp_print_footer_scripts', array($this, 'print_footer_scripts'), 21);
				add_action('admin_print_scripts', array($this, 'print_header_scripts'), 10);
				add_action('admin_print_footer_scripts', array($this, 'print_footer_scripts'), 21);
			}
		}

		if ('yes' == $this->options['enable_bloginfo'])
		{
			add_filter('stylesheet_uri', array($this, 'minify_item'));
			add_filter('locale_stylesheet_uri', array($this, 'minify_item'));
		}
	}

	function add_hooks()
	{
		if (false === strpos($_SERVER['REQUEST_URI'], 'wp-login.php')
			&& false === strpos($_SERVER['REQUEST_URI'], 'wp-signup.php'))
			add_action('template_redirect', array($this, 'add_conditional_hooks'));
		else
			$this->add_conditional_hooks();
	}

	function enqueue_media()
	{
		if ($this->is_admin_page())
		{
			wp_enqueue_script('bwp-minify', BWP_MINIFY_JS . '/bwp-minify.js',
				array('jquery'), false, true);
			wp_enqueue_style('bwp-minify', BWP_MINIFY_CSS . '/bwp-minify.css');
		}
	}

	/**
	 * Build the Menus
	 * TODO: simple menu version?
	 */
	function build_menus()
	{
		// use fancy dashicons if WP version is 3.8+
		$menu_icon = version_compare($this->ver, '3.8.0', '>=')
			? 'dashicons-performance'
			: BWP_MINIFY_IMAGES . '/icon_menu.png';

		add_menu_page(
			__('Better WordPress Minify', 'bwp-minify'),
			'BWP Minify',
			BWP_MINIFY_CAPABILITY,
			BWP_MINIFY_OPTION_GENERAL,
			array($this, 'build_option_pages'),
			$menu_icon
		);
		// Sub menus
		add_submenu_page(
			BWP_MINIFY_OPTION_GENERAL,
			__('General Options', 'bwp-minify'),
			__('General Options', 'bwp-minify'),
			BWP_MINIFY_CAPABILITY,
			BWP_MINIFY_OPTION_GENERAL,
			array($this, 'build_option_pages')
		);
		add_submenu_page(
			BWP_MINIFY_OPTION_GENERAL,
			__('Manage enqueued Files', 'bwp-minify'),
			__('Enqueued Files', 'bwp-minify'),
			BWP_MINIFY_CAPABILITY,
			BWP_MINIFY_MANAGE,
			array($this, 'build_option_pages')
		);
	}

	/**
	 * Build the option pages
	 *
	 * Utilizes BWP Option Page Builder (@see BWP_OPTION_PAGE)
	 */
	function build_option_pages()
	{
		if (!current_user_can(BWP_MINIFY_CAPABILITY))
			wp_die(__('You do not have sufficient permissions to access this page.'));

		// Init the class
		$page = $_GET['page'];
		$bwp_option_page = new BWP_OPTION_PAGE($page, $this->site_options);

		// Get option from the database, these options are used for both admin pages
		$options = $bwp_option_page->get_db_options(
			BWP_MINIFY_OPTION_GENERAL,
			$this->options
		);


		if (!empty($page))
		{
			if ($page == BWP_MINIFY_OPTION_GENERAL)
			{
				$bwp_option_page->set_current_tab(1);

				$form = array(
					'items' => array(
						'heading',
						'checkbox',
						'checkbox',
						'checkbox',
						'input',
						'input',
						'select',
						'heading',
						'input',
						'input',
						'input',
						'checkbox',
						'checkbox'
					),
					'item_labels' => array
					(
						__('Plugin Functionality', 'bwp-minify'),
						__('Minify JS files automatically?', 'bwp-minify'),
						__('Minify CSS files automatically?', 'bwp-minify'),
						__('Minify <code>bloginfo()</code> stylesheets?', 'bwp-minify'),
						__('Minify Path', 'bwp-minify'),
						__('One minify string will contain', 'bwp-minify'),
						__('Append the minify string with', 'bwp-minify'),
						__('Minify Library Settings', 'bwp-minify'),
						__('Document root', 'bwp-minify'),
						__('Cache directory', 'bwp-minify'),
						__('Cache age', 'bwp-minify'),
						__('Enable bubble CSS import?', 'bwp-minify'),
						__('Enable cache file locking?', 'bwp-minify')
					),
					'item_names' => array(
						'h1',
						'cb1',
						'cb3',
						'cb2',
						'input_minpath',
						'input_maxfiles',
						'select_buster_type',
						'h2',
						'input_doc_root',
						'input_cache_dir',
						'input_maxage',
						'cb4',
						'cb5'
					),
					'heading' => array(
						'h1' => '',
						'h2' => '<a name="minify.config.php"></a>' . sprintf(
							__('<em>These options will let you control how the actual '
							. '<a href="%s" target="_blank">Minify</a> library works.</em>', 'bwp-minify'),
							'https://code.google.com/p/minify/'
						)
					),
					'select' => array(
						'select_time_type' => array(
							__('second(s)', 'bwp-minify') => 1,
							__('minute(s)', 'bwp-minify') => 60,
							__('hour(s)', 'bwp-minify') => 3600,
							__('day(s)', 'bwp-minify') => 86400
						),
						'select_buster_type' => array(
							__('Do not append anything', 'bwp-minify') => 'none',
							__('Cache folder&#8217;s last modified time', 'bwp-minify') => 'mtime',
							__('Your WordPress&#8217;s current version', 'bwp-minify') => 'wpver',
							__('Your theme&#8217;s current version', 'bwp-minify') => 'tver',
							__('A custom number', 'bwp-minify') => 'custom'
						)
					),
					'checkbox' => array(
						'cb1' => array(__('you can still use <code>bwp_minify()</code> helper function if you disable this.', 'bwp-minify') => 'enable_min_js'),
						'cb3' => array(__('you can still use <code>bwp_minify()</code> helper function if you disable this.', 'bwp-minify') => 'enable_min_css'),
						'cb2' => array(__('enable this for themes that use <code>bloginfo()</code> to print the main stylesheet (i.e. <code>style.css</code>). If you want to minify <code>style.css</code> with the rest of your css files, you must enqueue it.', 'bwp-minify') => 'enable_bloginfo'),
						'cb4' => array(sprintf(__('move all <code>@import</code> rules in CSS files to the top. More info <a href="%s" target="_blank">here</a>.', 'bwp-minify'), 'http://code.google.com/p/minify/wiki/CommonProblems#@imports_can_appear_in_invalid_locations_in_combined_CSS_files') => 'enable_css_bubble'),
						'cb5' => array(__('disable this if filesystem is NFS.', 'bwp-minify') => 'enable_cache_file_lock'),
					),
					'input' => array(
						'input_minpath' => array(
							'size' => 55,
							'label' => sprintf(
								__('This should be set automatically. '
								. 'Please read <a href="%s#customization">here</a> '
								. 'to know how to properly modify this.', 'bwp-minify'),
								$this->plugin_url)
						),
						'input_doc_root' => array(
							'size' => 55,
							'label' => '<br />' . __('Leave empty '
								. 'to use PHP\'s <code>$_SERVER[\'DOCUMENT_ROOT\']</code>. '
								. 'On some servers the document root is '
								. 'misconfigured or missing, in that case you must '
								. 'set this to your full document root directory '
								. 'with NO trailing slash.', 'bwp-minify')
						),
						'input_cache_dir' => array(
							'size' => 55,
							'label' => '<br />' . sprintf(
								__('Expect a full path to your desired cache directory. '
								. 'More details can be found '
								. '<a href="%s#advanced_customization" target="_blank">here</a>. '
								. 'Cache directory must be writable '
								. '(i.e. CHMOD to 755 or 777).', 'bwp-minify'),
								$this->plugin_url)
						),
						'input_maxfiles' => array(
							'size' => 3,
							'label' => __('file(s) at most.', 'bwp-minify')
						),
						'input_maxage' => array(
							'size' => 5,
							'label' => __('&mdash;', 'bwp-minify')
						),
						'input_custom_buster' => array(
							'pre' => '<em>&rarr; /min/?f=file.js&#038;ver=</em> ',
							'size' => 12,
							'label' => '.',
							'disabled' => ' disabled="disabled"'
						)
					),
					'container' => array(
						'select_buster_type' => __(
							'<em><strong>Note:</strong> '
							. 'When you append one of the things above '
							. 'you are basically telling browsers '
							. 'to clear their cached version of your CSS and JS files, '
							. 'which is very useful when you change source files. '
							. 'Use this feature wisely :).</em>', 'bwp-minify')
					),
					'inline_fields' => array(
						'input_maxage' => array(
							'select_time_type' => 'select'
						),
						'select_buster_type' => array(
							'input_custom_buster' => 'input'
						)
					),
					'inline' => array(
						'input_cache_dir' => '<br /><br /><input type="submit" '
							. 'class="button-secondary action" name="flush_cache" '
							. 'value="' . __('Flush the cache', 'bwp-minify') . '" />'
					)
				);

				// Get a subset of the option array for this form
				$form_options = array(
					'input_minurl',
					'input_minpath',
					'input_cache_dir',
					'input_doc_root',
					'input_maxfiles',
					'input_maxage',
					'input_custom_buster',
					'enable_min_js',
					'enable_min_css',
					'enable_bloginfo',
					'enable_css_bubble',
					'enable_cache_file_lock',
					'select_buster_type',
					'select_time_type',
				);

				// Flush the cache
				if (isset($_POST['flush_cache']) && !$this->is_normal_admin())
				{
					check_admin_referer($page);

					$deleted = self::_flush_cache($options['input_cache_dir']);
					if (0 < $deleted)
						$this->add_notice(
							'<strong>' . __('Notice', 'bwp-minify') . ':</strong> '
							. sprintf(
								__("<strong>%d</strong> cached files "
								. "have been deleted successfully!", 'bwp-minify'),
								$deleted
							)
						);
					else
						$this->add_notice(
							'<strong>' . __('Notice', 'bwp-minify') . ':</strong> '
							. __("Could not delete any cached files. "
							. "Please manually flush the cache directory.", 'bwp-minify')
						);
				}
			}
			else if ($page == BWP_MINIFY_MANAGE)
			{
				$bwp_option_page->set_current_tab(2);

				remove_action('bwp_option_action_before_form', array($this, 'show_donation'), 12);

				// add a secondary button to clear enqueued file lists (both)
				add_filter('bwp_option_submit_button', array($this, 'add_clear_log_button'));
				if (isset($_POST['clear_log']))
				{
					check_admin_referer($page);

					$this->detector->clear_logs();
					$this->add_notice(
						__('Enqueued file lists have been cleared successfully. '
						. 'Please try visiting a few pages on your site '
						. 'and then refresh this page to see new file lists.', 'bwp-minify')
					);
				}

				$form = array(
					'items' => array(
						'heading',
						'heading'
					),
					'item_labels' => array (
						__('Move enqueued JS files to appropriate positions', 'bwp-minify'),
						__('Move enqueued CSS files to appropriate positions', 'bwp-minify')
					),
					'item_names' => array(
						'h1',
						'h2'
					),
					'heading' => array(
						'h1' => sprintf(
							__('<em> Below you can find a list of enqueued JS files '
							. 'detected by this plugin. Press <strong>select</strong> and '
							. 'then choose an appropriate position for selected JS file. '
							. 'You can also directly type in one script handle (<strong>NOT '
							. 'filename/script src</strong>) per line in the input field if '
							. 'you want.</em> More info <a href="%s#positioning-your-files">here</a>.', 'bwp-minify'),
							$this->plugin_url
						),
						'h2' => sprintf(
							__('<em> Below you can find a list of enqueued CSS files '
							. 'detected by this plugin. Press <strong>select</strong> and '
							. 'then choose an appropriate position for selected CSS file. '
							. 'You can also directly type in one style handle (<strong>NOT '
							. 'filename/style src</strong>) per line in the input field if '
							. 'you want.</em> More info <a href="%s#positioning-your-files">here</a>.', 'bwp-minify'),
							$this->plugin_url
						)
					),
					'textarea' => array (
					),
					'container' => array(
						'h1' => $this->_show_enqueued_scripts(),
						'h2' => $this->_show_enqueued_styles(),
					)
				);

				// Get a subset of the option array for this form
				$form_options = array(
					'input_ignore',
					'input_header',
					'input_direct',
					'input_footer',
					'input_oblivion',
					'input_style_ignore',
					'input_style_direct',
					'input_style_oblivion',
				);
			}
		}

		$option_formats = array(
			'input_maxfiles' => 'int',
			'input_maxage' => 'int',
			'select_time_type' => 'int'
		);

		$option_super_admin = $this->site_options;

		// Get option from user input
		if (isset($_POST['submit_' . $bwp_option_page->get_form_name()])
			&& isset($options) && is_array($options)
		) {
			// Basic security check
			check_admin_referer($page);

			foreach ($options as $key => &$option)
			{
				if (!in_array($key, $form_options)
					|| ($this->is_normal_admin()
					&& in_array($key, $option_super_admin))
				) {
					// field not found in options assigned to current form
					// OR not a super admin, and this is a super-admin only setting
					continue;
				}
				else
				{
					if (isset($_POST[$key]))
						$bwp_option_page->format_field($key, $option_formats);
					if (!isset($_POST[$key]) && !isset($form['input'][$key]['disabled']))
						$option = '';
					else if (isset($option_formats[$key]) && 0 == $_POST[$key] && 'int' == $option_formats[$key])
						$option = 0;
					else if (isset($option_formats[$key]) && empty($_POST[$key]) && 'int' == $option_formats[$key])
						$option = $this->options_default[$key];
					else if (!empty($_POST[$key]))
						$option = trim(stripslashes($_POST[$key]));
					else
						$option = '';
				}
			}

			// update per-blog options
			update_option(BWP_MINIFY_OPTION_GENERAL, $options);
			// if current user is super admin, allow him to update site-only
			// options - this is WPMS compatible
			if (!$this->is_normal_admin())
				update_site_option(BWP_MINIFY_OPTION_GENERAL, $options);

			// refresh the options property to include updated options
			$this->options = array_merge($this->options, $options);

			if ($page == BWP_MINIFY_OPTION_GENERAL) {
				$this->add_notice(__('All options have been saved.', 'bwp-minify'));
			} else if ($page == BWP_MINIFY_MANAGE) {
				$this->add_notice(
					__('All positions have been saved. '
					. 'Try visiting some pages on your site and then '
					. 'refresh this page for updated file lists.', 'bwp-minify')
				);
			}

			// take care of some POST actions when we're on the General Options page
			if ($page == BWP_MINIFY_OPTION_GENERAL && !$this->is_normal_admin())
			{
				// try to save the Minify config file
				$result = $this->create_minify_config_file();
				if (true === $result)
				{
					// config file was successfully written
					$this->add_notice(sprintf(
						__('Minify config file <code>%s</code> '
						. 'has been updated successfully.', 'bwp-minify'),
						$this->min_dir . 'config.php'
					));
				}
				else if ('config' === $result)
				{
					// config file is missing
					// TODO: should update to use `add_error` when all other
					// plugins have been updated
					$this->add_notice(
						'<strong style="color:red">' . __('Error') . ':</strong> '
						. __('Minify config file <code>config.php</code> is not found, '
						. 'please try re-uploading or re-installing this plugin.', 'bwp-minify')
					);
				}
				else if ('write' === $result)
				{
					// the write process failed for some reasons
					// TODO: should update to use `add_error` when all other
					// plugins have been updated
					$this->add_notice(sprintf(
						'<strong style="color:red">' . __('Error') . ':</strong> '
						. __('There was an error writing to Minify config file <code>%s</code>. '
						. 'Please try again.', 'bwp-minify'),
						$this->min_dir . 'config.php'
					));
				}
				else
				{
					// config file is not writable, show the auto-generated
					// contents to admin for manual update
					$this->add_notice(sprintf(
						__('Minify config file <code>%s</code> '
						. 'is not writable. See '
						. '<a href="#minify.config.php">below</a> '
						. 'for details.', 'bwp-minify'),
						$this->min_dir . 'config.php'
					));
					$form['container']['h2'] = $this->_show_generated_config($result);
				}
			}
		}

		// take care of non-POST actions when we're on General Options page
		if ($page == BWP_MINIFY_OPTION_GENERAL)
		{
			// re-generate the buster string preview whenever buster type change
			$options['input_custom_buster'] = $this->get_buster($options['select_buster_type']);
			if ('custom' == $options['select_buster_type'])
				unset($form['input']['input_custom_buster']['disabled']);

			// warns admin that the cache directory is not writable
			if (!empty($options['input_cache_dir'])
				&& !is_writable($options['input_cache_dir'])
				&& !$this->is_normal_admin()
			) {
				$this->add_notice(
					'<strong>' . __('Warning') . ':</strong> '
					. sprintf(
						__("Cache directory <code>%s</code> does not exist or is not writable. "
						. "Please try CHMOD your cache directory to 755. "
						. "If you still see this warning, CHMOD to 777.", 'bwp-minify'),
						$options['input_cache_dir']
					)
				);
			}

			// Guessing the cache directory based on updated min path
			$options['input_cache_dir'] = empty($options['input_cache_dir'])
				? $this->get_cache_dir($options['input_minpath'])
				: $options['input_cache_dir'];

			// Remove all Minify library-related settings if we're in multisite
			// and this is not super-admin
			if ($this->is_normal_admin())
				$bwp_option_page->kill_html_fields($form, array(7,8,9,10,11,12));
		}

		// Assign the form and option array
		$bwp_option_page->init($form, $options, $this->form_tabs);

		// Build the option page
		echo $bwp_option_page->generate_html_form();
	}

	private function _show_enqueued_styles()
	{
		$fields = array(
			'input_style_ignore' => __('Styles to be ignored (not minified)', 'bwp-minify'),
			'input_style_direct' => __('Styles to be minified and then printed separately', 'bwp-minify'),
			'input_style_oblivion' => __('Styles to be forgotten (to remove duplicate style)', 'bwp-minify')
		);

		return $this->_show_enqueued('style', $fields);
	}

	private function _show_enqueued_scripts()
	{
		$fields = array(
			'input_ignore' => __('Scripts to be ignored (not minified)', 'bwp-minify'),
			'input_direct' => __('Scripts to be minified and then printed separately', 'bwp-minify'),
			'input_header' => __('Scripts to be minified in header', 'bwp-minify'),
			'input_footer' => __('Scripts to be minified in footer', 'bwp-minify'),
			'input_oblivion' => __('Scripts to be forgotten (to remove duplicate scripts)', 'bwp-minify')
		);

		return $this->_show_enqueued('script', $fields);
	}

	private function _show_enqueued($type, $fields)
	{
		ob_start();
?>
		<div class="bwp-minify-table-wrapper">
			<div class="bwp-minify-table-scroller">
				<table class="bwp-minify-detector-table" cellpadding="0"
					cellspacing="0" border="0">
<?php
		if ('script' == $type)
			$this->detector->show_detected_scripts();
		else
			$this->detector->show_detected_styles();
?>
				</table>
			</div>
		</div>

		<div class="bwp-sidebar">
			<ul>
<?php
		foreach ($fields as $field => $label) :
			$value = isset($_POST[$field])
				? trim(strip_tags($_POST[$field]))
				: $this->options[$field];
?>
				<li>
					<a class="position-handle" data-position="<?php echo $field; ?>"
						href="#"><span class="bwp-sign">+</span> <?php echo $label ?></a>
					<textarea name="<?php echo $field ?>"
						cols="20" rows="5"><?php esc_html_e($value); ?></textarea>
				</li>
<?php
		endforeach;
?>
			</ul>
		</div>
		<div style="clear: both"></div>
<?php

		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	function add_clear_log_button($button)
	{
		$button = str_replace(
			'</p>',
			'&nbsp; <input type="submit" class="button-secondary action" '
				. 'name="clear_log" value="'
				. __('Clear File Lists', 'bwp-simple-gxs') . '" /></p>',
			$button);

		return $button;
	}

	private function _show_generated_config($contents)
	{
		$output  = __('Could not write Minify library settings to <code>%s</code>. '
			. 'Please update the config file manually using '
			. 'auto-generated contents as shown below:', 'bwp-minify');
		$output  = sprintf($output, $this->min_dir . 'config.php');
		$output .= '<br /><br />';
		$output .= '<textarea rows="16" cols="70">' . $contents . '</textarea>';

		return $output;
	}

	/**
	 * Gets blog path for the current blog in a multisite installation
	 *
	 * @return string
	 */
	private function _get_blog_path()
	{
		global $current_blog;

		if (!$this->is_multisite())
			return '';

		$blog_path = isset($current_blog->path) && '/' != $current_blog->path
			? $current_blog->path
			: '';

		// because `$blog_path` also contains the base, we need to remove the
		// base from it so we can process media source correctly later on
		if (0 === strpos($blog_path, '/' . $this->base . '/'))
			$blog_path = preg_replace('#/[^/]+#ui', '', $blog_path, 1);

		return $blog_path;
	}

	/**
	 * Creates config file for Minify library based on saved settings
	 *
	 * The plugin will try to create/update the `config.php` file that Minify
	 * library uses using saved settings in BWP Minify's admin page. If the
	 * config file is not found or not writable, admin will be given the option
	 * to copy the file's contents and create/update the file manually.
	 *
	 * @since 1.3.0
	 * @return bool|string true if config file is writable or the contents to
	 *         be written to the config file if not writable. Returns 'config'
	 *         if `config.php` file is missing or 'write' if the write process
	 *         fails.
	 */
	function create_minify_config_file()
	{
		$options = $this->options;

		// prepare config entry that can be changed
		$min_cachePath = $options['input_cache_dir'] == $this->get_cache_dir()
			|| empty($options['input_cache_dir'])
			? 'dirname(dirname(__FILE__)) . \'/cache\''
			: "'" . $options['input_cache_dir'] . "'";
		$min_cacheFileLocking = 'yes' == $options['enable_cache_file_lock']
			? 'true' : 'false';
		$min_bubbleCssImports = 'yes' == $options['enable_css_bubble']
			? 'true' : 'false';
		$min_maxAge = (int) $options['input_maxage'] * (int) $options['select_time_type'];
		$min_documentRoot = "'" . untrailingslashit($options['input_doc_root']) . "'";

		$configs = array(
			'min_enableBuilder' => 'false',
			'min_builderPassword' => "'admin'",
			'min_errorLogger' => 'false',
			'min_allowDebugFlag' => 'false',
			'min_cachePath' => $min_cachePath,
			'min_documentRoot' => $min_documentRoot,
			'min_cacheFileLocking' => $min_cacheFileLocking,
			'min_serveOptions[\'bubbleCssImports\']' => $min_bubbleCssImports,
			'min_serveOptions[\'maxAge\']' => $min_maxAge,
			'min_serveOptions[\'minApp\'][\'groupsOnly\']' => 'false',
			'min_symlinks' => 'array()',
			'min_uploaderHoursBehind' => '0',
			'min_libPath' => 'dirname(__FILE__) . \'/lib\'',
			'ini_set(\'zlib.output_compression\', \'0\')' => ''
		);

		$config_lines = array();
		foreach ($configs as $config_key => $config_value)
		{
			if (false === strpos($config_key, 'ini_set'))
				$config_lines[] = '$' . $config_key . ' = ' . $config_value . ';';
			else
				$config_lines[] = $config_key . ';';
		}

		$lines  = '<?php' . PHP_EOL;
		$lines .= implode(PHP_EOL, $config_lines);
		$lines .= PHP_EOL . '// auto-generated on ' . current_time('mysql');

		$min_dir = $this->get_min_dir();
		if (false == $min_dir)
			// Minify directory can not be found, warn admin
			return 'config';

		$config_file = $min_dir . 'config.php';

		if (is_writable($config_file))
		{
			// write Minify config variable to `config.php` file
			if (false === file_put_contents($config_file, $lines))
				return 'write';
			else
				return true;
		}
		else
		{
			// return the contents of the config file for manual update
			return $lines;
		}
	}

	/**
	 * Checks whether the config file needs to be rewritten
	 *
	 * This action should be called by `upgrader_process_complete` action hook
	 * if this plugin is updated individually or in bulk.
	 *
	 * @since 1.3.0
	 */
	function check_minify_config_file($upgrader, $data)
	{
		if (!isset($data['type']) || !isset($data['action'])
			|| 'plugin' != $data['type'] || 'update' != $data['action']
			|| false === strpos($data['plugin'], 'bwp-minify/')
		) {
			return;
		}

		// if user is updating this plugin, try to re-generate `config.php`
		// file silently, we do not handle any error at this stage
		$result = $this->create_minify_config_file();
	}

	/**
	 * Gets the directory where Minify library lives
	 *
	 * @since 1.3.0
	 * @return string|bool false if no directory can be found
	 */
	function get_min_dir()
	{
		if (!empty($this->min_dir))
			return $this->min_dir;

		$min_path = $this->options['input_minpath'];
		$min_path = ltrim($min_path, '/');

		$doc_root = !empty($this->options['input_doc_root'])
			? trim($this->options['input_doc_root'])
			: $_SERVER['DOCUMENT_ROOT'];

		$min_dir  = trailingslashit($doc_root) . $min_path;
		$min_dir  = trailingslashit($min_dir);

		if (false == $this->is_multisite())
		{
			// if this is NOT a multisite environment, and a `config.php` file
			// exists, this is the Minify directory we're looking for
			if (file_exists($min_dir . 'config.php'))
			{
				$this->min_dir = $min_dir;
				return $min_dir;
			}
			else
				return false;
		}
		else
		{
			// if we're in a multi-site environment, we look for Minify's
			// directory in current blog first, and then main blog, and use
			// the first directory that is found
			if (!file_exists($min_dir . 'config.php'))
			{
				// current blog's Minify directory doesn't seem to exist,
				// remove the current blog's path from the directory
				// being checked and try again
				$blog_path = $this->_get_blog_path();

				$min_dir = preg_replace('#' . $blog_path . '#ui', '/', $min_dir, 1);
				if (file_exists($min_dir . 'config.php'))
				{
					$this->min_dir = $min_dir;
					return $min_dir;
				}

				return false;
			}
			else
			{
				$this->min_dir = $min_dir;
				return $min_dir;
			}
		}
	}

	/**
	 * Gets (guess) the current cache directory based on min path
	 *
	 * The default cache directory is `/min/cache/` which can be changed inside
	 * admin area if `/min/config.php` file is writable (@since 1.3.0).
	 */
	function get_cache_dir($min_path = '')
	{
		$guess_cache = $this->get_min_dir();

		if (false == $guess_cache)
			// could not get Minify's directory, let admin manually set it
			return '';

		$guess_cache = dirname($guess_cache) . '/cache/';
		$guess_cache = str_replace('\\', '/', $guess_cache);

		return apply_filters('bwp_minify_cache_dir', $guess_cache);
	}

	private static function _flush_cache($cache_dir)
	{
		$deleted = 0;
		$cache_dir = trailingslashit($cache_dir);

		if (is_dir($cache_dir))
		{
			if ($dh = opendir($cache_dir))
			{
				while (($file = readdir($dh)) !== false)
				{
					if (preg_match('/^minify_[a-z0-9_\-.,]+(\.gz)?$/i', $file))
					{
						@unlink($cache_dir . $file);
						$deleted++;
					}
				}
				closedir($dh);
			}
		}

		return $deleted;
	}

	function parse_positions()
	{
		$positions = array(
			'header'         => $this->options['input_header'],
			'footer'         => $this->options['input_footer'],
			'direct'         => $this->options['input_direct'],
			'ignore'         => $this->options['input_ignore'],
			'oblivion'       => $this->options['input_oblivion'],
			'style_direct'   => $this->options['input_style_direct'],
			'style_ignore'   => $this->options['input_style_ignore'],
			'style_oblivion' => $this->options['input_style_oblivion']
		);

		foreach ($positions as $key => &$position)
		{
			if (!empty($position))
			{
				$position = explode("\n", $position);
				$position = array_map('trim', $position);
			}
			$filter = false === strpos($key, 'style_') ? '_script_' : '_';
			$position = apply_filters('bwp_minify' . $filter . $key, $position);
		}

		$this->print_positions = $positions;
	}

	/**
	 * Sets a base to prepend relative media sources
	 *
	 * The base is deduced from siteurl (the folder where actual WordPress
	 * files are located.), so if WP files are located in /blog instead of
	 * root, the base would be `blog`.
	 *
	 * @uses get_site_option to add support for Multisite
	 * @return void
	 */
	function get_base()
	{
		$site_url = get_site_option('siteurl');
		$base = trim(str_replace($this->http_host, '', $site_url), '/');
		$this->base = $base;
	}

	/**
	 * Gets the buster used to invalidate a cached Minify string
	 *
	 * @return string
	 */
	function get_buster($type)
	{
		$buster = '';

		switch ($type)
		{
			case 'mtime':
				if (file_exists($this->options['input_cache_dir']))
					$buster = filemtime($this->options['input_cache_dir']);
			break;

			case 'wpver':
				$buster = $this->ver;
			break;

			case 'tver':
				if (function_exists('wp_get_theme'))
				{
					$theme = wp_get_theme();
					if ($theme && $theme instanceof WP_Theme)
					{
						$version = $theme->get('Version');
						if (!empty($version))
							$buster = $version;
					}
				}
				else
				{
					$theme = get_theme_data(STYLESHEETPATH . '/style.css');
					if (!empty($theme['Version']))
						$buster = $theme['Version'];
				}
			break;

			case 'custom':
				$buster = $this->options['input_custom_buster'];
			break;

			case 'none':
			default:
				if (is_admin())
					$buster = __('empty', 'bwp-minify');
			break;
		}

		return apply_filters('bwp_minify_get_buster', $buster);
	}

	/**
	 * Checks whether the media files have been included in Minify string or not
	 *
	 * @return bool
	 */
	function is_in($handle, $position = 'header')
	{
		if (!isset($this->print_positions[$position])
			|| !is_array($this->print_positions[$position])
		) {
			return false;
		}

		if (in_array($handle, $this->print_positions[$position]))
			return true;
	}

	/**
	 * Checks if a style has inline styles to print
	 *
	 * @return bool
	 * @see wp-includes/functions.wp-styles.php
	 */
	private static function has_inline($handle)
	{
		global $wp_styles;

		if (isset($wp_styles->registered[$handle]->extra['after']))
			return true;

		return false;
	}

	/**
	 * Check if a sciprt has been localized using wp_localize_script()
	 *
	 * @return bool
	 * @see wp-includes/functions.wp-scripts.php
	 */
	private static function is_l10n($handle)
	{
		global $wp_scripts;

		// Since WordPress 3.3, 'l10n' has been changed into 'data'
		if (isset($wp_scripts->registered[$handle]->extra['l10n'])
			|| isset($wp_scripts->registered[$handle]->extra['data']))
			return true;

		return false;
	}

	/**
	 * Checks if media source is local
	 *
	 * @return bool
	 */
	function is_local($src = '')
	{
		// prepend scheme to scheme-less URLs, to make parse_url work
		if (0 === strpos($src, '//')) {
			$src = 'http:' . $src;
		}

		$url = @parse_url($src);
		$blog_url = @parse_url(home_url());
		if (false === $url)
			return false;

		// If scheme is set
		if (isset($url['scheme']))
		{
			// @since 1.3.0 consider sub-domain external for now
			if (0 <> strcmp($url['host'], $blog_url['host']))
				return false;

			return true;
		}
		else // Probably a relative link
			return true;
	}

	/**
	 * Make sure the source is valid.
	 *
	 * @since 1.0.3
	 */
	function is_source_static($src = '')
	{
		// Source that doesn't have .css or .js extesion is dynamic
		if (!preg_match('#[^,]+\.(css|js)$#ui', $src))
			return false;

		// Source that contains ?, =, & is dynamic
		if (strpos($src, '?') === false
			&& strpos($src, '=') === false
			&& strpos($src, '&') === false)
			return true;

		return false;
	}

	/**
	 * Formats media source before adding to Minify string
	 *
	 * @return string
	 */
	function process_media_source($src = '')
	{
		$src = trim($src);

		// handle possible scheme-less urls
		if ('//' === substr($src, 0, 2))
			$src = is_ssl() ? 'https:' . $src : 'http:' . $src;

		// Absolute url
		if (0 === strpos($src, 'http'))
		{
			// handle both http and https, can't assume `$src` is properly setup
			// with appropriate scheme
			$site_url = $this->http_host;
			if (0 <> strncmp($site_url, $src, 5)) {
				// site_url and $src don't share the same scheme, wonder how?
				$site_url = 0 === strpos($site_url, 'https')
					? str_replace('https:', 'http:', $site_url)
					: str_replace('http:', 'https:', $site_url);
			}

			// we need to remove blog path from `$src` as well, this is for
			// compatibility with sub-directory multisite installation
			$blog_path = $this->_get_blog_path();
			$src = str_replace($site_url, '', $src);
			$src = preg_replace('#' . $blog_path . '#ui', '/', $src, 1);
		}
		else if ('/' === substr($src, 0, 1))
		{
			// root relative url
			if (false !== strpos($src, 'wp-includes')
				|| false !== strpos($src, 'wp-admin')
				|| (false !== strpos($src, 'wp-content')
					&& false !== strpos(content_url(), '/' . $this->base . '/'))
			) {
				// Add base for relative media source. Because `wp_content`
				// folder can be moved away from where WordPress's files are
				// located, only add a base before `wp-content` if it is
				// expected.
				$src = $this->base . $src;
			}
		}

		// @since 1.0.3
		$src = str_replace('./', '/', $src);
		$src = str_replace('\\', '/', $src);
		$src = preg_replace('#[/]+#iu', '/', $src);
		$src = ltrim($src, '/');

		return $src;
	}

	/**
	 * Gets the Minify string to be printed
	 *
	 * @return string
	 */
	function get_minify_src($string)
	{
		if (empty($string))
			return '';

		$buster   = !empty($this->buster) ? '&#038;ver=' . $this->buster : '';
		$min_url  = $this->min_url;

		return apply_filters('bwp_get_minify_src',
			trailingslashit($min_url) . '?f=' . $string . $buster,
			$string, $buster, $min_url
		);
	}

	function get_minify_tag($type, $group, $group_handle)
	{
		if (!isset($group['string']))
			return '';

		$string = implode(',', $group['string']);
		$string = $this->get_minify_src($string);
		$media  = isset($group['media']) ? $group['media'] : false;
		$title  = isset($group['alt']) ? $group['alt'] : false;
		$if     = isset($group['if']) ? $group['if'] : false;

		switch ($type)
		{
			case 'script':
				$return  = "<script type='text/javascript' src='"
					. esc_url($string)
					. "'></script>\n";
			break;

			case 'style':
				$return = "<link rel='stylesheet' id='"
					. $group_handle . "-group-css' href='"
					. esc_url($string) . "' type='text/css' media='"
					. esc_attr($media) . "' />\n";

				if ($title)
				{
					$return = "<link rel='alternate stylesheet' id='"
						. $group_handle . "-group-css' title='"
						. esc_attr($title) . "' href='"
						. esc_url($string) . "' type='text/css' media='"
						. esc_attr($media) . "' />\n";
				}
			break;
		}

		if ($if)
			$return = "<!--[if " . esc_html($if) . "]>\n" . $return . "<![endif]-->\n";

		return apply_filters('bwp_get_minify_tag', $return, $string, $type, $group);
	}

	function minify_item($src)
	{
		return $this->get_minify_src($this->process_media_source($src));
	}

	function init_todo_item($handle, $type = 'script')
	{
		global $wp_scripts, $wp_styles;

		$media  = 'script' == $type ? $wp_scripts : $wp_styles;
		$prefix = 'script' == $type ? '' : 'style_';
		$item = $media->registered[$handle];

		// if $src contains minify url and/or the buster, we need to strip them
		$item->src = str_replace($this->min_url . '?f=', '', $item->src);
		$item->src = str_replace('&#038;ver=' . $this->buster, '', $item->src);
		$src = $item->src;

		$todo_item = array(
			'position' => 'dummy', // 'dummy', 'header', 'footer', or 'footer{n}'
			'min' => true, // expect to be minified
			'wp' => false, // expect to be handled by WP
			'forget' => false, // put into oblivion or not
			'depend' => false,
			'group' => false
		);

		// look for dependencies of this item
		$deps = $item->deps;
		if ($deps && is_array($deps) && 0 < sizeof($deps)) {
			$todo_item['depend'] = $deps;
		}

		if (empty($src))
			return $todo_item;

		if ($this->is_in($handle, $prefix . 'oblivion'))
		{
			// this item is put into oblivion (forget)
			$todo_item['min'] = false;
			$todo_item['forget'] = true;
			$todo_item['position'] = 'oblivion';
			$todo_item['src'] = $src;
		}
		else if (('all' != $this->print_positions[$prefix . 'allowed']
			&& !$this->is_in($handle, $prefix . 'allowed'))
			|| !$this->is_source_static($src) || !$this->is_local($src)
		) {
			// If this item is specifically disallowed to be minified
			// OR if this item is dynamic or external, no minify
			$todo_item['min'] = false;
		}
		else if ($this->is_in($handle, $prefix . 'ignore')
			|| $this->is_in($handle, $prefix . 'direct')
		) {
			// if this item belongs to 'ignore', no minify is needed
			if ($this->is_in($handle, $prefix . 'ignore'))
				$todo_item['min'] = false;

			// let WordPress handle the output
			$todo_item['wp'] = true;
		}

		return $todo_item;
	}

	/**
	 * Checks if a group is a dependency of some groups
	 *
	 * @param $group string the group handle that needs checking
	 * @param $groups array a list of group handles to check against
	 * @param $type string either 'script' or 'style'
	 * @return bool
	 */
	function is_a_dependency($group, $groups, $type)
	{
		if (0 == sizeof($groups))
			return false;

		$result = false;
		$min_groups = 'script' == $type ? $this->min_scripts : $this->min_styles;

		foreach ($groups as $_group => $active)
		{
			// if it's the same group handle no need to check
			if ($group == $_group)
				continue;

			$group_deps = $min_groups[$_group]['depend'];
			if (array_key_exists($group, $group_deps))
				return true;
			else
				$result = $this->is_a_dependency($group, $group_deps, $type);

			if ($result)
				return true;
		}

		return $result;
	}

	function get_dependencies($item, $type)
	{
		$deps = array();
		$todo = 'script' == $type ? $this->todo_scripts : $this->todo_styles;

		foreach ($item['depend'] as $dep)
		{
			if (isset($todo[$dep]))
				$deps[$dep] = $todo[$dep];
		}

		return $deps;
	}

	/**
	 * Builds a list of printable groups from an internal _todo_ list
	 *
	 * A group can be one of the following types:
	 * 1. A Minify group: files are minified and combined, printed together
	 * 2. A WP Minify group: files are minified but separately printed
	 * 3. A WP group: files are NOT minified and separately printed
	 *
	 * Apply to a Minify group: If the number of files per group reaches a
	 * limit (by default the limit is 10) this plugin will split the minify
	 * string into an appropriate number of <link> tags. On some server if the
	 * minify string is too long it could trigger a 500 Internal Server error.
	 *
	 * @since 1.3.0
	 */
	function minify($todo, $type, $recursive = false)
	{
		$group_deps = array();

		foreach ($todo as $handle => $item)
		{
			$group_handle = $handle;

			// only process item that is not in any group yet
			if ($item['group'])
			{
				if ($recursive)
				{
					// if we are looking for group dependencies, save the
					// current item's group
					$group_deps[$item['group']] = 1;
				}
				continue;
			}

			// when not in recrusive mode, only process actual items
			if ('dummy' == $item['position'] && !$recursive)
				continue;

			// if this item has dependencies, and we're not resolving
			// dependencies already OR this is a dummy item, we create group
			// for them first
			if ((!$recursive || 'dummy' == $item['position'])
				&& $item['depend'] && 0 < sizeof($item['depend'])
			) {
				// recursively resolve dependencies
				$deps = $this->get_dependencies($item, $type);
				$group_deps = array_merge(
					$group_deps,
					$this->minify($deps, $type, true)
				);
			}

			// if this is a dummy item, no processing
			if ('dummy' == $item['position'])
				continue;

			if ('script' == $type)
				$this->minify_script($handle, $item, $group_handle, $group_deps);
			else
				$this->minify_style($handle, $item, $group_handle, $group_deps);

			if (!$recursive)
				$group_deps = array();
		}

		return $group_deps;
	}

	/**
	 * Builds a list of internal $todo_styles from WP's $todo
	 *
	 * @since 1.3.0
	 */
	function add_styles($todo)
	{
		global $wp_styles;

		// @since 1.3.0 add 'dashicons' to default ignore list
		$this->print_positions['style_allowed'] = apply_filters('bwp_minify_allowed_styles', 'all');

		foreach ($todo as $handle)
		{
			$style = $wp_styles->registered[$handle];
			$todo_style = $this->init_todo_item($handle, 'style');

			if (empty($style->src))
			{
				if ($todo_styles['depend'])
				{
					$this->todo_styles[$handle] = $todo_style;
					$wp_styles->done[] = $handle;
				}
				continue;
			}

			$todo_style['media'] = 'all'; // can be 'all', 'print', etc.
			$todo_style['if'] = '';
			$todo_style['alt'] = '';

			// if this style has different media type, set it
			if (!empty($style->args) && 'all' != $style->args)
				$todo_style['media'] = trim($style->args);

			// if this style needs conditional statement (e.g. IE-specific
			// stylesheets)
			if (!empty($style->extra['conditional']))
				$todo_style['if'] = trim($style->extra['conditional']);

			// if this style is an alternate stylesheet (@link
			// http://www.w3.org/TR/REC-html40/present/styles.html#h-14.3.1)
			if (!empty($style->extra['alt']))
			{
				$todo_style['alt'] = isset($style->extra['title'])
					? trim($style->extra['title'])
					: '';
			}

			// if this style has a RTL version, and the current text direction
			// setting is RTL
			if ('rtl' === $wp_styles->text_direction
				&& !empty($style->extra['rtl'])
			) {
				if (is_bool($style->extra['rtl'])
					|| 'replace' === $style->extra['rtl']
				) {
					// replace the style's original src with RTL version
					$suffix = isset($style->extra['suffix'])
						? $style->extra['suffix']
						: '';
					$rtl_src = str_replace("{$suffix}.css", "-rtl{$suffix}.css", $style->src);
					$todo_style['src'] = $rtl_src;
				}
				else
				{
					// add a new todo_rtl as a clone of current todo_style and
					// make todo_style its dependency
					$rtl_src = trim($style->extra['rtl']);
					$todo_rtl = $todo_style;
					$todo_rtl['src'] = $rtl_src;
					$todo_rtl['depend'] = array($handle);
					$todo_rtl['min'] = $this->is_source_static($rtl_src) && $this->is_local($rtl_src);
				}
			}

			if (true === $todo_style['forget'])
			{
				// this style is forgotten, we don't process it, and tell
				// WordPress to forget it as well. We also need to detect this
				// style as forgotten, the same goes for script
				do_action('bwp_minify_processed_style', $handle, $todo_style);
				$wp_styles->done[] = $handle;
				continue;
			}

			if (did_action('bwp_minify_after_header_styles'))
			{
				// if this style is registered after the styles are printed, it is
				// expected in footer
				$todo_style['position'] = did_action('bwp_minify_after_footer_styles')
					? 'footer' . $this->late_style_order
					: 'footer';
			}
			else
			{
				// this style belongs to header
				$todo_style['position'] = 'header';
			}

			$this->todo_styles[$handle] = $todo_style;

			if (!empty($todo_rtl['src']))
			{
				// this style needs a separate RTL stylesheet
				$this->todo_styles[$handle . '_rtl'] = $todo_rtl;
				$todo_rtl = false;
			}

			$wp_styles->done[] = $handle;
		}

		// start minifying
		$this->minify($this->todo_styles, 'style');

		// if late styles are found, print them now
		if (!empty($this->todo_late_styles))
		{
			$this->print_styles($this->todo_late_styles);
			$this->todo_late_styles = '';
			$this->late_style_order++;
		}

		// no more $todo for WordPress because we have done it all
		return array();
	}

	/**
	 * Captures very late styles to print later
	 *
	 * @since 1.3.0
	 */
	function add_late_styles()
	{
		// only procceed if we have finished printing footer styles
		if (did_action('bwp_minify_after_footer_styles'))
			$this->todo_late_styles = 'footer' . $this->late_style_order;
	}

	function minify_style($handle, $item, $group_handle, $group_deps)
	{
		global $wp_styles;

		$style = $wp_styles->registered[$handle];
		$src = !empty($item['src']) ? $item['src'] : $style->src;

		if ($item['min'])
		{
			// minify is needed
			$src = $this->process_media_source($src);

			// if WordPress handles this item make sure the $src is
			// processed and version is not set
			if ($item['wp'])
			{
				// because WordPress needs original $src, we process the
				// original $style->src, not the pre-processed $src.
				$style->src = $this->get_minify_src($this->process_media_source($style->src));
				$style->ver = NULL;
				// add a new group to hold this item
				$this->min_styles[$group_handle] = array(
					'depend' => $group_deps,
					'handle' => $handle,
					'position' => $item['position']
				);
				$item['group'] = $group_handle;
			}
			else
			{
				$group_handle = false;
				foreach ($this->min_styles as $_group_handle => $_group)
				{
					// pick the first available group that is:
					// 1. in the same position (header or footer)
					// 2. has the same media type
					// 3. same conditional type, if is a condiional style
					// 4. same alternate title, if is an alternate style
					// 5. still has room for more scripts
					// but do not take into account group that is
					// dependency of this item's own dependencies.
					if (isset($_group['string']) && $_group['position'] == $item['position']
						&& $this->options['input_maxfiles'] > sizeof($_group['string'])
						&& !$this->is_a_dependency($_group_handle, $group_deps, 'style')
					) {
						$is_same_media = $_group['media'] == $item['media'];
						$is_same_if = $_group['if'] == $item['if'];
						$is_same_alt = $_group['alt'] == $item['alt'];
						if ($is_same_media && $is_same_if && $is_same_alt)
						{
							$group_handle = $_group_handle;
							break;
						}
					}
				}

				if ($group_handle)
				{
					// append to selected group's minify string, only if
					// this $src has not been added before
					$group = &$this->min_styles[$group_handle];
					if (!in_array($src, $group['string']))
					{
						$group['string'][] = $src;

						// make sure we don't self-referencing the
						// group_handle within this item's dependencies
						if (isset($group_deps[$group_handle]))
							unset($group_deps[$group_handle]);

						// merge this item's dependencies with the selected
						// group's deps.
						$group['depend'] = array_merge($group['depend'], $group_deps);
						$item['group'] = $group_handle;
					}
				}
				else
				{
					// otherwise make a new group
					$group_handle = $handle;
					$this->min_styles[$group_handle] = array(
						'depend' => $group_deps,
						'string' => array($src),
						'position' => $item['position'],
						'media' => $item['media'],
						'alt' => $item['alt'],
						'if' => $item['if']
					);
					$item['group'] = $group_handle;
				}
			}
		}
		else
		{
			// no minify is needed, add new group to hold this item
			$this->min_styles[$group_handle] = array(
				'depend' => $group_deps,
				'handle' => $handle,
				'position' => $item['position']
			);
			$item['group'] = $group_handle;
		}

		// if this item has inline styles, mark it to process later
		if (self::has_inline($handle))
			$this->todo_inline_styles[$group_handle][] = $handle;

		// update the internal _Todo_ list
		$item['src'] = $src;
		$this->todo_styles[$handle] = $item;

		do_action('bwp_minify_processed_style', $handle, $item);
	}

	/**
	 * Prints Minify string for CSS files recursively
	 *
	 * This function will traverse all style groups recursively to print styles
	 * while retaining dependencies. Use actions provided to add other things
	 * before or after the output.
	 *
	 * @since 1.3.0
	 * @uses $wp_styles
	 * @return void
	 */
	function print_styles($position = 'header', $groups = false, $recursive = false)
	{
		global $wp_styles;

		if (!$recursive)
			do_action('bwp_minify_before_' . $position . '_styles');

		$groups = !$groups ? $this->min_styles : $groups;
		$group_position = $position;

		foreach ($groups as $group_handle => $group)
		{
			// if group is already done, do not procceed
			if (!empty($this->min_styles[$group_handle]['done']))
				continue;

			// if this is not the correct position for the group, halt the
			// entire loop but return the correct position so the offending group
			// can update itself
			if ($group['position'] != $position)
				if ($recursive)
					return $group['position'];
				else
					continue;

			// print dependencies first
			$deps = array();
			if (0 < sizeof($group['depend']))
			{
				foreach ($group['depend'] as $dep => $active)
				{
					if (isset($this->min_styles[$dep]))
						$deps[$dep] = $this->min_styles[$dep];
				}
				$group_position = $this->print_styles($position, $deps, true);
			}

			// update this group's position if dependencies can not be resolved
			// and ignore this group for now
			if ($group_position != $position)
			{
				// this group needs to move to a new position, trigger events
				do_action('bwp_minify_moved_group', 'style',
					$group_handle,
					$group_position
				);
				$this->min_styles[$group_handle]['position'] = $group_position;
				continue;
			}

			if (isset($group['string']) && 0 < sizeof($group['string']))
			{
				// if this is a minify string
				echo $this->get_minify_tag('style', $group, $group_handle);
				// print inline style after if this group has any
				if (isset($this->todo_inline_styles[$group_handle]))
					$this->print_inline_styles($this->todo_inline_styles[$group_handle]);
			}
			else if (!empty($group['handle']))
			{
				// if this should be handled by WordPress
				$wp_styles->do_item($group['handle']);
			}

			$this->min_styles[$group_handle]['done'] = true;
		}

		if (!$recursive)
		{
			do_action('bwp_minify_after_' . $position . '_styles');
			// save detector's log whenever we finish printing a footer{n} batch
			if (false !== strpos($position, 'footer' . $this->late_style_order))
				$this->detector->commit_logs();
		}

		return $group_position;
	}

	function print_header_styles()
	{
		$this->print_styles();

		// fire an action after header styles have been printed
		do_action('bwp_minify_printed_header_styles');
	}

	function print_footer_styles()
	{
		$this->print_styles('footer');

		// fire an action after footer styles have been print
		do_action('bwp_minify_printed_footer_styles');
	}

	function print_inline_styles($handles)
	{
		global $wp_styles;

		// this feature is only available on WP 3.3 or higher
		if (version_compare($this->ver, '3.3', '<'))
			return;

		foreach ($handles as $handle)
			$wp_styles->print_inline_style($handle);
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_media_styles()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_dynamic_media_styles()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_dynamic_styles()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * Builds a list of internal $todo_scripts from WP's $todo
	 *
	 * @since 1.3.0
	 */
	function add_scripts($todo)
	{
		global $wp_scripts;

		// Avoid conflict with WordPress 3.1
		if (1 == sizeof($todo) && isset($todo[0]) && 'l10n' == $todo[0])
			return array();

		// @since 1.0.5 - 1.0.6
		$this->print_positions['allowed'] = apply_filters('bwp_minify_allowed_scripts', 'all');

		foreach ($todo as $handle)
		{
			$script = $wp_scripts->registered[$handle];
			$todo_script = $this->init_todo_item($handle);

			if (empty($script->src))
			{
				if ($todo_script['depend'])
				{
					// script's src is empty/invalid but it has dependencies so
					// it's probably a dummy script used to load other scripts
					$this->todo_scripts[$handle] = $todo_script;
					$wp_scripts->done[] = $handle;
				}
				continue;
			}

			if ('jquery-migrate' == $handle) {
				// jquery-migrate and jquery-core might get separated so we
				// force jquery-migrate to have jquery-core as its dependecy
				$todo_script['depend'] = false == $todo_script['depend']
					? array() : $todo_script['depend'];
				$todo_script['depend'][] = 'jquery-core';
			}

			if (true === $todo_script['forget'])
			{
				do_action('bwp_minify_processed_script', $handle, $todo_script);
				$wp_scripts->done[] = $handle;
				continue;
			}

			// if this script is registered in footer, or it is registered
			// after the header scripts are printed, it is expected in footer
			$expected_in_footer = isset($wp_scripts->groups[$handle])
				&& 0 < $wp_scripts->groups[$handle]
				|| did_action('bwp_minify_after_header_scripts')
				? true : false;

			// determine the position of the script
			if (!$this->is_in($handle, 'header')
				&& ($this->is_in($handle, 'footer') || $expected_in_footer)
			) {
				// if this script belongs to footer (logically or
				// 'intentionally') and is not 'forced' to be in header
				$todo_script['position'] = did_action('bwp_minify_after_footer_scripts')
					? 'footer' . $this->late_script_order
					: 'footer';
			}
			else
			{
				// this script belongs to header
				$todo_script['position'] = 'header';
			}

			$this->todo_scripts[$handle] = $todo_script;
			$wp_scripts->done[] = $handle;
		}

		// start minifying
		$this->minify($this->todo_scripts, 'script');

		// if late scripts are found, print them now
		if (!empty($this->todo_late_scripts))
		{
			$this->print_scripts($this->todo_late_scripts);
			$this->todo_late_scripts = '';
			$this->late_script_order++;
		}

		// no more $todo for WordPress because we have done it all
		return array();
	}

	/**
	 * Captures very late scripts to print later
	 *
	 * @since 1.3.0
	 */
	function add_late_scripts()
	{
		// only procceed if we have finished printing footer scripts
		if (did_action('bwp_minify_after_footer_scripts'))
			$this->todo_late_scripts = 'footer' . $this->late_script_order;
	}

	function minify_script($handle, $item, $group_handle, $group_deps)
	{
		global $wp_scripts;

		$script = $wp_scripts->registered[$handle];
		$src = $script->src;

		if ($item['min'])
		{
			// minify is needed
			$src = $this->process_media_source($src);

			if ($item['wp'])
			{
				// if WordPress handles this item make sure the $src is
				// processed and version is not set
				$script->src = $this->get_minify_src($src);
				$script->ver = NULL;
				// add a new group to hold this item
				$this->min_scripts[$group_handle] = array(
					'depend' => $group_deps,
					'handle' => $handle,
					'position' => $item['position']
				);
				$item['group'] = $group_handle;
			}
			else
			{
				$group_handle = false;
				foreach ($this->min_scripts as $_group_handle => $_group)
				{
					// pick the first available group in the same position
					// (header or footer) that still has room for more scripts,
					// but do not take into account group that is a
					// dependency of this item's own dependencies.
					if (isset($_group['string']) && $_group['position'] == $item['position']
						&& $this->options['input_maxfiles'] > sizeof($_group['string'])
						&& !$this->is_a_dependency($_group_handle, $group_deps, 'script')
					) {
						$group_handle = $_group_handle;
						break;
					}
				}

				if ($group_handle)
				{
					// append to selected group's minify string, only if
					// this $src has not been added before
					$group = &$this->min_scripts[$group_handle];
					if (!in_array($src, $group['string']))
					{
						$group['string'][] = $src;

						// make sure we don't self-referencing the
						// group_handle within this item's dependencies
						if (isset($group_deps[$group_handle]))
							unset($group_deps[$group_handle]);

						// merge this item's dependencies with the selected
						// group's deps.
						$group['depend'] = array_merge($group['depend'], $group_deps);
						$item['group'] = $group_handle;
					}
				}
				else
				{
					// otherwise make a new group
					$group_handle = $handle;
					$this->min_scripts[$group_handle] = array(
						'depend' => $group_deps,
						'string' => array($src),
						'position' => $item['position']
					);
					$item['group'] = $group_handle;
				}
			}
		}
		else
		{
			// no minify is needed, add new group to hold this item
			$this->min_scripts[$group_handle] = array(
				'depend' => $group_deps,
				'handle' => $handle,
				'position' => $item['position']
			);
			$item['group'] = $group_handle;
		}

		// if this item has l10n data, mark it to process later
		if (self::is_l10n($handle))
			$this->todo_l10n[$group_handle][] = $handle;

		// update the internal _Todo_ list
		$item['src'] = $src;
		$this->todo_scripts[$handle] = $item;

		do_action('bwp_minify_processed_script', $handle, $item);
	}

	/**
	 * Prints Minify string for JS files recursively
	 *
	 * This function will traverse all script groups recursively to print scripts
	 * while retaining dependencies. Use actions provided to add other things
	 * before or after the output.
	 *
	 * @since 1.3.0
	 * @uses $wp_scripts
	 * @return void
	 */
	function print_scripts($position = 'header', $groups = false, $recursive = false)
	{
		global $wp_scripts;

		if (!$recursive)
			do_action('bwp_minify_before_' . $position . '_scripts');

		$groups = !$groups ? $this->min_scripts : $groups;
		$group_position = $position;

		foreach ($groups as $group_handle => $group)
		{
			// if group is already done, no need to process anything
			if (!empty($this->min_scripts[$group_handle]['done']))
				continue;

			// if this is not the correct position for the group
			if ($group['position'] != $position)
				if ($recursive)
					return $group['position'];
				else
					continue;

			// print dependencies first
			$deps = array();
			if (0 < sizeof($group['depend']))
			{
				foreach ($group['depend'] as $dep => $active)
				{
					if (isset($this->min_scripts[$dep]))
						$deps[$dep] = $this->min_scripts[$dep];
				}
				$group_position = $this->print_scripts($position, $deps, true);
			}

			// resolve dependencies failed, ignore this group for now
			if ($group_position != $position)
			{
				// this group needs to move to a new position, trigger events
				do_action('bwp_minify_moved_group', 'script',
					$group_handle,
					$group_position
				);
				$this->min_scripts[$group_handle]['position'] = $group_position;
				continue;
			}

			// print this group using minify tag or $wp_scripts->do_item
			if (isset($group['string']) && 0 < sizeof($group['string']))
			{
				// print l10n data first if this group has any
				if (isset($this->todo_l10n[$group_handle]))
					$this->print_scripts_l10n($this->todo_l10n[$group_handle]);
				// if this is a minify string
				echo $this->get_minify_tag('script', $group, $group_handle);
			}
			else if (!empty($group['handle']))
			{
				// if this should be handled by WordPress
				$wp_scripts->do_item($group['handle']);
			}

			$this->min_scripts[$group_handle]['done'] = true;
		}

		if (!$recursive)
		{
			do_action('bwp_minify_after_' . $position . '_scripts');
			// save detector's log whenever we finish printing a footer batch
			if (false !== strpos($position, 'footer'))
				$this->detector->commit_logs();
		}

		return $group_position;
	}

	function print_header_scripts()
	{
		$this->print_scripts();

		// fire an action after header scripts have been printed
		do_action('bwp_minify_printed_header_scripts');
	}

	function print_footer_scripts()
	{
		$this->print_scripts('footer');

		// fire an action after footer scripts have been print
		do_action('bwp_minify_printed_footer_scripts');
	}

	function print_scripts_l10n($handles)
	{
		global $wp_scripts;

		foreach ($handles as $handle)
		{
			// since WordPress version 3.3: uses
			// $wp_scripts->print_extra_script instead to print l10n info
			if (version_compare($this->ver, '3.3', '>='))
				$wp_scripts->print_extra_script($handle);
			else
				$wp_scripts->print_scripts_l10n($handle);
		}
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_dynamic_scripts($type = 'header')
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_dynamic_header_scripts()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_dynamic_footer_scripts()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_header_scripts_l10n()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}

	/**
	 * @deprecated 1.3.0
	 */
	function print_footer_scripts_l10n()
	{
		_deprecated_function(__FUNCTION__, '1.3.0 (BWP Minify)');
	}
}

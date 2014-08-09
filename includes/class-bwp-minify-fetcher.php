<?php
/**
 * Copyright (c) 2014 Khang Minh <http://betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * Class BWP_Minify_Fetcher
 * @author Khang Minh <contact@betterwp.net>
 * @since BWP Minify 1.3.0
 * @package BWP Minify
 */
class BWP_Minify_Fetcher
{
	private $_domain = '';

	private $_options = array();

	private $_main;

	private $_detector;

	private $_min_url = '';

	private $_min_fly_url = '';

	private $_rewrite_pattern = '';

	private $_rewrite_rules_ready = false;

	private $_cache_dir = '';

	private $_group_cache = array();

	private $_minify_version = '2.1.7';

	public function __construct($options, $domain)
	{
		$this->_options = $options;
		$this->_domain  = $domain;

		$this->_init();
	}

	public function set_main($main)
	{
		$this->_main = $main;
	}

	public function set_detector($detector)
	{
		$this->_detector = $detector;
	}

	public function set_min_url($min_url)
	{
		$this->_min_url = $min_url;
	}

	public function set_min_fly_url($min_fly_url)
	{
		$this->_min_fly_url = $min_fly_url;
	}

	public function set_rewrite_pattern($pattern)
	{
		$this->_rewrite_pattern = $pattern;
	}

	/**
	 * Serves friendly minify url with minified contents
	 *
	 * This actually does a remote get from Minify's dir with appropriate `?f`
	 * query variable built from persistently stored detected enqueued files.
	 * All minified contents as well as headers should be preserved.
	 *
	 * Serving friendly minify urls like this cause a lot of overheads, but is
	 * safer to use than server rewrite rules.
	 *
	 * @uses wp_remote_get()
	 * @return void
	 */
	public function serve($query)
	{
		$group_handle = isset($_GET['min_group'])
			? $this->_sanitize_request($_GET['min_group'])
			: '';
		$type = isset($_GET['min_type'])
			&& in_array($_GET['min_type'], array('js', 'css'))
			? $_GET['min_type']
			: '';
		$bid = isset($_GET['blog']) ? (int) $_GET['blog'] : 0;

		if (empty($group_handle) || empty($type) || empty($bid))
			// if missing any important data, do not proceed
			return;

		global $blog_id;
		if ($bid != $blog_id)
			// if not on the correct blog, do not proceed
			return;

		$group = $this->_detector->get_group($group_handle);
		if (false == $group)
		{
			// this should not happen, but just in case it does
			echo sprintf(
				__('Minify group %s not found.', $this->_domain),
				$group_handle
			);

			exit;
		}

		// load Minify class based on default or custom Minify directory
		$min_dir = $this->_main->get_min_dir();

		if (empty($min_dir) || !file_exists($min_dir . 'config.php'))
		{
			// if Minify directory is not valid or Minify library is not found
			// we can not continue
			_e('Invalid Minify directory, please check Minify settings.', $this->_domain);
			exit;
		}

		// prepare input for Minify to handle
		$_GET['f'] = $group['string'];

		if ($this->_options['enable_debug'] == 'yes')
		{
			// add debug flag if needed
			$_GET['debug'] = 1;
		}
		else
		{
			// minified contents are often served compressed so it's best to turn off
			// error reporting to avoid content encoding error, unless debug is
			// enabled. This is useful when Minify doesn't catch all the notices
			// (for example when the cache directory is not writable).
			error_reporting(0);
		}

		// load Minify classes
		define('MINIFY_MIN_DIR', $min_dir);
		require_once MINIFY_MIN_DIR . '/config.php';
		require_once $min_libPath . '/Minify/Loader.php';
		Minify_Loader::register();

		// set some optional for the Minify application based on current settings
		Minify::$uploaderHoursBehind = $min_uploaderHoursBehind;

		// set cache file name (id) and cache path if needed
		Minify::setCacheId('minify-b' . $blog_id . '-' . $group_handle . '.' . $type);
		Minify::setCache(
			isset($min_cachePath) ? $min_cachePath : '',
			$min_cacheFileLocking
		);

		if ($min_documentRoot)
		{
			$_SERVER['DOCUMENT_ROOT'] = $min_documentRoot;
			Minify::$isDocRootSet     = true;
		}

		// set serve options for each file type if needed
		$min_serveOptions['minifierOptions']['text/css']['symlinks'] = $min_symlinks;
		foreach ($min_symlinks as $uri => $target)
			$min_serveOptions['minApp']['allowDirs'][] = $target;

		if ($min_allowDebugFlag)
		{
			// allow debugging Minify application
			$min_serveOptions['debug'] = Minify_DebugDetector::shouldDebugRequest(
				$_COOKIE,
				$_GET,
				$_SERVER['REQUEST_URI']
			);
		}

		if ($min_errorLogger)
		{
			// log Minify error if allowed
			if (true === $min_errorLogger)
				$min_errorLogger = FirePHP::getInstance(true);

			Minify_Logger::setLogger($min_errorLogger);
		}

		// serve minified contents, on the fly or from cache
		$min_serveController = new Minify_Controller_MinApp();
		Minify::serve($min_serveController, $min_serveOptions);

		// for a proper response headers
		exit;
	}

	/**
	 * Converts a regular Minify url to a more friendly one
	 *
	 * A regular Minify url is only converted when all enqueued files
	 * contained in it have already been detected (logged and saved in
	 * database). This is to make sure that when a friendly url is requested
	 * the fetcher knows what files to serve based on group handle.
	 *
	 * @param $string string the Minify url with min path prepended
	 * @param $original_string string the Minify url without min path
	 * @return string
	 */
	public function friendlify_src($string, $original_string, $group_handle, $buster)
	{
		// need to check whether a valid minify urls is set, if not we should
		// return the regular Minify string
		if (false === $this->_min_fly_url)
			return $string;

		// get extension from minify string, this determines the type of source
		// we're dealing with
		$ext = preg_match('/\.([^\.]+)$/ui', $original_string, $matches)
			? $matches[1] : '';

		if (empty($ext))
			return $string;

		$detector_version = $this->_detector->get_version();
		$group_hash       = md5($original_string . $detector_version);

		if (false == $this->_is_group_detected($group_hash))
		{
			// if this group has not been detected, do so. When this friendly
			// url is served it should use data from the just-detected group
			// because this group has not been persistently stored.
			$group_type = 'js' == $ext ? 'script' : 'style';

			$this->_detector->detect_group(
				$group_handle,
				$original_string,
				$group_type
			);
		}

		// build the friendly url for this minify url
		global $blog_id;

		$fly_url  = $this->_min_fly_url . 'minify-'
			. 'b' . $blog_id . '-' . $group_handle
			. '-' . $group_hash
			. '.' . $ext;

		// add a cache buster if needed
		$fly_url .= !empty($buster) ? '?ver=' . $buster : '';

		return $fly_url;
	}

	/**
	 * Checks whether all expected groups are already detected
	 *
	 * @param $group_hash string the handle of the group being checked
	 * @return bool
	 */
	private function _is_group_detected($group_hash)
	{
		$detected = $this->_detector->get_detected_groups();

		if (0 == sizeof($detected))
			return false;

		foreach ($detected as $key => $group)
		{
			if ($group_hash == $group['hash'])
				return true;
		}

		return false;
	}

	private function _sanitize_request($request)
	{
		return trim(stripslashes(strip_tags($request)));
	}

	/**
	 * Gets the original request headers
	 *
	 * @return array
	 */
	private function _get_request_headers()
	{
		$headers = array();

		foreach ($_SERVER as $k => $v)
		{
			if (substr($k, 0, 5) == 'HTTP_')
			{
				$k = str_replace(' ', '-', ucwords(
					strtolower(str_replace('_', ' ', substr($k, 5)))
				));
				$headers[$k] = $v;
			}
		}

		return $headers;
	}

	private function _register_hooks()
	{
		add_action('parse_request', array($this, 'serve'), 1);
		add_filter('bwp_minify_get_src', array($this, 'friendlify_src'), 10, 4);
	}

	private function _init_properties() {}

	private function _init()
	{
		$this->_init_properties();
		$this->_register_hooks();
	}
}

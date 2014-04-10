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
		$blog_id = isset($_GET['blog']) ? (int) $_GET['blog'] : 0;

		if (empty($group_handle) || empty($type) || empty($blog_id))
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

		$string  = $group['string'];
		$headers = $this->_get_request_headers();
		$url = trailingslashit($this->_min_url)
			. '?f=' . $string
			. '&name=' . $group_handle
			. '&type=' . $type
			. '&bid=' . $blog_id;

		// try to fetch actual minified contents from regular Minify url while
		// preserving correct headers
		$response = wp_remote_get($url, array(
			'headers' => $headers,
			'timeout' => 20,
			'redirection' => 0,
			'decompress' => false // keep the gzipped contents
		));

		// could not fetch minified contents, show an error message
		if (is_wp_error($response))
		{
			echo sprintf(
				__('Could not get minified contents for %s', $this->_domain),
				$url
			);
			exit;
		}

		// retrieve minified contents and headers, send all the headers issued
		// by regular Minify url
		$minified_contents = wp_remote_retrieve_body($response);
		$minified_headers  = wp_remote_retrieve_headers($response);
		$response_code     = wp_remote_retrieve_response_code($response);
		foreach ($minified_headers as $header_name => $headers)
		{
			// get rid of etag header, powered-by header, and server header
			$real_header_name = strtolower($header_name);
			if ('etag' == $real_header_name || 'x-powered-by' == $real_header_name
				|| 'server' == $real_header_name
			) {
				continue;
			}

			foreach ((array) $headers as $header)
				header($header_name . ': ' . $header);
		}

		// show minified contents with correct status code
		$response_code = empty($minified_contents) ? 304 : $response_code;
		status_header($response_code);
		echo $minified_contents;
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

		// if this group has not been detected we serve the regular Minify url,
		// and detect this group as well so it can be served using friendly url
		// next time
		$detector_version = $this->_detector->get_version();
		$group_hash = md5($original_string . $detector_version);
		if (false == $this->_is_group_detected($group_hash))
		{
			$group_type = 'js' == $ext ? 'script' : 'style';
			$this->_detector->detect_group(
				$group_handle,
				$original_string,
				$group_type
			);
			return $string;
		}

		// build the friendly url for this minify url
		global $blog_id;
		$fly_url  = $this->_min_fly_url . 'minify-'
			. 'b' . $blog_id . '-' . $group_handle
			. '-' . $group_hash
			. '.' . $ext;
		$fly_url .= !empty($buster) ? '&#038;ver=' . $buster : '';

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
		// get detected groups from db to make sure that they are persistently stored
		$detected = $this->_detector->get_detected_groups(true);

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
		add_action('parse_request', array($this, 'serve'));
		add_filter('bwp_get_minify_src', array($this, 'friendlify_src'), 10, 4);
	}

	private function _init_properties() {}

	private function _init()
	{
		$this->_init_properties();
		$this->_register_hooks();
	}
}

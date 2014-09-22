<?php
/**
 * Copyright (c) 2014 Khang Minh <http://betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * Class BWP_Minify_CDN
 * @author Khang Minh
 * @since BWP Minify 1.3.0
 * @package BWP Minify
 */
class BWP_Minify_CDN
{
	private $_options = array();

	private $_domain = '';

	public function __construct($options, $domain)
	{
		$this->_options = $options;
		$this->_domain = $domain;

		$this->_init();
	}

	public function replace_host($string, $original_string)
	{
		$ext = preg_match('/\.([^\.]+)$/ui', $original_string, $matches)
			? $matches[1] : '';

		if (empty($ext))
			return $string;

		$cdn_host = $this->_get_cdn_host($ext);
		if (empty($cdn_host))
			return $string;

		// force SSL when WordPress is on SSL, or use scheme-less URL
		$ssl_type = $this->_options['select_cdn_ssl_type'];

		$scheme = is_ssl() ? 'https://' : 'http://';
		$scheme = 'less' == $ssl_type ? '//' : $scheme;
		$scheme = 'off' == $ssl_type ? 'http://' : $scheme;

		$string = preg_replace('#https?://[^/]+#ui',
			$scheme . $cdn_host,
			$string
		);

		return $string;
	}

	private static function _sanitize_cdn_host($host)
	{
		$host = untrailingslashit($host);
		return str_replace(array('http://', 'https://'), '', $host);
	}

	private function _get_cdn_host($ext)
	{
		$cdn_host = self::_sanitize_cdn_host($this->_options['input_cdn_host']);

		// use file-type specific CDN host if needed
		$js_cdn = !empty($this->_options['input_cdn_host_js'])
			? self::_sanitize_cdn_host($this->_options['input_cdn_host_js'])
			: $cdn_host;
		$css_cdn = !empty($this->_options['input_cdn_host_css'])
			? self::_sanitize_cdn_host($this->_options['input_cdn_host_css'])
			: $cdn_host;

		return 'js' == $ext ? $js_cdn : $css_cdn;
	}

	private function _register_hooks()
	{
		// priority 11 to make sure that this filter is applied after the
		// fetcher class has finished friendlifying the Minify string
		add_filter('bwp_minify_get_src', array($this, 'replace_host'), 11, 2);
	}

	private function _init()
	{
		$this->_register_hooks();
	}
}

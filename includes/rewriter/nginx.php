<?php
/**
 * Copyright (c) 2014 Khang Minh <http://betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * Class BWP_Minify_Rewriter_Nginx
 * @author Khang Minh <contact@betterwp.net>
 * @since BWP Minify 1.3.0
 * @package BWP Minify
 */
class BWP_Minify_Rewriter_Nginx extends BWP_Minify_AbstractRewriter
{
	public function add_rewrite_rules($suppress = true)
	{
		$this->suppress = $suppress;
		return $this->add_wp_rewrite_rules();
	}

	public function get_wp_config_file()
	{
		$config_file = !empty($this->options['input_nginx_config_file'])
			? $this->options['input_nginx_config_file']
			: $this->main->get_doc_root('nginx.conf');

		return $config_file;
	}

	public function get_wp_config_dir()
	{
		$config_file = $this->get_wp_config_file();
		return dirname($config_file);
	}

	public function get_cache_config_file()
	{
		return $this->get_wp_config_file();
	}

	public function get_cache_config_dir()
	{
		return $this->get_wp_config_dir();
	}

	public function get_wp_rewrite_rules()
	{
		// make use of WordPress's base, with blog path removed if any
		$base         = $this->main->get_wp_base();
		$fly_min_path = $this->main->get_fly_min_path();
		$rules        = array();

		$rules[] = 'set $zip_ext "";';
		$rules[] = 'if ($http_accept_encoding ~* gzip) {';
		$rules[] = '    set $zip_ext ".gz";';
		$rules[] = '}';
		$rules[] = 'set $minify_static "";';
		$rules[] = 'if ($http_cache_control = false) {';
		$rules[] = '    set $minify_static "C";';
		$rules[] = '    set $http_cache_control "";';
		$rules[] = '}';
		$rules[] = 'if ($http_cache_control !~* no-cache) {';
		$rules[] = '    set $minify_static "C";';
		$rules[] = '}';
		$rules[] = 'if ($http_if_modified_since = false) {';
		$rules[] = '    set $minify_static "${minify_static}M";';
		$rules[] = '}';
		$rules[] = 'if (-f $request_filename$zip_ext) {';
		$rules[] = '    set $minify_static "${minify_static}E";';
		$rules[] = '}';
		$rules[] = 'if ($minify_static = CME) {';
		$rules[] = '    rewrite (.*) $1$zip_ext break;';
		$rules[] = '}';

		// nginx rewrite rules and location directive do not match query
		// variable so `/path/to/file.js` is the same as `/path/to/file.js?ver=1`

		if (BWP_MINIFY::is_multisite() && !BWP_MINIFY::is_subdomain_install())
		{
			// special rewrite rules for sub-directory multisite environment
			$blog_regex = '/[_0-9a-zA-Z-]+';
			$rules[]    = 'rewrite ^' . $blog_regex . '(' . $fly_min_path
				. 'minify-b\d+-[a-zA-Z0-9-_.]+\.(css|js))$ $1;';
		}

		$rules[] = 'rewrite ^' . $fly_min_path
			. 'minify-b(\d+)-([a-zA-Z0-9-_.]+)\.(css|js)$ '
			. $base . 'index.php?blog=$1&min_group=$2&min_type=$3 last;';
		$rules[] = "\n";

		$rules   = $this->get_cache_response_headers() . implode("\n", $rules);
		return $rules;
	}

	public function get_cache_rewrite_rules()
	{
		return $this->get_wp_rewrite_rules();
	}

	public function remove_wp_rewrite_rules()
	{
		$this->prepare_wp_rewrite_rules();
		$this->remove_rewrite_rules();
	}

	public function remove_cache_rewrite_rules() {}

	public function add_wp_rewrite_rules()
	{
		$this->prepare_wp_rewrite_rules();

		if (file_exists($this->config_file))
			return $this->write_rewrite_rules($this->rules);

		// if we reach here, nothing works
		return false;
	}

	public function add_cache_rewrite_rules() {}

	protected function get_cache_response_headers()
	{
		$fly_min_path = $this->main->get_fly_min_path();
		$header_begin = BWP_MINIFY_HEADERS_BEGIN . "\n";
		$header_end   = BWP_MINIFY_HEADERS_END . "\n";
		$headers      = array();

		$cache_age = (int) $this->options['input_maxage'] * (int) $this->options['select_time_type'];
		$headers[] = 'location ~ ' . $fly_min_path . '.*\.(js|css)$ {';
		$headers[] = '    add_header Cache-Control "public, max-age=' . $cache_age . '";';
		$headers[] = '    add_header Vary "Accept-Encoding";';
		$headers[] = '    etag off;';
		$headers[] = '}';
		$headers[] = 'location ~ ' . $fly_min_path . '.*\.js\.gz$ {';
		$headers[] = '    gzip off;';
		$headers[] = '    types {}';
		$headers[] = '    default_type application/x-javascript;';
		$headers[] = '    add_header Cache-Control "public, max-age=' . $cache_age . '";';
		$headers[] = '    add_header Content-Encoding gzip;';
		$headers[] = '    add_header Vary "Accept-Encoding";';
		$headers[] = '    etag off;';
		$headers[] = '}';
		$headers[] = 'location ~ ' . $fly_min_path . '.*\.css\.gz$ {';
		$headers[] = '    gzip off;';
		$headers[] = '    types {}';
		$headers[] = '    default_type text/css;';
		$headers[] = '    add_header Cache-Control "public, max-age=' . $cache_age . '";';
		$headers[] = '    add_header Content-Encoding gzip;';
		$headers[] = '    add_header Vary "Accept-Encoding";';
		$headers[] = '    etag off;';
		$headers[] = '}' . "\n";

		$headers   = implode("\n", $headers);
		return $header_begin . $headers . $header_end;
	}
}

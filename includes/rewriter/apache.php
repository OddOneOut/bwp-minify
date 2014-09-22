<?php
/**
 * Copyright (c) 2014 Khang Minh <http://betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * Class BWP_Minify_Rewriter_Apache
 * @author Khang Minh <contact@betterwp.net>
 * @since BWP Minify 1.3.0
 * @package BWP Minify
 */
class BWP_Minify_Rewriter_Apache extends BWP_Minify_AbstractRewriter
{
	public function add_rewrite_rules($suppress = true)
	{
		$this->suppress = $suppress;
		$result         = true;

		switch ($this->rule_set)
		{
			default:
			case '':
				$result = $this->add_wp_rewrite_rules();

				if ($result === true || $result === 'written')
					$result = $this->add_cache_rewrite_rules();
				break;

			case 'wp':
				$this->add_wp_rewrite_rules();
				break;

			case 'cache':
				$result = $this->add_cache_rewrite_rules();
				break;
		}

		return $result;
	}

	public function get_wp_config_file()
	{
		$config_dir = $this->get_wp_config_dir();
		return trailingslashit($config_dir) . '.htaccess';
	}

	public function get_wp_config_dir()
	{
		return $this->main->get_wp_doc_root();
	}

	public function get_cache_config_file()
	{
		$config_dir = $this->get_cache_config_dir();
		return trailingslashit($config_dir) . '.htaccess';
	}

	public function get_cache_config_dir()
	{
		return $this->main->get_cache_dir();
	}

	public function get_wp_rewrite_rules()
	{
		// get fly min path and remove the base
		$fly_min_path = ltrim($this->main->get_fly_min_path(), '/');
		$rules        = array();

		$rules[] = '<IfModule mod_rewrite.c>';
		$rules[] = 'RewriteEngine On';
		$rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
		$rules[] = 'RewriteRule ^([_0-9a-zA-Z-]+/)?'
			. '(' . $fly_min_path . 'minify-.*\.(js|css))$ $2 [L]';
		$rules[] = '</IfModule>' . "\n";

		$rules = implode("\n", $rules);
		return $rules;
	}

	public function get_cache_rewrite_rules()
	{
		// make use of WordPress's base, with blog path removed if any
		$base    = $this->main->get_wp_base();
		$rules   = array();

		$rules[] = '<IfModule mod_rewrite.c>';
		$rules[] = 'RewriteEngine On';
		$rules[] = 'RewriteCond %{HTTP:Accept-Encoding} gzip';
		$rules[] = 'RewriteRule .* - [E=ZIP_EXT:.gz]';
		$rules[] = 'RewriteCond %{HTTP:Cache-Control} !no-cache';
		$rules[] = 'RewriteCond %{HTTP:If-Modified-Since} !no-cache';
		$rules[] = 'RewriteCond %{REQUEST_FILENAME}%{ENV:ZIP_EXT} -f';
		$rules[] = 'RewriteRule (.*) $1%{ENV:ZIP_EXT} [L]';
		$rules[] = 'RewriteRule ^minify-b(\d+)-([a-zA-Z0-9-_.]+)\.(css|js)$ '
			. $base . 'index.php?blog=$1&min_group=$2&min_type=$3 [L]';
		$rules[] = '</IfModule>' . "\n";

		$rules   = $this->get_cache_response_headers() . implode("\n", $rules);
		return $rules;
	}

	public function remove_wp_rewrite_rules()
	{
		$this->prepare_wp_rewrite_rules();
		$this->remove_rewrite_rules();
	}

	public function remove_cache_rewrite_rules()
	{
		$this->prepare_cache_rewrite_rules();
		$this->remove_rewrite_rules();
	}

	public function is_wp_rewrite_rules_needed()
	{
		if (!BWP_MINIFY::is_multisite() || BWP_MINIFY::is_subdomain_install())
			return false;

		$fly_min_path = $this->main->get_fly_min_path();

		// only add these rules if `wp-content` is not already in fly min path
		if (false !== strpos($fly_min_path, 'wp-content'))
			return false;
		
		return true;
	}

	public function add_wp_rewrite_rules()
	{
		if (!$this->is_wp_rewrite_rules_needed())
			return true;

		$this->prepare_wp_rewrite_rules();

		// main wp htaccess files doesn't exist AND wp's root is not writable,
		// nothing more we can do
		if (!file_exists($this->config_file) && !is_writable($this->config_dir))
			return false;

		// this should create a new .htaccess file if it is not already there
		return $this->write_rewrite_rules($this->rules);
	}

	public function add_cache_rewrite_rules()
	{
		$this->prepare_cache_rewrite_rules();

		// server config file exists, OR doesn't exist but directory is
		// writable, attempt to create a new file, and write rewrite rules to it
		if (file_exists($this->config_file) || is_writable($this->config_dir))
		{
			return $this->write_rewrite_rules($this->rules);
		}

		// no need to check and return any error
		if ($this->suppress)
			return false;

		// if we reach here that mean we COULD NOT write rewrite rules
		// automatically. These check below tell the plugin to show the
		// contents to be written to the server config file to admin to manually
		// update the rewrite rules, but provide different error messages
		if (!file_exists($this->config_dir))
			return 'exists_dir';

		if (!file_exists($this->config_file) && !is_writable($this->config_dir))
			return 'write_dir';

		// if we reach here, nothing works
		return false;
	}

	protected function get_cache_response_headers()
	{
		$header_begin = BWP_MINIFY_HEADERS_BEGIN . "\n";
		$header_end   = BWP_MINIFY_HEADERS_END . "\n";
		$headers      = array();

		// file type and encoding handling
		$headers[] = '<Files "*.js.gz">';
		$headers[] = 'ForceType application/x-javascript';
		$headers[] = '</Files>';
		$headers[] = '<Files "*.css.gz">';
		$headers[] = 'ForceType text/css';
		$headers[] = '</Files>';
		$headers[] = '<IfModule mod_mime.c>';
		$headers[] = 'AddEncoding gzip .gz';
		$headers[] = 'AddCharset utf-8 .js .css';
		$headers[] = '</IfModule>';

		// compression handling
		$headers[] = '<IfModule mod_deflate.c>';
		$headers[] = '    <IfModule mod_setenvif.c>';
		$headers[] = '    SetEnvIfNoCase Request_URI "\.gz$" no-gzip';
		$headers[] = '    </IfModule>';
		$headers[] = '</IfModule>';

		// caching headers
		$cache_age = (int) $this->options['input_maxage'] * (int) $this->options['select_time_type'];
		$headers[] = '<IfModule mod_headers.c>';
		$headers[] = 'Header set Cache-Control "public, max-age=' . $cache_age . '"';
		$headers[] = 'Header set Vary "Accept-Encoding"';
		$headers[] = 'Header unset ETag';
		$headers[] = '</IfModule>' . "\n";

		$headers   = implode("\n", $headers);
		return $header_begin . $headers . $header_end;
	}
}

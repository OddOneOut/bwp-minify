<?php
/**
 * Copyright (c) 2014 Khang Minh <http://betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

/**
 * Abstract class BWP_Minify_AbstractRewriter
 * @author Khang Minh <contact@betterwp.net>
 * @since BWP Minify 1.3.0
 * @package BWP Minify
 */
abstract class BWP_Minify_AbstractRewriter
{
	protected $config_file  = '';

	protected $config_dir   = '';

	protected $start_marker = '';

	protected $end_marker   = '';

	protected $rules        = '';

	protected $rules_clean  = '';

	protected $rule_set     = '';

	protected $suppress     = true;

	protected $options      = '';

	protected $domain       = '';

	protected $main         = false; // BWP Minify class instance

	public function __construct($main)
	{
		$this->main    = $main;
		$this->options = &$main->options;
		$this->domain  = $main->domain;
		$this->init();
	}

	public function get_generated_wp_rewrite_rules()
	{
		$this->prepare_wp_rewrite_rules();
		return $this->start_marker . "\n" . $this->rules . $this->end_marker . "\n";
	}

	public function get_generated_cache_rewrite_rules()
	{
		$this->prepare_cache_rewrite_rules();
		return $this->start_marker . "\n" . $this->rules . $this->end_marker . "\n";
	}

	public function no_suppress()
	{
		$this->suppress = false;
		return $this;
	}

	abstract protected function add_rewrite_rules($suppress = true);
	abstract protected function add_wp_rewrite_rules();
	abstract protected function add_cache_rewrite_rules();
	abstract protected function remove_wp_rewrite_rules();
	abstract protected function remove_cache_rewrite_rules();
	abstract protected function get_wp_config_file();
	abstract protected function get_cache_config_file();
	abstract protected function get_wp_config_dir();
	abstract protected function get_cache_config_dir();
	abstract protected function get_wp_rewrite_rules();
	abstract protected function get_cache_rewrite_rules();
	abstract protected function get_cache_response_headers();

	protected function init()
	{
		// intentionally left blank
	}

	protected function prepare_wp_rewrite_rules()
	{
		$this->config_dir   = $this->get_wp_config_dir();
		$this->config_file  = $this->get_wp_config_file();
		$this->rules        = $this->get_wp_rewrite_rules();
		$this->start_marker = BWP_MINIFY_WP_RULES_BEGIN;
		$this->end_marker   = BWP_MINIFY_WP_RULES_END;
	}

	protected function prepare_cache_rewrite_rules()
	{
		$this->config_dir   = $this->get_cache_config_dir();
		$this->config_file  = $this->get_cache_config_file();
		$this->rules        = $this->get_cache_rewrite_rules();
		$this->start_marker = BWP_MINIFY_RULES_BEGIN;
		$this->end_marker   = BWP_MINIFY_RULES_END;
	}

	/**
	 * Writes rewrite rules to config file
	 *
	 * @return bool|string true if write succeeds, 'put' if write fails, and
	 *         'written' if rewrite rules are there but file is not writable
	 *         (probably manual update). should never return false
	 */
	protected function write_rewrite_rules($rules)
	{
		$rule_begin    = $this->start_marker . "\n";
		$rule_end      = $this->end_marker . "\n";
		$rules         = !empty($rules) ? $rule_begin . $rules . $rule_end : '';
		$rules_clean   = !empty($rules) ? $rules : $rule_begin . $this->rules . $rule_end;

		$current_rules = @file_get_contents($this->config_file);
		$current_rules = false === $current_rules ? '' : $current_rules;
		$begin         = strpos($current_rules, $rule_begin);
		$end           = strpos($current_rules, $rule_end);

		$current_rules_clean = preg_replace('/[\r\n]+/', "\n", $current_rules);
		if (!empty($rules) && false !== strpos($current_rules_clean, $rules_clean))
		{
			// if needed rewrite rules are there, we don't have to do anything,
			// but if $file is not writable we should return appropriate message
			if (!is_writable($this->config_file))
				return 'written';

			return true;
		}
		else
		{
			// otherwise we add/update rewrite rules in the file
			$end    = false === $end ? 0 : $end;
			$length = false !== $begin && $end > $begin
				? $end - $begin + strlen($rule_end) : 0;

			$begin  = false === $begin ? 0 : $begin;
			$current_rules  = empty($length) && empty($rules)
				? $current_rules
				: substr_replace($current_rules, $rules, $begin, $length);
		}

		if (false === @file_put_contents($this->config_file, $current_rules))
			// write to server config file failed for some reasons
			return 'put';
		else
			return true;
	}

	protected function remove_rewrite_rules()
	{
		if (file_exists($this->config_file))
			$this->write_rewrite_rules('');
	}
}

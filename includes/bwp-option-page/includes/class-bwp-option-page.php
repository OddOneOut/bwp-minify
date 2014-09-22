<?php
/**
 * Copyright (c) 2014 Khang Minh <betterwp.net>
 * @license http://www.gnu.org/licenses/gpl.html GNU GENERAL PUBLIC LICENSE VERSION 3.0 OR LATER
 */

class BWP_OPTION_PAGE
{
	/**
	 * The form
	 */
	var $form;

	/**
	 * The form name
	 */
	var $form_name;

	/**
	 * Tabs to build
	 */
	var $form_tabs;

	/**
	 * Current tab
	 */
	var $current_tab;

	/**
	 * This holds the form items, determining the position
	 */
	var $form_items = array();

	/**
	 * This holds the name for each items (an item can have more than one fields)
	 */
	var $form_item_names = array();

	/**
	 * This holds the form label
	 */
	var $form_item_labels = array();

	/**
	 * This holds the form option aka data
	 */
	var $form_options = array(), $site_options = array();

	/**
	 * Other things
	 */
	var $domain;

	/**
	 * Constructor
	 */
	function __construct($form_name = 'bwp_option_page', $site_options = array(), $domain = '')
	{
		$this->form_name    = $form_name;
		$this->site_options = $site_options;
		$this->domain       = $domain;
	}

	/**
	 * Init the class
	 *
	 * @param	array	$form	The form array that contains everything we need to build the form
	 * @param	array	$options	The data array that contains all data fetched from db or by default
	 * @param	string	$form_name	The name of the form, change this if you have more than one forms on a page
	 */
	function init($form = array(), $options = array(), $form_tabs = array())
	{
		$this->form_items       = $form['items'];
		$this->form_item_names  = $form['item_names'];
		$this->form_item_labels = $form['item_labels'];
		$this->form             = $form;
		$this->form_options     = $options;
		$this->form_tabs        = $form_tabs;

		if (sizeof($this->form_tabs) == 0)
			$this->form_tabs = array(__('Plugin Configurations', 'bwp-option-page'));
	}

	function get_form_name()
	{
		return $this->form_name;
	}

	function set_current_tab($current_tab = 0)
	{
		$this->current_tab = $current_tab;
	}

	function get_options($options = array(), $options_default = array())
	{
		foreach ($options_default as $key => $option)
		{
			if (!in_array($key, $options))
				unset($options_default[$key]);
		}

		return $options_default;
	}

	function get_db_options($name = '', $options = array())
	{
		$db_options = get_option($name);

		if (!$db_options)
		{
			update_option($name, $options);
		}
		else if (array_keys($options) != array_keys($db_options))
		{
			foreach ($db_options as $key => $data)
				if (isset($options[$key]) && !in_array($key, $this->site_options))
					$options[$key] = $data;

			update_option($name, $options);
		}
		else
		{
			foreach ($db_options as $key => $data)
			{
				if (!in_array($key, $this->site_options))
					$options[$key] = $data;
			}
		}

		return $options;
	}

	function format_field($key, $option_formats)
	{
		if (!empty($option_formats[$key]))
		{
			if ('int' == $option_formats[$key])
				$_POST[$key] = (int) $_POST[$key];
			else if ('float' == $option_formats[$key])
				$_POST[$key] = (float) $_POST[$key];
			else if ('html' == $option_formats[$key])
				$_POST[$key] = wp_filter_post_kses($_POST[$key]);
		}
		else
			$_POST[$key] = strip_tags($_POST[$key]);
	}

	function kill_html_fields(&$form, $names)
	{
		$ids   = array();
		$names = (array) $names;

		foreach ($form['item_names'] as $key => $name)
		{
			if (in_array($name, $names))
				$ids[] = $key;
		}

		$in_keys = array(
			'items',
			'item_labels',
			'item_names'
		);

		foreach ($ids as $id)
		{
			foreach ($in_keys as $key)
				unset($form[$key][$id]);
		}
	}

	/**
	 * Generate HTML field
	 */
	function generate_html_field($type = '', $data = array(), $name = '', $in_section = false)
	{
		$pre_html_field  = '';
		$post_html_field = '';

		$checked  = 'checked="checked" ';
		$selected = 'selected="selected" ';

		$value = isset($this->form_options[$name])
			? $this->form_options[$name]
			: '';

		$value = isset($data['value']) ? $data['value'] : $value;

		$value = !empty($this->domain)
			&& ('textarea' == $type || 'input' == $type)
			? __($value, $this->domain)
			: $value;

		if (is_array($value))
		{
			foreach ($value as &$v)
				$v = is_array($v) ? array_map('esc_attr', $v) : esc_attr($v);
		}
		else
		{
			$value = 'textarea' == $type
				? esc_html($value)
				: esc_attr($value);
		}

		$array_replace = array();
		$array_search  = array(
			'size',
			'name',
			'value',
			'cols',
			'rows',
			'label',
			'disabled',
			'pre',
			'post'
		);

		$return_html   = '';

		$br = isset($this->form['inline_fields'][$name])
			&& is_array($this->form['inline_fields'][$name])
			? ''
			: "<br />\n";

		$pre   = !empty($data['pre']) ? $data['pre'] : '';
		$post  = !empty($data['post']) ? $data['post'] : '';

		$param = empty($this->form['params'][$name])
			? false : $this->form['params'][$name];

		switch ($type)
		{
			case 'heading':
				$html_field = '%s';
			break;

			case 'input':
				$html_field = !$in_section
					? '%pre%<input%disabled% size="%size%" type="text" '
						. 'id="' . $name . '" '
						. 'name="' . $name . '" '
						. 'value="' . $value . '" /> <em>%label%</em>'
					: '<label for="' . $name . '">%pre%<input%disabled% size="%size%" type="text" '
						. 'id="' . $name . '" '
						. 'name="' . $name . '" '
						. 'value="' . $value . '" /> <em>%label%</em></label>';
			break;

			case 'select':
			case 'select_multi':
				$pre_html_field = 'select_multi' == $type
					? '%pre%<select id="' . $name . '" name="' . $name . '[]" multiple>' . "\n"
					: '%pre%<select id="' . $name . '" name="' . $name . '">' . "\n";

				$html_field = '<option %selected%value="%value%">%option%</option>';

				$post_html_field = '</select>%post%' . $br;
			break;

			case 'checkbox':
				$html_field = '<label for="%name%">'
					. '<input %checked%type="checkbox" id="%name%" name="%name%" value="yes" /> %label%</label>';
			break;

			case 'checkbox_multi':
				$html_field = '<label for="%name%-%value%">'
					. '<input %checked%type="checkbox" id="%name%-%value%" name="%name%[]" value="%value%" /> %label%</label>';
			break;

			case 'radio':
				$html_field = '<label>' . '<input %checked%type="radio" '
					. 'name="' . $name . '" value="%value%" /> %label%</label>';
			break;

			case 'textarea':
				$html_field = '%pre%<textarea%disabled% '
					. 'id="' . $name . '" '
					. 'name="' . $name . '" cols="%cols%" rows="%rows%">'
					. $value . '</textarea>%post%';
			break;
		}

		if (!isset($data))
			return;

		if ($type == 'heading' && !is_array($data))
		{
			$return_html .= sprintf($html_field, $data) . $br;
		}
		else if ($type == 'radio'
			|| $type == 'checkbox' || $type == 'checkbox_multi'
			|| $type == 'select' || $type == 'select_multi'
		) {
			foreach ($data as $key => $value)
			{
				if ($type == 'checkbox')
				{
					// handle checkbox a little bit differently
					if ($this->form_options[$value] == 'yes')
					{
						$return_html .= str_replace(
							array('%value%', '%name%', '%label%', '%checked%'),
							array($value, $value, $key, $checked),
							$html_field
						);

						$return_html .= apply_filters('bwp_option_after_' . $type . '_' . $name . '_checked', '', $value, $param);
						$return_html .= $br;
					}
					else
					{
						$return_html .= str_replace(
							array('%value%', '%name%', '%label%', '%checked%'),
							array($value, $value, $key, ''),
							$html_field
						);

						$return_html .= apply_filters('bwp_option_after_' . $type . '_' . $name, '', $value, $param);
						$return_html .= $br;
					}
				}
				else if ($type == 'checkbox_multi')
				{
					// handle a multi checkbox differently
					if (isset($this->form_options[$name])
						&& is_array($this->form_options[$name])
						&& (in_array($value, $this->form_options[$name])
							|| array_key_exists($value, $this->form_options[$name]))
					) {
						$return_html .= str_replace(
							array('%value%', '%name%', '%label%', '%checked%'),
							array($value, $name, $key, $checked),
							$html_field
						);

						$return_html .= apply_filters('bwp_option_after_' . $type . '_' . $name . '_checked', '', $value, $param);
						$return_html .= $br;
					}
					else
					{
						$return_html .= str_replace(
							array('%value%', '%name%', '%label%', '%checked%'),
							array($value, $name, $key, ''),
							$html_field
						);

						$return_html .= apply_filters('bwp_option_after_' . $type . '_' . $name, '', $value, $param);
						$return_html .= $br;
					}
				}
				else if (isset($this->form_options[$name])
					&& ($this->form_options[$name] == $value
						|| (is_array($this->form_options[$name])
							&& (in_array($value, $this->form_options[$name])
								|| array_key_exists($value, $this->form_options[$name]))))
				) {
					$item_br = $type == 'select' || $type == 'select_multi' ? "\n" : $br;

					$return_html .= str_replace(
						array('%value%', '%name%', '%label%', '%option%', '%checked%', '%selected%', '%pre%', '%post%'),
						array($value, $value, $key, $key, $checked, $selected, $pre, $post),
						$html_field
					) . $item_br;
				}
				else
				{
					$item_br = $type == 'select' || $type == 'select_multi' ? "\n" : $br;

					$return_html .= str_replace(
						array('%value%', '%name%', '%label%', '%option%', '%checked%', '%selected%', '%pre%', '%post%'),
						array($value, $value, $key, $key, '', '', $pre, $post),
						$html_field
					) . $item_br;
				}
			}
		}
		else
		{
			foreach ($array_search as &$keyword)
			{
				$array_replace[$keyword] = '';

				if (!empty($data[$keyword]))
				{
					$array_replace[$keyword] = $data[$keyword];
				}

				$keyword = '%' . $keyword . '%';
			}

			$return_html = str_replace($array_search, $array_replace, $html_field) . $br;
		}

		// inline fields
		$inline_html = '';
		if (isset($this->form['inline_fields'][$name]) && is_array($this->form['inline_fields'][$name]))
		{
			foreach ($this->form['inline_fields'][$name] as $field => $field_type)
			{
				if (isset($this->form[$field_type][$field]))
					$inline_html = ' ' . $this->generate_html_field($field_type, $this->form[$field_type][$field], $field, $in_section);
			}
		}

		// html after field
		$post = !empty($this->form['post'][$name])
			? ' ' . $this->form['post'][$name]
			: $post;

		return str_replace('%pre%', $pre, $pre_html_field) . $return_html . str_replace('%post%', $post, $post_html_field) . $inline_html;
	}

	/**
	 * Generate HTML fields
	 *
	 * @params	they explain themselves
	 */
	function generate_html_fields($type, $name)
	{
		$item_label  = '';
		$return_html = '';

		$item_key = array_keys($this->form_item_names, $name);

		$input_class = $type == 'heading'
			? 'bwp-option-page-heading-desc'
			: 'bwp-option-page-inputs';

		// an inline item can hold any HTML markup, example is to display some
		// kinds of button right be low the label
		$inline = '';

		if (isset($this->form['inline']) && is_array($this->form['inline'])
			&& array_key_exists($name, $this->form['inline'])
		) {
			$inline = empty($this->form['inline'][$name]) ? '' : $this->form['inline'][$name];
		}

		$inline .= "\n";

		switch ($type)
		{
			case 'section':
				if (!isset($this->form[$name]) || !is_array($this->form[$name]))
					return;

				$item_label = '<span class="bwp-opton-page-label">'
					. $this->form_item_labels[$item_key[0]]
					. $inline
					. '</span>';

				foreach ($this->form[$name] as $section_field)
				{
					$type = $section_field[0];
					$name = $section_field['name'];

					if (isset($this->form[$section_field[0]]))
					{
						$return_html .= $this->generate_html_field($section_field[0], $this->form[$type][$name], $name, true);
					}
				}
			break;

			default:
				if (!isset($this->form[$type][$name])
					|| ($type != 'heading' && !is_array($this->form[$type][$name])))
					return;

				$item_label = $type != 'checkbox' && $type != 'checkbox_multi' && $type != 'radio'
					? '<label class="bwp-opton-page-label" for="' . $name . '">'
						. $this->form_item_labels[$item_key[0]] . $inline
						. '</label>'
					: '<span class="bwp-opton-page-label type-' . $type . '">'
						. $this->form_item_labels[$item_key[0]] . $inline
						. '</span>';

				$item_label = $type == 'heading'
					? '<h3>' . $this->form_item_labels[$item_key[0]] . '</h3>' . $inline
					: $item_label;

				if (isset($this->form[$type]))
					$return_html = $this->generate_html_field($type, $this->form[$type][$name], $name);
			break;
		}

		// a container can hold some result executed by customized script,
		// such as displaying something when user press the submit button
		$containers = '';

		if (isset($this->form['container'])
			&& is_array($this->form['container'])
			&& array_key_exists($name, $this->form['container'])
		) {
			$container_array = (array) $this->form['container'][$name];

			foreach ($container_array as $container)
			{
				$containers .= empty($container)
					? '<div style="display: none;"><!-- --></div>'
					: '<div class="bwp-clear">' . $container . '</div>' . "\n";
			}
		}

		$pure_return = trim(strip_tags($return_html));

		if (empty($pure_return) && $type == 'heading')
		{
			return $item_label . $containers;
		}
		else
		{
			return $item_label . '<p class="' . $input_class . '">'
				. $return_html . '</p>'
				. $containers;
		}
	}

	/**
	 * Generate HTML form
	 *
	 * @see Constructor
	 */
	function generate_html_form()
	{
		$return_str = '<div class="wrap" style="padding-bottom: 20px;">' . "\n";

		if (sizeof($this->form_tabs) >= 2)
			$return_str .= apply_filters('bwp-admin-form-icon', '<div class="icon32" id="icon-options-general"><br></div>'  . "\n");
		else
			$return_str .= '<div class="icon32" id="icon-options-general"><br></div>';

		if (sizeof($this->form_tabs) >= 2)
		{
			$count = 0;

			$return_str .= '<h2 class="bwp-option-page-tabs">' . "\n";
			$return_str .= apply_filters('bwp-admin-plugin-version', '') . "\n";

			foreach ($this->form_tabs as $title => $link)
			{
				$count++;

				$active      = $count == $this->current_tab ? ' nav-tab-active' : '';
				$return_str .= '<a class="nav-tab' . $active . '" href="' . $link . '">' . $title . '</a>' . "\n";
			}

			$return_str .= '</h2>' . "\n";
		}
		else if (!isset($this->form_tabs[0]))
		{
			$title       = array_keys($this->form_tabs);
			$return_str .= '<h2>' . $title[0] . '</h2>'  . "\n";
		}
		else
			$return_str .= '<h2>' . $this->form_tabs[0] . '</h2>'  . "\n";

		$return_str .= apply_filters('bwp_option_before_form', '');
		echo $return_str;

		do_action('bwp_option_action_before_form');

		$return_str  = '';
		$return_str .= '<form class="bwp-option-page" name="' . $this->form_name . '" method="post" action="">'  . "\n";

		if (function_exists('wp_nonce_field'))
		{
			echo $return_str;

			wp_nonce_field($this->form_name);

			$return_str = '';
		}

		$return_str .= '<ul>' . "\n";

		// generate filled form
		if (isset($this->form_items) && is_array($this->form_items))
		{
			foreach ($this->form_items as $key => $type)
			{
				$name = !empty($this->form_item_names[$key])
					? $this->form_item_names[$key]
					: '';

				if (isset($this->form['env'])
					&& !BWP_FRAMEWORK_IMPROVED::is_multisite()
					&& array_key_exists($name, $this->form['env'])
					&& $this->form['env'][$name] == 'multisite')
				{
					// hide multisite field if not in multisite environment
					continue;
				}

				if (isset($this->form['role'])
					&& BWP_FRAMEWORK_IMPROVED::is_normal_admin()
					&& array_key_exists($name, $this->form['role'])
					&& $this->form['role'][$name] == 'superadmin')
				{
					// hide superadmin-only fields if user is normal admin
					continue;
				}

				if (!empty($name) && !empty($this->form_item_labels[$key])
				) {
					$return_str .= '<li class="bwp-clear">'
						. $this->generate_html_fields($type, $name)
						. '</li>'
						. "\n";
				}
			}
		}

		$return_str .= '</ul>' . "\n";
		$return_str .= apply_filters('bwp_option_before_submit_button', '');

		echo $return_str;
		do_action('bwp_option_action_before_submit_button');

		$return_str  = '';
		$return_str .= apply_filters('bwp_option_submit_button',
			'<p class="submit"><input type="submit" class="button-primary" name="submit_'
			. $this->form_name . '" value="' . __('Save Changes') . '" /></p>') . "\n";

		$return_str .= '</form>' . "\n";
		$return_str .= '</div>' . "\n";

		echo $return_str;
	}
}

<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// tracker.inc.php
// Copyright 2003-2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Issue tracker plugin (See Also bugtrack plugin)

// tracker_listで表示しないページ名(正規表現で)
// 'SubMenu'ページ および '/'を含むページを除外する
define('TRACKER_LIST_EXCLUDE_PATTERN', '#^SubMenu$|/#');
// 制限しない場合はこちら
//define('TRACKER_LIST_EXCLUDE_PATTERN','#(?!)#');

// 項目の取り出しに失敗したページを一覧に表示する
define('TRACKER_LIST_SHOW_ERROR_PAGE', true);

// Use cache
define('TRACKER_LIST_USE_CACHE', true);

function plugin_tracker_convert(string ...$args) : string
{
	global $vars;

	$script = get_base_uri();

	if (PKWK_READONLY) {
		// Show nothing
		return '';
	}

	$refer = $vars['page'];
	$base = $refer;

	$config_name = 'default';
	$form = 'form';
	$options = [];

	switch (func_num_args()) {
		case 3:
			$options = array_splice($args, 2);
			//ToDo: FALLTHROUGH?

		case 2:
			$args[1] = get_fullname($args[1], $base);
			$base = (is_pagename($args[1])) ? ($args[1]) : ($base);
			//ToDo: FALLTHROUGH?

		case 1:
			$config_name = ($args[0] != '') ? ($args[0]) : ($config_name);
			[$config_name,$form] = array_pad(explode('/', $config_name, 2), 2, $form);

			break;

		default:
			break;
	}

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read()) {
		return '<p>config file \''.htmlspecialchars($config_name, ENT_COMPAT, 'UTF-8').'\' not found.</p>';
	}

	$config->config_name = $config_name;

	$fields = plugin_tracker_get_fields($base, $refer, $config);

	$form = $config->page.'/'.$form;

	if (!is_page($form)) {
		return '<p>config file \''.make_pagelink($form).'\' not found.</p>';
	}

	$retval = convert_html(plugin_tracker_get_source($form));
	$hiddens = '';

	foreach (array_keys($fields) as $name) {
		$replace = $fields[$name]->get_tag();

		if (is_a($fields[$name], 'Tracker_field_hidden')) {
			$hiddens .= $replace;
			$replace = '';
		}

		$retval = str_replace('['.$name.']', $replace, $retval);
	}

	return <<<EOD
<form enctype="multipart/form-data" action="{$script}" method="post" class="_p_tracker_form">
	<div>
		{$retval}
		{$hiddens}
	</div>
</form>
EOD;
}

function plugin_tracker_action() : array
{
	global $post;
	global $vars;
	global $now;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	$config_name = (array_key_exists('_config', $post)) ? ($post['_config']) : ('');

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read()) {
		return ['msg'=>'cannot write', 'body'=>'config file \''.htmlspecialchars($config_name, ENT_COMPAT, 'UTF-8').'\' not found.'];
	}

	$config->config_name = $config_name;
	$source = $config->page.'/page';

	$refer = (array_key_exists('_refer', $post)) ? ($post['_refer']) : ($post['_base']);

	if (!is_pagename($refer)) {
		return ['msg'=>'cannot write', 'body'=>'page name ('.htmlspecialchars($refer, ENT_COMPAT, 'UTF-8').') is not valid.'];
	}

	if (!is_page($source)) {
		return ['msg'=>'cannot write', 'body'=>'page template ('.htmlspecialchars($source, ENT_COMPAT, 'UTF-8').') is not exist.'];
	}

	// ページ名を決定
	$base = $post['_base'];

	if (!is_pagename($base)) {
		return ['msg'=>'cannot write', 'body'=>'page name ('.htmlspecialchars($base, ENT_COMPAT, 'UTF-8').') is not valid.'];
	}

	$name = (array_key_exists('_name', $post)) ? ($post['_name']) : ('');
	$_page = (array_key_exists('_page', $post)) ? ($post['_page']) : ('');

	if (is_pagename($_page)) {
		// Create _page page if _page is in parameters
		$real = $_page;
		$page = $real;
	} elseif (is_pagename($name)) {
		// Create "$base/$name" page if _name is in parameters
		$real = $name;
		$page = get_fullname('./'.$name, $base);
	} else {
		$page = '';
	}

	if ((!is_pagename($page)) || (is_page($page))) {
		// Need new page name=>Get last article number + 1
		$page_list = plugin_tracker_get_page_list($base, false);
		usort($page_list, '_plugin_tracker_list_paganame_compare');

		if (count($page_list) === 0) {
			$num = 1;
		} else {
			$latest_page = $page_list[count($page_list) - 1]['name'];
			$num = (int) (substr($latest_page, strlen($base) + 1)) + 1;
		}

		$real = ''.$num;
		$page = $base.'/'.$num;
	}

	// ページデータを生成
	$postdata = plugin_tracker_get_source($source);

	// 規定のデータ
	$_post = array_merge($post, $_FILES);
	$_post['_date'] = $now;
	$_post['_page'] = $page;
	$_post['_name'] = $name;
	$_post['_real'] = $real;
	// $_post['_refer'] = $_post['refer'];

	$fields = plugin_tracker_get_fields($page, $refer, $config);

	check_editable($page, true, true);

	// Creating an empty page, before attaching files
	touch(get_filename($page));

	foreach (array_keys($fields) as $key) {
		$value = (array_key_exists($key, $_post)) ? ($fields[$key]->format_value($_post[$key])) : ('');

		foreach (array_keys($postdata) as $num) {
			if (trim($postdata[$num]) == '') {
				continue;
			}

			$postdata[$num] = str_replace('['.$key.']', ((($postdata[$num][0] == '|') || ($postdata[$num][0] == ':')) ? (str_replace('|', '&#x7c;', $value)) : ($value)), $postdata[$num]);
		}
	}

	// Writing page data, without touch
	page_write($page, implode('', $postdata));
	pkwk_headers_sent();
	header('Location: '.get_page_uri($page, PKWK_URI_ROOT));

	exit;
}

/**
 * Page_list comparator.
 */
function _plugin_tracker_list_paganame_compare(string $a, string $b) : int
{
	return strnatcmp($a['name'], $b['name']);
}

/**
 * Get page list for "$page/".
 */
function plugin_tracker_get_page_list(string $page, bool $needs_filetime) : array
{
	$page_list = [];
	$pattern = $page.'/';
	$pattern_len = strlen($pattern);

	foreach (get_existpages() as $p) {
		if ((strncmp($p, $pattern, $pattern_len) === 0) && (ctype_digit(substr($p, $pattern_len)))) {
			if ($needs_filetime) {
				$page_list[] = ['name'=>$p, 'filetime'=>get_filetime($p)];
			} else {
				$page_list[] = ['name'=>$p];
			}
		}
	}

	return $page_list;
}

// フィールドオブジェクトを構築する
function plugin_tracker_get_fields(string $base, string $refer, Config &$config) : array
{
	global $now;
	global $_tracker_messages;

	$fields = [];
	// 予約語
	foreach (
		[
			// 投稿日時
			'_date'=>'text',

			// 最終更新
			'_update'=>'date',

			// 経過(passage)
			'_past'=>'past',

			// ページ名
			'_page'=>'page',

			// 指定されたページ名
			'_name'=>'text',

			// 実際のページ名
			'_real'=>'real',

			// 参照元(フォームのあるページ)
			'_refer'=>'page',

			// 基準ページ
			'_base'=>'page',

			// 追加ボタン
			'_submit'=>'submit',
		] as $field=>$class) {
		$class = 'Tracker_field_'.$class;
		$fields[$field] = new $class([$field, $_tracker_messages['btn'.$field], '', '20', ''], $base, $refer, $config);
	}

	foreach ($config->get('fields') as $field) {
		// 0=>項目名 1=>見出し 2=>形式 3=>オプション 4=>デフォルト値
		$class = 'Tracker_field_'.$field[2];

		// デフォルト
		if (!class_exists($class)) {
			$class = 'Tracker_field_text';
			$field[2] = 'text';
			$field[3] = '20';
		}

		$fields[$field[0]] = new $class($field, $base, $refer, $config);
	}

	return $fields;
}

// フィールドクラス
class Tracker_field
{
	public /* string */ $name;

	public /* string */ $title;

	public /* array */ $values;

	public /* string */ $default_value;

	public /* string */ $page;

	public /* string */ $refer;

	public /* Config */ $config;

	public /* string */ $data;

	public /* int */ $sort_type = SORT_REGULAR;

	public /* int */ $id = 0;

	public function Tracker_field(array $field, string $page, string $refer, Config &$config) : void
	{
		$this->__construct($field, $page, $refer, $config);
	}

	public function __construct(array $field, string $page, string $refer, Config &$config)
	{
		global $post;
		static $id = 0;

		$this->id = ++$id;
		$this->name = $field[0];
		$this->title = $field[1];
		$this->values = explode(',', $field[3]);
		$this->default_value = $field[4];
		$this->page = $page;
		$this->refer = $refer;
		$this->config = &$config;
		$this->data = (array_key_exists($this->name, $post)) ? ($post[$this->name]) : ('');
	}

	public function get_tag() : string
	{
		return '';
	}

	public function get_style(string $str) : string
	{
		return '%s';
	}

	public function format_value($value) : string
	{
		return $value;
	}

	public function format_cell(string $str) : string
	{
		return $str;
	}

	public function get_value(string $value) : string
	{
		return $value;
	}
}

class Tracker_field_text extends Tracker_field
{
	public /* int */ $sort_type = SORT_STRING;

	public function get_tag() : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_size = htmlspecialchars($this->values[0], ENT_COMPAT, 'UTF-8');
		$s_value = htmlspecialchars($this->default_value, ENT_COMPAT, 'UTF-8');

		return '<input type="text" name="'.$s_name.'" size="'.$s_size.'" value="'.$s_value.'" />';
	}
}

class Tracker_field_page extends Tracker_field_text
{
	public /* int */ $sort_type = SORT_STRING;

	public function format_value($value) : string
	{
		global $WikiName;

		$value = strip_bracket($value);

		if (is_pagename($value)) {
			$value = '[['.$value.']]';
		}

		return parent::format_value($value);
	}
}

class Tracker_field_real extends Tracker_field_text
{
	public /* int */ $sort_type = SORT_REGULAR;
}

class Tracker_field_title extends Tracker_field_text
{
	public /* int */ $sort_type = SORT_STRING;

	public function format_cell(string $str) : string
	{
		make_heading($str);

		return $str;
	}
}

class Tracker_field_textarea extends Tracker_field
{
	public /* int */ $sort_type = SORT_STRING;

	public function get_tag() : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_cols = htmlspecialchars($this->values[0], ENT_COMPAT, 'UTF-8');
		$s_rows = htmlspecialchars($this->values[1], ENT_COMPAT, 'UTF-8');
		$s_value = htmlspecialchars($this->default_value, ENT_COMPAT, 'UTF-8');
		$s_value = str_replace("\n", '&NewLine;', $s_value);

		return '<textarea name="'.$s_name.'" cols="'.$s_cols.'" rows="'.$s_rows.'">'.$s_value.'</textarea>';
	}

	public function format_cell(string $str) : string
	{
		$str = preg_replace('/[\r\n]+/', '', $str);

		if ((!empty($this->values[2])) && (strlen($str) > ($this->values[2] + 3))) {
			$str = mb_substr($str, 0, $this->values[2]).'...';
		}

		return $str;
	}
}

class Tracker_field_format extends Tracker_field
{
	public /* int */ $sort_type = SORT_STRING;

	public /* array */ $styles = [];

	public /* array */ $formats = [];

	public function Tracker_field_format(array $field, string $page, string $refer, Config &$config) : void
	{
		$this->__construct($field, $page, $refer, $config);
	}

	public function __construct(array $field, string $page, string $refer, Config &$config)
	{
		parent::__construct($field, $page, $refer, $config);

		foreach ($this->config->get($this->name) as $option) {
			[$key,$style,$format] = array_pad(array_map('trim', $option), 3, '');

			if ($style != '') {
				$this->styles[$key] = $style;
			}

			if ($format != '') {
				$this->formats[$key] = $format;
			}
		}
	}

	public function get_tag() : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_size = htmlspecialchars($this->values[0], ENT_COMPAT, 'UTF-8');

		return '<input type="text" name="'.$s_name.'" size="'.$s_size.'" />';
	}

	public function get_key(string $str) : string
	{
		return ($str == '') ? ('IS NULL') : ('IS NOT NULL');
	}

	public function format_value($str) : string
	{
		if (is_array($str)) {
			return implode(', ', array_map([$this, 'format_value'], $str));
		}

		$key = $this->get_key($str);

		return (array_key_exists($key, $this->formats)) ? (str_replace('%s', $str, $this->formats[$key])) : ($str);
	}

	public function get_style(string $str) : string
	{
		$key = $this->get_key($str);

		return (array_key_exists($key, $this->styles)) ? ($this->styles[$key]) : ('%s');
	}
}

class Tracker_field_file extends Tracker_field_format
{
	public /* int */ $sort_type = SORT_STRING;

	public function get_tag() : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_size = htmlspecialchars($this->values[0], ENT_COMPAT, 'UTF-8');

		return '<input type="file" name="'.$s_name.'" size="'.$s_size.'" />';
	}

	public function format_value($str) : string
	{
		if (array_key_exists($this->name, $_FILES)) {
			require_once PLUGIN_DIR.'attach.inc.php';
			$result = attach_upload($_FILES[$this->name], $this->page);

			// アップロード成功
			if ($result['result']) {
				return parent::format_value($this->page.'/'.$_FILES[$this->name]['name']);
			}
		}

		// ファイルが指定されていないか、アップロードに失敗
		return parent::format_value('');
	}
}

class Tracker_field_radio extends Tracker_field_format
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function get_tag() : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$retval = '';
		$id = 0;

		foreach ($this->config->get($this->name) as $option) {
			$s_option = htmlspecialchars($option[0], ENT_COMPAT, 'UTF-8');
			$checked = (trim($option[0]) == trim($this->default_value)) ? (' checked="checked"') : ('');
			$id++;
			$s_id = '_p_tracker_'.$s_name.'_'.$this->id.'_'.$id;
			$retval .= '<input type="radio" name="'.$s_name.'" id="'.$s_id.'" value="'.$s_option.'"'.$checked.' /><label for="'.$s_id.'">'.$s_option.'</label>'."\n";
		}

		return $retval;
	}

	public function get_key(string $str) : string
	{
		return $str;
	}

	public function get_value(string $value) : string
	{
		static $options = [];

		if (!array_key_exists($this->name, $options)) {
			// 'reset' means function($arr) { return $arr[0]; }
			$options[$this->name] = array_flip(array_map('reset', $this->config->get($this->name)));
		}

		return (array_key_exists($value, $options[$this->name])) ? ($options[$this->name][$value]) : ($value);
	}
}

class Tracker_field_select extends Tracker_field_radio
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function get_tag(bool $empty = false) : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_size = ((array_key_exists(0, $this->values)) && (is_numeric($this->values[0]))) ? (' size="'.htmlspecialchars($this->values[0], ENT_COMPAT, 'UTF-8').'"') : ('');
		$s_multiple = ((array_key_exists(1, $this->values)) && (strtolower($this->values[1]) == 'multiple')) ? (' multiple="multiple"') : ('');
		$retval = '<select name="'.$s_name.'[]"'.$s_size.$s_multiple.'>'."\n";

		if ($empty) {
			$retval .= "\t".'<option value=""></option>'."\n";
		}

		$defaults = array_flip(preg_split('/\s*,\s*/', $this->default_value, -1, PREG_SPLIT_NO_EMPTY));

		foreach ($this->config->get($this->name) as $option) {
			$s_option = htmlspecialchars($option[0], ENT_COMPAT, 'UTF-8');
			$selected = (array_key_exists(trim($option[0]), $defaults)) ? (' selected="selected"') : ('');
			$retval .= "\t".'<option value="'.$s_option.'"'.$selected.'>'.$s_option.'</option>'."\n";
		}

		$retval .= '</select>';

		return $retval;
	}
}

class Tracker_field_checkbox extends Tracker_field_radio
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function get_tag(bool $empty = false) : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$defaults = array_flip(preg_split('/\s*,\s*/', $this->default_value, -1, PREG_SPLIT_NO_EMPTY));
		$retval = '';
		$id = 0;

		foreach ($this->config->get($this->name) as $option) {
			$s_option = htmlspecialchars($option[0], ENT_COMPAT, 'UTF-8');
			$checked = (array_key_exists(trim($option[0]), $defaults)) ? (' checked="checked"') : ('');
			$id++;
			$s_id = '_p_tracker_'.$s_name.'_'.$this->id.'_'.$id;
			$retval .= '<input type="checkbox" name="'.$s_name.'[]" id="'.$s_id.'" value="'.$s_option.'"'.$checked.' /><label for="'.$s_id.'">'.$s_option.'</label>'."\n";
		}

		return $retval;
	}
}

class Tracker_field_hidden extends Tracker_field_radio
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function get_tag(bool $empty = false) : string
	{
		$s_name = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8');
		$s_default = htmlspecialchars($this->default_value, ENT_COMPAT, 'UTF-8');

		return '<input type="hidden" name="'.$s_name.'" value="'.$s_default.'" />'."\n";
	}
}

class Tracker_field_submit extends Tracker_field
{
	public function get_tag() : string
	{
		$s_title = htmlspecialchars($this->title, ENT_COMPAT, 'UTF-8');
		$s_page = htmlspecialchars($this->page, ENT_COMPAT, 'UTF-8');
		$s_refer = htmlspecialchars($this->refer, ENT_COMPAT, 'UTF-8');
		$s_config = htmlspecialchars($this->config->config_name, ENT_COMPAT, 'UTF-8');

		return <<<EOD
<input type="submit" value="{$s_title}" />
<input type="hidden" name="plugin" value="tracker" />
<input type="hidden" name="_refer" value="{$s_refer}" />
<input type="hidden" name="_base" value="{$s_page}" />
<input type="hidden" name="_config" value="{$s_config}" />
EOD;
	}
}

class Tracker_field_date extends Tracker_field
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function format_cell(string $timestamp) : string
	{
		return format_date((int) ($timestamp));
	}
}

class Tracker_field_past extends Tracker_field
{
	public /* int */ $sort_type = SORT_NUMERIC;

	public function format_cell(string $timestamp) : string
	{
		return '&passage("'.get_date_atom($timestamp + LOCALZONE).'");';
	}

	public function get_value(string $value) : string
	{
		return (string) (UTIME - ((int) ($value)));
	}
}

///////////////////////////////////////////////////////////////////////////
// 一覧表示
function plugin_tracker_list_convert(string ...$args) : string
{
	global $vars;
	global $_title_cannotread;

	$config = 'default';
	$refer = $vars['page'];
	$page = $refer;
	$field = '_page';
	$order = '';
	$list = 'list';
	$limit = null;
	$start_n = null;
	$last_n = null;

	switch (func_num_args()) {
		case 4:
			$range_m = null;

			if (is_numeric($args[3])) {
				$limit = $args[3];
			} else {
				if (preg_match('#^(\d+)-(\d+)$#', $args[3], $range_m)) {
					$start_n = (int) ($range_m[1]);
					$last_n = (int) ($range_m[2]);
				}
			}

			//ToDo: FALLTHROUGH?

		case 3:
			$order = $args[2];
			//ToDo: FALLTHROUGH?

		case 2:
			$args[1] = get_fullname($args[1], $page);
			$page = (is_pagename($args[1])) ? ($args[1]) : ($page);
			//ToDo: FALLTHROUGH?

		case 1:
			$config = ($args[0] != '') ? ($args[0]) : ($config);
			[$config,$list] = array_pad(explode('/', $config, 2), 2, $list);

			break;

		default:
			break;
	}

	if (!is_page_readable($page)) {
		$body = str_replace('$1', htmlspecialchars($page, ENT_COMPAT, 'UTF-8'), $_title_cannotread);

		return $body;
	}

	return plugin_tracker_getlist($page, $refer, $config, $list, $order, $limit, $start_n, $last_n);
}

function plugin_tracker_list_action() : array
{
	global $vars;
	global $_tracker_messages;
	global $_title_cannotread;

	$refer = $vars['refer'];
	$page = $refer;
	$s_page = make_pagelink($page);
	$config = $vars['config'];
	$list = (array_key_exists('list', $vars)) ? ($vars['list']) : ('list');
	$order = (array_key_exists('order', $vars)) ? ($vars['order']) : ('_real:SORT_DESC');

	if (!is_page_readable($page)) {
		$body = str_replace('$1', htmlspecialchars($page, ENT_COMPAT, 'UTF-8'), $_title_cannotread);

		return ['msg'=>$body, 'body'=>$body];
	}

	return ['msg'=>$_tracker_messages['msg_list'], 'body'=>str_replace('$1', $s_page, $_tracker_messages['msg_back']).plugin_tracker_getlist($page, $refer, $config, $list, $order)];
}

function plugin_tracker_getlist(string $page, $refer, string $config_name, string $list, string $order = '', ?int $limit = null, ?int $start_n = null, ?int $last_n = null) : string
{
	global $whatsdeleted;

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read()) {
		return '<p>config file \''.htmlspecialchars($config_name, ENT_COMPAT, 'UTF-8').'\' is not exist.</p>';
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list)) {
		return '<p>config file \''.make_pagelink($config->page.'/'.$list).'\' not found.</p>';
	}

	$cache_enabled = (defined('TRACKER_LIST_USE_CACHE')) && (TRACKER_LIST_USE_CACHE);

	if (($limit === null) && ($start_n === null)) {
		$cache_filepath = CACHE_DIR.encode($page).'.tracker';
	} elseif ((ctype_digit($limit)) && ($limit > 0) && ($limit <= 1000)) {
		$cache_filepath = CACHE_DIR.encode($page).'.'.$limit.'.tracker';
	} elseif (($start_n !== null) && ($last_n !== null)) {
		$cache_filepath = CACHE_DIR.encode($page).'.'.$start_n.'-'.$last_n.'.tracker';
	} else {
		$cache_enabled = false;
	}

	$cachedata = null;
	$cache_format_version = 1;

	if ($cache_enabled) {
		$config_filetime = get_filetime($config->page);
		$config_list_filetime = get_filetime($config->page.'/'.$list);

		if (file_exists($cache_filepath)) {
			$json_cached = pkwk_file_get_contents($cache_filepath);

			if ($json_cached) {
				$wrapdata = json_decode($json_cached, true);

				if ((is_array($wrapdata)) && (isset($wrapdata['version'], $wrapdata['html'], $wrapdata['refreshed_at']))) {
					$cache_time_prev = $wrapdata['refreshed_at'];

					if ($cache_format_version === $wrapdata['version']) {
						if (($config_filetime === $wrapdata['config_updated_at']) && ($config_list_filetime === $wrapdata['config_list_updated_at'])) {
							$cachedata = $wrapdata;
						} else {
							// (Ignore) delete file
							unlink($cache_filepath);
						}
					}
				}
			}
		}
	}

	// Check recent.dat timestamp
	$recent_dat_filemtime = filemtime(CACHE_DIR.PKWK_MAXSHOW_CACHE);

	// Check RecentDeleted timestamp
	$recent_deleted_filetime = get_filetime($whatsdeleted);

	if ($cachedata === null) {
		$cachedata = [];
	} else {
		if ($recent_dat_filemtime !== false) {
			if (($recent_dat_filemtime === $cachedata['recent_dat_filemtime']) && ($recent_deleted_filetime === $cachedata['recent_deleted_filetime']) && ($order === $cachedata['order'])) {
				// recent.dat is unchanged
				// RecentDeleted is unchanged
				// order is unchanged
				return $cachedata['html'];
			}
		}
	}

	$cache_holder = $cachedata;
	$tracker_list = new Tracker_list($page, $refer, $config, $list, $cache_holder);

	if (($order === $cache_holder['order']) && (empty($tracker_list->newly_deleted_pages)) && (empty($tracker_list->newly_updated_pages)) && (!$tracker_list->link_update_required) && ($start_n === null) && ($last_n === null)) {
		$result = $cache_holder['html'];
	} else {
		$tracker_list->sort($order);
		$result = $tracker_list->toString($limit, $start_n, $last_n);
	}

	if ($cache_enabled) {
		$refreshed_at = time();

		$json =
		[
			'refreshed_at'=>$refreshed_at,
			'rows'=>$tracker_list->rows,
			'html'=>$result,
			'order'=>$order,
			'config_updated_at'=>$config_filetime,
			'config_list_updated_at'=>$config_list_filetime,
			'recent_dat_filemtime'=>$recent_dat_filemtime,
			'recent_deleted_filetime'=>$recent_deleted_filetime,
			'link_pages'=>$tracker_list->link_pages,
			'version'=>$cache_format_version,
		];

		$cache_body = json_encode($json, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		file_put_contents($cache_filepath, $cache_body, LOCK_EX);
	}

	return $result;
}

// 一覧クラス
class Tracker_list
{
	public /* string */ $page;

	public /* Config */ $config;

	public /* string */ $list;

	public /* array */ $fields;

	public /* string */ $pattern;

	public /* array */ $pattern_fields;

	public /* array */ $rows;

	public /* array */ $order;

	public /* array */ $sort_keys;

	public /* array */ $newly_deleted_pages = [];

	public /* array */ $newly_updated_pages = [];

	public /* array */ $link_pages;

	public /* bool */ $link_update_required;

	public /* array */ $items;

	public /* bool */ $pipe;

	public function Tracker_list(string $page, string $refer, Config &$config, string $list, array &$cache_holder) : void
	{
		$this->__construct($page, $refer, $config, $list, $cache_holder);
	}

	public function __construct(string $page, string $refer, Config &$config, string $list, array &$cache_holder)
	{
		global $whatsdeleted;
		global $_cached_page_filetime;

		$this->page = $page;
		$this->config = &$config;
		$this->list = $list;
		$this->fields = plugin_tracker_get_fields($page, $refer, $config);

		$pattern = implode('', plugin_tracker_get_source($config->page.'/page'));
		// ブロックプラグインをフィールドに置換
		// #commentなどで前後に文字列の増減があった場合に、[_block_xxx]に吸い込ませるようにする
		$pattern = preg_replace('/^\#([^\(\s]+)(?:\((.*)\))?\s*$/m', '[_block_$1]', $pattern);

		// パターンを生成
		$this->pattern = '';
		$this->pattern_fields = [];
		$pattern = preg_split('/\\\\\[(\w+)\\\\\]/', preg_quote($pattern, '/'), -1, PREG_SPLIT_DELIM_CAPTURE);

		while (count($pattern)) {
			$this->pattern .= preg_replace('/\s+/', '\\s*', '(?>\\s*'.trim(array_shift($pattern)).'\\s*)');

			if (count($pattern)) {
				$field = array_shift($pattern);
				$this->pattern_fields[] = $field;
				$this->pattern .= '(.*?)';
			}
		}

		if (empty($cache_holder)) {
			// List pages and get contents (non-cache behavior)
			$this->rows = [];
			$pattern = $page.'/';
			$pattern_len = strlen($pattern);

			foreach (get_existpages() as $_page) {
				if (substr($_page, 0, $pattern_len) === $pattern) {
					$name = substr($_page, $pattern_len);

					if (preg_match(TRACKER_LIST_EXCLUDE_PATTERN, $name)) {
						continue;
					}

					$this->add($_page, $name);
				}
			}

			$this->link_pages = $this->get_filetimes($this->get_all_links());
		} else {
			// Cache-available behavior
			// Check RecentDeleted timestamp
			$cached_rows = $this->decode_cached_rows($cache_holder['rows']);
			$updated_linked_pages = [];
			$newly_deleted_pages = [];
			$pattern = $page.'/';
			$pattern_len = strlen($pattern);
			$recent_deleted_filetime = get_filetime($whatsdeleted);
			$deleted_page_list = [];

			if ($recent_deleted_filetime !== $cache_holder['recent_deleted_filetime']) {
				foreach (plugin_tracker_get_source($whatsdeleted) as $line) {
					$m = null;

					if (preg_match('#\[\[([^\]]+)\]\]#', $line, $m)) {
						$_page = $m[1];

						if (is_pagename($_page)) {
							$deleted_page_list[] = $m[1];
						}
					}
				}

				foreach ($deleted_page_list as $_page) {
					if (substr($_page, 0, $pattern_len) === $pattern) {
						$name = substr($_page, $pattern_len);

						if ((!is_page($_page)) && (isset($cached_rows[$name])) && (!preg_match(TRACKER_LIST_EXCLUDE_PATTERN, $name))) {
							// This page was just deleted
							array_push($newly_deleted_pages, $_page);
							unset($cached_rows[$name]);
						}
					}
				}
			}

			$this->newly_deleted_pages = $newly_deleted_pages;
			$updated_pages = [];
			$this->rows = $cached_rows;
			// Check recent.dat timestamp
			$recent_dat_filemtime = filemtime(CACHE_DIR.PKWK_MAXSHOW_CACHE);
			$updated_page_list = [];

			if ($recent_dat_filemtime !== $cache_holder['recent_dat_filemtime']) {
				// recent.dat was updated. Search which page was updated.
				$target_pages = [];
				// Active page file time (1 hour before timestamp of recent.dat)
				$target_filetime = $cache_holder['recent_dat_filemtime'] - LOCALZONE - 60 * 60;

				foreach (get_recent_files() as $_page=>$time) {
					if ($time <= $target_filetime) {
						// Older updated pages
						break;
					}

					$updated_page_list[$_page] = $time;
					$name = substr($_page, $pattern_len);

					if (substr($_page, 0, $pattern_len) === $pattern) {
						$name = substr($_page, $pattern_len);

						if (preg_match(TRACKER_LIST_EXCLUDE_PATTERN, $name)) {
							continue;
						}

						// Tracker target page
						if (isset($this->rows[$name])) {
							// Existing page
							$row = $this->rows[$name];

							if ($row['_update'] === get_filetime($_page)) {
								// Same as cache
								continue;
							} else {
								// Found updated page
								$updated_pages[] = $_page;
								unset($this->rows[$name]);
								$this->add($_page, $name);
							}
						} else {
							// Add new page
							$updated_pages[] = $_page;
							$this->add($_page, $name);
						}
					}
				}
			}

			$this->newly_updated_pages = $updated_pages;
			$new_link_names = $this->get_all_links();
			$old_link_map = [];

			foreach ($cache_holder['link_pages'] as $link_page) {
				$old_link_map[$link_page['page']] = $link_page['filetime'];
			}

			$new_link_map = $old_link_map;
			$link_update_required = false;

			foreach ($deleted_page_list as $_page) {
				if (in_array($_page, $new_link_names, true)) {
					if (isset($old_link_map[$_page])) {
						// This link keeps existing
						if (!is_page($_page)) {
							// OK. Confirmed the page doesn't exist
							if ($old_link_map[$_page] === 0) {
								// Do nothing (From no-page to no-page)
							} else {
								// This page was just deleted
								$new_link_map[$_page] = get_filetime($_page);
								$link_update_required = true;
							}
						}
					} else {
						// This link was just added
						$new_link_map[$_page] = get_filetime($_page);
						$link_update_required = true;
					}
				}
			}

			foreach ($updated_page_list as $_page=>$time) {
				if (in_array($_page, $new_link_names, true)) {
					if (isset($old_link_map[$_page])) {
						// This link keeps existing
						if (is_page($_page)) {
							// OK. Confirmed the page now exists
							if ($old_link_map[$_page] === 0) {
								// This page was just added
								$new_link_map[$_page] = get_filetime($_page);
								$link_update_required = true;
							} else {
								// Do nothing (existing-page to existing-page)
							}
						}
					} else {
						// This link was just added
						$new_link_map[$_page] = get_filetime($_page);
						$link_update_required = true;
					}
				}
			}

			$new_link_pages = [];

			foreach ($new_link_map as $_page=>$time) {
				$new_link_pages[] =
				[
					'page'=>$_page,
					'filetime'=>$time,
				];
			}

			$this->link_pages = $new_link_pages;
			$this->link_update_required = $link_update_required;
			$time_map_for_cache = $new_link_map;

			foreach ($this->rows as $row) {
				$time_map_for_cache[$this->page.'/'.$row['_real']] = $row['_update'];
			}

			$_cached_page_filetime = $time_map_for_cache;
		}
	}

	public function decode_cached_rows(array $decoded_rows) : array
	{
		$ar = [];

		foreach ($decoded_rows as $row) {
			$ar[$row['_real']] = $row;
		}

		return $ar;
	}

	public function get_all_links() : array
	{
		$ar = [];

		foreach ($this->rows as $row) {
			foreach ($row['_links'] as $link) {
				$ar[$link] = 0;
			}
		}

		return array_keys($ar);
	}

	public function get_filetimes(array $pages) : array
	{
		$filetimes = [];

		foreach ($pages as $page) {
			$filetimes[] =
			[
				'page'=>$page,
				'filetime'=>get_filetime($page),
			];
		}

		return $filetimes;
	}

	public function add(string $page, string $name)
	{
		static $moved = [];

		// 無限ループ防止
		if (array_key_exists($name, $this->rows)) {
			return;
		}

		$source = plugin_tracker_get_source($page);

		if (preg_match('/move\sto\s(.+)/', $source[0], $matches)) {
			$page = strip_bracket(trim($matches[1]));

			if ((array_key_exists($page, $moved)) || (!is_page($page))) {
				return;
			}

			$moved[$page] = true;

			return $this->add($page, $name);
		}

		$source = implode('', preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/', '$1$2', $source));

		// Default value
		$page_filetime = get_filetime($page);

		$row =
		[
			'_page'=>'[['.$page.']]',
			'_refer'=>$this->page,
			'_real'=>$name,
			'_update'=>$page_filetime,
			'_past'=>$page_filetime,
		];

		$links = [];

		if ($row['_match'] = preg_match("/{$this->pattern}/s", $source, $matches)) {
			array_shift($matches);

			foreach ($this->pattern_fields as $key=>$field) {
				$row[$field] = trim($matches[$key]);

				if ($field === '_refer') {
					continue;
				}

				$lmatch = null;

				if (preg_match('/\[\[([^\]\]]+)\]/', $row[$field], $lmatch)) {
					$link = $lmatch[1];

					if ((is_pagename($link)) && ($link !== $this->page) && ($link !== $page)) {
						if (!in_array($link, $links, true)) {
							$links[] = $link;
						}
					}
				}
			}
		}

		$row['_links'] = $links;
		$this->rows[$name] = $row;
	}

	public function compare(array $a, array $b) : int
	{
		foreach ($this->sort_keys as $sort_key) {
			$field = $sort_key['field'];
			$dir = $sort_key['dir'];
			$f = $this->fields[$field];
			$sort_type = $f->sort_type;
			$aVal = (isset($a[$field])) ? ($f->get_value($a[$field])) : ('');
			$bVal = (isset($b[$field])) ? ($f->get_value($b[$field])) : ('');
			$c = strnatcmp($aVal, $bVal) * (($dir === SORT_ASC) ? (1) : (-1));

			if ($c === 0) {
				continue;
			}

			return $c;
		}

		return 0;
	}

	public function sort(string $order) : void
	{
		if ($order == '') {
			return;
		}

		$names = array_flip(array_keys($this->fields));
		$this->order = [];

		foreach (explode(';', $order) as $item) {
			[$key,$dir] = array_pad(explode(':', $item), 1, 'ASC');

			if (!array_key_exists($key, $names)) {
				continue;
			}

			switch (strtoupper($dir)) {
				case 'SORT_ASC':
				case 'ASC':
				case SORT_ASC:
					$dir = SORT_ASC;

					break;

				case 'SORT_DESC':
				case 'DESC':
				case SORT_DESC:
					$dir = SORT_DESC;

					break;

				default:
					continue 2;
			}

			$this->order[$key] = $dir;
		}

		$sort_keys = [];

		foreach ($this->order as $field=>$order) {
			if (!array_key_exists($field, $names)) {
				continue;
			}

			$sort_keys[] = ['field'=>$field, 'dir'=>$order];
		}

		$this->sort_keys = $sort_keys;
		usort($this->rows, [$this, 'compare']);
	}

	public function replace_item(string $arr) : string
	{
		$params = explode(',', $arr[1]);
		$name = array_shift($params);

		if ($name == '') {
			$str = '';
		} elseif (array_key_exists($name, $this->items)) {
			$str = $this->items[$name];

			if (array_key_exists($name, $this->fields)) {
				$str = $this->fields[$name]->format_cell($str);
			}
		} else {
			return ($this->pipe) ? (str_replace('|', '&#x7c;', $arr[0])) : ($arr[0]);
		}

		$style = (count($params)) ? ($params[0]) : ($name);

		if ((array_key_exists($style, $this->items)) && (array_key_exists($style, $this->fields))) {
			$str = sprintf($this->fields[$style]->get_style($this->items[$style]), $str);
		}

		return ($this->pipe) ? (str_replace('|', '&#x7c;', $str)) : ($str);
	}

	public function replace_title(array $arr) : string
	{
		$sort = $arr[1];
		$field = $sort;

		if (($sort == '_name') || ($sort == '_page')) {
			$sort = '_real';
		}

		if (!array_key_exists($field, $this->fields)) {
			return $arr[0];
		}

		$dir = SORT_ASC;
		$arrow = '';
		$order = $this->order;

		if ((is_array($order)) && (isset($order[$sort]))) {
			// with array_shift();
			$order_keys = array_keys($order);

			$index = array_flip($order_keys);
			$pos = 1 + $index[$sort];
			$b_end = ($sort == array_shift($order_keys));
			$b_order = ($order[$sort] == SORT_ASC);
			$dir = ($b_end xor $b_order) ? (SORT_ASC) : (SORT_DESC);
			$arrow = '&br;'.(($b_order) ? ('&uarr;') : ('&darr;')).'('.$pos.')';

			unset($order[$sort], $order_keys);
		}

		$title = $this->fields[$field]->title;
		$r_page = rawurlencode($this->page);
		$r_config = rawurlencode($this->config->config_name);
		$r_list = rawurlencode($this->list);
		$_order = [$sort.':'.$dir];

		if (is_array($order)) {
			foreach ($order as $key=>$value) {
				$_order[] = $key.':'.$value;
			}
		}

		$r_order = rawurlencode(implode(';', $_order));

		$script = get_base_uri(PKWK_URI_ABSOLUTE);

		return '[['.$title.$arrow.'>'.$script.'?plugin=tracker_list&refer='.$r_page.'&config='.$r_config.'&list='.$r_list.'&order='.$r_order.']]';
	}

	public function toString(?int $limit = null, ?int $start_n = null, ?int $last_n = null) : string
	{
		global $_tracker_messages;

		$source = '';
		$body = [];

		if (($limit !== null) && (count($this->rows) > $limit)) {
			$source = str_replace(['$1', '$2'], [count($this->rows), $limit], $_tracker_messages['msg_limit'])."\n";
			$this->rows = array_splice($this->rows, 0, $limit);
		} elseif (($start_n !== null) && ($last_n !== null)) {
			// sublist (range "start-last")
			$sublist = [];

			foreach ($this->rows as $row) {
				if (($start_n <= $row['_real']) && ($row['_real'] <= $last_n)) {
					$sublist[] = $row;
				}
			}

			$this->rows = $sublist;
		}

		if (count($this->rows) == 0) {
			return '';
		}

		foreach (plugin_tracker_get_source($this->config->page.'/'.$this->list) as $line) {
			if (preg_match('/^\|(.+)\|[hHfFcC]$/', $line)) {
				$source .= preg_replace_callback('/\[([^\[\]]+)\]/', [$this, 'replace_title'], $line);
			} else {
				$body[] = $line;
			}
		}

		foreach ($this->rows as $key=>$row) {
			if ((!TRACKER_LIST_SHOW_ERROR_PAGE) && (!$row['_match'])) {
				continue;
			}

			$this->items = $row;

			foreach ($body as $line) {
				if (trim($line) == '') {
					// Ignore empty line
					continue;
				}

				$this->pipe = ($line[0] == '|') || ($line[0] == ':');
				$source .= preg_replace_callback('/\[([^\[\]]+)\]/', [$this, 'replace_item'], $line);
			}
		}

		return convert_html($source);
	}
}

function plugin_tracker_get_source(string $page) : string
{
	$source = get_source($page);

	// Delete anchor part of Headings (Example: "*Heading1 [#id] AAA" to "*Heading1 AAA")
	$s2 = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m', '$1$2', $source);

	// Delete #freeze
	$s3 = preg_replace('/^#freeze\s*$/im', '', $s2);

	// Delete #author line
	return preg_replace('/^#author\b[^\r\n]*$/im', '', $s3);
}

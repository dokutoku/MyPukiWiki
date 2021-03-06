<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// bugtrack.inc.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2002 Y.MASUI GPL2  http://masui.net/pukiwiki/ masui@masui.net
//
// BugTrack plugin

// Numbering format
// Like 'page/1'
define('PLUGIN_BUGTRACK_NUMBER_FORMAT', '%d');

// Like 'page/001'
//define('PLUGIN_BUGTRACK_NUMBER_FORMAT', '%03d');

function plugin_bugtrack_init() : void
{
	global $_plugin_bugtrack;
	static $init;

	if (isset($init)) {
		// Already init
		return;
	}

	if (isset($_plugin_bugtrack)) {
		die('Global $_plugin_bugtrack had been init. Why?');
	}

	$init = true;

	$_plugin_bugtrack =
	[
		'priority_list'=>['緊急', '重要', '普通', '低'],
		'state_list'=>['提案', '着手', '完了', '保留', '却下'],
		'state_sort'=>['着手', '保留', '完了', '提案', '却下'],
		'state_class'=>
		[
			'bugtrack_state_proposal',
			'bugtrack_state_accept',
			'bugrack_state_resolved',
			'bugtrack_state_pending',
			'bugtrack_state_cancel',
			'bugtrack_state_undef',
		],
		'base'=>'ページ',
		'summary'=>'サマリ',
		'nosummary'=>'ここにサマリを記入して下さい',
		'priority'=>'優先順位',
		'state'=>'状態',
		'name'=>'投稿者',
		'noname'=>'名無しさん',
		'date'=>'投稿日',
		'body'=>'メッセージ',
		'category'=>'カテゴリー',
		'pagename'=>'ページ名',
		'pagename_comment'=>'空欄のままだと自動的にページ名が振られます。',
		'version_comment'=>'空欄でも構いません',
		'version'=>'バージョン',
		'submit'=>'追加',
	];
}

// #bugtrack: Show bugtrack form
function plugin_bugtrack_convert(string ...$args) : string
{
	global $vars;

	if (PKWK_READONLY) {
		// Show nothing
		return '';
	}

	$base = $vars['page'];
	$category = [];

	if (func_num_args()) {
		$category = $args;
		$_base = get_fullname(strip_bracket(array_shift($category)), $base);

		if (is_pagename($_base)) {
			$base = $_base;
		}
	}

	return plugin_bugtrack_print_form($base, $category);
}

function plugin_bugtrack_print_form(string $base, array $category) : string
{
	global $_plugin_bugtrack;
	static $id = 0;

	$id++;

	$select_priority = "\n";
	$count = count($_plugin_bugtrack['priority_list']);
	$selected = '';

	for ($i = 0; $i < $count; $i++) {
		if ($i == ($count - 1)) {
			$selected = ' selected="selected"';
		} // The last one
		$priority_list = htmlspecialchars($_plugin_bugtrack['priority_list'][$i], ENT_COMPAT, 'UTF-8');
		$select_priority .= "\t\t\t\t".'<option value="'.$priority_list.'"'.$selected.'>'.$priority_list.'</option>'."\n";
	}

	$select_state = "\n";

	for ($i = 0; $i < count($_plugin_bugtrack['state_list']); $i++) {
		$state_list = htmlspecialchars($_plugin_bugtrack['state_list'][$i], ENT_COMPAT, 'UTF-8');
		$select_state .= "\t\t\t\t".'<option value="'.$state_list.'">'.$state_list.'</option>'."\n";
	}

	if (empty($category)) {
		$encoded_category = '<input name="category" id="_p_bugtrack_category_'.$id.'" type="text" />';
	} else {
		$encoded_category = '<select name="category" id="_p_bugtrack_category_'.$id.'">';

		foreach ($category as $_category) {
			$s_category = htmlspecialchars($_category, ENT_COMPAT, 'UTF-8');
			$encoded_category .= '<option value="'.$s_category.'">'.$s_category.'</option>'."\n";
		}

		$encoded_category .= '</select>';
	}

	$script = get_base_uri();
	$s_base = htmlspecialchars($base, ENT_COMPAT, 'UTF-8');
	$s_name = htmlspecialchars($_plugin_bugtrack['name'], ENT_COMPAT, 'UTF-8');
	$s_category = htmlspecialchars($_plugin_bugtrack['category'], ENT_COMPAT, 'UTF-8');
	$s_priority = htmlspecialchars($_plugin_bugtrack['priority'], ENT_COMPAT, 'UTF-8');
	$s_state = htmlspecialchars($_plugin_bugtrack['state'], ENT_COMPAT, 'UTF-8');
	$s_pname = htmlspecialchars($_plugin_bugtrack['pagename'], ENT_COMPAT, 'UTF-8');
	$s_pnamec = htmlspecialchars($_plugin_bugtrack['pagename_comment'], ENT_COMPAT, 'UTF-8');
	$s_version = htmlspecialchars($_plugin_bugtrack['version'], ENT_COMPAT, 'UTF-8');
	$s_versionc = htmlspecialchars($_plugin_bugtrack['version_comment'], ENT_COMPAT, 'UTF-8');
	$s_summary = htmlspecialchars($_plugin_bugtrack['summary'], ENT_COMPAT, 'UTF-8');
	$s_body = htmlspecialchars($_plugin_bugtrack['body'], ENT_COMPAT, 'UTF-8');
	$s_submit = htmlspecialchars($_plugin_bugtrack['submit'], ENT_COMPAT, 'UTF-8');
	$body = <<<EOD
<form action="{$script}" method="post" class="_p_bugtrack_form">
	<table border="0">
		<tr>
			<th><label for="_p_bugtrack_name_{$id}">{$s_name}</label></th>
			<td><input id="_p_bugtrack_name_{$id}" name="name" size="20" type="text" /></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_category_{$id}">{$s_category}</label></th>
			<td>{$encoded_category}</td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_priority_{$id}">{$s_priority}</label></th>
			<td><select id="_p_bugtrack_priority_{$id}" name="priority">{$select_priority}   </select></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_state_{$id}">{$s_state}</label></th>
			<td><select id="_p_bugtrack_state_{$id}" name="state">{$select_state}   </select></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_pagename_{$id}">{$s_pname}</label></th>
			<td><input id="_p_bugtrack_pagename_{$id}" name="pagename" size="20" type="text" />
				<small>{$s_pnamec}</small></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_version_{$id}">{$s_version}</label></th>
			<td><input id="_p_bugtrack_version_{$id}" name="version" size="10" type="text" />
				<small>{$s_versionc}</small></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_summary_{$id}">{$s_summary}</label></th>
			<td><input id="_p_bugtrack_summary_{$id}" name="summary" size="60" type="text" /></td>
		</tr>
		<tr>
			<th><label for="_p_bugtrack_body_{$id}">{$s_body}</label></th>
			<td><textarea id="_p_bugtrack_body_{$id}" name="body" cols="60" rows="6"></textarea></td>
		</tr>
		<tr>
			<td colspan="2" align="center">
				<input type="submit" value="{$s_submit}" />
				<input type="hidden" name="plugin" value="bugtrack" />
				<input type="hidden" name="mode" value="submit" />
				<input type="hidden" name="base" value="{$s_base}" />
			</td>
		</tr>
	</table>
</form>
EOD;

	return $body;
}

// Add new issue
function plugin_bugtrack_action() : bool
{
	global $post;

	if (PKWK_READONLY) {
		die_message('PKWK_READONLY prohibits editing');
	}

	if ($post['mode'] != 'submit') {
		return false;
	}

	$page = plugin_bugtrack_write($post['base'], $post['pagename'], $post['summary'], $post['name'], $post['priority'], $post['state'], $post['category'], $post['version'], $post['body']);

	pkwk_headers_sent();
	header('Location: '.get_page_uri($page, PKWK_URI_ROOT));

	exit;
}

function plugin_bugtrack_write(string $base, string $pagename, string $summary, string $name, string $priority, string $state, string $category, string $version, string $body) : string
{
	global $post;

	$base = strip_bracket($base);
	$pagename = strip_bracket($pagename);

	$postdata = plugin_bugtrack_template($base, $summary, $name, $priority, $state, $category, $version, $body);

	$page_list = plugin_bugtrack_get_page_list($base, false);
	usort($page_list, '_plugin_bugtrack_list_paganame_compare');

	if (count($page_list) == 0) {
		$id = 1;
	} else {
		$latest_page = $page_list[count($page_list) - 1]['name'];
		$id = (int) (substr($latest_page, strlen($base) + 1)) + 1;
	}

	$page = $base.'/'.sprintf(PLUGIN_BUGTRACK_NUMBER_FORMAT, $id);

	check_editable($page, true, true);

	if ($pagename == '') {
		page_write($page, $postdata);
	} else {
		$pagename = get_fullname($pagename, $base);

		if ((is_page($pagename)) || (!is_pagename($pagename))) {
			// Set default
			$pagename = $page;
		} else {
			check_editable($pagename, true, true);
			page_write($page, 'move to [['.$pagename.']]');
		}

		page_write($pagename, $postdata);
	}

	return $page;
}

// Generate new page contents
function plugin_bugtrack_template(string $base, string $summary, string $name, string $priority, string $state, string $category, string $version, string $body) : string
{
	global $_plugin_bugtrack;

	$base = '[['.$base.']]';

	if ($name != '') {
		$name = '[['.$name.']]';
	}

	if ($name == '') {
		$name = $_plugin_bugtrack['noname'];
	}

	if ($summary == '') {
		$summary = $_plugin_bugtrack['nosummary'];
	}

	return <<<EOD
* {$summary}

- ${_plugin_bugtrack['base']}: {$base}
- ${_plugin_bugtrack['name']}: {$name}
- ${_plugin_bugtrack['priority']}: {$priority}
- ${_plugin_bugtrack['state']}: {$state}
- ${_plugin_bugtrack['category']}: {$category}
- ${_plugin_bugtrack['date']}: &now;
- ${_plugin_bugtrack['version']}: {$version}

** ${_plugin_bugtrack['body']}
{$body}
--------

#comment
EOD;
}

// ----------------------------------------
// BugTrack-List plugin

function _plugin_bugtrack_list_paganame_compare(array $a, array $b) : int
{
	return strnatcmp($a['name'], $b['name']);
}

/**
 * Get page list for "$page/".
 */
function plugin_bugtrack_get_page_list(string $page, bool $needs_filetime) : array
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

/**
 * #bugtrack_list plugin itself.
 *
 * Cache specification
 * * Enable only for PHP5.4+ (Because of JSON_UNESCAPE_UNICODE)
 * * Use cached values for articles that have the unchaged filemtime
 * * Invalidate all cache data everyday
 */
function plugin_bugtrack_list_convert(string ...$args) : string
{
	global $vars;
	global $_plugin_bugtrack;
	global $_title_cannotread;
	global $whatsdeleted;

	$cache_format_version = 1;
	$cache_expire_time = 60 * 60 * 24;
	$cache_refresh_time_prev = null;

	$page = $vars['page'];

	if (func_num_args()) {
		$_page = $args[0];
		$_page = get_fullname(strip_bracket($_page), $page);

		if (is_pagename($_page)) {
			$page = $_page;
		}
	}

	if (!is_page_readable($page)) {
		$body = str_replace('$1', htmlspecialchars($page, ENT_COMPAT, 'UTF-8'), $_title_cannotread);

		return $body;
	}

	$cache_filepath = CACHE_DIR.encode($page).'.bugtrack';
	$json_cached = pkwk_file_get_contents($cache_filepath);
	$wrapdata = json_decode($json_cached);

	if ((is_object($wrapdata)) && ($wrapdata)) {
		$recent_deleted_filetime = get_filetime($whatsdeleted);
		$recent_dat_filemtime = filemtime(CACHE_DIR.PKWK_MAXSHOW_CACHE);

		if (($recent_deleted_filetime === $wrapdata->recent_deleted_filetime) && ($recent_dat_filemtime === $wrapdata->recent_dat_filemtime) && ($recent_dat_filemtime !== false) && ($recent_deleted_filetime !== 0)) {
			return $wrapdata->html;
		}
	}

	$cache_data = [];
	$data = [];
	$page_list = plugin_bugtrack_get_page_list($page, true);
	usort($page_list, '_plugin_bugtrack_list_paganame_compare');
	$count_list = count($_plugin_bugtrack['state_list']);
	$data_map = [];

	// Cache management
	$data_updated = true;
	$cache_filepath = CACHE_DIR.encode($page).'.bugtrack';
	$json_cached = pkwk_file_get_contents($cache_filepath);

	if ($json_cached) {
		$wrapdata = json_decode($json_cached);

		if (is_object($wrapdata)) {
			if (isset($wrapdata->version, $wrapdata->pages, $wrapdata->refreshed_at)) {
				$cache_time_prev = $wrapdata->refreshed_at;

				if (($cache_format_version == $wrapdata->version) && (time() < ($cache_time_prev + $cache_expire_time))) {
					$data = $wrapdata->pages;
					$cache_refresh_time_prev = $cache_time_prev;
				}
			}
		}

		if ((is_array($data)) && (!empty($data))) {
			$all_ok = true;

			foreach ($page_list as $i=>$page_info) {
				[$page_name, $no, $summary, $name, $priority, $state, $category, $filetime] = $data[$i];

				if (($filetime !== $page_info['filetime']) || ($page_name !== $page_info['name'])) {
					$all_ok = false;

					break;
				}
			}

			if (!$all_ok) {
				// Clear cache
				foreach ($data as $d) {
					$page_name = $d[0];
					$data_map[$page_name] = $d;
				}

				$data = [];
			}
		}
	}

	if ((empty($data)) || (!empty($data_map))) {
		// No cache
		foreach ($page_list as $page_info) {
			$page_name = $page_info['name'];
			$filled = false;

			if (isset($data_map[$page_name])) {
				$d = $data_map[$page_name];
				[$page_name, $no, $summary, $name, $priority, $state, $category, $filetime] = $d;

				if ($filetime == $page_info['filetime']) {
					$data[] = $d;
					$filled = true;
				}
			}

			if (!$filled) {
				$data[] = plugin_bugtrack_list_pageinfo($page_info['name'], null, true, $page_info['filetime']);
			}
		}

		foreach ($data as $i=>$line) {
			[$page_name, $no, $summary, $name, $priority, $state, $category, $filetime, $state_no_cached, $html_cached] = $line;

			if ((isset($state_no_cached)) && (isset($html_cached))) {
				continue;
			}

			$name = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
			$priority = htmlspecialchars($priority, ENT_COMPAT, 'UTF-8');
			$state = htmlspecialchars($state, ENT_COMPAT, 'UTF-8');
			$category = htmlspecialchars($category, ENT_COMPAT, 'UTF-8');

			$page_link = make_pagelink($page_name);

			$state_no = array_search($state, $_plugin_bugtrack['state_sort'], true);

			if (($state_no === null) || ($state_no === false)) {
				$state_no = $count_list;
			}

			$cssclass = htmlspecialchars($_plugin_bugtrack['state_class'][$state_no], ENT_COMPAT, 'UTF-8');

			$row = <<<EOD
	<tr class="{$cssclass}">
		<td>{$page_link}</td>
		<td>{$state}</td>
		<td>{$priority}</td>
		<td>{$category}</td>
		<td>{$name}</td>
		<td>{$summary}</td>
	</tr>
EOD;

			// Reduce space size
			$row = preg_replace('#(?<=>)\n\s+#', '', $row)."\n";

			$rec = &$data[$i];

			// color number
			$rec[] = $state_no;

			// HTML content
			$rec[] = $row;
		}

		// Save cache
		if ($cache_refresh_time_prev !== null) {
			$refreshed_at = $cache_refresh_time_prev;
		} else {
			$refreshed_at = time();
		}

		$cache_data = ['refreshed_at'=>$refreshed_at, 'pages'=>$data, 'version'=>$cache_format_version];
	}

	$table = [];

	for ($i = 0; $i <= ($count_list + 1); $i++) {
		$table[$i] = [];
	}

	foreach ($data as $line) {
		[$page_name, $no, $summary, $name, $priority, $state, $category, $filetime, $state_no, $html] = $line;
		$table[$state_no][$no] = $html;
	}

	$table_html = "\t".'<tr class="bugtrack_list_header">';

	foreach (['pagename', 'state', 'priority', 'category', 'name', 'summary'] as $item) {
		$table_html .= '<th>'.htmlspecialchars($_plugin_bugtrack[$item], ENT_COMPAT, 'UTF-8').'</th>';
	}

	$table_html .= '</tr>'."\n";

	for ($i = 0; $i <= $count_list; $i++) {
		ksort($table[$i], SORT_NUMERIC);
		$table_html .= implode('', $table[$i]);
	}

	$result_html = '<table border="1" width="100%">'."\n".$table_html."\n".'</table>';

	$cache_data['recent_deleted_filetime'] = get_filetime($whatsdeleted);
	$cache_data['recent_dat_filemtime'] = filemtime(CACHE_DIR.PKWK_MAXSHOW_CACHE);
	$cache_data['html'] = $result_html;
	$cache_body = json_encode($cache_data, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);
	file_put_contents($cache_filepath, $cache_body, LOCK_EX);

	return $result_html;
}

// Get one set of data from a page (or a page moved to $page)
function plugin_bugtrack_list_pageinfo(string $page, ?int $no = null, $recurse = true, $filetime = null) : array
{
	global $WikiName;
	global $InterWikiName;
	global $BracketName;
	global $_plugin_bugtrack;

	if ($no === null) {
		$no = (preg_match('/\/([0-9]+)$/', $page, $matches)) ? ($matches[1]) : (0);
	}

	$source = get_source($page);

	// Check 'moved' page _just once_
	$regex = "/move\\s*to\\s*({$WikiName}|{$InterWikiName}|\\[\\[{$BracketName}\\]\\])/";
	$match = [];

	if (($recurse) && (preg_match($regex, $source[0], $match))) {
		return plugin_bugtrack_list_pageinfo(strip_bracket($match[1]), $no, false, $filetime);
	}

	$body = implode("\n", $source);

	$summary = (preg_match('/-\s*'.preg_quote($_plugin_bugtrack['summary'], '/').'\s*:(.*)/', $body, $matches)) ? (trim($matches[1])) : ('');
	$name = (preg_match('/-\s*'.preg_quote($_plugin_bugtrack['name'], '/').'\s*:(.*)/', $body, $matches)) ? (strip_bracket(trim($matches[1]))) : ('');
	$priority = (preg_match('/-\s*'.preg_quote($_plugin_bugtrack['priority'], '/').'\s*:(.*)/', $body, $matches)) ? (trim($matches[1])) : ('');
	$state = (preg_match('/-\s*'.preg_quote($_plugin_bugtrack['state'], '/').'\s*:(.*)/', $body, $matches)) ? (trim($matches[1])) : ('');
	$category = (preg_match('/-\s*'.preg_quote($_plugin_bugtrack['category'], '/').'\s*:(.*)/', $body, $matches)) ? (trim($matches[1])) : ('');

	if (preg_match("/\\*([^\n]*)/", $body, $matches)) {
		$summary = $matches[1];
		make_heading($summary);
		$summary = trim($summary);
	}

	return [$page, $no, $summary, $name, $priority, $state, $category, $filetime];
}

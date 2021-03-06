<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// search2.inc.php
// Copyright 2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Search2 plugin - Show detail result using JavaScript

// #search(1,2,3,...,15,16)
define('PLUGIN_SEARCH2_MAX_BASE', 16);

define('PLUGIN_SEARCH2_RESULT_RECORD_LIMIT', 10000);
define('PLUGIN_SEARCH2_RESULT_RECORD_LIMIT_START', 100);
define('PLUGIN_SEARCH2_SEARCH_WAIT_MILLISECONDS', 1000);
define('PLUGIN_SEARCH2_SEARCH_MAX_RESULTS', 1000);

// Show a search box on a page
function plugin_search2_convert(string ...$args) : string
{
	return plugin_search_search_form('', '', $args);
}

function plugin_search2_action() : array
{
	global $vars;
	global $_title_search;
	global $_title_result;
	global $_msg_searching;

	$action = (isset($vars['action'])) ? ($vars['action']) : ('');
	$base = (isset($vars['base'])) ? ($vars['base']) : ('');
	$start_s = (isset($vars['start'])) ? ($vars['start']) : ('');
	$start_index = (ctype_digit($start_s)) ? ((int) ($start_s)) : (0);
	$bases = [];

	if ($base !== '') {
		$bases[] = $base;
	}

	if ($action === '') {
		$q = trim((isset($vars['q'])) ? ($vars['q']) : (''));
		$offset_s = (isset($vars['offset'])) ? ($vars['offset']) : ('');
		$offset = (ctype_digit($offset_s)) ? ((int) ($offset_s)) : (0);
		$prev_offset_s = (isset($vars['prev_offset'])) ? ($vars['prev_offset']) : ('');

		if ($q === '') {
			return ['msg'=>$_title_search, 'body'=>'<br />'.$_msg_searching."\n".plugin_search2_search_form($q, $bases, $offset)];
		} else {
			$msg = str_replace('$1', htmlspecialchars($q, ENT_COMPAT, 'UTF-8'), $_title_result);

			return ['msg'=>$msg, 'body'=>plugin_search2_search_form($q, $bases, $offset, $prev_offset_s)];
		}
	} elseif ($action === 'query') {
		$q = (isset($vars['q'])) ? ($vars['q']) : ('');
		$search_start_time = (isset($vars['search_start_time'])) ? ((int) ($vars['search_start_time'])) : (null);
		$modified_since = (int) ((isset($vars['modified_since'])) ? ($vars['modified_since']) : ('0'));
		header('Content-Type: application/json; charset=UTF-8');
		plugin_search2_do_search($q, $base, $start_index, $search_start_time, $modified_since);

		exit;
	}

	return [];
}

function plugin_search2_get_base_url(string $search_text) : string
{
	global $vars;

	$params = [];

	$params[] = 'cmd=search2';

	if ($search_text) {
		$params[] = 'q='.plugin_search2_urlencode_searchtext($search_text);
	}

	if ((isset($vars['base'])) && ($vars['base'])) {
		$params[] = 'base='.rawurlencode($vars['base']);
	}

	$url = get_base_uri().'?'.implode('&', $params);

	return $url;
}

function plugin_search2_urlencode_searchtext(string $search_text) : string
{
	$s2 = preg_replace('#^\s+|\s+$#', '', $search_text);

	if (!$s2) {
		return '';
	}

	$sp = preg_split('#\s+#', $s2);
	$list = [];

	for ($i = 0; $i < count($sp); $i++) {
		$list[] = rawurlencode($sp[$i]);
	}

	return implode('+', $list);
}

function plugin_search2_do_search(string $query_text, string $base, int $start_index, ?int $search_start_time, int $modified_since) : void
{
	global $whatsnew;
	global $non_list;
	global $search_non_list;
	global $_msg_andresult;
	global $_msg_orresult;
	global $search_auth;
	global $auth_user;

	$result_record_limit = ($start_index === 0) ? (PLUGIN_SEARCH2_RESULT_RECORD_LIMIT_START) : (PLUGIN_SEARCH2_RESULT_RECORD_LIMIT);
	$retval = [];

	// AND:true OR:false
	$b_type_and = true;

	$key_candidates = preg_split('/\s+/', $query_text, -1, PREG_SPLIT_NO_EMPTY);

	for ($i = count($key_candidates) - 2; $i >= 1; $i--) {
		if ($key_candidates[$i] === 'OR') {
			$b_type_and = false;
			unset($key_candidates[$i]);
		}
	}

	$key_candidates = array_merge($key_candidates);
	$keys = get_search_words($key_candidates);

	foreach ($keys as $key=>$value) {
		$keys[$key] = '/'.$value.'/S';
	}

	if ($modified_since > 0) {
		// Recent search
		$recent_files = get_recent_files();
		$modified_loc = $modified_since - LOCALZONE;
		$pages = [];

		foreach ($recent_files as $p=>$time) {
			if ($time >= $modified_loc) {
				$pages[] = $p;
			}
		}

		if ($base != '') {
			$pages = preg_grep('/^'.preg_quote($base, '/').'/S', $pages);
		}

		$page_names = $pages;
	} else {
		// Normal search
		$pages = get_existpages();

		// Avoid
		if ($base != '') {
			$pages = preg_grep('/^'.preg_quote($base, '/').'/S', $pages);
		}

		if (!$search_non_list) {
			$pages = array_diff($pages, preg_grep('/'.$non_list.'/S', $pages));
		}

		$pages = array_flip($pages);
		unset($pages[$whatsnew]);
		$page_names = array_keys($pages);
	}

	natsort($page_names);

	// Cache collabolate
	if ($search_start_time === null) {
		// Don't use client cache
		$search_start_time = UTIME + LOCALZONE;
	}

	$found_pages = [];
	$readable_page_index = -1;
	$scan_page_index = -1;
	$saved_scan_start_index = -1;
	$last_read_page_name = null;

	foreach ($page_names as $page) {
		$b_match = false;
		$pagename_only = false;
		$scan_page_index++;

		if (!is_page_readable($page)) {
			if ($search_auth) {
				// $search_auth - 1: User can know page names that contain search text if the page is readable
				continue;
			}

			// $search_auth - 0: All users can know page names that conntain search text
			$pagename_only = true;
		}

		$readable_page_index++;

		if ($readable_page_index < $start_index) {
			// Skip: It's not time to read
			continue;
		}

		if ($saved_scan_start_index === -1) {
			$saved_scan_start_index = $scan_page_index;
		}

		if (count($keys) > 0) {
			// Search for page name and contents
			$body = get_source($page, true, true, true);
			$target = $page."\n".remove_author_header($body);

			foreach ($keys as $key) {
				$b_match = preg_match($key, $target);

				if ($b_type_and xor $b_match) {
					break;
				} // OR
			}
		} else {
			// No search target. get_source($page) is meaningless.
			// $b_match is always false.
		}

		if ($b_match) {
			// Found!
			$author_info = get_author_info($body);

			if ($author_info) {
				$updated_at = get_update_datetime_from_author($author_info);
				$updated_time = strtotime($updated_at);
			} else {
				$updated_time = filemtime(get_filename($page));
				$updated_at = get_date_atom($updated_time);
			}

			if ($pagename_only) {
				// The user cannot read this page body
				$found_pages[] =
				[
					'name'=>(string) ($page),
					'url'=>get_page_uri($page),
					'updated_at'=>$updated_at,
					'updated_time'=>$updated_time,
					'body'=>'',
					'pagename_only'=>1,
				];
			} else {
				$found_pages[] =
				[
					'name'=>(string) ($page),
					'url'=>get_page_uri($page),
					'updated_at'=>$updated_at,
					'updated_time'=>$updated_time,
					'body'=>(string) ($body),
				];
			}
		}

		$last_read_page_name = $page;

		if (($start_index + $result_record_limit) <= ($readable_page_index + 1)) {
			// Read page limit
			break;
		}
	}

	$message = str_replace('$1', htmlspecialchars($query_text, ENT_COMPAT, 'UTF-8'), str_replace('$2', count($found_pages), str_replace('$3', count($page_names), (($b_type_and) ? ($_msg_andresult) : ($_msg_orresult)))));
	$search_done = (bool) (($scan_page_index + 1) === count($page_names));

	$result_obj =
	[
		'message'=>$message,
		'q'=>$query_text,
		'start_index'=>$start_index,
		'limit'=>$result_record_limit,
		'read_page_count'=>$readable_page_index - $start_index + 1,
		'scan_page_count'=>$scan_page_index - $saved_scan_start_index + 1,
		'page_count'=>count($page_names),
		'last_read_page_name'=>$last_read_page_name,
		'next_start_index'=>$readable_page_index + 1,
		'search_done'=>$search_done,
		'search_start_time'=>$search_start_time,
		'auth_user'=>$auth_user,
		'results'=>$found_pages,
	];

	$obj = $result_obj;

	echo json_encode($obj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function plugin_search2_search_form(string $search_text, array $bases, int $offset, string $prev_offset_s = null) : string
{
	global $_btn_search;
	global $_search_pages;
	global $_search_all;
	global $_msg_andresult;
	global $_msg_orresult;
	global $_msg_notfoundresult;
	global $_search_detail;
	global $_search_searching;
	global $_search_showing_result;
	global $_msg_unsupported_webbrowser;
	global $_msg_use_alternative_link;
	global $_msg_more_results;
	global $_msg_prev_results;
	global $_msg_general_error;
	global $auth_user;
	static $search2_form_total_count = 0;

	$search2_form_total_count++;
	$script = get_base_uri();
	$h_search_text = htmlspecialchars($search_text, ENT_COMPAT, 'UTF-8');

	$base_option = '';

	if (!empty($bases)) {
		$base_msg = '';
		$_num = 0;
		$check = ' checked';

		foreach ($bases as $base) {
			$_num++;

			if (PLUGIN_SEARCH2_MAX_BASE < $_num) {
				break;
			}

			$s_base = htmlspecialchars($base, ENT_COMPAT, 'UTF-8');
			$base_str = '<strong>'.$s_base.'</strong>';
			$base_label = str_replace('$1', $base_str, $_search_pages);
			$base_msg .= <<<EOD
	<div>
		<label>
			<input type="radio" name="base" value="{$s_base}" {$check}> {$base_label}
		</label>
	</div>
EOD;
			$check = '';
		}

		$base_msg .= <<<EOD
<label><input type="radio" name="base" value=""> {$_search_all}</label>
EOD;
		$base_option = "\n\t".'<div class="small">'."\n".$base_msg."\n\t".'</div>';
	}

	$_search2_result_notfound = htmlspecialchars($_msg_notfoundresult, ENT_COMPAT, 'UTF-8');
	$_search2_result_found = htmlspecialchars($_msg_andresult, ENT_COMPAT, 'UTF-8');
	$_search2_search_wait_milliseconds = PLUGIN_SEARCH2_SEARCH_WAIT_MILLISECONDS;
	$result_page_panel = <<<EOD
<input type="checkbox" id="_plugin_search2_detail" checked><label for="_plugin_search2_detail">{$_search_detail}</label>
<ul id="_plugin_search2_result-list">
</ul>
EOD;

	if (($h_search_text == '') || ($search2_form_total_count > 1)) {
		$result_page_panel = '';
	}

	$plain_search_link = '<a href="'.$script.'?cmd=search">'.htmlspecialchars($_btn_search, ENT_COMPAT, 'UTF-8').'</a>';
	$alt_msg = str_replace('$1', $plain_search_link, $_msg_use_alternative_link);
	$status_span_text = '<span class="_plugin_search2_search_status_text1"></span><span class="_plugin_search2_search_status_text2"></span>';
	$form = <<<EOD
<form action="{$script}" method="GET" class="_plugin_search2_form">
	<div>
		<input type="hidden" name="cmd" value="search2">
		<input type="search" name="q" value="{$h_search_text}" data-original-q="{$h_search_text}" size="40">
		<input type="submit" value="{$_btn_search}">
	</div>{$base_option}
</form>
EOD;
	$second_form = <<<EOD
<div class="_plugin_search2_second_form" style="display:none;">
	<div class="_plugin_search2_search_status">{$status_span_text}</span></div>
	<div class="_plugin_search2_message"></div>
{$form}
</div>
EOD;

	$h_auth_user = htmlspecialchars($auth_user, ENT_COMPAT, 'UTF-8');
	$h_base_url = htmlspecialchars(plugin_search2_get_base_url($search_text), ENT_COMPAT, 'UTF-8');
	$h_msg_more_results = htmlspecialchars($_msg_more_results, ENT_COMPAT, 'UTF-8');
	$h_msg_prev_results = htmlspecialchars($_msg_prev_results, ENT_COMPAT, 'UTF-8');
	$max_results = PLUGIN_SEARCH2_SEARCH_MAX_RESULTS;
	$prev_offset = (ctype_digit($prev_offset_s)) ? ($prev_offset_s) : ('');
	$search_props = <<<EOD
<div style="display:none;">
	<input type="hidden" id="_plugin_search2_auth_user" value="{$h_auth_user}">
	<input type="hidden" id="_plugin_search2_base_url" value="{$h_base_url}">
	<input type="hidden" id="_plugin_search2_msg_searching" value="{$_search_searching}">
	<input type="hidden" id="_plugin_search2_msg_showing_result" value="{$_search_showing_result}">
	<input type="hidden" id="_plugin_search2_msg_result_notfound" value="{$_search2_result_notfound}">
	<input type="hidden" id="_plugin_search2_msg_result_found" value="{$_search2_result_found}">
	<input type="hidden" id="_plugin_search2_msg_more_results" value="{$h_msg_more_results}">
	<input type="hidden" id="_plugin_search2_msg_prev_results" value="{$h_msg_prev_results}">
	<input type="hidden" id="_plugin_search2_search_wait_milliseconds" value="{$_search2_search_wait_milliseconds}">
	<input type="hidden" id="_plugin_search2_max_results" value="{$max_results}">
	<input type="hidden" id="_plugin_search2_offset" value="{$offset}">
	<input type="hidden" id="_plugin_search2_prev_offset" value="{$prev_offset}">
	<input type="hidden" id="_plugin_search2_msg_error" value="{$_msg_general_error}">
</div>
EOD;

	if ($search2_form_total_count > 1) {
		$search_props = '';
	}

	return <<<EOD
<noscript><p>{$_msg_unsupported_webbrowser} {$alt_msg}</p></noscript>
<style>
input#_plugin_search2_detail:checked ~ ul > li > div.search-result-detail {
  display:block;
}
input#_plugin_search2_detail ~ ul > li > div.search-result-detail {
  display:none;
}
._plugin_search2_search_status {
  min-height:1.5em;
}
@keyframes plugin-search2-searching {
  10% { opacity: 1; }
  40% { opacity: 0; }
  70% { opacity: 0; }
  90% { opacity: 1; }
}
span.plugin-search2-progress {
  animation: plugin-search2-searching 1.5s infinite ease-out;
}
span.plugin-search2-progress1 {
  animation-delay: -1s;
}
span.plugin-search2-progress2 {
  animation-delay: -0.8s;
}
span.plugin-search2-progress3 {
  animation-delay: -0.6s;
}
</style>
<p class="_plugin_search2_nosupport_message" style="display:none;">{$_msg_unsupported_webbrowser} {$alt_msg}</p>
{$search_props}
{$form}
<div class="_plugin_search2_search_status">{$status_span_text}</div>
<div class="_plugin_search2_message"></div>
{$result_page_panel}
{$second_form}
EOD;
}

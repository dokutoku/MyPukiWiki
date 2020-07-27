<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// calendar_viewer.inc.php
// Copyright  2002-2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Calendar viewer plugin - List pages that calendar/calnedar2 plugin created
// (Based on calendar and recent plugin)

// Page title's date format
//  * See PHP date() manual for detail
//  * '$\w' = weeklabel defined in $_msg_week
define('PLUGIN_CALENDAR_VIEWER_DATE_FORMAT',
	//	false         // 'pagename/2004-02-09' -- As is
	//	'D, d M, Y'   // 'Mon, 09 Feb, 2004'
	//	'F d, Y'      // 'February 09, 2004'
	//	'[Y-m-d]'     // '[2004-02-09]'
		'Y/n/j ($\w)' // '2004/2/9 (Mon)'
	);
/**
 * Limit of show count per pagename.
 */
define('PLUGIN_CALENDAR_VIEWER_MAX_SHOW_COUNT', 4);

// ----

define('PLUGIN_CALENDAR_VIEWER_USAGE', '#calendar_viewer(pagename,this|yyyy-mm|n|x*y[,mode[,separater]])');
/*
 ** pagename
 * - A working root of calendar or calendar2 plugin
 *   pagename/2004-12-30
 *   pagename/2004-12-31
 *   ...
 *
 ** (yyyy-mm|n|this)
 * this    - Show 'this month'
 * yyyy-mm - Show pages at year:yyyy and month:mm
 * n       - Show first n pages
 * x*n     - Show first n pages from x-th page (0 = first)
 *
 ** [mode]
 * past   - Show today, and the past below. Recommended for ChangeLogs and diaries (default)
 * future - Show today, and the future below. Recommended for event planning and scheduling
 * view   - Show all, from the past to the future
 *
 ** [separater]
 * - Specify separator of yyyy/mm/dd
 * - Default: '-' (yyyy-mm-dd)
 *
 * TODO
 *  Stop showing links 'next month' and 'previous month' with past/future mode for 'this month'
 *    #calendar_viewer(pagename,this,past)
 */

function plugin_calendar_viewer_convert(string ...$func_args) : string
{
	global $vars;
	global $get;
	global $post;
	global $script;
	global $weeklabels;
	global $_msg_calendar_viewer_right;
	global $_msg_calendar_viewer_left;
	global $_msg_calendar_viewer_restrict;
	global $_err_calendar_viewer_param2;

	static $show_count = [];

	if (func_num_args() < 2) {
		return PLUGIN_CALENDAR_VIEWER_USAGE.'<br />'."\n";
	}

	// Default values

	// 基準となるページ名
	$pagename = $func_args[0];

	// 一覧表示する年月
	$page_YM = '';

	// 先頭から数えて何ページ目から表示するか (先頭)
	$limit_base = 0;

	// 何件づつ表示するか
	$limit_pitch = 0;

	// サーチするページ数
	$limit_page = 0;

	// 動作モード
	$mode = 'past';

	// 日付のセパレータ calendar2なら '-', calendarなら ''
	$date_sep = '-';

	// Check $func_args[1]
	$matches = [];

	if (preg_match('/[0-9]{4}'.$date_sep.'[0-9]{2}/', $func_args[1])) {
		// 指定年月の一覧表示
		$page_YM = $func_args[1];
		$limit_page = 31;
	} elseif (preg_match('/this/si', $func_args[1])) {
		// 今月の一覧表示
		$page_YM = get_date('Y'.$date_sep.'m');
		$limit_page = 31;
	} elseif (preg_match('/^[0-9]+$/', $func_args[1])) {
		// n日分表示
		$limit_pitch = $func_args[1];
		$limit_page = $func_args[1];
	} elseif (preg_match('/(-?[0-9]+)\*([0-9]+)/', $func_args[1], $matches)) {
		// 先頭より数えて x ページ目から、y件づつ表示
		$limit_base = $matches[1];
		$limit_pitch = $matches[2];

		// 読み飛ばす + 表示する
		$limit_page = $matches[1] + $matches[2];
	} else {
		return '#calendar_viewer(): '.$_err_calendar_viewer_param2.'<br />'."\n";
	}

	// $func_args[2]: Mode setting
	if ((isset($func_args[2])) && (preg_match('/^(past|view|future)$/si', $func_args[2]))) {
		$mode = $func_args[2];
	}

	// $func_args[3]: Change default delimiter
	if (isset($func_args[3])) {
		$date_sep = $func_args[3];
	}

	// Avoid Loop etc.
	if (!isset($show_count[$pagename])) {
		$show_count[$pagename] = 0;
	}

	$show_count[$pagename]++;

	if ($show_count[$pagename] > PLUGIN_CALENDAR_VIEWER_MAX_SHOW_COUNT) {
		$s_page = htmlspecialchars($pagename, ENT_COMPAT, 'UTF-8');

		return '#calendar_viewer(): Exceeded the limit of show count: '.$s_page.'<br />';
	}

	// page name pattern
	$simple_pagename = strip_bracket($pagename);

	if ($pagename === '') {
		// Support non-pagename yyyy-mm-dd pattern
		$pagepattern = $page_YM;
		$page_datestart_idx = 0;
	} else {
		$pagepattern = $simple_pagename.'/'.$page_YM;
		$page_datestart_idx = strlen($simple_pagename) + 1;
	}

	$pagepattern_len = strlen($pagepattern);
	// Get pagelist
	$pagelist = [];
	$_date = get_date('Y'.$date_sep.'m'.$date_sep.'d');

	foreach (get_existpages() as $page) {
		if (strncmp($page, $pagepattern, $pagepattern_len) !== 0) {
			continue;
		}

		$page_date = substr($page, $page_datestart_idx);

		// Verify the $page_date pattern (Default: yyyy-mm-dd).
		// Past-mode hates the future, and
		// Future-mode hates the past.
		if ((plugin_calendar_viewer_isValidDate($page_date, $date_sep) === false) || (($page_date > $_date) && ($mode === 'past')) || (($page_date < $_date) && ($mode === 'future'))) {
			continue;
		}

		$pagelist[] = $page;
	}

	if ($mode == 'past') {
		// New=>Old
		rsort($pagelist, SORT_STRING);
	} else {
		// Old=>New
		sort($pagelist, SORT_STRING);
	}

	// Include start
	$tmppage = $vars['page'];
	$return_body = '';

	// $limit_page の件数までインクルード

	// Skip minus
	$tmp = max($limit_base, 0);

	while ($tmp < $limit_page) {
		if (!isset($pagelist[$tmp])) {
			break;
		}

		$page = $pagelist[$tmp];
		$vars['page'] = $page;
		$post['page'] = $page;
		$get['page'] = $page;

		// 現状で閲覧許可がある場合だけ表示する
		if (check_readable($page, false, false)) {
			$body = convert_html(get_source($page));
		} else {
			$body = str_replace('$1', $page, $_msg_calendar_viewer_restrict);
		}

		$r_page = pagename_urlencode($page);

		if (PLUGIN_CALENDAR_VIEWER_DATE_FORMAT !== false) {
			// $date_sep must be assumed '-' or ''!
			$time = strtotime(basename($page));

			if (($time === false) || ($time === -1)) {
				// Failed. Why?
				$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
			} else {
				$week = $weeklabels[date('w', $time)];
				$s_page = htmlspecialchars(str_replace(['$w'], [$week], date(PLUGIN_CALENDAR_VIEWER_DATE_FORMAT, $time)), ENT_COMPAT, 'UTF-8');
			}
		} else {
			$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
		}

		if (PKWK_READONLY) {
			$link = $script.'?'.$r_page;
		} else {
			$link = $script.'?cmd=edit&amp;page='.$r_page;
		}

		$link = '<a href="'.$link.'">'.$s_page.'</a>';

		$head = '<h1>'.$link.'</h1>'."\n";
		$return_body .= $head.$body;

		$tmp++;
	}

	// ここで、前後のリンクを表示
	// ?plugin=calendar_viewer&file=ページ名&date=yyyy-mm
	if ($page_YM != '') {
		// 年月表示時
		$date_sep_len = strlen($date_sep);
		$this_year = substr($page_YM, 0, 4);
		$this_month = substr($page_YM, 4 + $date_sep_len, 2);

		// 次月
		$next_year = $this_year;
		$next_month = $this_month + 1;

		if ($next_month > 12) {
			$next_year++;
			$next_month = 1;
		}

		$next_YM = sprintf('%04d%s%02d', $next_year, $date_sep, $next_month);

		// 前月
		$prev_year = $this_year;
		$prev_month = $this_month - 1;

		if ($prev_month < 1) {
			$prev_year--;
			$prev_month = 12;
		}

		$prev_YM = sprintf('%04d%s%02d', $prev_year, $date_sep, $prev_month);

		if ($mode == 'past') {
			$right_YM = $prev_YM;

			// >>
			$right_text = $prev_YM.'&gt;&gt;';

			$left_YM = $next_YM;

			// <<
			$left_text = '&lt;&lt;'.$next_YM;
		} else {
			$left_YM = $prev_YM;

			// <<
			$left_text = '&lt;&lt;'.$prev_YM;

			$right_YM = $next_YM;

			// >>
			$right_text = $next_YM.'&gt;&gt;';
		}
	} else {
		// n件表示時
		if ($limit_base <= 0) {
			// 表示しない (それより前の項目はない)
			$left_YM = '';
		} else {
			$left_YM = $limit_base - $limit_pitch.'*'.$limit_pitch;
			$left_text = sprintf($_msg_calendar_viewer_left, $limit_pitch);
		}

		if ($limit_base + $limit_pitch >= count($pagelist)) {
			// 表示しない (それより後の項目はない)
			$right_YM = '';
		} else {
			$right_YM = $limit_base + $limit_pitch.'*'.$limit_pitch;
			$right_text = sprintf($_msg_calendar_viewer_right, $limit_pitch);
		}
	}

	// ナビゲート用のリンクを末尾に追加
	if (($left_YM != '') || ($right_YM != '')) {
		$s_date_sep = htmlspecialchars($date_sep, ENT_COMPAT, 'UTF-8');
		$right_link = '';
		$left_link = '';
		$link = $script.'?plugin=calendar_viewer&amp;mode='.$mode.'&amp;file='.rawurlencode($simple_pagename).'&amp;date_sep='.$s_date_sep.'&amp;';

		if ($left_YM != '') {
			$left_link = '<a href="'.$link.'date='.$left_YM.'">'.$left_text.'</a>';
		}

		if ($right_YM != '') {
			$right_link = '<a href="'.$link.'date='.$right_YM.'">'.$right_text.'</a>';
		}

		// past modeは<<新 旧>> 他は<<旧 新>>
		$return_body .= '<div class="calendar_viewer"><span class="calendar_viewer_left">'.$left_link.'</span><span class="calendar_viewer_right">'.$right_link.'</span></div>';
	}

	$vars['page'] = $tmppage;
	$post['page'] = $tmppage;
	$get['page'] = $tmppage;

	return $return_body;
}

function plugin_calendar_viewer_action() : array
{
	global $vars;
	global $get;
	global $post;
	global $script;

	$date_sep = '-';

	$return_vars_array = [];

	$page = strip_bracket($vars['page']);
	$vars['page'] = '*';

	if (isset($vars['file'])) {
		$vars['page'] = $vars['file'];
	}

	$date_sep = $vars['date_sep'];

	$page_YM = $vars['date'];

	if ($page_YM == '') {
		$page_YM = get_date('Y'.$date_sep.'m');
	}

	$mode = $vars['mode'];

	$args_array = [$vars['page'], $page_YM, $mode, $date_sep];
	$return_vars_array['body'] = call_user_func_array('plugin_calendar_viewer_convert', $args_array);

	//$return_vars_array['msg'] = 'calendar_viewer ' . $vars['page'] . '/' . $page_YM;
	$return_vars_array['msg'] = 'calendar_viewer '.htmlspecialchars($vars['page'], ENT_COMPAT, 'UTF-8');

	if ($vars['page'] != '') {
		$return_vars_array['msg'] .= '/';
	}

	if (preg_match('/\*/', $page_YM)) {
		// うーん、n件表示の時はなんてページ名にしたらいい？
	} else {
		$return_vars_array['msg'] .= htmlspecialchars($page_YM, ENT_COMPAT, 'UTF-8');
	}

	$vars['page'] = $page;

	return $return_vars_array;
}

function plugin_calendar_viewer_isValidDate(string $aStr, string $aSepList = '-/ .') : bool
{
	$matches = [];

	if ($aSepList == '') {
		// yyymmddとしてチェック（手抜き(^^;）
		return checkdate(substr($aStr, 4, 2), substr($aStr, 6, 2), substr($aStr, 0, 4));
	} elseif (preg_match("#^([0-9]{2,4})[{$aSepList}]([0-9]{1,2})[{$aSepList}]([0-9]{1,2})$#", $aStr, $matches)) {
		return checkdate($matches[2], $matches[3], $matches[1]);
	} else {
		return false;
	}
}

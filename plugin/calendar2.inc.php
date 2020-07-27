<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// calendar2.inc.php
// Copyright 2002-2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Calendar2 plugin
//
// Usage:
//	#calendar2({[pagename|*],[yyyymm],[off]})
//	off: Don't view today's

function plugin_calendar2_convert(string ...$args) : string
{
	global $vars;
	global $post;
	global $get;
	global $weeklabels;
	global $WikiName;
	global $BracketName;
	global $_calendar2_plugin_edit;
	global $_calendar2_plugin_empty;

	$script = get_base_uri();
	$date_str = get_date('Ym');
	$base = strip_bracket($vars['page']);

	$today_view = true;

	if (func_num_args()) {
		foreach ($args as $arg) {
			if ((is_numeric($arg)) && (strlen($arg) == 6)) {
				$date_str = $arg;
			} elseif ($arg == 'off') {
				$today_view = false;
			} else {
				$base = strip_bracket($arg);
			}
		}
	}

	if ($base == '*') {
		$base = '';
		$prefix = '';
	} else {
		$prefix = $base.'/';
	}

	$r_base = rawurlencode($base);
	$s_base = htmlspecialchars($base, ENT_COMPAT, 'UTF-8');
	$r_prefix = rawurlencode($prefix);
	$s_prefix = htmlspecialchars($prefix, ENT_COMPAT, 'UTF-8');

	$yr = substr($date_str, 0, 4);
	$mon = substr($date_str, 4, 2);

	if (($yr != get_date('Y')) || ($mon != get_date('m'))) {
		$now_day = 1;
		$other_month = 1;
	} else {
		$now_day = get_date('d');
		$other_month = 0;
	}

	$today = getdate(mktime(0, 0, 0, $mon, $now_day, $yr) - LOCALZONE + ZONETIME);

	$m_num = $today['mon'];
	$d_num = $today['mday'];
	$year = $today['year'];

	$f_today = getdate(mktime(0, 0, 0, $m_num, 1, $year) - LOCALZONE + ZONETIME);
	$wday = $f_today['wday'];
	$day = 1;

	$m_name = $year.'.'.$m_num;

	$y = substr($date_str, 0, 4) + 0;
	$m = substr($date_str, 4, 2) + 0;

	$prev_date_str = ($m == 1) ? (sprintf('%04d%02d', $y - 1, 12)) : (sprintf('%04d%02d', $y, $m - 1));

	$next_date_str = ($m == 12) ? (sprintf('%04d%02d', $y + 1, 1)) : (sprintf('%04d%02d', $y, $m + 1));

	$ret = '';

	if ($today_view) {
		$ret = '<table border="0" summary="calendar frame">'."\n\t".'<tr>'."\n\t\t".'<td valign="top">'."\n";
	}

	$ret .= <<<EOD
			<table class="style_calendar" cellspacing="1" width="200" border="0" summary="calendar body">
				<tr>
					<td class="style_td_caltop" colspan="7">
						<a href="{$script}?plugin=calendar2&amp;file={$r_base}&amp;date={$prev_date_str}">&lt;&lt;</a>
						<strong>{$m_name}</strong>
						<a href="{$script}?plugin=calendar2&amp;file={$r_base}&amp;date={$next_date_str}">&gt;&gt;</a>
EOD;

	if ($prefix) {
		$ret .= "\n\t\t\t\t\t\t".'<br />[<a href="'.get_page_uri($base).'">'.$s_base.'</a>]';
	}

	$ret .= "\n\t\t\t\t\t".'</td>'."\n\t\t\t\t".'</tr>'."\n\t\t\t\t".'<tr>'."\n";

	foreach ($weeklabels as $label) {
		$ret .= "\t\t\t\t\t".'<td class="style_td_week">'.$label.'</td>'."\n";
	}

	$ret .= "\t\t\t\t".'</tr>'."\n\t\t\t\t".'<tr>'."\n";
	// Blank
	for ($i = 0; $i < $wday; $i++) {
		$ret .= "\t\t\t\t\t".'<td class="style_td_blank">&nbsp;</td>'."\n";
	}

	while (checkdate($m_num, $day, $year)) {
		$dt = sprintf('%4d-%02d-%02d', $year, $m_num, $day);
		$page = $prefix.$dt;
		$r_page = pagename_urlencode($page);
		$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');

		if (($wday == 0) && ($day > 1)) {
			$ret .= "\t\t\t\t".'</tr>'."\n\t\t\t\t".'<tr>'."\n";
		}

		// Weekday
		$style = 'style_td_day';

		if ((!$other_month) && ($day == $today['mday']) && ($m_num == $today['mon']) && ($year == $today['year'])) {
			// Today
			$style = 'style_td_today';
		} elseif ($wday == 0) {
			// Sunday
			$style = 'style_td_sun';
		} elseif ($wday == 6) {
			//  Saturday
			$style = 'style_td_sat';
		}

		if (is_page($page)) {
			$link = '<a href="'.get_page_uri($page).'" title="'.$s_page.'"><strong>'.$day.'</strong></a>';
		} else {
			if (PKWK_READONLY) {
				$link = '<span class="small">'.$day.'</small>';
			} else {
				$link = $script.'?cmd=edit&amp;page='.$r_page.'&amp;refer='.$r_base;
				$link = '<a class="small" href="'.$link.'" title="'.$s_page.'">'.$day.'</a>';
			}
		}

		$ret .= "\t\t\t\t\t".'<td class="'.$style.'">'."\n\t\t\t\t\t\t".$link."\n\t\t\t\t\t".'</td>'."\n";
		$day++;
		$wday = ++$wday % 7;
	}

	if ($wday > 0) {

		// Blank
		while ($wday++ < 7) {
			$ret .= "\t\t\t\t\t".'<td class="style_td_blank">&nbsp;</td>'."\n";
		}
	}

	$ret .= "\t\t\t\t".'</tr>'."\n\t\t\t".'</table>'."\n";

	if ($today_view) {
		$tpage = $prefix.sprintf('%4d-%02d-%02d', $today['year'], $today['mon'], $today['mday']);
		$r_tpage = rawurlencode($tpage);

		if (is_page($tpage)) {
			$_page = $vars['page'];
			$vars['page'] = $tpage;
			$post['page'] = $tpage;
			$get['page'] = $tpage;
			$str = convert_html(get_source($tpage));
			$str .= "\n".'<hr />'."\n".'<a class="small" href="'.$script.'?cmd=edit&amp;page='.$r_tpage.'">'.$_calendar2_plugin_edit.'</a>';
			$vars['page'] = $_page;
			$post['page'] = $_page;
			$get['page'] = $_page;
		} else {
			$str = sprintf($_calendar2_plugin_empty, make_pagelink(sprintf('%s%4d-%02d-%02d', $prefix, $today['year'], $today['mon'], $today['mday'])));
		}

		$ret .= "\t\t".'</td>'."\n\t\t".'<td valign="top">'.$str.'</td>'."\n\t".'</tr>'."\n".'</table>'."\n";
	}

	return $ret;
}

function plugin_calendar2_action() : array
{
	global $vars;

	$page = strip_bracket($vars['page']);
	$vars['page'] = '*';

	if ($vars['file']) {
		$vars['page'] = $vars['file'];
	}

	$date = $vars['date'];

	if ($date == '') {
		$date = get_date('Ym');
	}

	$yy = sprintf('%04d.%02d', substr($date, 0, 4), substr($date, 4, 2));

	$aryargs = [$vars['page'], $date];
	$s_page = htmlspecialchars($vars['page'], ENT_COMPAT, 'UTF-8');

	$ret['msg'] = 'calendar '.$s_page.'/'.$yy;
	$ret['body'] = call_user_func_array('plugin_calendar2_convert', $aryargs);

	$vars['page'] = $page;

	return $ret;
}

<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// calendar.inc.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Calendar plugin

function plugin_calendar_convert(string ...$args)
{
	global $weeklabels;
	global $vars;
	global $command;

	$script = get_base_uri();
	$date_str = get_date('Ym');
	$page = '';

	if (func_num_args() == 1) {
		if ((is_numeric($args[0])) && (strlen($args[0]) == 6)) {
			$date_str = $args[0];
		} else {
			$page = $args[0];
		}
	} elseif (func_num_args() == 2) {
		if ((is_numeric($args[0])) && (strlen($args[0]) == 6)) {
			$date_str = $args[0];
			$page = $args[1];
		} elseif ((is_numeric($args[1])) && (strlen($args[1]) == 6)) {
			$date_str = $args[1];
			$page = $args[0];
		}
	}

	if ($page == '') {
		$page = $vars['page'];
	} elseif (!is_pagename($page)) {
		return false;
	}

	$pre = $page;
	$prefix = $page.'/';

	if (!$command) {
		$cmd = 'read';
	} else {
		$cmd = $command;
	}

	$prefix = strip_tags($prefix);

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

	$m_name = $year.'.'.$m_num.' ('.$cmd.')';

	$pre = strip_bracket($pre);
	$page_uri = get_page_uri($pre);

	$ret = <<<EOD
<table class="style_calendar" cellspacing="1" width="200" border="0">
	<tr>
		<td class="style_td_caltop" colspan="7"><strong>{$m_name}</strong><br /> [<a href="{$page_uri}">{$pre}</a>]</td>
	</tr>
	<tr>
EOD;

	foreach ($weeklabels as $label) {
		$ret .= "\t\t".'<td class="style_td_week"><strong>'.$label.'</strong></td>'."\n";
	}

	$ret .= "\t".'</tr>'."\n\t".'<tr>'."\n";

	// Blank
	for ($i = 0; $i < $wday; $i++) {
		$ret .= "\t\t\t\t".'<td class="style_td_blank">&nbsp;</td>'."\n";
	}

	while (checkdate($m_num, $day, $year)) {
		$dt = sprintf('%04d%02d%02d', $year, $m_num, $day);
		$name = $prefix.$dt;
		$r_page = rawurlencode($name);
		$s_page = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');

		$refer = ($cmd == 'edit') ? ('&amp;refer='.rawurlencode($page)) : ('');

		if (($cmd == 'read') && (!is_page($name))) {
			$link = '<strong>'.$day.'</strong>';
		} else {
			$link = '<a href="'.$script.'?cmd='.$cmd.'&amp;page='.$r_page.$refer.'" title="'.$s_page.'"><strong>'.$day.'</strong></a>';
		}

		if (($wday == 0) && ($day > 1)) {
			$ret .= "\t".'</tr>'."\n\t".'<tr>'."\n";
		}

		if ((!$other_month) && ($day == $today['mday']) && ($m_num == $today['mon']) && ($year == $today['year'])) {
			//  Today
			$ret .= "\t\t".'<td class="style_td_today"><span class="small">'.$link.'</span></td>'."\n";
		} elseif ($wday == 0) {
			//  Sunday
			$ret .= "\t\t".'<td class="style_td_sun"><span class="small">'.$link.'</span></td>'."\n";
		} elseif ($wday == 6) {
			//  Saturday
			$ret .= "\t\t".'<td class="style_td_sat"><span class="small">'.$link.'</span></td>'."\n";
		} else {
			// Weekday
			$ret .= "\t\t".'<td class="style_td_day"><span class="small">'.$link.'</span></td>'."\n";
		}

		$day++;
		$wday++;
		$wday = $wday % 7;
	}

	if ($wday > 0) {
		while ($wday < 7) {
			// Blank
			$ret .= "\t\t".'<td class="style_td_blank">&nbsp;</td>'."\n";
			$wday++;
		}
	}

	$ret .= "\t".'</tr>'."\n".'</table>'."\n";

	return $ret;
}

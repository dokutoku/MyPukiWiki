<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: calendar_read.inc.php,v 1.9 2005/05/04 05:13:54 henoheno Exp $
// Copyright (C)
//   2003,2005 PukiWiki Developers Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Calendar_read plugin (needs calendar plugin)

function plugin_calendar_read_convert(string ...$args)
{
	global $command;

	if (!file_exists(PLUGIN_DIR.'calendar.inc.php')) {
		return false;
	}

	require_once PLUGIN_DIR.'calendar.inc.php';

	if (!function_exists('plugin_calendar_convert')) {
		return false;
	}

	$command = 'read';

	return call_user_func_array('plugin_calendar_convert', $args);
}

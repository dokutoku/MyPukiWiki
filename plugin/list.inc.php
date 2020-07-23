<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// list.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// IndexPages plugin: Show a list of page names

function plugin_list_action()
{
	global $vars;
	global $_title_list;
	global $_title_filelist;
	global $whatsnew;

	// Redirected from filelist plugin?
	$filelist = (isset($vars['cmd'])) && ($vars['cmd'] === 'filelist');

	return ['msg'=>(($filelist) ? ($_title_filelist) : ($_title_list)), 'body'=>plugin_list_getlist($filelist)];
}

// Get a list
function plugin_list_getlist($withfilename = false)
{
	global $non_list;
	global $whatsnew;

	$pages = array_diff(get_existpages(), [$whatsnew]);

	if (!$withfilename) {
		$pages = array_diff($pages, preg_grep('/'.$non_list.'/S', $pages));
	}

	if (empty($pages)) {
		return '';
	}

	return page_list($pages, 'read', $withfilename);
}

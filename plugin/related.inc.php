<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// related.inc.php
// Copyright 2005-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Related plugin: Show Backlinks for the page

function plugin_related_convert() : string
{
	global $vars;

	return make_related($vars['page'], 'p');
}

// Show Backlinks: via related caches for the page
function plugin_related_action() : array
{
	global $vars;
	global $defaultpage;
	global $whatsnew;

	$_page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if ($_page == '') {
		$_page = $defaultpage;
	}

	// Get related from cache
	$data = links_get_related_db($_page);

	if (!empty($data)) {
		// Hide by array keys (not values)
		foreach (array_keys($data) as $page) {
			if (($page == $whatsnew) || (check_non_list($page))) {
				unset($data[$page]);
			}
		}
	}

	// Result
	$s_word = htmlspecialchars($_page, ENT_COMPAT, 'UTF-8');
	$msg = 'Backlinks for: '.$s_word;
	$retval = '<a href="'.get_page_uri($_page).'">Return to '.$s_word.'</a><br />'."\n";

	if (empty($data)) {
		$retval .= '<ul><li>No related pages found.</li></ul>'."\n";
	} else {
		// Show count($data)?
		ksort($data, SORT_STRING);
		$retval .= '<ul>'."\n";

		foreach ($data as $page=>$time) {
			$s_page = htmlspecialchars($page, ENT_COMPAT, 'UTF-8');
			$mtime_span = get_passage_mtime_html_span($time + LOCALZONE);
			$retval .= "\t".'<li><a href="'.get_page_uri($page).'">'.$s_page.'</a>'.$mtime_span.'</li>'."\n";
		}

		$retval .= '</ul>'."\n";
	}

	return ['msg'=>$msg, 'body'=>$retval];
}

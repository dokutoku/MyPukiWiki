<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// read.inc.php
// Copyright 2003-2017 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Read plugin: Show a page and InterWiki

function plugin_read_action()
{
	global $vars;
	global $_title_invalidwn;
	global $_msg_invalidiwn;
	global $autoalias;

	$page = (isset($vars['page'])) ? ($vars['page']) : ('');

	if (is_page($page)) {
		// Show this page
		check_readable($page, true, true);
		header_lastmod($page);

		// Enable get_existpage() cache
		is_pagelist_cache_enabled(true);

		return ['msg'=>'', 'body'=>''];
	} elseif ((!PKWK_SAFE_MODE) && (is_interwiki($page))) {
		// Process InterWikiName
		return do_plugin_action('interwiki');
	} elseif (is_pagename($page)) {
		if ($autoalias) {
			$real = get_autoalias_right_link($page);

			if ($real != '') {
				if (is_page($real)) {
					$uri = get_page_uri($real, PKWK_URI_ROOT);
				} else {
					$uri = get_base_uri(PKWK_URI_ROOT).'?cmd=edit&page='.rawurlencode($real);
				}

				header('HTTP/1.0 302 Found');
				header('Location: '.$uri);

				return;
			}
		}

		$vars['cmd'] = 'edit';

		// Page not found, then show edit form
		return do_plugin_action('edit');
	} else {
		// Invalid page name
		return ['msg'=>$_title_invalidwn, 'body'=>str_replace('$1', htmlsc($page), str_replace('$2', 'WikiName', $_msg_invalidiwn))];
	}
}

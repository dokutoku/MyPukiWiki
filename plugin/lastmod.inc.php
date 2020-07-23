<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// $Id: lastmod.inc.php,v 1.3 2005/01/31 13:03:41 henoheno Exp $
//
// Lastmod plugin - Show lastmodifled date of the page
// Originally written by Reimy, 2003

function plugin_lastmod_inline(string ...$args)
{
	global $vars;
	global $WikiName;
	global $BracketName;

	$page = $args[0];

	if ($page == '') {
		// Default: page itself
		$page = $vars['page'];
	} else {
		if (preg_match('/^('.$WikiName.'|'.$BracketName.')$/', strip_bracket($page))) {
			$page = get_fullname(strip_bracket($page), $vars['page']);
		} else {
			return false;
		}
	}

	if (!is_page($page)) {
		return false;
	}

	return format_date(get_filetime($page));
}

<?php declare(strict_types=1);
// PukiWiki - Yet another WikiWikiWeb clone.
// interwiki.inc.php
// Copyright 2003-2018 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// InterWiki redirection plugin (OBSOLETE)

function plugin_interwiki_action()
{
	global $vars, $InterWikiName;

	if (PKWK_SAFE_MODE) {
		die_message('InterWiki plugin is not allowed');
	}

	$match = [];
	$page = $vars['page'];

	if (!preg_match("/^{$InterWikiName}$/", $page, $match)) {
		return plugin_interwiki_invalid($page);
	}
	$url = get_interwiki_url($match[2], $match[3]);

	if ($url === false) {
		return plugin_interwiki_invalid($page);
	}

	pkwk_headers_sent();
	header('Location: '.$url);

	exit;
}

function plugin_interwiki_invalid($page)
{
	global $_title_invalidiwn, $_msg_invalidiwn, $interwiki;

	return [
		'msg'=>$_title_invalidiwn,
		'body'=>str_replace(['$1', '$2'],
			[htmlsc($page),
				make_pagelink($interwiki, 'InterWikiName'), ],
			$_msg_invalidiwn), ];
}

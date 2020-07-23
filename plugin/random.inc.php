<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// random.inc.php
// Copyright 2002-2019 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Show random page plugin

/*
 *プラグイン random
  配下のページをランダムに表示する

 *Usage
  #random(メッセージ)

 *パラメータ
 -メッセージ~
 リンクに表示する文字列

 */

function plugin_random_convert(string ...$args) : string
{
	global $vars;

	$script = get_base_uri();

	// default
	$title = '[Random Link]';

	if (func_num_args()) {
		$title = $args[0];
	}

	return '<p><a href="'.$script.'?plugin=random&amp;refer='.pagename_urlencode($vars['page']).'">'.htmlsc($title).'</a></p>';
}

function plugin_random_action() : array
{
	global $vars;

	$pattern = strip_bracket($vars['refer']).'/';
	$pages = [];

	foreach (get_existpages() as $_page) {
		if (strpos($_page, $pattern) === 0) {
			$pages[$_page] = strip_bracket($_page);
		}
	}

	mt_srand((float) (microtime()) * 1000000);
	$page = array_rand($pages);

	if ($page != '') {
		$vars['refer'] = $page;
	}

	return ['body'=>'', 'msg'=>''];
}

<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone
// size.inc.php
// Copyright 2002-2020 PukiWiki Development Team
// License: GPL v2 or (at your option) any later version
//
// Text-size changing via CSS plugin

// px
define('PLUGIN_SIZE_MAX', 60);

// px
define('PLUGIN_SIZE_MIN', 8);

// ----
define('PLUGIN_SIZE_USAGE', '&size(px){Text you want to change};');

function plugin_size_inline(string ...$args) : string
{
	if (func_num_args() != 2) {
		return PLUGIN_SIZE_USAGE;
	}

	$size = $args[0];
	$body = $args[1];

	// strip_autolink() is not needed for size plugin
	//$body = strip_htmltag($body);

	if (($size == '') || ($body == '') || (!preg_match('/^\d+$/', $size))) {
		return PLUGIN_SIZE_USAGE;
	}

	$size = max(PLUGIN_SIZE_MIN, min(PLUGIN_SIZE_MAX, (int) ($size)));

	return '<span style="font-size:'.$size.'px;display:inline-block;line-height:130%;text-indent:0">'.$body.'</span>';
}

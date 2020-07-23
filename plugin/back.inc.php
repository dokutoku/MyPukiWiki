<?php
declare(strict_types=1);

// PukiWiki - Yet another WikiWikiWeb clone.
// back.inc.php
// Copyright
//   2003-2018 PukiWiki Development Team
//   2002      Katsumi Saito <katsumi@jo1upk.ymt.prug.or.jp>
//
// back plugin

// Allow specifying back link by page name and anchor, or
// by relative or site-abusolute path
// false(Compat), true
define('PLUGIN_BACK_ALLOW_PAGELINK', PKWK_SAFE_MODE);

// Allow JavaScript (Compat)
// true(Compat), false
define('PLUGIN_BACK_ALLOW_JAVASCRIPT', true);

// ----
define('PLUGIN_BACK_USAGE', '#back([text],[center|left|right][,0(no hr)[,Page-or-URI-to-back]])');

function plugin_back_convert(string ...$args) : string
{
	global $_msg_back_word;
	global $script;

	if (func_num_args() > 4) {
		return PLUGIN_BACK_USAGE;
	}

	[$word, $align, $hr, $href] = array_pad($args, 4, '');

	$word = trim($word);
	$word = ($word == '') ? ($_msg_back_word) : (htmlsc($word));

	$align = strtolower(trim($align));

	switch ($align) {
		case '':
			$align = 'center';
			// FALLTHROUGH

		case 'center':
		case 'left':
		case 'right':
			break;

		default:
			return PLUGIN_BACK_USAGE;
	}

	$hr = (trim($hr) != '0') ? ('<hr class="full_hr" />'."\n") : ('');

	$link = true;
	$href = trim($href);

	if ($href != '') {
		if (PLUGIN_BACK_ALLOW_PAGELINK) {
			if (is_url($href)) {
				$href = htmlsc($href);
			} else {
				$refer = (isset($vars['page'])) ? ($vars['page']) : ('');
				$array = anchor_explode($href);
				$page = get_fullname($array[0], $refer);

				if (!is_pagename($page)) {
					return PLUGIN_BACK_USAGE;
				}

				$anchor = ($array[1] != '') ? ('#'.rawurlencode($array[1])) : ('');
				$href = get_page_uri($page).$anchor;
				$link = is_page($page);
			}
		} else {
			if (is_url($href)) {
				$href = htmlsc($href);
			} else {
				return PLUGIN_BACK_USAGE.': Set a page name or an URI';
			}
		}
	} else {
		if (!PLUGIN_BACK_ALLOW_JAVASCRIPT) {
			return PLUGIN_BACK_USAGE.': Set a page name or an URI';
		}

		$href = 'javascript:history.go(-1)';
	}

	if ($link) {
		// Normal link
		return $hr.'<div style="text-align:'.$align.'">[ <a href="'.$href.'">'.$word.'</a> ]</div>'."\n";
	} else {
		// Dangling link
		return $hr.'<div style="text-align:'.$align.'">[ <span class="noexists">'.$word.'<a href="'.$href.'">?</a></span> ]</div>'."\n";
	}
}
